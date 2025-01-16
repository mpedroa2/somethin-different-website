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

    // Get and validate input
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    // Validate email format
    if (!$data || !isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }

    $api_key = getenv('MAILCHIMP_API_KEY');
    $list_id = getenv('MAILCHIMP_LIST_ID');

    if (!$api_key || !$list_id) {
        throw new Exception('Missing required configuration');
    }

    // Initialize Mailchimp
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => $api_key,
        'server' => 'us8'
    ]);

    // Test connection first
    try {
        $ping = $mailchimp->ping->get();
        error_log('Mailchimp connection successful: ' . json_encode($ping));
    } catch (Exception $e) {
        throw new Exception('Failed to connect to Mailchimp');
    }

    // Check if subscriber already exists
    try {
        $subscriber_hash = md5(strtolower($data['email']));
        $existing_member = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
        throw new Exception('This email is already subscribed');
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()->getStatusCode() !== 404) {
            throw $e;
        }
        // 404 means member not found, continue with subscription
    }

    // Prepare subscriber data
    $subscriber_data = [
        'email_address' => $data['email'],
        'status' => 'pending',
        'merge_fields' => [
            'FNAME' => isset($data['name']) ? $data['name'] : ''
        ]
    ];

    // Add subscriber
    $result = $mailchimp->lists->addListMember($list_id, $subscriber_data);
    error_log('Subscriber added successfully: ' . json_encode($result));
    
    echo json_encode([
        'success' => true,
        'message' => 'Thanks! Please check your email to confirm your subscription.'
    ]);

} catch (Exception $e) {
    error_log('Subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Please try again or contact support if the problem persists.'
    ]);
}

ob_end_flush(); 