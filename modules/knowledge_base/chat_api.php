<?php
/**
 * Chatbot API
 *
 * Handles search queries against the Knowledge Base and IT Settings.
 * Multi-tenant scoped by company_id.
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';

// Ensure user is logged in and has an active company
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
$query = trim((string)($data['query'] ?? ''));

if ($query === '') {
    echo json_encode(['response' => 'Hello! I am your IT Support Assistant. How can I help you today?']);
    exit;
}

$responseParts = [];
$searchEsc = mysqli_real_escape_string($conn, '%' . $query . '%');

// 1. Search IT Settings for contact/hours/escalation if keywords match
$itKeywords = ['contact', 'email', 'phone', 'call', 'hours', 'operation', 'open', 'escalate', 'support', 'help', 'it team', 'it department'];
$isItQuery = false;
foreach ($itKeywords as $kw) {
    if (stripos($query, $kw) !== false) {
        $isItQuery = true;
        break;
    }
}

if ($isItQuery) {
    $itSql = "SELECT * FROM it_settings WHERE company_id = $company_id AND active = 1 LIMIT 1";
    $itRes = mysqli_query($conn, $itSql);
    if ($itRes && $row = mysqli_fetch_assoc($itRes)) {
        if (stripos($query, 'contact') !== false || stripos($query, 'email') !== false || stripos($query, 'phone') !== false || stripos($query, 'call') !== false || stripos($query, 'it team') !== false) {
            $contact = "You can contact our IT Support team at:\n";
            if ($row['contact_email']) $contact .= "📧 Email: " . $row['contact_email'] . "\n";
            if ($row['contact_phone']) $contact .= "📞 Phone: " . $row['contact_phone'] . "\n";
            $responseParts[] = $contact;
        }
        if (stripos($query, 'hours') !== false || stripos($query, 'open') !== false) {
            $responseParts[] = "🕒 Our hours of operation are: " . ($row['hours_of_operation'] ?: 'Standard business hours') . ".";
        }
        if (stripos($query, 'escalate') !== false || stripos($query, 'unresolved') !== false) {
            $responseParts[] = "⚠️ Escalation Procedure:\n" . ($row['escalation_procedure'] ?: 'Please contact the IT Manager if your issue remains unresolved.');
        }
    }
}

// 2. Search Knowledge Base
$kbSql = "SELECT title, content FROM knowledge_base
          WHERE company_id = $company_id
          AND active = 1
          AND (title LIKE '$searchEsc' OR content LIKE '$searchEsc' OR category LIKE '$searchEsc')
          LIMIT 3";
$kbRes = mysqli_query($conn, $kbSql);
$kbMatches = [];
if ($kbRes) {
    while ($row = mysqli_fetch_assoc($kbRes)) {
        $kbMatches[] = "**" . $row['title'] . "**\n" . substr(strip_tags($row['content']), 0, 500) . "...";
    }
}

if (!empty($kbMatches)) {
    $responseParts[] = "I found some articles that might help:\n\n" . implode("\n\n---\n\n", $kbMatches);
}

// 3. Fallback
if (empty($responseParts)) {
    // Try word-by-word search if no direct match found
    $words = explode(' ', $query);
    $wordConditions = [];
    foreach ($words as $word) {
        if (strlen($word) > 3) {
            $wEsc = mysqli_real_escape_string($conn, '%' . $word . '%');
            $wordConditions[] = "(title LIKE '$wEsc' OR content LIKE '$wEsc')";
        }
    }

    if (!empty($wordConditions)) {
        $wordSql = "SELECT title, content FROM knowledge_base
                    WHERE company_id = $company_id
                    AND active = 1
                    AND (" . implode(' OR ', $wordConditions) . ")
                    LIMIT 2";
        $wordRes = mysqli_query($conn, $wordSql);
        if ($wordRes && mysqli_num_rows($wordRes) > 0) {
            $responseParts[] = "I couldn't find an exact match, but these articles might be relevant:";
            while ($row = mysqli_fetch_assoc($wordRes)) {
                $responseParts[] = "**" . $row['title'] . "**\n" . substr(strip_tags($row['content']), 0, 300) . "...";
            }
        }
    }
}

if (empty($responseParts)) {
    $itSql = "SELECT contact_email, contact_phone FROM it_settings WHERE company_id = $company_id AND active = 1 LIMIT 1";
    $itRes = mysqli_query($conn, $itSql);
    $itRow = ($itRes) ? mysqli_fetch_assoc($itRes) : null;

    $msg = "I'm sorry, I couldn't find any information related to your request. \n\nWould you like to contact our IT support team?";
    if ($itRow) {
        if ($itRow['contact_email']) $msg .= "\n📧 Email: " . $itRow['contact_email'];
        if ($itRow['contact_phone']) $msg .= "\n📞 Phone: " . $itRow['contact_phone'];
    }
    $responseParts[] = $msg;
}

echo json_encode(['response' => implode("\n\n", $responseParts)]);
