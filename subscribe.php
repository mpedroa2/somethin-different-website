<?php
// Prevent any output before headers
ob_start();

// Prevent PHP errors from being displayed
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
error_log('API Key value: ' . substr(getenv('MAILCHIMP_API_KEY'), 0, 6) . '...');
error_log('List ID value: ' . getenv('MAILCHIMP_LIST_ID'));

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
    error_log('Setting up Mailchimp client...');
    
    $mailchimp->setConfig([
        'apiKey' => getenv('MAILCHIMP_API_KEY'),
        'server' => 'us8'
    ]);

    $list_id = getenv('MAILCHIMP_LIST_ID');
    $subscriber_hash = md5(strtolower($data['email']));
    
    try {
        error_log('Checking if member exists...');
        $member = $mailchimp->lists->getListMember($list_id, $subscriber_hash);
        error_log('Member exists');
        echo json_encode(['success' => true, 'message' => 'You\'re already subscribed!']);
        exit;
    } catch (\Exception $e) {
        error_log('Member not found, attempting to add...');
        error_log('Adding member with email: ' . $data['email']);
        
        // Format the member data according to Mailchimp's API requirements
        $member_data = [
            'email_address' => $data['email'],
            'status' => 'pending',
            'merge_fields' => [
                'FNAME' => isset($data['name']) ? $data['name'] : ''
            ]
        ];

        error_log('Sending member data: ' . json_encode($member_data));
        
        $result = $mailchimp->lists->addListMember($list_id, $member_data);
        error_log('API Response: ' . json_encode($result));
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thanks! Please check your email to confirm your subscription.'
        ]);
    }
} catch (\Exception $e) {
    $error_message = $e->getMessage();
    error_log('Detailed error: ' . $error_message);
    error_log('Full exception: ' . print_r($e, true));
    http_response_code(500);
    echo json_encode([
        'error' => 'Subscription failed. Please try again.',
        'details' => $error_message
    ]);
}

// Flush output buffer
ob_end_flush(); 