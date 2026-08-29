<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');
if (!in_array($role_id, [1, 2]) && !in_array($role_name_session, ['admin', 'superadmin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: .");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

// Variabel penampung error untuk Mini Debugger
$debug_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $attendance_method = $_POST['attendance_method'] ?? 'geo_face';
    
    // Variabel Payroll Settings
    $payroll_cutoff_start = (int)($_POST['payroll_cutoff_start'] ?? 1);
    $payroll_cutoff_end = (int)($_POST['payroll_cutoff_end'] ?? 31);
    $payroll_bpjs_enabled = isset($_POST['payroll_bpjs_enabled']) ? 1 : 0;
    $bpjs_kesehatan_percent = (float)($_POST['bpjs_kesehatan_percent'] ?? 1.00);
    $bpjs_ketenagakerjaan_percent = (float)($_POST['bpjs_ketenagakerjaan_percent'] ?? 3.00);
    $payroll_alpha_method = $_POST['payroll_alpha_method'] ?? 'none';
    $payroll_alpha_nominal = (float)($_POST['payroll_alpha_nominal'] ?? 0);
    $payroll_meal_allowance = (float)($_POST['payroll_meal_allowance'] ?? 0);
    $payroll_transport_allowance = (float)($_POST['payroll_transport_allowance'] ?? 0);

    if (empty($name)) {
        $_SESSION['toast_msg'] = "Nama perusahaan wajib diisi!";
        $_SESSION['toast_type'] = "warning";
    } else {
        try {
            $pdo->beginTransaction(); 

            // Proses Upload Logo
            $stmtLogoCheck = $pdo->prepare("SELECT logo FROM tenants WHERE id = ?");
            $stmtLogoCheck->execute([$tenant_id]);
            $existingLogo = $stmtLogoCheck->fetchColumn();
            $logo_filename = $existingLogo ?: null;

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/assets/img/tenants/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed_ext)) {
                    if ($logo_filename && file_exists($upload_dir . $logo_filename)) unlink($upload_dir . $logo_filename);
                    $logo_filename = 'tenant_' . $tenant_id . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename);
                } else {
                    throw new Exception("Format logo tidak valid.");
                }
            }

            // 1. Update tenants
            $pdo->prepare("UPDATE tenants SET name = ?, email = ?, phone = ?, address = ?, logo = ? WHERE id = ?")
                ->execute([$name, $email, $phone, $address, $logo_filename, $tenant_id]);

            // 2. Update tenant_settings
            $stmtCheck = $pdo->prepare("SELECT id FROM tenant_settings WHERE tenant_id = ?");
            $stmtCheck->execute([$tenant_id]);

            if ($stmtCheck->fetch()) {
                $pdo->prepare("
                    UPDATE tenant_settings 
                    SET attendance_method = ?, payroll_cutoff_start = ?, payroll_cutoff_end = ?, payroll_bpjs_enabled = ?, 
                        bpjs_kesehatan_percent = ?, bpjs_ketenagakerjaan_percent = ?, payroll_alpha_method = ?, 
                        payroll_alpha_nominal = ?, payroll_meal_allowance = ?, payroll_transport_allowance = ?
                    WHERE tenant_id = ?
                ")->execute([
                    $attendance_method, $payroll_cutoff_start, $payroll_cutoff_end, $payroll_bpjs_enabled, 
                    $bpjs_kesehatan_percent, $bpjs_ketenagakerjaan_percent, $payroll_alpha_method, 
                    $payroll_alpha_nominal, $payroll_meal_allowance, $payroll_transport_allowance, $tenant_id
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO tenant_settings (
                        tenant_id, attendance_method, payroll_cutoff_start, payroll_cutoff_end, payroll_bpjs_enabled, 
                        bpjs_kesehatan_percent, bpjs_ketenagakerjaan_percent, payroll_alpha_method, 
                        payroll_alpha_nominal, payroll_meal_allowance, payroll_transport_allowance
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $tenant_id, $attendance_method, $payroll_cutoff_start, $payroll_cutoff_end, $payroll_bpjs_enabled, 
                    $bpjs_kesehatan_percent, $bpjs_ketenagakerjaan_percent, $payroll_alpha_method, 
                    $payroll_alpha_nominal, $payroll_meal_allowance, $payroll_transport_allowance
                ]);
            }

            $pdo->commit();
            $_SESSION['tenant_name'] = $name;
            $_SESSION['toast_msg'] = "Pengaturan berhasil diperbarui!";
            $_SESSION['toast_type'] = "success";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast_msg'] = "Gagal memperbarui pengaturan. Cek Debugger (Superadmin).";
            $_SESSION['toast_type'] = "error";
            
            // Tangkap full message untuk debug
            $debug_error = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
}

$tenantData = [];
try {
    $stmtTenant = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmtTenant->execute([$tenant_id]);
    if ($baseTenant = $stmtTenant->fetch(PDO::FETCH_ASSOC)) $tenantData = $baseTenant;

    $stmtSettings = $pdo->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ?");
    $stmtSettings->execute([$tenant_id]);
    if ($settingsData = $stmtSettings->fetch(PDO::FETCH_ASSOC)) $tenantData = array_merge($tenantData, $settingsData);
} catch (Exception $e) {
    if (empty($debug_error)) {
        $debug_error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $tenantData['name'] ?? $_SESSION['tenant_name'] ?? 'Perusahaan';

require_once __DIR__ . '/components/head.php';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" />';
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script>';
require_once __DIR__ . '/components/sidebar.php';
?>

<style>
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
    .dropify-wrapper { border-radius: 0.75rem !important; border: 1px solid #e5e7eb !important; background-color: #f9fafb !important; }
</style>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-20 md:pb-8 md:px-6">
        <div class="hidden md:block"><?php require_once __DIR__ . '/components/header.php'; ?></div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto ">
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="<?= ($base_url ?? '') ?>/." class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition"><i data-lucide="chevron-left" class="w-5 h-5"></i></a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Profil Perusahaan</h2>
                    <p class="text-[11px] text-gray-500">Pengaturan profil, absensi, dan jadwal cut-off penggajian.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="space-y-6">
                        
                        <!-- SEC 1: PROFIL -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Dasar</h3>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Logo Perusahaan</label>
                                <input type="file" name="logo" class="dropify" data-max-file-size="3M" data-allowed-file-extensions="jpg jpeg png webp" data-default-file="<?= !empty($tenantData['logo']) ? ($base_url ?? '') . '/assets/img/tenants/' . htmlspecialchars($tenantData['logo']) : '' ?>" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Nama Perusahaan</label><input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $tenantData['name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary text-xs font-bold text-gray-800"></div>
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Email</label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $tenantData['email'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary text-xs font-bold text-gray-800"></div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">No. Telepon</label><input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $tenantData['phone'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary text-xs font-bold text-gray-800"></div>
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Alamat</label><input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? $tenantData['address'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary text-xs font-bold text-gray-800"></div>
                            </div>
                        </div>

                        <!-- SEC 2: PAYROLL -->
                        <div class="space-y-4 pt-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Pengaturan Penggajian (Payroll)</h3>
                            
                            <!-- CUT OFF DATE -->
                            <div class="grid grid-cols-2 gap-4 mb-4 border border-primary/20 bg-primary/5 p-4 rounded-xl">
                                <div>
                                    <label class="block text-[10px] font-semibold text-primary mb-1.5 uppercase tracking-wider">Tanggal Buka Buku</label>
                                    <input type="number" name="payroll_cutoff_start" min="1" max="31" value="<?= htmlspecialchars($_POST['payroll_cutoff_start'] ?? $tenantData['payroll_cutoff_start'] ?? 1) ?>" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none text-xs font-bold text-gray-800" placeholder="1">
                                    <p class="text-[9px] text-gray-500 mt-1">Mulai hitung absen</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-primary mb-1.5 uppercase tracking-wider">Tanggal Tutup Buku</label>
                                    <input type="number" name="payroll_cutoff_end" min="1" max="31" value="<?= htmlspecialchars($_POST['payroll_cutoff_end'] ?? $tenantData['payroll_cutoff_end'] ?? 31) ?>" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl focus:outline-none text-xs font-bold text-gray-800" placeholder="31">
                                    <p class="text-[9px] text-gray-500 mt-1">Akhir hitung absen</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between bg-gray-50 border border-gray-200 p-4 rounded-xl">
                                <div><h4 class="text-xs font-bold text-gray-800">Potong BPJS Karyawan</h4><p class="text-[9px] text-gray-500 mt-0.5">Potong BPJS otomatis</p></div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <?php $bpjs_enabled = $_POST['payroll_bpjs_enabled'] ?? $tenantData['payroll_bpjs_enabled'] ?? 0; ?>
                                    <input type="checkbox" name="payroll_bpjs_enabled" id="payroll_bpjs_enabled" value="1" class="sr-only peer" <?= $bpjs_enabled ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                </label>
                            </div>
                            
                            <div id="bpjs_percent_container" class="grid grid-cols-2 gap-4 <?= $bpjs_enabled ? '' : 'hidden' ?> pb-2 border-b border-gray-100 border-dashed">
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Kes (%)</label><input type="number" step="0.01" name="bpjs_kesehatan_percent" value="<?= htmlspecialchars($_POST['bpjs_kesehatan_percent'] ?? $tenantData['bpjs_kesehatan_percent'] ?? 1.00) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800"></div>
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">TK (%)</label><input type="number" step="0.01" name="bpjs_ketenagakerjaan_percent" value="<?= htmlspecialchars($_POST['bpjs_ketenagakerjaan_percent'] ?? $tenantData['bpjs_ketenagakerjaan_percent'] ?? 3.00) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800"></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Metode Potongan Alpha</label>
                                    <div class="relative">
                                        <?php $alphaMethod = $_POST['payroll_alpha_method'] ?? $tenantData['payroll_alpha_method'] ?? 'none'; ?>
                                        <select name="payroll_alpha_method" id="payroll_alpha_method" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none appearance-none text-xs font-bold text-gray-800">
                                            <option value="none" <?= ($alphaMethod == 'none') ? 'selected' : '' ?>>Tanpa Potongan</option>
                                            <option value="prorata" <?= ($alphaMethod == 'prorata') ? 'selected' : '' ?>>Pro-rata</option>
                                            <option value="fixed" <?= ($alphaMethod == 'fixed') ? 'selected' : '' ?>>Nominal Tetap</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                </div>
                                
                                <div id="alpha_nominal_container" class="<?= ($alphaMethod == 'fixed') ? '' : 'hidden' ?>">
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nominal Alpha / Hari</label>
                                    <input type="number" name="payroll_alpha_nominal" id="payroll_alpha_nominal" value="<?= htmlspecialchars($_POST['payroll_alpha_nominal'] ?? $tenantData['payroll_alpha_nominal'] ?? 0) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800">
                                </div>
                                
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Uang Makan / Hadir</label><input type="number" name="payroll_meal_allowance" value="<?= htmlspecialchars($_POST['payroll_meal_allowance'] ?? $tenantData['payroll_meal_allowance'] ?? 0) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800"></div>
                                <div><label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Uang Transport / Hadir</label><input type="number" name="payroll_transport_allowance" value="<?= htmlspecialchars($_POST['payroll_transport_allowance'] ?? $tenantData['payroll_transport_allowance'] ?? 0) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800"></div>
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
    $(document).ready(function(){ $('.dropify').dropify(); });
    document.getElementById('payroll_bpjs_enabled').addEventListener('change', function() { document.getElementById('bpjs_percent_container').classList.toggle('hidden', !this.checked); });
    document.getElementById('payroll_alpha_method').addEventListener('change', function() { document.getElementById('alpha_nominal_container').classList.toggle('hidden', this.value !== 'fixed'); });
</script>
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>