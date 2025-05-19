<?php
/*-------------------------------------------------
  chat_mark_read.php
  Marks all *unread* messages in a thread as read
  ------------------------------------------------*/
session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);       // unauthorised
    exit;
}

$uid = $_SESSION['user']['id'];
$tid = intval($_GET['thread_id'] ?? 0);
if ($tid === 0) exit;             // nothing to do

$q = $pdo->prepare(
    "UPDATE messages
       SET read_at = NOW()
     WHERE thread_id   = ?
       AND receiver_id = ?
       AND read_at IS NULL"
);
$q->execute([$tid, $uid]);

echo 'ok';
