<?php
session_save_path(__DIR__ . '/tmp');
session_start();
$_SESSION['test'] = "Session çalışıyor!";
echo "Session yazıldı: " . $_SESSION['test'];
?>
