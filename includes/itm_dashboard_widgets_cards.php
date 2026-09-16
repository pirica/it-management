<?php
/**
 * Smart dashboard widget cards (role-aware metrics + sparklines).
 *
 * Expects $smartDash from itm_dashboard_load_smart_widgets() in scope.
 */

if (!isset($smartDash) || !is_array($smartDash)) {
    return;
}

$smartWidgets = is_array($smartDash['widgets'] ?? null) ? $smartDash['widgets'] : [];
$smartData = is_array($smartDash['data'] ?? null) ? $smartDash['data'] : [];

if ($smartWidgets === []) {
    return;
}
?>
<section class="itm-smart-dash-section" aria-label="Smart widgets">
    <header class="itm-smart-dash-section-head">
        <h2 class="itm-smart-dash-section-title" title="Smart widgets">📊</h2>
        <span class="itm-smart-dash-section-sub">Role-aware metrics and trends</span>
    </header>
    <div class="itm-smart-dash-grid">
        <?php foreach ($smartWidgets as $widgetDef): ?>
            <?php
            $widgetSlug = (string)($widgetDef['slug'] ?? '');
            if ($widgetSlug === '' || !isset($smartData[$widgetSlug])) {
                continue;
            }
            $widgetRow = $smartData[$widgetSlug];
            $metric = (int)($widgetRow['metric'] ?? 0);
            $subtitle = trim((string)($widgetRow['subtitle'] ?? ''));
            $deepLink = trim((string)($widgetRow['deep_link'] ?? ''));
            $title = trim((string)($widgetDef['title'] ?? $widgetSlug));
            $icon = trim((string)($widgetDef['icon'] ?? '📊'));
            $sparkline = is_array($widgetRow['sparkline'] ?? null) ? $widgetRow['sparkline'] : ['labels' => [], 'data' => []];
            $chartId = 'itm-smart-dash-chart-' . preg_replace('/[^a-z0-9_-]/i', '', $widgetSlug);
            ?>
            <article class="itm-smart-dash-widget">
                <div class="itm-smart-dash-widget-head">
                    <span class="itm-smart-dash-widget-icon" aria-hidden="true"><?php echo sanitize($icon); ?></span>
                    <div class="itm-smart-dash-widget-titles">
                        <h3 class="itm-smart-dash-widget-title" title="<?php echo sanitize($title); ?>"><?php echo sanitize($title); ?></h3>
                        <?php if ($subtitle !== ''): ?>
                            <p class="itm-smart-dash-widget-sub"><?php echo sanitize($subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="itm-smart-dash-widget-metric"><?php echo sanitize((string)$metric); ?></p>
                <div class="itm-smart-dash-widget-chart-wrap">
                    <canvas
                        id="<?php echo sanitize($chartId); ?>"
                        class="itm-smart-dash-widget-chart"
                        data-itm-smart-dash-chart="1"
                        data-chart-labels="<?php echo sanitize(json_encode($sparkline['labels'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                        data-chart-data="<?php echo sanitize(json_encode($sparkline['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"
                        aria-hidden="true"
                    ></canvas>
                </div>
                <?php if ($deepLink !== ''): ?>
                    <footer class="itm-smart-dash-widget-foot">
                        <a class="btn btn-sm" href="<?php echo sanitize($deepLink); ?>" title="View">🔎</a>
                    </footer>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
