<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/google.php'; // this should define $client

$auth_url = $client->createAuthUrl();

echo "Redirecting to Google: <a href='$auth_url'>$auth_url</a>";

// Redirect to Google's OAuth 2.0 server
$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;
