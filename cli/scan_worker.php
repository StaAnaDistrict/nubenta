#!/usr/bin/env php
<?php
require_once __DIR__.'/../bootstrap.php';

$qfile=__DIR__.'/../tmp/scan_queue.txt';
if(!file_exists($qfile)) exit;
$ids=file($qfile,FILE_IGNORE_NEW_LINES);
file_put_contents($qfile,'');               // reset queue

foreach($ids as $id){
  // $isBad = my_nsfw_check($absPath);   // <-- implement later
  $isBad = false;
  if($isBad){
     $pdo->prepare("UPDATE messages
                       SET suspect=1, hidden_reason='nsfw'
                     WHERE id=?")->execute([$id]);
  }
}
