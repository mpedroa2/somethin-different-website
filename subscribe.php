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

    // Verify environment variables
    $api_key = getenv('MAILCHIMP_API_KEY');
    $list_id = getenv('MAILCHIMP_LIST_ID');

    if (!$api_key || !$list_id) {
        throw new Exception('Missing required configuration');
    }

    // Get and validate input
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if (!$data || !isset($data['email'])) {
        throw new Exception('Invalid input data');
    }

    // Initialize Mailchimp
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => $api_key,
        'server' => 'us8'
    ]);

    // Test connection
    $ping = $mailchimp->ping->get();
    error_log('Mailchimp ping response: ' . json_encode($ping));

    // Prepare subscriber data
    $subscriber_data = [
        'email_address' => $data['email'],
        'status' => 'pending'
    ];

    if (!empty($data['name'])) {
        $subscriber_data['merge_fields'] = ['FNAME' => $data['name']];
    }

    // Add subscriber
    $result = $mailchimp->lists->addListMember($list_id, $subscriber_data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Thanks! Please check your email to confirm your subscription.'
    ]);

} catch (Exception $e) {
    error_log('Subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Subscription failed. Please try again.',
        'details' => $e->getMessage()
    ]);
}

ob_end_flush(); 