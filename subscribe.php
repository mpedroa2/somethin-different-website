<?php
// CORS headers must be first
header('Access-Control-Allow-Origin: https://www.somethindifferent.co');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400'); // 24 hours cache

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rest of your code...
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';

    // Debug logging
    error_log("Attempting to subscribe: $email, $name");
    error_log("API Key: " . substr(getenv('MAILCHIMP_API_KEY'), 0, 5) . '...');
    error_log("List ID: " . getenv('MAILCHIMP_LIST_ID'));

    try {
        $mailchimp = new \MailchimpMarketing\ApiClient();
        
        // Get server prefix from API key
        $api_key = getenv('MAILCHIMP_API_KEY');
        $server = explode('-', $api_key)[1] ?? 'us8';
        
        $mailchimp->setConfig([
            'apiKey' => $api_key,
            'server' => $server
        ]);

        // Add member with error checking
        $list_id = getenv('MAILCHIMP_LIST_ID');
        if (!$list_id) {
            throw new Exception('Mailchimp list ID not configured');
        }

        $response = $mailchimp->lists->addListMember($list_id, [
            'email_address' => $email,
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $name
            ]
        ]);
        
        error_log("Successfully subscribed: $email");
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing! Welcome to Somethin\' Different.']);
    } catch (Exception $e) {
        error_log('Mailchimp Error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Subscription failed. Please try again.',
            'debug' => $e->getMessage()
        ]);
    }
} 

$allowed_origins = [
    'https://www.somethindifferent.co',
    'https://somethindifferent.co',
    'https://somethin-different-dd8aefc58ac8.herokuapp.com'
]; 