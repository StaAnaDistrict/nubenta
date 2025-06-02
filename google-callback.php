<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/google.php';

session_start();

try {
    if (!isset($_GET['code'])) {
        throw new Exception('No code provided from Google.');
    }

    // Fetch the access token using the provided code
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // Log the token response for debugging
    file_put_contents('token_log.txt', print_r($token, true));

    if (isset($token['error'])) {
        throw new Exception('Google OAuth Error: ' . $token['error']);
    }

    $client->setAccessToken($token['access_token']);

    // Get user info
    $oauth2 = new Google_Service_Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    // Connect to DB
    $pdo = new PDO('mysql:host=localhost;dbname=nubenta_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$userInfo->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role, created_at, full_name)
            VALUES (:name, :email, :password, 'user', NOW(), :full_name)
        ");
        $stmt->execute([
            ':name' => $userInfo->name,
            ':email' => $userInfo->email,
            ':password' => password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT),
            ':full_name' => $userInfo->name
        ]);

        // Re-fetch the user after insert
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userInfo->email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Save session data
    $_SESSION['user'] = $user;


    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Redirect to dashboard
    header('Location: dashboard.php');
    exit;

} catch (Exception $e) {
    echo '❌ Error during Google login: ' . htmlspecialchars($e->getMessage());
}
