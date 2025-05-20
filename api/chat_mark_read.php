<?php
/*-------------------------------------------------
  chat_mark_read.php
  Marks all *unread* messages in a thread as read
  ------------------------------------------------*/
error_reporting(E_ALL); // Ensure errors are reported
ini_set('display_errors', 0); // Keep display errors off
ini_set('log_errors', 1); // Ensure logging is on
// Temporarily log to a separate file for debugging
$temp_log_file = __DIR__ . '/../chat_mark_read_debug.log';
ini_set('error_log', $temp_log_file);

// Basic file write test
$test_file = __DIR__ . '/../read_test.log';
file_put_contents($test_file, "chat_mark_read.php was accessed.\n", FILE_APPEND);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);       // unauthorised
    exit;
}

$uid = $_SESSION['user']['id'];
$tid = intval($_GET['thread_id'] ?? 0);
if ($tid === 0) exit;             // nothing to do

error_log("chat_mark_read.php: Marking thread " . $tid . " as read for user " . $uid);

$q = $pdo->prepare(
    "UPDATE messages
       SET read_at = NOW()
     WHERE thread_id   = ?
       AND receiver_id = ?
       AND read_at IS NULL"
);
$q->execute([$tid, $uid]);

error_log("chat_mark_read.php: Rows affected: " . $q->rowCount());

echo 'ok';
