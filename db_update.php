<?php
include "db.php";
$sql = "CREATE TABLE IF NOT EXISTS molalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kayit_id INT NOT NULL,
    baslangic TIME NOT NULL,
    bitis TIME DEFAULT NULL,
    FOREIGN KEY (kayit_id) REFERENCES kayitlar(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === TRUE) {
    echo "Tablo olusturuldu.";
} else {
    echo "Hata: " . $conn->error;
}
?>
