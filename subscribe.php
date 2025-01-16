<?php
// Prevent PHP errors from being output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'vendor/autoload.php';

// Ensure clean output buffer
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log errors to file instead of output
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

try {
    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => getenv('MAILCHIMP_API_KEY'),
        'server' => 'us8'
    ]);

    $list_id = getenv('MAILCHIMP_LIST_ID');
    $subscriber_hash = md5(strtolower($data['email']));
    
    try {
        $member = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'You\'re already subscribed!']);
        exit;
    } catch (\Exception $e) {
        $result = $mailchimp->lists->addListMember($list_id, [
            'email_address' => $data['email'],
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $data['name']
            ]
        ]);
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing!']);
    }
} catch (\Exception $e) {
    error_log('Mailchimp API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Subscription failed. Please try again.']);
} 