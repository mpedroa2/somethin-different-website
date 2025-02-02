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

    try {
        // Initialize Mailchimp
        $mailchimp = new \MailchimpMarketing\ApiClient();
        $mailchimp->setConfig([
            'apiKey' => getenv('MAILCHIMP_API_KEY'),
            'server' => 'us8'  // Your server prefix from the API key
        ]);

        // Add subscriber to list
        $response = $mailchimp->lists->addListMember(getenv('MAILCHIMP_LIST_ID'), [
            'email_address' => $email,
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $name
            ]
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing! Welcome to Somethin\' Different.']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} 

$allowed_origins = [
    'https://www.somethindifferent.co',
    'https://somethindifferent.co',
    'https://somethin-different-dd8aefc58ac8.herokuapp.com'
]; 