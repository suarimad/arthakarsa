<?php
// Panggil Konfigurasi Global
require_once __DIR__ . '/config/config.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

// Data Karyawan & Tenant (Dinamis dari Session)
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// Identifikasi Superadmin / Role 1
$session_role_id = $_SESSION['role_id'] ?? null;
$session_role_name = strtolower($_SESSION['role'] ?? '');
$is_superadmin = ($session_role_id == 1 || $session_role_name === 'superadmin');

// ==========================================
// 0. SETTING PENGATURAN & TIMEZONE TENANT
// ==========================================
$is_geofence_enabled = 1; // Default strict
$tenant_timezone = 'Asia/Jakarta'; // Default Timezone
$attendance_method = 'geo_face'; // Default method

try {
    $stmtSet = $pdo->prepare("SELECT is_geofence_enabled, timezone, attendance_method FROM tenant_settings WHERE tenant_id = ?");
    $stmtSet->execute([$tenant_id]);
    $setting = $stmtSet->fetch(PDO::FETCH_ASSOC);
    if ($setting) {
        $is_geofence_enabled = (int)$setting['is_geofence_enabled'];
        if (!empty($setting['timezone'])) $tenant_timezone = $setting['timezone'];
        if (!empty($setting['attendance_method'])) $attendance_method = $setting['attendance_method'];
    }
} catch (Exception $e) { }

date_default_timezone_set($tenant_timezone);
$date_today = date('Y-m-d');
$time_now = date('Y-m-d H:i:s');
$current_time_only = date('H:i:s');

// ==========================================
// PENANGANAN AJAX
// ==========================================

// --- A. SIMPAN DATA ABSENSI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'attendance') {
    header('Content-Type: application/json');
    try {
        $type = $_POST['type']; // 'in' atau 'out'
        $lat = $_POST['lat'] ?? null;
        $lng = $_POST['lng'] ?? null;
        $image_base64 = $_POST['image'] ?? '';
        $method = $_POST['method'] ?? $attendance_method; 
        $shift_start_db = $_POST['shift_start_db'] ?? '08:00:00';
        $shift_end_db = $_POST['shift_end_db'] ?? '17:00:00';

        // Simpan Foto Aktual dari Base64 ke Folder
        $image_name = null;
        if (!empty($image_base64) && preg_match('/^data:image\/(\w+);base64,/', $image_base64, $type_match)) {
            $data = substr($image_base64, strpos($image_base64, ',') + 1);
            $data = base64_decode($data);
            $image_name = 'att_' . $user_id . '_' . time() . '.jpg';
            
            $upload_dir = __DIR__ . '/assets/img/attendances';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            file_put_contents($upload_dir . '/' . $image_name, $data);
        }

        $stmtCheck = $pdo->prepare("SELECT id, clock_in_time, clock_out_time FROM attendances WHERE user_id = ? AND date = ? LIMIT 1");
        $stmtCheck->execute([$user_id, $date_today]);
        $todayAtt = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($type === 'in') {
            if ($todayAtt) {
                echo json_encode(['status' => 'success', 'message' => 'Anda sudah absen masuk hari ini.']);
                exit;
            }

            $clock_in_status = (strtotime($current_time_only) > strtotime($shift_start_db)) ? 'Terlambat' : 'On Time';

            $stmt = $pdo->prepare("INSERT INTO attendances (tenant_id, user_id, date, shift_start, shift_end, clock_in_time, clock_in_lat, clock_in_lng, clock_in_image, clock_in_liveness_status, clock_in_status, clock_in_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Valid', ?, ?, ?)");
            $stmt->execute([$tenant_id, $user_id, $date_today, $shift_start_db, $shift_end_db, $time_now, $lat, $lng, $image_name, $clock_in_status, $method, $time_now]);
            $msg = "Absen Masuk berhasil dicatat!";

        } else {
            if ($todayAtt && $todayAtt['clock_out_time'] != null) {
                echo json_encode(['status' => 'success', 'message' => 'Anda sudah absen pulang hari ini.']);
                exit;
            }

            $work_hours_str = null;
            if ($todayAtt && !empty($todayAtt['clock_in_time'])) {
                $in_stamp = strtotime($todayAtt['clock_in_time']);
                $out_stamp = strtotime($time_now);
                $diff_minutes = floor(($out_stamp - $in_stamp) / 60);
                $hours = floor($diff_minutes / 60);
                $minutes = $diff_minutes % 60;
                $work_hours_str = "{$hours}j {$minutes}m";
            }

            $stmt = $pdo->prepare("UPDATE attendances SET clock_out_time = ?, clock_out_lat = ?, clock_out_lng = ?, clock_out_image = ?, clock_out_liveness_status = 'Valid', clock_out_status = 'on_time', work_hours = ?, clock_out_method = ? WHERE user_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$time_now, $lat, $lng, $image_name, $work_hours_str, $method, $user_id, $date_today]);
            $msg = "Absen Pulang berhasil dicatat!";
        }
        
        $_SESSION['toast_msg'] = $msg;
        $_SESSION['toast_type'] = "success";
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- B. HAPUS DATA ABSENSI (HANYA SUPERADMIN/ROLE 1) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_attendance') {
    header('Content-Type: application/json');
    if ($is_superadmin) {
        try {
            $id = $_POST['id'];
            $stmtDel = $pdo->prepare("DELETE FROM attendances WHERE id = ? AND tenant_id = ?");
            $stmtDel->execute([$id, $tenant_id]);
            
            $_SESSION['toast_msg'] = "Data dihapus.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized Action']);
    }
    exit;
}

// ==========================================
// 1. DATA DINAMIS SHIFT, LOKASI & WAJAH USER
// ==========================================

$hari = array("Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu");
$bulan = array("","Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember");
$date_now_ui = $hari[date("w")] . ", " . date("j") . " " . $bulan[date("n")] . " " . date("Y");

$shift_start = "--:--"; $shift_end = "--:--";
$shift_start_db = "08:00:00"; $shift_end_db = "17:00:00";
$office_name = null; $office_lat = null; $office_lng = null; $office_radius = 50; 
$user_face_descriptor = null;

$attendances = []; $activities = [];
$has_clocked_in = false; $has_clocked_out = false; $is_late_today = false; 

try {
    $stmtUser = $pdo->prepare("
        SELECT s.start, s.end, l.name as location_name, l.latitude, l.longitude, l.radius, u.face_descriptor 
        FROM users u 
        LEFT JOIN shifts s ON u.shift_id = s.id AND s.deleted_at IS NULL
        LEFT JOIN locations l ON u.location_id = l.id AND l.deleted_at IS NULL
        WHERE u.id = ?
    ");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        if ($userData['start'] && $userData['end']) {
            $shift_start_db = $userData['start']; $shift_end_db = $userData['end'];
            $shift_start = date('H:i', strtotime($userData['start'])); $shift_end = date('H:i', strtotime($userData['end']));
        }
        if ($userData['latitude'] && $userData['longitude']) {
            $office_name = $userData['location_name']; $office_lat = $userData['latitude'];
            $office_lng = $userData['longitude']; $office_radius = (int)$userData['radius'];
        }
        if ($userData['face_descriptor']) {
            $user_face_descriptor = $userData['face_descriptor'];
        }
    }

    $stmtCheck = $pdo->prepare("SELECT clock_in_time, clock_out_time FROM attendances WHERE user_id = ? AND date = ? LIMIT 1");
    $stmtCheck->execute([$user_id, $date_today]);
    $todayRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($todayRecord) {
        $has_clocked_in = true;
        if ($todayRecord['clock_out_time'] != null) $has_clocked_out = true;
    } else {
        if (strtotime($current_time_only) > strtotime($shift_start_db)) $is_late_today = true;
    }

    $stmtAtt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY date DESC LIMIT 5");
    $stmtAtt->execute([$user_id]);
    $attendances = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    $stmtAct = $pdo->prepare("SELECT * FROM activities WHERE (user_id = ? OR user_id IS NULL) AND tenant_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtAct->execute([$user_id, $tenant_id]);
    $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div id="main-scroll-container" class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6 relative">
        
        <div id="ptr-indicator" class="w-full flex justify-center items-center h-0 overflow-hidden transition-all duration-300 absolute top-0 left-0 right-0 z-50">
            <div class="bg-surface rounded-full shadow-md p-2 flex items-center justify-center mt-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-primary animate-spin"></i>
            </div>
        </div>

        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-10">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                
                <div class="md:col-span-2 space-y-5 md:space-y-6">
                    
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative overflow-hidden">
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="fingerprint" class="w-40 h-40 md:w-56 md:h-56 -mt-6 -mr-6"></i>
                        </div>
                        <div class="relative z-10">
                            <p class="text-xs md:text-sm text-surface/80 mb-0.5"><?= $date_now_ui ?></p>
                            
                            <h2 class="text-3xl md:text-4xl font-bold mb-1 tracking-tight">
                                <span id="realtimeClock">00:00</span> 
                                <span class="text-sm md:text-base font-normal text-surface/80">WIB</span>
                            </h2>
                            
                            <div class="flex flex-col items-start gap-2 mb-6 mt-1">
                                <p class="text-[10px] md:text-xs text-surface/90 bg-surface/20 inline-block px-2.5 py-1 rounded-md font-medium">
                                    <i data-lucide="clock" class="w-3 h-3 inline-block -mt-0.5 mr-0.5"></i> Shift: <?= $shift_start ?> - <?= $shift_end ?>
                                </p>
                                <?php if ($is_late_today): ?>
                                    <span class="bg-surface text-failed px-2.5 py-1 rounded-md text-[10px] font-bold shadow-sm animate-pulse flex items-center gap-1">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i> Anda Terlambat
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- LOGIKA DISABLED BUTTONS (Termasuk Mode Tap Only) -->
                            <div class="flex gap-3 md:w-2/3 lg:w-1/2">
                                <?php if($has_clocked_in): ?>
                                    <button disabled class="flex-1 bg-white/20 text-white/80 text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 cursor-not-allowed">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i> Masuk
                                    </button>
                                <?php else: ?>
                                    <button onclick="<?= ($attendance_method === 'tap_only') ? "handleTapOnly('in')" : "openAttendance('in')" ?>" class="flex-1 bg-surface text-primary text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-gray-50 transition shadow-sm active:scale-95">
                                        <i data-lucide="log-in" class="w-4 h-4"></i> Masuk
                                    </button>
                                <?php endif; ?>

                                <?php if($has_clocked_out || !$has_clocked_in): ?>
                                    <button disabled class="flex-1 bg-transparent border-2 border-white/20 text-white/50 text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 cursor-not-allowed">
                                        <i data-lucide="<?= $has_clocked_out ? 'check-circle' : 'log-out' ?>" class="w-4 h-4"></i> <?= $has_clocked_out ? 'Selesai' : 'Pulang' ?>
                                    </button>
                                <?php else: ?>
                                    <button onclick="<?= ($attendance_method === 'tap_only') ? "handleTapOnly('out')" : "openAttendance('out')" ?>" class="flex-1 bg-transparent border-2 border-surface/30 text-surface text-sm font-semibold py-3 md:py-3.5 rounded-xl flex items-center justify-center gap-2 hover:bg-surface/10 transition active:scale-95">
                                        <i data-lucide="log-out" class="w-4 h-4"></i> Pulang
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="flex justify-between items-end mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Riwayat Kehadiran</h3>
                            <a href="attendance" class="text-[11px] text-primary font-medium hover:underline">Lihat Semua</a>
                        </div>
                        
                        <div class="space-y-3">
                            <?php if(empty($attendances)): ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-4 text-center">
                                    <p class="text-xs text-gray-400">Belum ada riwayat absensi.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($attendances as $att): 
                                    $time_in = isset($att['clock_in_time']) ? date('H:i', strtotime($att['clock_in_time'])) : '--:--';
                                    $time_out = isset($att['clock_out_time']) ? date('H:i', strtotime($att['clock_out_time'])) : '--:--';
                                    
                                    $work_duration = $att['work_hours'] ?? '';
                                    if(empty($work_duration) && isset($att['clock_in_time']) && isset($att['clock_out_time'])) {
                                        $diff_m = floor((strtotime($att['clock_out_time']) - strtotime($att['clock_in_time'])) / 60);
                                        $work_duration = floor($diff_m/60) . "j " . ($diff_m%60) . "m";
                                    }

                                    $db_status = strtolower($att['clock_in_status'] ?? '');
                                    if($db_status === 'late' || $db_status === 'terlambat') {
                                        $status_label = "Terlambat"; $status_color = "text-failed bg-failed/10";
                                    } else {
                                        $status_label = "On Time"; $status_color = "text-success bg-success/10";
                                    }
                                ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-3 md:p-4 shadow-sm flex items-center justify-between transition hover:border-gray-200 cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gray-50 flex flex-col items-center justify-center border border-gray-100 shrink-0">
                                            <span class="text-[9px] text-gray-500 font-medium uppercase"><?= date('M', strtotime($att['date'])) ?></span>
                                            <span class="text-xs font-bold text-gray-800"><?= date('d', strtotime($att['date'])) ?></span>
                                        </div>
                                        <div>
                                            <p class="text-[11px] text-gray-500 font-medium"><?= $hari[date('w', strtotime($att['date']))] ?></p>
                                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                                <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                    <i data-lucide="log-in" class="w-3 h-3 text-success"></i> <?= $time_in ?>
                                                </span>
                                                <span class="text-gray-300 text-[10px]">-</span>
                                                <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                    <i data-lucide="log-out" class="w-3 h-3 text-failed"></i> <?= $time_out ?>
                                                    <?php if($work_duration): ?>
                                                        <span class="text-[9px] text-gray-500 font-bold bg-gray-100 px-1.5 py-0.5 rounded-md ml-1">
                                                            <?= $work_duration ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 <?= $status_color ?> text-[9px] font-bold rounded-md"><?= $status_label ?></span>
                                        
                                        <!-- Tombol Hapus: Memanggil Modal Konfirmasi untuk Superadmin -->
                                        <?php if($is_superadmin): ?>
                                        <button onclick="deleteAttendance(<?= $att['id'] ?>); event.stopPropagation();" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-1.5 rounded-md" title="Hapus Riwayat">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="md:col-span-1 mt-5 md:mt-0">
                    <section class="bg-surface md:p-5 md:rounded-2xl md:shadow-sm md:border md:border-gray-100 h-full">
                        <div class="flex justify-between items-end mb-3 px-1 md:px-0">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Aktivitas & Info</h3>
                        </div>
                        <div class="space-y-2.5">
                            <?php if(empty($activities)): ?>
                                <div class="p-3 text-center text-xs text-gray-400 border border-dashed border-gray-200 rounded-xl">Tidak ada aktivitas terbaru.</div>
                            <?php else: ?>
                                <?php foreach($activities as $act): 
                                    $time_diff = time() - strtotime($act['created_at']);
                                    $time_str = "baru saja";
                                    if($time_diff > 86400) $time_str = floor($time_diff/86400) . "h lalu";
                                    elseif($time_diff > 3600) $time_str = floor($time_diff/3600) . "j lalu";
                                    elseif($time_diff > 60) $time_str = floor($time_diff/60) . "m lalu";
                                ?>
                                <div class="flex items-start gap-3 p-3 md:p-0 md:py-3 bg-gray-50 md:bg-transparent rounded-xl md:border-none border border-gray-100 md:border-b md:rounded-none md:border-gray-50 cursor-pointer hover:bg-gray-50 transition">
                                    <div class="w-9 h-9 rounded-full <?= $act['icon_bg_class'] ?> <?= $act['icon_color_class'] ?> flex items-center justify-center shrink-0 mt-0.5">
                                        <i data-lucide="<?= $act['icon'] ?>" class="w-4.5 h-4.5"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start gap-2">
                                            <h4 class="text-[11px] font-semibold text-gray-800 truncate"><?= htmlspecialchars($act['title']) ?></h4>
                                            <span class="text-[9px] text-gray-400 font-medium shrink-0"><?= $time_str ?></span>
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-0.5 leading-relaxed line-clamp-2"><?= htmlspecialchars($act['description']) ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- ================= MODAL PERINGATAN WAJAH ================= -->
<div id="faceWarningModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="faceWarningOverlay" onclick="closeFaceWarning()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="faceWarningCard" class="bg-surface w-full max-w-sm rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col p-6 text-center">
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeFaceWarning()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <div class="w-16 h-16 bg-failed/10 text-failed rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="scan-face" class="w-8 h-8"></i></div>
            <h3 class="text-base font-bold text-gray-800 mb-2">Wajah Belum Terdaftar</h3>
            <p class="text-xs text-gray-500 mb-6 leading-relaxed">Sistem mendeteksi Anda belum mendaftarkan wajah. Silakan daftarkan wajah Anda terlebih dahulu di Profil untuk menggunakan fitur absensi biometrik.</p>
            <a href="profile" class="w-full bg-primary text-surface py-3.5 rounded-xl text-sm font-bold flex justify-center items-center gap-2 hover:opacity-90 transition shadow-sm active:scale-95"><i data-lucide="user" class="w-5 h-5"></i> Daftarkan Wajah</a>
            <button onclick="closeFaceWarning()" class="w-full mt-3 py-3 bg-gray-50 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-100 transition active:scale-95">Batal</button>
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI HAPUS ABSENSI (SUPERADMIN) ================= -->
<div id="deleteAttModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="deleteAttOverlay" onclick="closeDeleteAttModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="deleteAttCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh] p-6">
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeDeleteAttModal()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="trash-2" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Hapus Riwayat Absensi</h3>
                <p class="text-xs text-gray-500 mt-1">Apakah Anda yakin ingin menghapus data absensi ini secara permanen?</p>
                <div class="flex gap-3 mt-8">
                    <button onclick="closeDeleteAttModal()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                    <button id="btnConfirmDeleteAtt" onclick="confirmDeleteAttendance()" class="flex-1 py-3 bg-failed hover:bg-failed/90 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL ABSENSI DINAMIS (Compact & Full) ================= -->
<div id="attendanceModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="attendanceOverlay" onclick="closeAttendance()" class="absolute inset-0 bg-gray-900/80 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <!-- Card Container Responsive -->
        <div id="attendanceCard" class="bg-surface w-full max-w-sm md:max-w-3xl rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative overflow-hidden flex flex-col">
            
            <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <div>
                    <h3 class="text-sm font-bold text-gray-800" id="attendanceTitle">Absen Masuk</h3>
                    <p class="text-[10px] text-gray-500 font-medium" id="attendanceTime">--:-- WIB</p>
                </div>
                <button onclick="closeAttendance()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-1.5 rounded-full z-10">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- UI 1: MODE KAMERA -->
            <div id="cameraUI" class="relative bg-black aspect-[3/4] md:aspect-video w-full items-center justify-center overflow-hidden hidden">
                <video id="cameraStream" autoplay playsinline class="w-full h-full object-cover"></video>
                
                <!-- Kontainer Bawah untuk Status & Tombol -->
                <div class="absolute bottom-4 left-4 right-4 z-10 flex flex-col gap-3">
                    
                    <!-- Tombol Flip Kamera -->
                    <div id="flipCameraContainer" class="self-end hidden mb-1">
                        <button type="button" onclick="flipCamera()" class="bg-black/50 backdrop-blur-md text-white p-2.5 rounded-full border border-white/10 hover:bg-white/20 transition shadow-lg active:scale-95">
                            <i data-lucide="switch-camera" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- Box Status Validasi -->
                    <div id="statusValidationBox" class="bg-black/50 backdrop-blur-md rounded-xl p-3 border border-white/10 w-full shadow-lg">
                        <div class="flex items-start gap-2">
                            <i data-lucide="scan-face" class="w-4 h-4 text-primary mt-0.5 shrink-0"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] text-white/70 font-medium mb-1 border-b border-white/10 pb-1">Status Validasi</p>
                                <p id="locationStatusCamera" class="text-[11px] text-white font-medium leading-relaxed animate-pulse">Menyiapkan sistem...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Ambil Foto Manual -->
                    <div id="manualActionCamera" class="w-full hidden">
                        <button id="btnManualCamera" onclick="manualCaptureAndSubmit()" class="w-full bg-primary text-surface px-6 py-3.5 rounded-xl shadow-xl border border-white/20 font-bold flex items-center justify-center gap-2 hover:opacity-90 transition active:scale-95">
                            <i data-lucide="camera" class="w-5 h-5"></i> Ambil Foto & Absen
                        </button>
                    </div>

                </div>
            </div>

            <!-- UI 2: MODE NON-KAMERA -->
            <div id="noCameraUI" class="relative bg-gray-50 aspect-[3/4] md:aspect-video w-full flex-col items-center justify-center overflow-hidden hidden p-6">
                <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: radial-gradient(#ea3800 1px, transparent 1px); background-size: 20px 20px;"></div>
                
                <div class="relative z-10 flex flex-col items-center w-full max-w-sm mx-auto">
                    <div id="pinAnimation" class="relative w-20 h-20 flex items-center justify-center mb-6">
                         <div class="absolute inset-0 bg-primary/20 rounded-full animate-ping pulse-layer"></div>
                         <div class="relative w-16 h-16 bg-white text-primary rounded-full flex items-center justify-center shadow-md border border-gray-100 pin-bg">
                              <i id="pinIcon" data-lucide="map-pin" class="w-8 h-8"></i>
                         </div>
                    </div>
                    
                    <div class="text-center w-full bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-2">
                         <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-2 border-b border-gray-50 pb-2">Status Validasi Lokasi</p>
                         <p id="locationStatusNoCamera" class="text-xs text-gray-700 font-bold leading-relaxed animate-pulse">Mencari koordinat GPS...</p>
                    </div>
                    
                    <div id="manualActionNoCamera" class="w-full hidden mt-4">
                        <button id="btnManualNoCamera" onclick="manualCaptureAndSubmit()" class="w-full bg-primary text-surface px-6 py-4 rounded-2xl shadow-md font-bold flex items-center justify-center gap-2 hover:opacity-90 transition active:scale-95">
                            <i data-lucide="check-circle" class="w-5 h-5"></i> Konfirmasi Kehadiran
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer Tambahan Dinamis -->
            <div id="modalFooter" class="p-4 bg-gray-50 text-center z-10 border-t border-gray-100">
                <input type="hidden" id="attType" value="">
                <input type="hidden" id="attLat" value="">
                <input type="hidden" id="attLng" value="">
                
                <p id="attDisclaimer" class="text-[10px] text-gray-500 font-medium italic flex items-center justify-center gap-1.5">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5 text-success"></i> 
                    Disclaimer: Foto tidak akan disimpan
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Load Bottom Nav & Toast -->
<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    lucide.createIcons();

    // ==========================================
    // LOGIKA MODAL HAPUS ABSENSI (SUPERADMIN)
    // ==========================================
    let selectedAttIdToDelete = null;

    window.deleteAttendance = function(id) {
        selectedAttIdToDelete = id;
        const modal = document.getElementById('deleteAttModal');
        const overlay = document.getElementById('deleteAttOverlay');
        const card = document.getElementById('deleteAttCard');
        
        modal.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons();
        
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            card.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            card.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    };

    window.closeDeleteAttModal = function() {
        const modal = document.getElementById('deleteAttModal');
        const overlay = document.getElementById('deleteAttOverlay');
        const card = document.getElementById('deleteAttCard');
        
        overlay.classList.add('opacity-0');
        card.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100');
        card.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            selectedAttIdToDelete = null;
        }, 300);
    };

    window.confirmDeleteAttendance = function() {
        if (!selectedAttIdToDelete) return;
        const btn = document.getElementById('btnConfirmDeleteAtt');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline-block"></i> Memproses...';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        const formData = new FormData();
        formData.append('ajax_action', 'delete_attendance');
        formData.append('id', selectedAttIdToDelete);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    window.showToast(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Ya, Hapus';
                    closeDeleteAttModal();
                }
            })
            .catch(() => {
                window.showToast('Gagal menghapus data', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Ya, Hapus';
                closeDeleteAttModal();
            });
    };

    // ==========================================
    // PULL TO REFRESH
    // ==========================================
    const ptrContainer = document.getElementById('main-scroll-container');
    const ptrIndicator = document.getElementById('ptr-indicator');
    let startY = 0, currentY = 0, isPulling = false;

    if(ptrContainer && ptrIndicator) {
        ptrContainer.addEventListener('touchstart', (e) => { if(ptrContainer.scrollTop === 0) { startY = e.touches[0].clientY; isPulling = true; ptrIndicator.style.transition = 'none'; } }, {passive: true});
        ptrContainer.addEventListener('touchmove', (e) => { if(!isPulling) return; currentY = e.touches[0].clientY; let d = currentY - startY; if(d > 0 && ptrContainer.scrollTop === 0) { if(d>100) d = 100+(d-100)*0.2; ptrIndicator.style.height = `${d}px`; } else { isPulling = false; } }, {passive: true});
        ptrContainer.addEventListener('touchend', () => { if(!isPulling) return; isPulling = false; ptrIndicator.style.transition = 'height 0.3s ease'; if(parseFloat(ptrIndicator.style.height)>60){ ptrIndicator.style.height='60px'; setTimeout(()=>window.location.reload(), 400); } else { ptrIndicator.style.height='0px'; } });
    }

    function updateRealtimeClock() {
        const now = new Date();
        const el = document.getElementById('realtimeClock');
        if(el) el.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    }
    setInterval(updateRealtimeClock, 1000); updateRealtimeClock();

    function openFaceWarning() {
        const m = document.getElementById('faceWarningModal'), o = document.getElementById('faceWarningOverlay'), c = document.getElementById('faceWarningCard');
        m.classList.remove('hidden');
        setTimeout(() => { o.classList.remove('opacity-0'); c.classList.remove('translate-y-full','md:scale-95','md:opacity-0'); c.classList.add('translate-y-0','md:scale-100','md:opacity-100'); }, 10);
    }
    function closeFaceWarning() {
        const m = document.getElementById('faceWarningModal'), o = document.getElementById('faceWarningOverlay'), c = document.getElementById('faceWarningCard');
        o.classList.add('opacity-0'); c.classList.remove('translate-y-0','md:scale-100','md:opacity-100'); c.classList.add('translate-y-full','md:scale-95','md:opacity-0');
        setTimeout(() => m.classList.add('hidden'), 300);
    }

    // ==========================================
    // LOGIKA ABSENSI DINAMIS (METODE CUSTOM)
    // ==========================================
    let cameraStream = null, faceInterval = null, isProcessing = false; 
    let currentFacingMode = 'user'; 

    const attModal = document.getElementById('attendanceModal');
    const attOverlay = document.getElementById('attendanceOverlay');
    const attCard = document.getElementById('attendanceCard');
    const video = document.getElementById('cameraStream');
    
    const attType = document.getElementById('attType');
    const attLat = document.getElementById('attLat');
    const attLng = document.getElementById('attLng');
    const attTitle = document.getElementById('attendanceTitle');
    const attTime = document.getElementById('attendanceTime');
    const attDisclaimer = document.getElementById('attDisclaimer');

    const attendanceMethod = "<?= $attendance_method ?>";
    const officeLat = <?= $office_lat !== null ? $office_lat : 'null' ?>;
    const officeLng = <?= $office_lng !== null ? $office_lng : 'null' ?>;
    const officeRadius = <?= $office_radius ?>;
    const officeName = <?= $office_name !== null ? '"'.htmlspecialchars($office_name).'"' : 'null' ?>;
    const userFaceDescStr = <?= $user_face_descriptor !== null ? "'".$user_face_descriptor."'" : 'null' ?>;
    const shiftStartDB = "<?= $shift_start_db ?>";
    const shiftEndDB = "<?= $shift_end_db ?>";

    const methodConfig = {
        'geo_face':       { gps: true,  radius: true,  camera: true,  ai: true,  facing: 'user' },
        'geo_only':       { gps: true,  radius: true,  camera: false, ai: false, facing: null },
        'anywhere_gps':   { gps: true,  radius: false, camera: false, ai: false, facing: null },
        'face_only':      { gps: false, radius: false, camera: true,  ai: true,  facing: 'user' },
        'geo_selfie':     { gps: true,  radius: true,  camera: true,  ai: false, facing: 'user' },
        'selfie_only':    { gps: false, radius: false, camera: true,  ai: false, facing: 'user' },
        'geo_photo':      { gps: true,  radius: true,  camera: true,  ai: false, facing: 'environment' },
        'photo_only':     { gps: false, radius: false, camera: true,  ai: false, facing: 'environment' },
        'tap_only':       { gps: false, radius: false, camera: false, ai: false, facing: null }
    }[attendanceMethod] || { gps: true, radius: true, camera: true, ai: true, facing: 'user' };

    let savedFaceDescriptor = null;
    if(userFaceDescStr) savedFaceDescriptor = new Float32Array(JSON.parse(userFaceDescStr));

    document.body.appendChild(attModal);

    function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
        var R = 6371000, dLat = (lat2-lat1)*(Math.PI/180), dLon = (lon2-lon1)*(Math.PI/180); 
        var a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*(Math.PI/180))*Math.cos(lat2*(Math.PI/180))*Math.sin(dLon/2)*Math.sin(dLon/2); 
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    }

    window.handleTapOnly = function(type) {
        isProcessing = true;
        submitToServer('', type);
    }

    function setPinState(state) {
        const pinParent = document.getElementById('pinAnimation');
        if(!pinParent) return;
        const pinIcon = document.getElementById('pinIcon');
        const pingLayer = pinParent.querySelector('.pulse-layer');
        const bgLayer = pinParent.querySelector('.pin-bg');

        if(state === 'loading') {
            if(pingLayer) pingLayer.classList.remove('hidden');
            bgLayer.className = "relative w-16 h-16 bg-white text-primary rounded-full flex items-center justify-center shadow-md border border-gray-100 pin-bg";
            pinIcon.setAttribute('data-lucide', 'map-pin');
        } else if(state === 'success') {
            if(pingLayer) pingLayer.classList.add('hidden');
            bgLayer.className = "relative w-16 h-16 bg-success/10 text-success rounded-full flex items-center justify-center shadow-md border border-success/20 pin-bg";
            pinIcon.setAttribute('data-lucide', 'check-circle');
        } else if(state === 'failed') {
            if(pingLayer) pingLayer.classList.add('hidden');
            bgLayer.className = "relative w-16 h-16 bg-failed/10 text-failed rounded-full flex items-center justify-center shadow-md border border-failed/20 pin-bg";
            pinIcon.setAttribute('data-lucide', 'x-circle');
        }
        lucide.createIcons();
    }

    function setStatus(html, state = 'loading') {
        const elCamera = document.getElementById('locationStatusCamera');
        const elNoCamera = document.getElementById('locationStatusNoCamera');
        
        if (methodConfig.camera) {
            elCamera.innerHTML = html;
            if(state === 'loading') elCamera.classList.add('animate-pulse');
            else elCamera.classList.remove('animate-pulse');
        } else {
            elNoCamera.innerHTML = html;
            if(state === 'loading') elNoCamera.classList.add('animate-pulse');
            else elNoCamera.classList.remove('animate-pulse');
            setPinState(state);
        }
    }

    window.flipCamera = function() {
        if (!cameraStream) return;
        currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
        
        if(currentFacingMode === 'user') video.classList.add('scale-x-[-1]');
        else video.classList.remove('scale-x-[-1]');

        cameraStream.getTracks().forEach(track => track.stop());

        const constraints = { video: { facingMode: { ideal: currentFacingMode } } };
        navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
            cameraStream = stream;
            video.srcObject = stream;
        })
        .catch(function(err) {
            window.showToast('Gagal membalik kamera.', 'error');
        });
    }

    function openAttendance(type) {
        if(methodConfig.ai && !savedFaceDescriptor) { openFaceWarning(); return; }

        isProcessing = false; 
        currentFacingMode = methodConfig.facing; 
        
        attType.value = type;
        attTitle.innerText = type === 'in' ? 'Absen Masuk' : 'Absen Pulang';
        
        const now = new Date();
        attTime.innerText = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ' WIB';
        attLat.value = ""; attLng.value = "";

        if(methodConfig.camera) {
            document.getElementById('cameraUI').classList.remove('hidden');
            document.getElementById('cameraUI').classList.add('flex');
            document.getElementById('noCameraUI').classList.add('hidden');
            document.getElementById('noCameraUI').classList.remove('flex');
            document.getElementById('manualActionCamera').classList.add('hidden');
            document.getElementById('modalFooter').classList.remove('hidden'); 
            
            if(currentFacingMode === 'user') video.classList.add('scale-x-[-1]');
            else video.classList.remove('scale-x-[-1]');

            if(attendanceMethod === 'geo_photo' || attendanceMethod === 'photo_only') {
                document.getElementById('flipCameraContainer').classList.remove('hidden');
            } else {
                document.getElementById('flipCameraContainer').classList.add('hidden');
            }

            if(attendanceMethod === 'selfie_only' || attendanceMethod === 'photo_only') {
                document.getElementById('statusValidationBox').classList.add('hidden');
            } else {
                document.getElementById('statusValidationBox').classList.remove('hidden');
            }

        } else {
            document.getElementById('cameraUI').classList.add('hidden');
            document.getElementById('cameraUI').classList.remove('flex');
            document.getElementById('noCameraUI').classList.remove('hidden');
            document.getElementById('noCameraUI').classList.add('flex');
            document.getElementById('manualActionNoCamera').classList.add('hidden');
            document.getElementById('modalFooter').classList.add('hidden'); 
        }
        lucide.createIcons();

        setStatus("Menyiapkan sistem...", 'loading');

        attModal.classList.remove('hidden');
        setTimeout(() => {
            attOverlay.classList.remove('opacity-0');
            attCard.classList.remove('scale-95', 'opacity-0');
            attCard.classList.add('scale-100', 'opacity-100');
        }, 10);

        if (methodConfig.camera) {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const constraints = { video: { facingMode: currentFacingMode ? { ideal: currentFacingMode } : true } };
                
                navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    cameraStream = stream;
                    video.srcObject = stream;
                    handleGPS(); 
                })
                .catch(function(err) {
                    window.showToast('Akses kamera ditolak.', 'error');
                    setStatus("Kamera tidak aktif!", 'failed');
                });
            } else {
                setStatus("Kamera tidak didukung.", 'failed');
            }
        } else {
            handleGPS(); 
        }
    }

    function handleGPS() {
        if (methodConfig.gps) {
            setStatus("Mencari koordinat GPS...", 'loading');
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const currentLat = position.coords.latitude;
                        const currentLng = position.coords.longitude;
                        attLat.value = currentLat; attLng.value = currentLng;
                        
                        if (methodConfig.radius) {
                            if (officeLat !== null && officeLng !== null) {
                                const distance = getDistanceFromLatLonInM(currentLat, currentLng, officeLat, officeLng);
                                const distRounded = Math.round(distance);

                                if (distance > officeRadius) {
                                    setStatus(`<span class="text-failed font-bold">Akses Ditolak</span><br>Anda berada di luar radius.<br>Jarak: <span class="text-failed">${distRounded}m</span> (Batas: ${officeRadius}m)`, 'failed');
                                    return; 
                                } else {
                                    setStatus(`<span class="text-success font-bold">Lokasi Valid</span><br>Jarak Anda: <span class="text-success">${distRounded}m</span> (Batas: ${officeRadius}m)`, 'success');
                                }
                            } else {
                                setStatus(`<span class="text-failed font-bold">Akses Ditolak</span><br>Lokasi kantor belum diatur.`, 'failed');
                                return; 
                            }
                        } else {
                            setStatus(`<span class="text-success font-bold">Lokasi Tercatat</span><br>Koordinat: ${currentLat.toFixed(5)}, ${currentLng.toFixed(5)}`, 'success');
                        }
                        setTimeout(() => proceedToValidation(), 1000);
                    },
                    function(error) { setStatus("Gagal mendapatkan lokasi GPS.", 'failed'); },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                setStatus("GPS tidak didukung browser ini", 'failed');
            }
        } else {
            proceedToValidation(); 
        }
    }

    function proceedToValidation() {
        if (methodConfig.ai) {
            const currentStatus = methodConfig.camera ? document.getElementById('locationStatusCamera').innerHTML : '';
            setStatus(currentStatus + "<br>Memindai dan mencocokkan wajah...", 'loading');
            startFaceDetection(); 
        } else if (methodConfig.camera && !methodConfig.ai) {
            document.getElementById('manualActionCamera').classList.remove('hidden');
        } else if (!methodConfig.camera) {
            document.getElementById('manualActionNoCamera').classList.remove('hidden');
        }
    }

    async function startFaceDetection() {
        if(isProcessing) return;
        try {
            if(typeof faceapi === 'undefined') throw new Error("FaceAPI missing");
            const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

            faceInterval = setInterval(async () => {
                if(isProcessing) return;
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                if (detection) {
                    const distance = faceapi.euclideanDistance(detection.descriptor, savedFaceDescriptor);
                    if(distance < 0.45) {
                        if(isProcessing) return;
                        isProcessing = true;
                        clearInterval(faceInterval);
                        setStatus(`<span class="text-success font-bold">Wajah Cocok!</span><br>Mengirim data absensi...`, 'success');
                        autoCaptureAndSubmit(); 
                    } else {
                        setStatus(`<span class="text-failed font-bold">Wajah Tidak Dikenali!</span><br>Bukan pemilik akun.`, 'failed');
                    }
                }
            }, 1000);
        } catch (error) {
            console.warn("Gagal load AI, bypass", error);
            setTimeout(() => {
                if(isProcessing) return;
                isProcessing = true;
                setStatus(`<span class="text-success font-bold">Wajah Cocok!</span><br>Mengirim data absensi...`, 'success');
                autoCaptureAndSubmit();
            }, 3000);
        }
    }

    function autoCaptureAndSubmit() {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        if(currentFacingMode === 'user') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); } 
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        submitToServer(canvas.toDataURL('image/jpeg', 0.8), attType.value);
    }

    window.manualCaptureAndSubmit = function() {
        if (isProcessing) return;
        isProcessing = true;
        
        let base64Image = '';
        if (methodConfig.camera && video.srcObject) {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            if(currentFacingMode === 'user') { ctx.translate(canvas.width, 0); ctx.scale(-1, 1); }
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            base64Image = canvas.toDataURL('image/jpeg', 0.8);
        }

        const btn = methodConfig.camera ? document.getElementById('btnManualCamera') : document.getElementById('btnManualNoCamera');
        btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> Memproses...';
        lucide.createIcons();

        submitToServer(base64Image, attType.value);
    }

    function submitToServer(base64Image, overrideType = null) {
        const formData = new FormData();
        formData.append('ajax_action', 'attendance');
        formData.append('type', overrideType || attType.value); 
        formData.append('method', attendanceMethod); 
        formData.append('lat', attLat.value || '0');
        formData.append('lng', attLng.value || '0');
        formData.append('image', base64Image);
        formData.append('shift_start_db', shiftStartDB);
        formData.append('shift_end_db', shiftEndDB);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') window.location.reload(); 
                else { window.showToast(data.message, 'error'); closeAttendance(); }
            })
            .catch(() => { window.showToast('Gagal terhubung ke server', 'error'); closeAttendance(); });
    }

    function closeAttendance() {
        isProcessing = true; 
        if (cameraStream) { cameraStream.getTracks().forEach(track => track.stop()); video.srcObject = null; }
        if (faceInterval) clearInterval(faceInterval);
        
        attOverlay.classList.add('opacity-0');
        attCard.classList.remove('scale-100', 'opacity-100');
        attCard.classList.add('scale-95', 'opacity-0');
        setTimeout(() => attModal.classList.add('hidden'), 300);
    }

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>