<?php
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';

    try {
        // Get config
        $api_key = getenv('MAILCHIMP_API_KEY');
        $list_id = getenv('MAILCHIMP_LIST_ID');

        // Initialize Mailchimp
        $mailchimp = new \MailchimpMarketing\ApiClient();
        $mailchimp->setConfig([
            'apiKey' => $api_key,
            'server' => 'us8'
        ]);

        // Add subscriber
        $result = $mailchimp->lists->addListMember($list_id, [
            'email_address' => $email,
            'status' => 'subscribed',
            'merge_fields' => [
                'FNAME' => $name
            ]
        ]);

        // Redirect with success
        header('Location: indexSD.html?status=success');
        exit();
    } catch (Exception $e) {
        // Redirect with error
        header('Location: indexSD.html?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
} 