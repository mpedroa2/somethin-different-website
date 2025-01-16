<?php
// Prevent any output before headers
ob_start();

// Prevent PHP errors from being displayed
error_reporting(0);
ini_set('display_errors', 0);

require_once 'vendor/autoload.php';

// Clear any previous output
ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log to file instead of output
error_log('Starting subscription process');

$data = json_decode(file_get_contents('php://input'), true);

try {
    if (!getenv('MAILCHIMP_API_KEY') || !getenv('MAILCHIMP_LIST_ID')) {
        throw new Exception('Mailchimp configuration missing');
    }

    $mailchimp = new \MailchimpMarketing\ApiClient();
    $mailchimp->setConfig([
        'apiKey' => getenv('MAILCHIMP_API_KEY'),
        'server' => 'us8'
    ]);

    $list_id = getenv('MAILCHIMP_LIST_ID');
    $subscriber_hash = md5(strtolower($data['email']));
    
    try {
        $member = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
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
        
        echo json_encode(['success' => true, 'message' => 'Thanks for subscribing!']);
    }
} catch (\Exception $e) {
    error_log('Subscription error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Subscription failed. Please try again.']);
}

// Flush output buffer
ob_end_flush(); 