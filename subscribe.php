<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Add logging
error_log('Received request: ' . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

error_log('Received data: ' . print_r($data, true));

try {
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => getenv('MAILCHIMP_API_KEY'),
        'server' => 'us8'
    ]);

    $list_id = getenv('MAILCHIMP_LIST_ID');
    $subscriber_hash = md5(strtolower($data['email']));
    
    try {
        // Try to get the member first
        $member = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
        // If we get here, the member exists
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'You\'re already subscribed!']);
        exit;
    } catch (\Exception $e) {
        // Member doesn't exist, proceed with adding them
        $result = $mailchimp->lists->addListMember($list_id, [
            'email_address' => $data['email'],
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $data['name']
            ]
        ]);

        error_log('API call successful: ' . print_r($result, true));
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing!']);
    }
} catch (\Exception $e) {
    error_log('Error in Mailchimp API call: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 