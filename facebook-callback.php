<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php'; // or your SDK path
require_once 'db.php'; // Your existing database connection

$fb = new \Facebook\Facebook([
  'app_id' => '1754094531841255',
  'app_secret' => '065d4b781bae589d0104bfc0fa82cc90',
  'default_graph_version' => 'v18.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
  $accessToken = $helper->getAccessToken();
} catch(Facebook\Exceptions\FacebookResponseException $e) {
  echo 'Graph error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
  echo 'SDK error: ' . $e->getMessage();
  exit;
}

if (!isset($accessToken)) {
  echo 'Access token not received.';
  exit;
}

try {
  $response = $fb->get('/me?fields=id,name,email', $accessToken);
} catch(Facebook\Exceptions\FacebookResponseException $e) {
  echo 'Graph returned an error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
  echo 'Facebook SDK returned an error: ' . $e->getMessage();
  exit;
}

$fbUser = $response->getGraphUser();

$email = $fbUser['email'];
$name = $fbUser['name'];

// Check if user exists in your database
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
  // Register new user
  $stmt = $pdo->prepare("INSERT INTO users (name, email, role) VALUES (?, ?, ?)");
  $stmt->execute([$name, $email, 'user']);

  $userId = $pdo->lastInsertId();
  $user = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'user'];
} else {
  $userId = $user['id'];
}

// Set session
$_SESSION['user'] = $user;

// Redirect to dashboard
header('Location: dashboard.php');
exit;
