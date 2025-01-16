<?php
// Prevent any output before headers
ob_start();

// Set error handling to catch all errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off HTML error display
ini_set('log_errors', 1);     // Enable error logging

require_once 'vendor/autoload.php';

// Clear any previous output
ob_clean();

function logError($message, $data = null) {
    $log = "[" . date('Y-m-d H:i:s') . "] $message";
    if ($data) {
        $log .= " Data: " . json_encode($data);
    }
    error_log($log);
}

try {
    // Set JSON headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: https://somethin-different-dd8aefc58ac8.herokuapp.com');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Log request
    logError('Received request', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]);

    // Get input
    $raw_input = file_get_contents('php://input');
    logError('Raw input', $raw_input);

    $data = json_decode($raw_input, true);
    logError('Parsed data', $data);

    // Validate email
    if (!$data || !isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }

    // Get config
    $api_key = getenv('MAILCHIMP_API_KEY');
    $list_id = getenv('MAILCHIMP_LIST_ID');

    logError('Config check', [
        'api_key_exists' => !empty($api_key),
        'list_id_exists' => !empty($list_id),
        'api_key_prefix' => $api_key ? substr($api_key, 0, 6) . '...' : 'missing',
        'list_id' => $list_id
    ]);

    if (!$api_key || !$list_id) {
        throw new Exception('Missing required configuration');
    }

    // Initialize Mailchimp
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => $api_key,
        'server' => 'us8'
    ]);

    // Test connection
    try {
        logError('Testing Mailchimp connection');
        $ping = $mailchimp->ping->get();
        logError('Ping successful', $ping);
    } catch (Exception $e) {
        logError('Ping failed', $e->getMessage());
        throw new Exception('Failed to connect to Mailchimp');
    }

    // Prepare subscriber data
    $subscriber_data = [
        'email_address' => $data['email'],
        'status' => 'pending',
        'merge_fields' => [
            'FNAME' => isset($data['name']) ? $data['name'] : ''
        ]
    ];

    logError('Attempting to add subscriber', $subscriber_data);

    // Add subscriber
    $result = $mailchimp->lists->addListMember($list_id, $subscriber_data);
    logError('Subscription successful', $result);
    
    echo json_encode([
        'success' => true,
        'message' => 'Thanks! Please check your email to confirm your subscription.'
    ]);

} catch (Exception $e) {
    logError('Error occurred', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Please try again or contact support if the problem persists.'
    ]);
}

ob_end_flush(); 