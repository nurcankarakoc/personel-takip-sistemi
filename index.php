<?php
session_start();
date_default_timezone_set("Europe/Istanbul");

// db.php dosyasının bağlantıyı doğru kurduğundan emin olun.
include "db.php";

// Eğer personel girişi yapılmamışsa login.php'ye yönlendir
if (empty($_SESSION['personel_id'])) {
    header("Location: login.php");
    exit;
}

$personel_id = intval($_SESSION['personel_id']);
$tarih = date("Y-m-d");

// Bugünkü kayıt
$stmt_kayit = $conn->prepare("SELECT * FROM kayitlar WHERE personel_id = ? AND tarih = ?");

if ($stmt_kayit === false) {
    die("SQL HATA: 'kayitlar' tablosu yok veya bağlantı problemi var. Hata: " . $conn->error);
}

$stmt_kayit->bind_param("is", $personel_id, $tarih);
$stmt_kayit->execute();
$bugunku_kayit = $stmt_kayit->get_result()->fetch_assoc();
$stmt_kayit->close();

// Saatler
$simdi = time();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personel Takip Sistemi - Ana Sayfa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <div class="header-title">
                <h1>Personel Takip Sistemi</h1>
                <p>Günlük Giriş-Çıkış ve Mola Takip Paneli</p>
            </div>
            
            <div class="user-badge">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['ad_soyad']); ?></div>
                    <div class="user-role"><?php echo ($_SESSION['role'] === 'admin') ? 'Yönetici' : 'Personel'; ?></div>
                </div>
                <div class="nav-links">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="admin.php" class="btn btn-secondary nav-link-btn">Yönetici Paneli</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-danger nav-link-btn" style="box-shadow: none;">Çıkış Yap</a>
                </div>
            </div>
        </header>

        <?php
        // Hata mesajını göster
        if (isset($_SESSION['hata'])) {
            echo '<div class="alert alert-danger">⚠️ HATA: ' . htmlspecialchars($_SESSION['hata']) . '</div>';
            unset($_SESSION['hata']);
        }
        ?>

        <!-- Stats Grid -->
        <div class="grid-stats">
            <!-- Clock Card -->
            <div class="stat-card live-clock-card">
                <div class="stat-label">Canlı Saat</div>
                <div class="live-clock-time" id="live-clock"><?php echo date("H:i:s"); ?></div>
                <div class="live-clock-date"><?php echo date("d.m.Y"); ?></div>
            </div>

            <!-- Start Work Card -->
            <div class="stat-card <?php echo ($bugunku_kayit && $bugunku_kayit['ise_gelis']) ? 'stat-success' : 'stat-danger'; ?>">
                <div class="stat-label">İşe Geliş</div>
                <div class="stat-value">
                    <?php echo ($bugunku_kayit && $bugunku_kayit['ise_gelis']) ? date("H:i", strtotime($bugunku_kayit['ise_gelis'])) : '--:--'; ?>
                </div>
                <div class="stat-desc">Günlük mesai başlangıç saati</div>
            </div>

            <!-- Break Status Card -->
            <?php
            $mola_durumu = 'Başlamadı';
            $card_class = '';
            $mola_sayisi = 0;
            $toplam_mola_saniye = 0;
            $aktif_mola_baslangic = null;

            if ($bugunku_kayit) {
                $kayit_id = $bugunku_kayit['id'];
                $stmt_molalar = $conn->prepare("SELECT * FROM molalar WHERE kayit_id = ? ORDER BY id ASC");
                $stmt_molalar->bind_param("i", $kayit_id);
                $stmt_molalar->execute();
                $molalar_result = $stmt_molalar->get_result();
                
                while($m = $molalar_result->fetch_assoc()) {
                    $mola_sayisi++;
                    if ($m['bitis']) {
                        $toplam_mola_saniye += strtotime($m['bitis']) - strtotime($m['baslangic']);
                    } else {
                        $aktif_mola_baslangic = $m['baslangic'];
                        $mola_durumu = 'Molada';
                        $card_class = 'stat-warning';
                    }
                }
                $stmt_molalar->close();
                
                if ($mola_sayisi > 0 && $mola_durumu !== 'Molada') {
                    $mola_durumu = 'Mola Yapıldı';
                    $card_class = 'stat-success';
                }
            }
            ?>
            <div class="stat-card <?php echo $card_class; ?>">
                <div class="stat-label">Mola Durumu</div>
                <div class="stat-value"><?php echo $mola_durumu; ?></div>
                <div class="stat-desc">
                    <?php 
                    if ($mola_durumu === 'Molada') {
                        $gecmis_toplam_dk = round($toplam_mola_saniye / 60);
                        echo 'Önceki: ' . $gecmis_toplam_dk . ' dk | Şu an: <span id="break-timer" style="font-weight: 700; font-variant-numeric: tabular-nums;">00:00</span>';
                    } elseif ($mola_durumu === 'Mola Yapıldı') {
                        echo 'Toplam mola: ' . round($toplam_mola_saniye / 60) . ' dk (' . $mola_sayisi . ' mola)';
                    } else {
                        echo 'İstediğiniz zaman molaya çıkabilirsiniz';
                    }
                    ?>
                </div>
            </div>

            <!-- End Work Card -->
            <div class="stat-card <?php echo ($bugunku_kayit && $bugunku_kayit['isten_cikis']) ? 'stat-success' : 'stat-muted'; ?>">
                <div class="stat-label">İşten Çıkış</div>
                <div class="stat-value">
                    <?php echo ($bugunku_kayit && $bugunku_kayit['isten_cikis']) ? date("H:i", strtotime($bugunku_kayit['isten_cikis'])) : '--:--'; ?>
                </div>
                <div class="stat-desc">Günlük mesai bitiş saati</div>
            </div>
        </div>

        <!-- Mola Süresi Uyarısı (JS ile gösterilecek) -->
        <div id="break-warning" class="alert alert-danger" style="display: none; font-weight: bold; margin-bottom: 2rem;">
            ⚠️ UYARI: 1 saatlik mola süreniz dolmuştur! Lütfen işe dönüş kaydı yapın.
        </div>

        <!-- Actions Section -->
        <div class="actions-card">
            <h2>İşlemler</h2>
            <form method="POST" action="kaydet.php">
                <div class="actions-buttons">
                    <?php if (!$bugunku_kayit) : ?>
                        <button type="submit" name="ise_gelis" class="btn btn-success">İşe Giriş Yap</button>

                    <?php elseif (!$bugunku_kayit['isten_cikis']) : ?>
                        <?php if ($mola_durumu !== 'Molada') : ?>
                            <button type="submit" name="molaya_cikis" class="btn btn-warning">Molaya Çık</button>

                        <?php else : ?>
                            <button type="submit" name="moladan_donus" class="btn btn-primary">Moladan Dönüş Yap</button>

                        <?php endif; ?>

                        <button type="submit" name="isten_cikis" class="btn btn-danger">İşten Çıkış Yap</button>

                    <?php else: ?>
                        <span class="badge badge-success" style="font-size: 1.1rem; padding: 0.6rem 1.2rem;">
                            ✨ Bugün için tüm işlemleriniz tamamlanmıştır. İyi dinlenmeler!
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Recent Records Table -->
        <div class="table-card">
            <div class="table-card-header">
                <h2>Tüm Günlük Kayıtlar</h2>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Personel</th>
                            <th>Tarih</th>
                            <th>İşe Geliş</th>
                            <th>Molalar</th>
                            <th>İşten Çıkış</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT k.*, p.ad_soyad, 
                                  (SELECT GROUP_CONCAT(CONCAT('<span class=\"badge badge-warning\">', DATE_FORMAT(baslangic, '%H:%i'), '</span> - <span class=\"badge badge-primary\" style=\"background-color: rgba(99, 102, 241, 0.1); color: #818cf8;\">', IFNULL(DATE_FORMAT(bitis, '%H:%i'), 'Devam'), '</span>') SEPARATOR '<br><br>') FROM molalar WHERE kayit_id = k.id) as mola_saatleri
                                  FROM kayitlar k 
                                  JOIN personeller p ON p.id = k.personel_id 
                                  ORDER BY k.tarih DESC, k.id DESC";
                        
                        $result = $conn->query($query);
                        
                        if ($result === FALSE) {
                            echo "<tr><td colspan='5' style='color:var(--danger-gradient); text-align:center;'>SQL Hatası: " . htmlspecialchars($conn->error) . "</td></tr>";
                        } elseif ($result->num_rows === 0) {
                            echo "<tr><td colspan='5' style='text-align:center; color:var(--text-secondary);'>Henüz hiçbir kayıt bulunmuyor.</td></tr>";
                        } else {
                            while ($row = $result->fetch_assoc()) {
                                $gelis = $row['ise_gelis'] ? '<span class="badge badge-success">' . date("H:i", strtotime($row['ise_gelis'])) . '</span>' : '<span class="badge badge-gray">-</span>';
                                $molalar_badge = $row['mola_saatleri'] ? '<div style="font-size:0.85em; line-height:1.2;">' . $row['mola_saatleri'] . '</div>' : '<span class="badge badge-gray">-</span>';
                                $cikis = $row['isten_cikis'] ? '<span class="badge badge-danger">' . date("H:i", strtotime($row['isten_cikis'])) . '</span>' : '<span class="badge badge-gray">-</span>';
                                
                                echo "<tr>
                                        <td style='font-weight:600;'>" . htmlspecialchars($row['ad_soyad']) . "</td>
                                        <td>" . date("d.m.Y", strtotime($row['tarih'])) . "</td>
                                        <td>$gelis</td>
                                        <td>$molalar_badge</td>
                                        <td>$cikis</td>
                                      </tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- JavaScript For Realtime Elements -->
    <script>
        // Live Clock
        setInterval(() => {
            const now = new Date();
            const timeString = now.toLocaleTimeString('tr-TR', { hour12: false });
            document.getElementById('live-clock').innerText = timeString;
        }, 1000);

        // Break Timer (If user is currently on break)
        <?php if ($mola_durumu === 'Molada' && $aktif_mola_baslangic): ?>
            const parts = "<?php echo $aktif_mola_baslangic; ?>".split(':');
            const molaStart = new Date();
            molaStart.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]));

            const breakTimerEl = document.getElementById('break-timer');
            const breakWarningEl = document.getElementById('break-warning');

            function updateBreakTimer() {
                const now = new Date();
                let diffMs = now - molaStart;
                
                // Eğer gün döndüyse veya zaman farkı negatifse düzeltme
                if (diffMs < 0) {
                    diffMs = 0;
                }

                const totalSeconds = Math.floor(diffMs / 1000);
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;

                const formatted = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                if (breakTimerEl) {
                    breakTimerEl.innerText = formatted;
                }

                // 1 saati aşarsa kırmızıya boya ve uyarıyı göster (3600 sn)
                if (totalSeconds >= 3600) {
                    if (breakTimerEl) {
                        breakTimerEl.style.color = '#f87171';
                    }
                    if (breakWarningEl) {
                        breakWarningEl.style.display = 'block';
                    }
                }
            }

            updateBreakTimer();
            setInterval(updateBreakTimer, 1000);
        <?php endif; ?>
    </script>
</body>
</html>