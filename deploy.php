<?php
// Script Auto Pull Git
echo "<pre>";
$pull = shell_exec('git pull origin main 2>&1');
echo $pull;
echo "</pre>";
?>
