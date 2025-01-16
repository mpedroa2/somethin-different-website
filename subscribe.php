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

// Add detailed logging
error_log('Starting subscription process');
error_log('API Key exists: ' . (getenv('MAILCHIMP_API_KEY') ? 'Yes' : 'No'));
error_log('List ID exists: ' . (getenv('MAILCHIMP_LIST_ID') ? 'Yes' : 'No'));

$data = json_decode(file_get_contents('php://input'), true);
error_log('Received data: ' . json_encode($data));

// Validate email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

try {
    if (!getenv('MAILCHIMP_API_KEY')) {
        throw new Exception('Mailchimp API key is missing');
    }
    if (!getenv('MAILCHIMP_LIST_ID')) {
        throw new Exception('Mailchimp List ID is missing');
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
        error_log('Adding new member. Error was: ' . $e->getMessage());
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
    $error_message = $e->getMessage();
    error_log('Detailed error: ' . $error_message);
    http_response_code(500);
    echo json_encode(['error' => 'Subscription failed. Please try again.', 'details' => $error_message]);
}

// Flush output buffer
ob_end_flush(); 