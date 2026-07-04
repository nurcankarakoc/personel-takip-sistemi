<?php

$servername = "localhost";
$username = "root"; // Kendi kullanıcı adınız
$password = "";     // Kendi şifreniz
$dbname = "personel_takip";

// MySQLi ile bağlantı
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantı kontrolü
if ($conn->connect_error) {
    die("Veritabanı bağlantı hatası: " . $conn->connect_error);
}

// Türkçe karakterler için charset ayarı
$conn->set_charset("utf8mb4");
?>