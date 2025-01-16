<?php
// Prevent PHP errors from being output
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'vendor/autoload.php';

// Log environment variables
error_log('MAILCHIMP_API_KEY: ' . (getenv('MAILCHIMP_API_KEY') ? 'exists' : 'missing'));
error_log('MAILCHIMP_LIST_ID: ' . (getenv('MAILCHIMP_LIST_ID') ? 'exists' : 'missing'));

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$data = json_decode(file_get_contents('php://input'), true);
error_log('Received data: ' . print_r($data, true));

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
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'You\'re already subscribed!']);
        exit;
    } catch (\Exception $e) {
        error_log('Adding new member: ' . $data['email']);
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
    error_log('Detailed error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Subscription failed. Please try again.']);
} 