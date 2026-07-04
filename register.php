<?php
session_start();
// Veritabanı bağlantı dosyasını dahil et
include "db.php"; 

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['personel_id']) && !empty($_SESSION['personel_id'])) {
    header("Location: index.php");
    exit;
}

$hata = '';
$basarili = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POST verilerini al ve temizle
    $personel_id = isset($_POST['personel_id']) ? intval($_POST['personel_id']) : 0;
    $ad_soyad = htmlspecialchars($_POST['ad_soyad']);
    $sifre = $_POST['sifre'];
    $telefon = htmlspecialchars($_POST['phone_number']);
    $email = htmlspecialchars($_POST['email']); 
    $role = isset($_POST['role']) ? htmlspecialchars($_POST['role']) : 'personel';
    if (!in_array($role, ['personel', 'admin'])) {
        $role = 'personel';
    }

    // 1. Doğrulama Kontrolleri
    if (empty($personel_id) || $personel_id <= 0) {
        $hata = "❌ Personel ID boş bırakılamaz ve sıfırdan büyük olmalıdır.";
    } elseif (empty($ad_soyad)) {
        $hata = "❌ Ad Soyad boş bırakılamaz.";
    } elseif (empty($sifre) || strlen($sifre) < 4) {
        $hata = "❌ Şifre en az 4 karakter uzunluğunda olmalıdır.";
    } elseif (empty($email)) {
        $hata = "❌ E-posta adresi boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = "❌ Geçerli bir e-posta adresi giriniz.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $telefon)) { 
        $hata = "❌ Geçerli bir telefon numarası giriniz (sadece rakam, 10-15 hane).";
    } 
    else {
        // 2. Benzersizlik Kontrolleri (ID ve E-posta)
        
        // A) ID Kontrolü
        $stmt_id = $conn->prepare("SELECT id FROM personeller WHERE id = ?");
        $stmt_id->bind_param("i", $personel_id);
        $stmt_id->execute();
        $stmt_id->store_result();

        if ($stmt_id->num_rows > 0) {
            $hata = "❌ Girdiğiniz Personel ID numarası zaten kayıtlı!";
            $stmt_id->close();
        } else {
            $stmt_id->close();
            
            // B) E-posta Kontrolü
            $stmt_email = $conn->prepare("SELECT id FROM personeller WHERE email = ?");
            $stmt_email->bind_param("s", $email);
            $stmt_email->execute();
            $stmt_email->store_result();

            if ($stmt_email->num_rows > 0) {
                $hata = "❌ Bu e-posta adresi zaten kayıtlı!";
                $stmt_email->close();
            } else {
                $stmt_email->close(); 
                
                // C) Telefon Kontrolü
                $stmt_phone = $conn->prepare("SELECT id FROM personeller WHERE phone_number = ?");
                $stmt_phone->bind_param("s", $telefon);
                $stmt_phone->execute();
                $stmt_phone->store_result();

                if ($stmt_phone->num_rows > 0) {
                    $hata = "❌ Bu telefon numarası zaten kayıtlı!";
                    $stmt_phone->close();
                } else {
                    $stmt_phone->close();

                    // 3. Kayıt İşlemi
                    $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
                    
                    $stmt2 = $conn->prepare("INSERT INTO personeller (id, ad_soyad, sifre, phone_number, email, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt2->bind_param("isssss", $personel_id, $ad_soyad, $sifre_hash, $telefon, $email, $role);

                    if ($stmt2->execute()) {
                        $basarili = "✅ Kayıt başarıyla tamamlandı! Giriş yapabilirsiniz.";
                    } else {
                        $hata = "❌ Kayıt sırasında beklenmeyen bir hata oluştu: " . $stmt2->error;
                    }
                    $stmt2->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Personel Takip Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Kayıt Ol</h1>
                <p>Yeni Personel Hesap Oluşturma</p>
            </div>

            <?php if ($hata): ?>
                <div class="alert alert-danger">
                    <?php echo $hata; ?>
                </div>
            <?php endif; ?>

            <?php if ($basarili): ?>
                <div class="alert alert-success">
                    <?php echo $basarili; ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="personel_id">Personel ID / Numarası</label>
                    <input type="number" id="personel_id" name="personel_id" class="form-control" placeholder="Örn: 123" required>
                </div>

                <div class="form-group">
                    <label for="ad_soyad">Ad Soyad</label>
                    <input type="text" id="ad_soyad" name="ad_soyad" class="form-control" placeholder="Örn: Ahmet Yılmaz" required>
                </div>

                <div class="form-group">
                    <label for="email">E-posta Adresi</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="ornek@firma.com" required>
                </div>

                <div class="form-group">
                    <label for="phone_number">Telefon Numarası</label>
                    <input type="text" id="phone_number" name="phone_number" class="form-control" placeholder="5551234567" required>
                </div>

                <div class="form-group">
                    <label for="sifre">Şifre</label>
                    <input type="password" id="sifre" name="sifre" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="role">Yetki / Rol</label>
                    <select id="role" name="role" class="form-control form-select">
                        <option value="personel">Personel</option>
                        <option value="admin">Yönetici (Admin)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Kaydı Tamamla</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">
                    Zaten hesabınız var mı? <a href="login.php" style="font-weight: 500;">Giriş yap</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>