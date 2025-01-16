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

    // Replace the multiple header calls with a single one using an array
    $allowed_origins = [
        'https://www.somethindifferent.co',
        'http://www.somethindifferent.co',  // For initial SSL redirect
        'https://somethin-different-dd8aefc58ac8.herokuapp.com'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Add these headers for better mobile support
    header('Vary: User-Agent');
    header('Cache-Control: no-cache');

    // Add security headers for desktop browsers
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');

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

    // Enhanced email validation
    if (!$data || !isset($data['email'])) {
        throw new Exception('Email is required');
    }

    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) {
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

    // Prepare subscriber data with validated email
    $subscriber_data = [
        'email_address' => $email,
        'status' => 'subscribed',  // Changed from 'pending' to 'subscribed'
        'merge_fields' => [
            'FNAME' => isset($data['name']) ? trim($data['name']) : ''
        ]
    ];

    logError('Attempting to add subscriber', $subscriber_data);

    try {
        // Check if subscriber already exists
        $subscriber_hash = md5(strtolower($email));
        try {
            $existing = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
            throw new Exception('This email is already subscribed');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        // Add new subscriber
        $result = $mailchimp->lists->addListMember($list_id, $subscriber_data);
        logError('Subscription successful', $result);
        
        echo json_encode([
            'success' => true,
            'message' => 'Thanks for subscribing! Welcome to Somethin\' Different.'
        ]);

    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $response = $e->getResponse();
        $body = json_decode($response->getBody()->getContents(), true);
        
        logError('Mailchimp API error', [
            'status' => $response->getStatusCode(),
            'body' => $body
        ]);

        throw new Exception(
            isset($body['detail']) ? $body['detail'] : 'Failed to add subscriber'
        );
    }

} catch (Exception $e) {
    logError('Error occurred', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush(); 