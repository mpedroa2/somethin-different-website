<?php
// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$redirect = $_POST['redirect'] ?? 'https://www.somethindifferent.co/indexSD.html';

// Process subscription
try {
    // Your existing Mailchimp code here...
    
    // Redirect with success
    header('Location: ' . $redirect . '?status=success');
    exit();
} catch (Exception $e) {
    // Redirect with error
    header('Location: ' . $redirect . '?status=error&message=' . urlencode($e->getMessage()));
    exit();
} 

$allowed_origins = [
    'https://www.somethindifferent.co',
    'https://somethindifferent.co',
    'https://somethin-different-dd8aefc58ac8.herokuapp.com'
]; 