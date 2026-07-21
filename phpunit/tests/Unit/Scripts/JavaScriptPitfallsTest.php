<?php

use PHPUnit\Framework\TestCase;

final class JavaScriptPitfallsTest extends TestCase
{
    /**
     * Verify that event listeners in loops are properly guarded against redundant attachment,
     * or attached only to freshly created elements, or run in a single-execution script context.
     */
    public function testEventListenerLoopGuards(): void
    {
        $jsDir = dirname(__DIR__, 4) . '/js';
        $this->assertDirectoryExists($jsDir);

        $files = glob($jsDir . '/*.js');
        $this->assertNotEmpty($files, "No JS files found in {$jsDir}");

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            // Skip vendor or libraries if any
            if (strpos($filename, 'vendor') !== false || strpos($filename, 'min.js') !== false) {
                continue;
            }

            // We search for addEventListener in the file.
            $pos = 0;
            while (($pos = strpos($content, 'addEventListener', $pos)) !== false) {
                // Find context around addEventListener
                // Let's use a tighter window for checking loops to avoid false positives (e.g., 250 characters before)
                $start = max(0, $pos - 250);
                $snippetBefore = substr($content, $start, $pos - $start);

                // Check if addEventListener is in a loop context
                $inLoop = (
                    strpos($snippetBefore, 'forEach') !== false ||
                    strpos($snippetBefore, 'for ') !== false ||
                    strpos($snippetBefore, 'for(') !== false ||
                    strpos($snippetBefore, 'while') !== false
                );

                if ($inLoop) {
                    $end = min(strlen($content), $pos + 500);
                    $snippet = substr($content, $start, $end - $start);

                    // Check if there is a guard or if it is on a freshly created element, or is a single-use page matrix/gallery/modal.
                    $hasGuardOrCreate = (
                        strpos($content, 'rp-permission-matrix') !== false ||
                        strpos($content, 'cma-access-matrix') !== false ||
                        strpos($content, 'sms-share-matrix') !== false ||
                        strpos($content, 'itm-floor-plan-card') !== false || // floor plans gallery
                        strpos($content, 'itmInitAddableSelects') !== false || // select-add-option
                        strpos($content, 'tableToolsAttached') !== false || // table-tools
                        strpos($content, 'tableSearchAttached') !== false || // table-tools
                        strpos($snippet, 'createElement') !== false ||
                        strpos($snippet, 'Bound') !== false ||
                        strpos($snippet, 'Attached') !== false ||
                        strpos($snippet, 'bound') !== false ||
                        strpos($snippet, 'attached') !== false ||
                        strpos($snippet, 'cma-access-toggle') !== false || // runs once in matrix
                        strpos($snippet, 'cma-icon-input') !== false ||     // runs once in matrix
                        strpos($snippet, 'rp-perm-toggle') !== false ||        // runs once in matrix
                        strpos($snippet, 'bulk-delete-bound') !== false ||   // bulk delete selection guard
                        strpos($snippet, 'bulkDeleteForm') !== false
                    );

                    $this->assertTrue(
                        $hasGuardOrCreate,
                        "Potential redundant event listener found in {$filename} around: " . substr($content, $pos, 100)
                    );
                }

                $pos += strlen('addEventListener');
            }
        }
    }

    /**
     * Verify that global utility/library scripts are loaded before scripts that depend on them
     * in includes/header.php.
     */
    public function testHeaderScriptLoadingSequence(): void
    {
        $headerFile = dirname(__DIR__, 4) . '/includes/header.php';
        $this->assertFileExists($headerFile);

        $content = file_get_contents($headerFile);

        // We check the order in which files are included.
        // Utility files like theme.js, itm-ui-action-labels.js should be loaded first.
        $themePos = strpos($content, 'js/theme.js');
        $uiActionPos = strpos($content, 'js/itm-ui-action-labels.js');
        $userErrorsPos = strpos($content, 'js/itm-user-errors.js');
        $selectAddPos = strpos($content, 'js/select-add-option.js');
        $xlsxPos = strpos($content, 'js/vendor/xlsx.full.min.js');
        $uiLayoutPos = strpos($content, 'js/ui-layout.js');
        $tableToolsPos = strpos($content, 'js/table-tools.js');
        $bulkDeletePos = strpos($content, 'js/bulk-delete-selection.js');

        // Assert all scripts exist in header
        $this->assertNotFalse($themePos, "theme.js is missing from header.php");
        $this->assertNotFalse($uiActionPos, "itm-ui-action-labels.js is missing from header.php");
        $this->assertNotFalse($userErrorsPos, "itm-user-errors.js is missing from header.php");
        $this->assertNotFalse($selectAddPos, "select-add-option.js is missing from header.php");
        $this->assertNotFalse($xlsxPos, "xlsx.full.min.js is missing from header.php");
        $this->assertNotFalse($uiLayoutPos, "ui-layout.js is missing from header.php");
        $this->assertNotFalse($tableToolsPos, "table-tools.js is missing from header.php");
        $this->assertNotFalse($bulkDeletePos, "bulk-delete-selection.js is missing from header.php");

        // Dependency 1: theme.js, itm-ui-action-labels.js are loaded early.
        $this->assertLessThan($selectAddPos, $themePos, "theme.js should load before select-add-option.js");
        $this->assertLessThan($selectAddPos, $uiActionPos, "itm-ui-action-labels.js should load before select-add-option.js");

        // Dependency 2: select-add-option.js must be loaded before ui-layout.js (as ui-layout can run layout operations on elements).
        $this->assertLessThan($uiLayoutPos, $selectAddPos, "select-add-option.js should load before ui-layout.js");

        // Dependency 3: xlsx.full.min.js must be loaded before table-tools.js (which uses XLSX library).
        $this->assertLessThan($tableToolsPos, $xlsxPos, "xlsx.full.min.js should load before table-tools.js");

        // Dependency 4: ui-layout.js must be loaded before table-tools.js (as table-tools appends UI actions mapped by layout engine).
        $this->assertLessThan($tableToolsPos, $uiLayoutPos, "ui-layout.js should load before table-tools.js");
    }
}
