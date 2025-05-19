<?php
require_once '../bootstrap.php';
$userId = $_SESSION['user']['id'];
$msgId  = intval($_POST['msg_id']??0);
$flag   = $_POST['flag'];           // 'spam' | 'archive' | 'deleted'

$pdo->prepare("REPLACE INTO mailbox_flags (message_id,user_id,flag)
               VALUES (?,?,?)")->execute([$msgId,$userId,$flag]);
echo json_encode(['ok'=>1]);
