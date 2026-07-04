<?php
session_start();
date_default_timezone_set("Europe/Istanbul");

include "db.php"; // Veritabanı bağlantısı

if (empty($_SESSION['personel_id'])) {
    header("Location: login.php");
    exit;
}



$personel_id = intval($_SESSION['personel_id']);
$tarih = date("Y-m-d");
$saat = date("H:i:s");

// Hata mesajlarını saklamak için
$hata_mesaji = null;

// O gün için KAYDIN TÜMÜNÜ ÇEK
$stmt_kayit = $conn->prepare("SELECT * FROM kayitlar WHERE personel_id = ? AND tarih = ?");
$stmt_kayit->bind_param("is", $personel_id, $tarih);
$stmt_kayit->execute();
$bugunku_kayit = $stmt_kayit->get_result()->fetch_assoc();
$stmt_kayit->close();

$alan = null;

// 1. İŞE GELİŞ İŞLEMİ (Eğer bugün hiç kayıt yoksa)
if (!$bugunku_kayit) {
    if (isset($_POST['ise_gelis'])) {
        // Güvenli INSERT
        $stmt_insert = $conn->prepare("INSERT INTO kayitlar (personel_id, tarih, ise_gelis) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iss", $personel_id, $tarih, $saat);
        
        if (!$stmt_insert->execute()) {
            $hata_mesaji = "İşe Geliş kaydı yapılırken SQL hatası: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }

// 2. DİĞER İŞLEMLER (Eğer bugün bir kayıt varsa)
} else {
    $kayit_id = $bugunku_kayit['id'];
    
    // Zaten İşten Çıkış yapılmışsa hiçbir işleme izin verme
    if (!empty($bugunku_kayit['isten_cikis'])) {
        $hata_mesaji = "Hata: Bugün için tüm işlemleriniz tamamlanmıştır.";
        
    } else {
        // Aktif mola kontrolü
        $stmt_mola = $conn->prepare("SELECT id FROM molalar WHERE kayit_id = ? AND bitis IS NULL ORDER BY id DESC LIMIT 1");
        $stmt_mola->bind_param("i", $kayit_id);
        $stmt_mola->execute();
        $aktif_mola = $stmt_mola->get_result()->fetch_assoc();
        $stmt_mola->close();

        if (isset($_POST['molaya_cikis'])) {
            if ($aktif_mola) {
                $hata_mesaji = "Hata: Moladan dönmeden tekrar molaya çıkamazsınız.";
            } else {
                // Yeni mola kaydı oluştur
                $stmt_insert = $conn->prepare("INSERT INTO molalar (kayit_id, baslangic) VALUES (?, ?)");
                $stmt_insert->bind_param("is", $kayit_id, $saat);
                if (!$stmt_insert->execute()) {
                    $hata_mesaji = "Mola kaydı sırasında SQL hatası: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }

        } elseif (isset($_POST['moladan_donus'])) {
            if (!$aktif_mola) {
                $hata_mesaji = "Hata: Aktif bir mola kaydınız bulunmuyor.";
            } else {
                // Aktif molayı bitir
                $mola_id = $aktif_mola['id'];
                $stmt_update = $conn->prepare("UPDATE molalar SET bitis = ? WHERE id = ?");
                $stmt_update->bind_param("si", $saat, $mola_id);
                if (!$stmt_update->execute()) {
                    $hata_mesaji = "Mola dönüşü sırasında SQL hatası: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
            
        } elseif (isset($_POST['isten_cikis'])) {
            if ($aktif_mola) {
                $hata_mesaji = "Hata: İşten çıkmadan önce moladan dönmeniz gerekmektedir.";
            } else {
                $alan = "isten_cikis";
            }
        }
    }

    // Herhangi bir alan güncellenecekse (sadece kayitlar tablosu için, örn isten_cikis)
    if ($alan && !$hata_mesaji) {
        $stmt_update = $conn->prepare("UPDATE kayitlar SET $alan = ? WHERE id = ?");
        $stmt_update->bind_param("si", $saat, $kayit_id);
        
        if (!$stmt_update->execute()) {
            $hata_mesaji = "Kayıt güncelleme sırasında SQL hatası: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

// Sonuç: Hata mesajı varsa, onu oturuma kaydedip ana sayfaya yönlendir (index.php)
if ($hata_mesaji) {
    // index.php dosyanızda göstermek için bir Session değişkeni kullanın
    $_SESSION['hata'] = $hata_mesaji;
} else {
    // Başarılı işlemde eski hata mesajını temizle
    unset($_SESSION['hata']);
}

header("Location: index.php");
exit;
?>