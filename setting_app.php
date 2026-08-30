<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin yang bisa akses
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');
if (!in_array($role_id, [1, 2]) && !in_array($role_name_session, ['admin', 'superadmin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

// Proses ketika form disubmit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_method = $_POST['attendance_method'] ?? 'geo_face';
    $timezone = $_POST['timezone'] ?? 'Asia/Jakarta';

    try {
        $pdo->beginTransaction(); 

        // Cek apakah data setting untuk tenant ini sudah ada
        $stmtCheck = $pdo->prepare("SELECT id FROM tenant_settings WHERE tenant_id = ?");
        $stmtCheck->execute([$tenant_id]);

        if ($stmtCheck->fetch()) {
            // Update jika sudah ada
            $pdo->prepare("
                UPDATE tenant_settings 
                SET attendance_method = ?, timezone = ?
                WHERE tenant_id = ?
            ")->execute([$attendance_method, $timezone, $tenant_id]);
        } else {
            // Insert baru jika belum ada
            $pdo->prepare("
                INSERT INTO tenant_settings (tenant_id, attendance_method, timezone) 
                VALUES (?, ?, ?)
            ")->execute([$tenant_id, $attendance_method, $timezone]);
        }

        $pdo->commit();
        
        $_SESSION['toast_msg'] = "Pengaturan aplikasi berhasil disimpan!";
        $_SESSION['toast_type'] = "success";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['toast_msg'] = "Terjadi kesalahan sistem: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
}

// Ambil data Settings saat ini
$tenantSettings = [
    'attendance_method' => 'geo_face',
    'timezone' => 'Asia/Jakarta'
];

try {
    $stmtSettings = $pdo->prepare("SELECT attendance_method, timezone FROM tenant_settings WHERE tenant_id = ?");
    $stmtSettings->execute([$tenant_id]);
    if ($data = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
        $tenantSettings = array_merge($tenantSettings, $data);
    }
} catch (Exception $e) {
    // Abaikan jika error
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-20 md:pb-8 md:px-6">
        
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto ">
            
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="<?= ($base_url ?? '') ?>/index" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Pengaturan Aplikasi</h2>
                    <p class="text-[11px] text-gray-500">Konfigurasi metode absensi dan zona waktu sistem.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="">
                    <div class="space-y-6">
                        
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Sistem & Log Absensi</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <!-- PILIHAN METODE ABSENSI -->
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Metode Absensi Default</label>
                                    <div class="relative">
                                        <?php $current_method = $tenantSettings['attendance_method']; ?>
                                        <select name="attendance_method" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none appearance-none text-xs font-bold text-gray-800">
                                            <option value="geo_face" <?= ($current_method == 'geo_face') ? 'selected' : '' ?>>Radius Lokasi + Deteksi Wajah</option>
                                            <option value="geo_only" <?= ($current_method == 'geo_only') ? 'selected' : '' ?>>Radius Lokasi Saja</option>
                                            <option value="anywhere_gps" <?= ($current_method == 'anywhere_gps') ? 'selected' : '' ?>>Lokasi Saja (Dimana Saja)</option>
                                            <option value="face_only" <?= ($current_method == 'face_only') ? 'selected' : '' ?>>Deteksi Wajah Saja</option>
                                            <option value="geo_selfie" <?= ($current_method == 'geo_selfie') ? 'selected' : '' ?>>Radius Lokasi + Selfie</option>
                                            <option value="selfie_only" <?= ($current_method == 'selfie_only') ? 'selected' : '' ?>>Selfie Saja</option>
                                            <option value="geo_photo" <?= ($current_method == 'geo_photo') ? 'selected' : '' ?>>Radius Lokasi + Foto (Selfie / Lokasi)</option>
                                            <option value="photo_only" <?= ($current_method == 'photo_only') ? 'selected' : '' ?>>Foto Saja (Selfie / Lokasi)</option>
                                            <option value="tap_only" <?= ($current_method == 'tap_only') ? 'selected' : '' ?>>Hanya Klik Absen</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                    <p class="text-[9px] text-gray-500 mt-1">Pilih tingkat keamanan validasi saat karyawan menekan tombol absen.</p>
                                </div>

                                <!-- PILIHAN ZONA WAKTU -->
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Zona Waktu (Timezone)</label>
                                    <div class="relative">
                                        <?php $current_tz = $tenantSettings['timezone']; ?>
                                        <select name="timezone" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none appearance-none text-xs font-bold text-gray-800">
                                            <option value="Asia/Jakarta" <?= ($current_tz == 'Asia/Jakarta') ? 'selected' : '' ?>>WIB (Waktu Indonesia Barat)</option>
                                            <option value="Asia/Makassar" <?= ($current_tz == 'Asia/Makassar') ? 'selected' : '' ?>>WITA (Waktu Indonesia Tengah)</option>
                                            <option value="Asia/Jayapura" <?= ($current_tz == 'Asia/Jayapura') ? 'selected' : '' ?>>WIT (Waktu Indonesia Timur)</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                    <p class="text-[9px] text-gray-500 mt-1">Menyesuaikan jam masuk/pulang serta waktu cut-off penggajian.</p>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <button type="submit" class="w-full bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/components/toast.php'; ?>
<script>
    lucide.createIcons();
</script>
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>