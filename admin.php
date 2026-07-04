<?php
session_start();
include "db.php";

// Admin yetki kontrolü
if (empty($_SESSION['personel_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$mesaj = '';
$mesaj_turu = '';

// 1. PERSONEL SİLME İŞLEMİ
if (isset($_GET['sil_id'])) {
    $silinecek_id = intval($_GET['sil_id']);
    
    if ($silinecek_id > 0) {
        // Yöneticinin kendini silmesini engelle
        if ($silinecek_id === intval($_SESSION['personel_id'])) {
            $mesaj = "❌ Kendi hesabınızı silemezsiniz!";
            $mesaj_turu = "danger";
        } else {
            // ON DELETE CASCADE sayesinde kayitlar tablosundaki veriler de otomatik silinir.
            $stmt_personel = $conn->prepare("DELETE FROM personeller WHERE id = ?");
            $stmt_personel->bind_param("i", $silinecek_id);
            
            if ($stmt_personel->execute()) {
                $mesaj = "✅ " . $silinecek_id . " ID numaralı personel ve tüm kayıtları başarıyla silindi.";
                $mesaj_turu = "success";
            } else {
                $mesaj = "❌ HATA: Personel silinirken bir sorun oluştu: " . $conn->error;
                $mesaj_turu = "danger";
            }
            $stmt_personel->close();
        }
    }
}

// 2. YENİ PERSONEL EKLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['personel_ekle'])) {
    $personel_id = intval($_POST['personel_id']);
    $ad_soyad = htmlspecialchars($_POST['ad_soyad']);
    $sifre = $_POST['sifre'];
    $telefon = htmlspecialchars($_POST['phone_number']);
    $email = htmlspecialchars($_POST['email']); 
    $role = isset($_POST['role']) ? htmlspecialchars($_POST['role']) : 'personel';

    if (empty($personel_id) || $personel_id <= 0) {
        $mesaj = "❌ Personel ID boş bırakılamaz ve sıfırdan büyük olmalıdır.";
        $mesaj_turu = "danger";
    } elseif (empty($ad_soyad)) {
        $mesaj = "❌ Ad Soyad alanı boş bırakılamaz.";
        $mesaj_turu = "danger";
    } elseif (empty($sifre) || strlen($sifre) < 4) {
        $mesaj = "❌ Şifre en az 4 karakter olmalıdır.";
        $mesaj_turu = "danger";
    } elseif (empty($email)) {
        $mesaj = "❌ E-posta adresi boş bırakılamaz.";
        $mesaj_turu = "danger";
    } else {
        // Benzersizlik kontrolleri
        $stmt_check = $conn->prepare("SELECT id FROM personeller WHERE id = ? OR email = ? OR phone_number = ?");
        $stmt_check->bind_param("iss", $personel_id, $email, $telefon);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mesaj = "❌ Girdiğiniz Personel ID, E-posta veya Telefon numarası zaten kullanımda!";
            $mesaj_turu = "danger";
            $stmt_check->close();
        } else {
            $stmt_check->close();
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            
            $stmt_insert = $conn->prepare("INSERT INTO personeller (id, ad_soyad, sifre, phone_number, email, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("isssss", $personel_id, $ad_soyad, $sifre_hash, $telefon, $email, $role);

            if ($stmt_insert->execute()) {
                $mesaj = "✅ Personel başarıyla eklendi.";
                $mesaj_turu = "success";
            } else {
                $mesaj = "❌ Hata oluştu: " . $stmt_insert->error;
                $mesaj_turu = "danger";
            }
            $stmt_insert->close();
        }
    }
}

// Filtre Değişkenleri
$filter_personel = isset($_GET['filter_personel']) ? intval($_GET['filter_personel']) : 0;
$filter_tarih = isset($_GET['filter_tarih']) ? htmlspecialchars($_GET['filter_tarih']) : '';

// Personel Listesi
$personeller = $conn->query("SELECT id, ad_soyad, email, phone_number, role FROM personeller ORDER BY id ASC");

// Filtre için personel listesini diziye doldurma
$filter_personel_list = [];
if ($personeller) {
    while($p = $personeller->fetch_assoc()) {
        $filter_personel_list[] = $p;
    }
    // Result pointer'ı başa sıfırlıyoruz tekrar döngüde kullanabilmek için
    $personeller->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli - Personel Takip Sistemi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="header-title">
                <h1>Yönetici Kontrol Paneli</h1>
                <p>Sistem Personel Yönetimi ve Çalışma Logları</p>
            </div>
            
            <div class="user-badge">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['ad_soyad']); ?></div>
                    <div class="user-role">Sistem Yöneticisi</div>
                </div>
                <div class="nav-links">
                    <a href="index.php" class="btn btn-secondary nav-link-btn">Ana Sayfaya Dön</a>
                    <a href="logout.php" class="btn btn-danger nav-link-btn" style="box-shadow: none;">Çıkış Yap</a>
                </div>
            </div>
        </header>

        <!-- Bildirim Mesajları -->
        <?php if (!empty($mesaj)): ?>
            <div class="alert alert-<?php echo $mesaj_turu; ?>">
                <?php echo $mesaj; ?>
            </div>
        <?php endif; ?>

        <!-- Admin Paneli Tab Menüsü -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="switchTab('logs')">Giriş-Çıkış Logları</button>
            <button class="tab-btn" onclick="switchTab('personnel')">Personel Yönetimi</button>
            <button class="tab-btn" onclick="switchTab('add-personnel')">Yeni Personel Ekle</button>
        </div>

        <!-- 1. TAB: GİRİŞ-ÇIKIŞ LOGLARI -->
        <div id="tab-logs" class="tab-content active">
            <!-- Filtreleme Çubuğu -->
            <form method="GET" class="filter-bar">
                <div class="filter-group">
                    <label for="filter_personel">Personel Filtrele</label>
                    <select id="filter_personel" name="filter_personel" class="form-control">
                        <option value="0">Tümü</option>
                        <?php foreach($filter_personel_list as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($filter_personel == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['ad_soyad']); ?> (ID: <?php echo $p['id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_tarih">Tarih Filtrele</label>
                    <input type="date" id="filter_tarih" name="filter_tarih" class="form-control" value="<?php echo $filter_tarih; ?>">
                </div>

                <div style="display:flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">Filtrele</button>
                    <a href="admin.php" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;">Temizle</a>
                </div>
            </form>

            <div class="table-card" style="padding-top:1rem;">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Personel ID</th>
                                <th>Ad Soyad</th>
                                <th>Tarih</th>
                                <th>İşe Geliş</th>
                                <th>Molalar</th>
                                <th>İşten Çıkış</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Filtrelenmiş sorguyu hazırlama
                            $query = "SELECT k.*, p.ad_soyad, 
                                      (SELECT GROUP_CONCAT(CONCAT('<span class=\"badge badge-warning\">', DATE_FORMAT(baslangic, '%H:%i'), '</span> - <span class=\"badge badge-primary\" style=\"background-color: rgba(99, 102, 241, 0.1); color: #818cf8;\">', IFNULL(DATE_FORMAT(bitis, '%H:%i'), 'Devam'), '</span>') SEPARATOR '<br><br>') FROM molalar WHERE kayit_id = k.id) as mola_saatleri
                                      FROM kayitlar k 
                                      JOIN personeller p ON p.id = k.personel_id 
                                      WHERE 1=1";
                            
                            if ($filter_personel > 0) {
                                $query .= " AND k.personel_id = " . $filter_personel;
                            }
                            if (!empty($filter_tarih)) {
                                $query .= " AND k.tarih = '" . $conn->real_escape_string($filter_tarih) . "'";
                            }
                            
                            $query .= " ORDER BY k.tarih DESC, k.id DESC";
                            $result = $conn->query($query);

                            if ($result && $result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    $gelis = $row['ise_gelis'] ? '<span class="badge badge-success">' . date("H:i", strtotime($row['ise_gelis'])) . '</span>' : '<span class="badge badge-gray">-</span>';
                                    $molalar_badge = $row['mola_saatleri'] ? '<div style="font-size:0.85em; line-height:1.2;">' . $row['mola_saatleri'] . '</div>' : '<span class="badge badge-gray">-</span>';
                                    $cikis = $row['isten_cikis'] ? '<span class="badge badge-danger">' . date("H:i", strtotime($row['isten_cikis'])) . '</span>' : '<span class="badge badge-gray">-</span>';

                                    echo "<tr>
                                            <td>" . $row['personel_id'] . "</td>
                                            <td style='font-weight:600;'>" . htmlspecialchars($row['ad_soyad']) . "</td>
                                            <td>" . date("d.m.Y", strtotime($row['tarih'])) . "</td>
                                            <td>$gelis</td>
                                            <td>$molalar_badge</td>
                                            <td>$cikis</td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; color: var(--text-secondary);'>Kayıt bulunamadı.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2. TAB: PERSONEL LİSTESİ VE YÖNETİMİ -->
        <div id="tab-personnel" class="tab-content">
            <div class="table-card" style="padding-top:1rem;">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ad Soyad</th>
                                <th>E-posta</th>
                                <th>Telefon</th>
                                <th>Yetki</th>
                                <th style="text-align:right;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($personeller && $personeller->num_rows > 0): ?>
                                <?php while($row = $personeller->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['id']; ?></td>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($row['ad_soyad']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                        <td>
                                            <span class="badge <?php echo ($row['role'] === 'admin') ? 'badge-danger' : 'badge-gray'; ?>">
                                                <?php echo ($row['role'] === 'admin') ? 'Yönetici' : 'Personel'; ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <?php if($row['id'] !== intval($_SESSION['personel_id'])): ?>
                                                <a href="admin.php?sil_id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-danger" 
                                                   style="padding: 0.35rem 0.75rem; font-size: 0.8rem; box-shadow: none;"
                                                   onclick="return confirm('<?php echo htmlspecialchars($row['ad_soyad']); ?> adlı personeli ve TÜM kayıtlarını silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');">
                                                    Sil
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Aktif Hesap</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; color: var(--text-secondary);">Sistemde personel kaydı bulunmuyor.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. TAB: YENİ PERSONEL EKLE -->
        <div id="tab-add-personnel" class="tab-content" style="max-width: 600px; margin: 0 auto;">
            <div class="table-card" style="padding: 2rem;">
                <h2 style="margin-bottom: 1.5rem; text-align: center; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Yeni Personel Kayıt Formu</h2>
                
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label for="personel_id">Personel ID / Numarası</label>
                        <input type="number" id="personel_id" name="personel_id" class="form-control" placeholder="Örn: 105" required>
                    </div>

                    <div class="form-group">
                        <label for="ad_soyad">Ad Soyad</label>
                        <input type="text" id="ad_soyad" name="ad_soyad" class="form-control" placeholder="Örn: Mehmet Can" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-posta Adresi</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="mehmet@firma.com" required>
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Telefon Numarası</label>
                        <input type="text" id="phone_number" name="phone_number" class="form-control" placeholder="5559876543" required>
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

                    <button type="submit" name="personel_ekle" class="btn btn-primary" style="margin-top: 1rem;">Personel Ekle</button>
                </form>
            </div>
        </div>

    </div>

    <!-- JavaScript for Admin Tabs and Page Logic -->
    <script>
        function switchTab(tabId) {
            // Tüm sekmeleri gizle ve butonların aktifliğini kaldır
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Seçilen sekmeyi göster ve butona aktiflik ver
            document.getElementById('tab-' + tabId).classList.add('active');
            
            // Tıklanan butonu bulup aktif yapalım
            const eventTarget = window.event ? window.event.currentTarget : null;
            if (eventTarget) {
                eventTarget.classList.add('active');
            } else {
                // Eğer event bulunamazsa buton yazısına göre eşleştir
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    if (tabId === 'logs' && btn.innerText.includes('Log')) btn.classList.add('active');
                    if (tabId === 'personnel' && btn.innerText.includes('Yönet')) btn.classList.add('active');
                    if (tabId === 'add-personnel' && btn.innerText.includes('Ekle')) btn.classList.add('active');
                });
            }

            // Url hash'ini kaydet
            localStorage.setItem('activeAdminTab', tabId);
        }

        // Sayfa yüklendiğinde son seçili sekmeyi yükle
        window.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeAdminTab');
            // Eğer URL'de bir arama (filtreleme) yapıldıysa direkt log sekmesini aç
            const hasQuery = window.location.search.length > 0;
            
            if (hasQuery) {
                switchTab('logs');
            } else if (savedTab) {
                switchTab(savedTab);
            }
        });
    </script>
</body>
</html>
