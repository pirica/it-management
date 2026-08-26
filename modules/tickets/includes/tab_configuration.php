<?php
/**
 * Tickets module — Configuration tab (tenant ticket_settings + related links).
 *
 * Expects: $conn, $company_id, $employee_id (session), $ticketsCanEditSettings, $ticketsSettingsRow, $ticketsConfigFlash
 */
$csrfToken = itm_get_csrf_token();
$settings = is_array($ticketsSettingsRow ?? null) ? $ticketsSettingsRow : [];
$autoIssueOn = (int)($settings['auto_issue_survey_on_close'] ?? 0) === 1;
$sendEmailOn = (int)($settings['survey_send_email_on_issue'] ?? 1) === 1;
$slaOnCreate = (int)($settings['sla_enabled_on_create'] ?? 1) === 1;
$base = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/';

$relatedLinks = [
    ['label' => 'Ticket Questionnaires', 'href' => $base . 'modules/ticket_questionnaires/index.php', 'icon' => '📋'],
    ['label' => 'Survey Dashboard', 'href' => $base . 'modules/ticket_survey_dashboard/index.php', 'icon' => '📊'],
    ['label' => 'Ticket SLA Policies', 'href' => $base . 'modules/ticket_sla_policies/index.php', 'icon' => '⏱️'],
    ['label' => 'SLA Command Center', 'href' => $base . 'modules/ticket_sla_dashboard/index.php', 'icon' => '📊'],
    ['label' => 'Canned Responses', 'href' => $base . 'modules/ticket_canned_responses/index.php', 'icon' => '💬'],
    ['label' => 'Ticket Categories', 'href' => $base . 'modules/ticket_categories/index.php', 'icon' => '🏷️'],
    ['label' => 'Ticket Statuses', 'href' => $base . 'modules/ticket_statuses/index.php', 'icon' => '🚦'],
    ['label' => 'Ticket Priorities', 'href' => $base . 'modules/ticket_priorities/index.php', 'icon' => '🔥'],
    ['label' => 'Email / Inbound SMTP', 'href' => $base . 'modules/emails/index.php?tab=smtp', 'icon' => '📧'],
];
?>
<div class="card" style="margin-bottom:16px;">
    <h1 title="Ticket configuration">⚙️</h1>
    <p style="color:var(--text-secondary);margin-top:8px;">Company defaults for ticket surveys, SLA stamping, and related modules. Manual survey issue from a ticket view is always available when questionnaires exist.</p>
    <?php if (!empty($ticketsConfigFlash)): ?>
        <div class="alert alert-success" style="margin-top:12px;"><?php echo sanitize((string)$ticketsConfigFlash); ?></div>
    <?php endif; ?>
    <?php if (!$ticketsCanEditSettings): ?>
        <div class="alert alert-info" style="margin-top:12px;">Settings are read-only. Contact an administrator with edit access to change toggles.</div>
    <?php endif; ?>
    <form method="post" action="index.php?tab=configuration" style="margin-top:16px;">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="save_ticket_settings" value="1">

        <div class="form-group">
            <label><?php echo sanitize(function_exists('cr_humanize_field') ? cr_humanize_field('auto_issue_survey_on_close') : 'Auto issue survey on close'); ?></label>
            <label class="itm-checkbox-control">
                <input type="checkbox" name="auto_issue_survey_on_close" value="1" <?php echo $autoIssueOn ? 'checked' : ''; ?> <?php echo $ticketsCanEditSettings ? '' : 'disabled'; ?>>
                <span>Auto issue survey on close <span class="itm-check-indicator" aria-hidden="true"><?php echo $autoIssueOn ? '✅' : '❌'; ?></span></span>
            </label>
            <small class="text-muted">When enabled, moving a ticket to a closed status automatically issues a post-ticket questionnaire (default off).</small>
        </div>

        <div class="form-group">
            <label><?php echo sanitize(function_exists('cr_humanize_field') ? cr_humanize_field('survey_send_email_on_issue') : 'Email survey link on issue'); ?></label>
            <label class="itm-checkbox-control">
                <input type="checkbox" name="survey_send_email_on_issue" value="1" <?php echo $sendEmailOn ? 'checked' : ''; ?> <?php echo $ticketsCanEditSettings ? '' : 'disabled'; ?>>
                <span>Email survey link on issue <span class="itm-check-indicator" aria-hidden="true"><?php echo $sendEmailOn ? '✅' : '❌'; ?></span></span>
            </label>
            <small class="text-muted">When enabled, the requester receives an email when a survey invite is issued (auto or manual from ticket view).</small>
        </div>

        <div class="form-group">
            <label><?php echo sanitize(function_exists('cr_humanize_field') ? cr_humanize_field('sla_enabled_on_create') : 'SLA on new tickets'); ?></label>
            <label class="itm-checkbox-control">
                <input type="checkbox" name="sla_enabled_on_create" value="1" <?php echo $slaOnCreate ? 'checked' : ''; ?> <?php echo $ticketsCanEditSettings ? '' : 'disabled'; ?>>
                <span>SLA on new tickets <span class="itm-check-indicator" aria-hidden="true"><?php echo $slaOnCreate ? '✅' : '❌'; ?></span></span>
            </label>
            <small class="text-muted">When enabled, new tickets receive response/resolve due dates from Ticket SLA Policies.</small>
        </div>

        <?php if ($ticketsCanEditSettings): ?>
            <button type="submit" class="btn btn-primary" title="Save">💾</button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2 title="Related ticket configuration">🔗</h2>
    <p style="color:var(--text-secondary);">Open related lookup and workflow modules in a new browser tab.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:16px;">
        <?php foreach ($relatedLinks as $link): ?>
            <a class="itm-plain-link btn" href="<?php echo sanitize($link['href']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sanitize($link['label']); ?>" style="text-align:left;display:flex;align-items:center;gap:8px;">
                <span aria-hidden="true"><?php echo sanitize($link['icon']); ?></span>
                <span><?php echo sanitize($link['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
