<?php
session_start();

// db.php dosyasının bağlantıyı doğru kurduğundan emin olun.
include "db.php";

// Eğer kullanıcı zaten giriş yapmışsa index.php’ye yönlendir
if (isset($_SESSION['personel_id']) && !empty($_SESSION['personel_id'])) {
    header("Location: index.php");
    exit;
}

$hata = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['personel_id']);
    $sifre = $_POST['sifre'];

    // 1. Tablo Adı ve Role sütunu sorguya eklendi.
    $stmt = $conn->prepare("SELECT id, sifre, ad_soyad, role FROM personeller WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // 2. Şifre Doğrulaması (Kayıt olurken hash'lediğiniz şifre ile karşılaştırılır)
        if (password_verify($sifre, $row['sifre'])) {
            // Başarılı giriş
            $_SESSION['personel_id'] = $row['id'];
            $_SESSION['ad_soyad'] = htmlspecialchars($row['ad_soyad']);
            $_SESSION['role'] = $row['role'] ? $row['role'] : 'personel'; // Varsayılan rol
            
            $stmt->close();
            header("Location: index.php");
            exit;
        } else {
            $hata = "❌ Şifre yanlış!";
        }
    } else {
        $hata = "❌ Bu ID bulunamadı!";
    }
    
    if (isset($stmt) && $stmt !== false) {
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Personel Takip Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Giriş Yap</h1>
                <p>Personel Takip Sistemine Hoş Geldiniz</p>
            </div>

            <?php if ($hata): ?>
                <div class="alert alert-danger">
                    <?php echo $hata; ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="personel_id">Personel ID / Numarası</label>
                    <input type="number" id="personel_id" name="personel_id" class="form-control" placeholder="Örn: 123" required>
                </div>

                <div class="form-group">
                    <label for="sifre">Şifre</label>
                    <input type="password" id="sifre" name="sifre" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary">Giriş Yap</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">
                    Hesabınız yok mu? <a href="register.php" style="font-weight: 500;">Kayıt olun</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>