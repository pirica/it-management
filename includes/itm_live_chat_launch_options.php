<?php
/**
 * Server-built launch option menus for Live Agent and Chat with flows.
 */

if (!function_exists('itm_live_chat_launch_options_it_email')) {
    function itm_live_chat_launch_options_it_email($conn, $companyId)
    {
        $companyId = (int)$companyId;
        $sql = 'SELECT contact_email FROM it_settings WHERE company_id = ? AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return trim((string)($row['contact_email'] ?? ''));
    }
}

if (!function_exists('itm_live_chat_launch_options_live_agent')) {
    function itm_live_chat_launch_options_live_agent($conn, $companyId)
    {
        $companyId = (int)$companyId;
        $options = [
            [
                'id' => 'start_chat',
                'label' => 'Start live chat',
                'description' => 'Connect with the next available support agent',
                'icon' => '💬',
                'open_mode' => 'in_app',
                'url' => null,
            ],
            [
                'id' => 'knowledge_base',
                'label' => 'Knowledge Base',
                'description' => 'Browse self-service articles',
                'icon' => '🧩',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/knowledge_base/',
            ],
            [
                'id' => 'knowledge_base_list_all',
                'label' => 'List all (knowledge-base)',
                'description' => 'Open the full knowledge base list',
                'icon' => '📋',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/knowledge_base/list_all.php',
            ],
            [
                'id' => 'create_ticket',
                'label' => 'Create ticket',
                'description' => 'Open the ticket creation form',
                'icon' => '🎫',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/tickets/create.php',
            ],
            [
                'id' => 'reopen_ticket',
                'label' => 'Re-open ticket',
                'description' => 'Reopen a closed ticket and start live chat',
                'icon' => '🔓',
                'open_mode' => 'in_app',
                'url' => null,
            ],
        ];
        $email = itm_live_chat_launch_options_it_email($conn, $companyId);
        if ($email !== '') {
            $options[] = [
                'id' => 'email_it',
                'label' => 'Email IT',
                'description' => 'Send email to IT support',
                'icon' => '📧',
                'open_mode' => 'browser_tab',
                'url' => 'mailto:' . rawurlencode($email),
            ];
        }
        return $options;
    }
}

if (!function_exists('itm_live_chat_launch_options_chat_with')) {
    function itm_live_chat_launch_options_chat_with($conn, $companyId)
    {
        return [
            [
                'id' => 'message_colleague',
                'label' => 'Message a colleague',
                'description' => 'Start a peer chat with another employee',
                'icon' => '💬',
                'open_mode' => 'in_app',
                'url' => null,
            ],
            [
                'id' => 'knowledge_base_list_all',
                'label' => 'List all (knowledge-base)',
                'description' => 'Open the full knowledge base list',
                'icon' => '📋',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/knowledge_base/list_all.php',
            ],
            [
                'id' => 'company_contacts',
                'label' => 'Company contacts',
                'description' => 'Browse the company directory',
                'icon' => '👥',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/contacts/',
            ],
            [
                'id' => 'org_chart',
                'label' => 'Org chart',
                'description' => 'View the organization chart',
                'icon' => '🏢',
                'open_mode' => 'browser_tab',
                'url' => BASE_URL . 'modules/org_chart/',
            ],
        ];
    }
}
