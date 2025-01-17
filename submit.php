<?php
// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';

// Forward to Heroku
$ch = curl_init('https://somethin-different-dd8aefc58ac8.herokuapp.com/subscribe.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'name' => $name,
    'email' => $email
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

// Redirect back
header('Location: indexSD.html?status=success');
exit(); 