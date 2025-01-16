<?php
// Prevent any output before headers
ob_start();

// Prevent PHP errors from being displayed
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

// Clear any previous output
ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Diagnostic logging function
function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - $message";
    if ($data) {
        $log .= " - Data: " . json_encode($data);
    }
    error_log($log);
}

logDebug('Starting subscription process');

// 1. First verify we can read the environment variables
$api_key = getenv('MAILCHIMP_API_KEY');
$list_id = getenv('MAILCHIMP_LIST_ID');

logDebug('Configuration', [
    'api_key_exists' => !empty($api_key),
    'list_id_exists' => !empty($list_id),
    'api_key_preview' => $api_key ? substr($api_key, 0, 6) . '...' : 'missing',
    'list_id' => $list_id
]);

// 2. Get and validate input
$raw_input = file_get_contents('php://input');
logDebug('Raw input received', $raw_input);

$data = json_decode($raw_input, true);
if (!$data) {
    logDebug('Failed to parse JSON input');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

logDebug('Parsed input data', $data);

// 3. Validate email
if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    logDebug('Invalid email address', $data['email']);
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

try {
    // 4. Test Mailchimp connection
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => $api_key,
        'server' => 'us8'
    ]);

    // 5. Try a simple ping first
    try {
        logDebug('Testing Mailchimp connection');
        $ping_result = $mailchimp->ping->get();
        logDebug('Ping response', $ping_result);
    } catch (\Exception $e) {
        logDebug('Ping failed', $e->getMessage());
        throw new Exception('Failed to connect to Mailchimp: ' . $e->getMessage());
    }

    // 6. Prepare subscriber data
    $subscriber_data = [
        'email_address' => $data['email'],
        'status' => 'pending'
    ];

    if (!empty($data['name'])) {
        $subscriber_data['merge_fields'] = ['FNAME' => $data['name']];
    }

    logDebug('Attempting to add subscriber', $subscriber_data);

    // 7. Add the subscriber
    $result = $mailchimp->lists->addListMember($list_id, $subscriber_data);
    logDebug('Mailchimp API response', $result);

    echo json_encode([
        'success' => true,
        'message' => 'Thanks! Please check your email to confirm your subscription.'
    ]);

} catch (\Exception $e) {
    logDebug('Error occurred', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Subscription failed. Please try again.',
        'details' => $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush(); 