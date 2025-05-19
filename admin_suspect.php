<?php
session_start(); require_once 'db.php';
if($_SESSION['user']['role']!=='admin') exit('no');
$rows=$pdo->query("SELECT m.*, u.name
                     FROM messages m
                     JOIN users u ON u.id=m.sender_id
                    WHERE m.suspect=1
                 ORDER BY m.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>Suspect messages</h2>
<?php foreach($rows as $m): ?>
 <div style="border:1px solid #ccc;margin:.5em;padding:.5em">
   <b>#<?= $m['id']?></b> by <?= htmlspecialchars($m['name'])?> –
   reason: <?= $m['hidden_reason']?> <br>
   <?= nl2br(htmlspecialchars($m['body'])) ?>
   <?php if($m['file_path']): ?>
      <br><a href="<?= $m['file_path']?>" target="_blank">attached file</a>
   <?php endif; ?>
   <form method="post" action="api/chat_flag.php" style="display:inline">
      <input type="hidden" name="msg_id" value="<?= $m['id']?>">
      <input type="hidden" name="flag"   value="deleted">
      <button class="btn btn-sm btn-danger">hide</button>
   </form>
 </div>
<?php endforeach;?>
