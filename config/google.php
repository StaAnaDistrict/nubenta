<?php
require_once __DIR__ . '/../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('146330969283-itnsp54fdhv9ofj9i48g1ojoqbuam5fr.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/nubenta/google-callback.php');
$client->addScope("email");
$client->addScope("profile");
