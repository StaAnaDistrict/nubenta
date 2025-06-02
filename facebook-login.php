<?php
session_start(); // ← This line is critical for Facebook's CSRF protection to work

require_once __DIR__ . '/vendor/autoload.php'; // or path to Facebook SDK

$fb = new \Facebook\Facebook([
  'app_id' => '1754094531841255',
  'app_secret' => '065d4b781bae589d0104bfc0fa82cc90',
  'default_graph_version' => 'v18.0',
]);

$helper = $fb->getRedirectLoginHelper();
$permissions = ['email']; // Add 'public_profile' is optional, included by default
$loginUrl = $helper->getLoginUrl('http://localhost/nubenta/facebook-callback.php', $permissions);

header('Location: ' . $loginUrl);
exit;
