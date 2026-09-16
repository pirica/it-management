<?php
/**
 * Shared helpers for CRUD mutation RBAC chokepoint apply/check scripts.
 */

declare(strict_types=1);

if (!function_exists('itm_crud_rbac_mutation_early_gate_snippet')) {
    function itm_crud_rbac_mutation_early_gate_snippet(): string
    {
        return <<<'PHP'
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

PHP;
    }
}

if (!function_exists('itm_crud_rbac_mutation_entry_bootstrap_snippet')) {
    function itm_crud_rbac_mutation_entry_bootstrap_snippet(): string
    {
        return <<<'PHP'
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

PHP;
    }
}

if (!function_exists('itm_crud_rbac_mutation_has_chokepoint')) {
    function itm_crud_rbac_mutation_has_chokepoint(string $content): bool
    {
        return strpos($content, 'itm_crud_mutation_guard_entry(') !== false;
    }
}

if (!function_exists('itm_crud_rbac_mutation_strip_legacy_handler_guards')) {
    function itm_crud_rbac_mutation_strip_legacy_handler_guards(string $content): string
    {
        $patterns = [
            '/\r?\n[ \t]*\/\/ Why: Server-side RBAC before CSRF\/delete SQL \(UI-only hiding is not enough\)\.\r?\n[ \t]*itm_require_crud_role_module_permission\([^;]+;\r?\n/s',
            '/\r?\n[ \t]*\/\/ Why: Server-side RBAC before CSRF persistence \(UI-only hiding is not enough\)\.\r?\n[ \t]*itm_require_crud_role_module_permission\([^;]+;\r?\n/s',
        ];

        foreach ($patterns as $pattern) {
            $content = (string)preg_replace($pattern, "\n", $content);
        }

        if (!itm_crud_rbac_mutation_has_chokepoint($content)
            && !itm_crud_rbac_mutation_index_has_early_gate($content)) {
            return $content;
        }

        do {
            $before = $content;
            $content = (string)preg_replace(
                '/\r?\n[ \t]*itm_require_crud_role_module_permission\(\$conn,\s*(?:\$crud_action|[\'"](?:create|edit|delete)[\'"]),\s*(?:\$crud_table|[\'"][^\'"]+[\'"])\);\r?\n/',
                "\n",
                $content,
                1
            );
        } while ($content !== $before);

        return $content;
    }
}

if (!function_exists('itm_crud_rbac_mutation_index_has_early_gate')) {
    function itm_crud_rbac_mutation_index_has_early_gate(string $content): bool
    {
        return itm_crud_rbac_mutation_has_chokepoint($content);
    }
}

if (!function_exists('itm_crud_rbac_mutation_apply_index_content')) {
    function itm_crud_rbac_mutation_apply_index_content(string $content, string $slug): string
    {
        if (itm_crud_rbac_mutation_index_has_early_gate($content)) {
            return itm_crud_rbac_mutation_strip_legacy_handler_guards($content);
        }

        $updated = $content;
        if (preg_match('/require(?:_once)?\s+[\'"][^\'"]*config\/config\.php[\'"]\s*;/', $updated, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = (int)$m[0][1] + strlen($m[0][0]);
            $updated = substr($updated, 0, $insertAt)
                . "\n" . itm_crud_rbac_mutation_early_gate_snippet()
                . substr($updated, $insertAt);
        }

        return itm_crud_rbac_mutation_strip_legacy_handler_guards($updated);
    }
}

if (!function_exists('itm_crud_rbac_mutation_entry_is_redirect_stub')) {
    function itm_crud_rbac_mutation_entry_is_redirect_stub(string $content): bool
    {
        if (strpos($content, '$_SERVER[\'REQUEST_METHOD\']') !== false || strpos($content, '$_POST') !== false) {
            return false;
        }

        return (bool)preg_match('/header\s*\(\s*[\'"]Location:/', $content)
            && (bool)preg_match('/\bexit\s*;/', $content);
    }
}

if (!function_exists('itm_crud_rbac_mutation_entry_is_index_wrapper')) {
    function itm_crud_rbac_mutation_entry_is_index_wrapper(string $content): bool
    {
        if (itm_crud_rbac_mutation_entry_is_redirect_stub($content)) {
            return true;
        }

        if ((bool)preg_match('/require(?:_once)?\s+(?:__DIR__\s*\.\s*[\'"]\/index\.php[\'"]|[\'"]index\.php[\'"])\s*;/', $content)) {
            return true;
        }

        if ((bool)preg_match('/require(?:_once)?\s+[\'"]create\.php[\'"]\s*;/', $content)) {
            return true;
        }

        return (bool)preg_match(
            '/require(?:_once)?\s+[\'"]\.\.\/equipment\/(?:create|edit|delete)\.php[\'"]\s*;/',
            $content
        );
    }
}

if (!function_exists('itm_crud_rbac_mutation_apply_entry_content')) {
    function itm_crud_rbac_mutation_apply_entry_content(string $content): string
    {
        if (itm_crud_rbac_mutation_entry_is_index_wrapper($content)) {
            return $content;
        }

        if (itm_crud_rbac_mutation_has_chokepoint($content)) {
            return itm_crud_rbac_mutation_strip_legacy_handler_guards($content);
        }

        $updated = $content;
        if (preg_match('/require(?:_once)?\s+[\'"][^\'"]*config\/config\.php[\'"]\s*;/', $updated, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = (int)$m[0][1] + strlen($m[0][0]);
            $updated = substr($updated, 0, $insertAt)
                . "\n" . itm_crud_rbac_mutation_entry_bootstrap_snippet()
                . substr($updated, $insertAt);
        }

        return itm_crud_rbac_mutation_strip_legacy_handler_guards($updated);
    }
}
