<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin, superadmin, hr
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');
if (!in_array($role_id, [1, 2, 3]) && !in_array($role_name_session, ['superadmin', 'admin', 'hr'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: ../../employee");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    header("Location: ../../employee");
    exit;
}

// 1. Ambil data karyawan terkait + department_id dari relasi tabel positions
$stmtUser = $pdo->prepare("
    SELECT u.*, r.name as role_name, p.department_id 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    LEFT JOIN positions p ON u.position_id = p.id
    WHERE u.uuid = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
");
$stmtUser->execute([$uuid, $tenant_id]);
$editUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$editUser) {
    $_SESSION['toast_msg'] = "Data karyawan tidak ditemukan.";
    $_SESSION['toast_type'] = "failed";
    header("Location: ../../employee");
    exit;
}

$toast_msg = '';
$toast_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = $_POST['password'] ?? ''; 
    $role = $_POST['role'] ?? 'employee';
    
    // Tangkap data relasi
    $position_id = !empty($_POST['position_id']) ? $_POST['position_id'] : null;
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    $shift_id = !empty($_POST['shift_id']) ? $_POST['shift_id'] : null;

    if (empty($name) || empty($email)) {
        $toast_msg = "Nama dan Email wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $editUser['id']]);
            
            if ($stmtCheck->fetch()) {
                $toast_msg = "Email sudah terdaftar di akun lain. Gunakan email berbeda.";
                $toast_type = "failed";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, whatsapp = ?, password = ?, 
                            role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1),
                            position_id = ?, location_id = ?, shift_id = ?
                        WHERE uuid = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$name, $email, $whatsapp, $hashed_password, $role, $position_id, $location_id, $shift_id, $uuid, $tenant_id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, whatsapp = ?, 
                            role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1),
                            position_id = ?, location_id = ?, shift_id = ?
                        WHERE uuid = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$name, $email, $whatsapp, $role, $position_id, $location_id, $shift_id, $uuid, $tenant_id]);
                }

                $_SESSION['toast_msg'] = "Data $name berhasil diperbarui!";
                $_SESSION['toast_type'] = "success";
                header("Location: ../../employee");
                exit;
            }
        } catch (Exception $e) {
            $toast_msg = "Kesalahan sistem: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

// AMBIL DATA DROPDOWN DARI DATABASE
$departments = []; $positions = []; $locations = []; $shifts = [];
try {
    $stmtDept = $pdo->prepare("SELECT id, name FROM departments WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmtDept->execute([$tenant_id]);
    $departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

    $stmtPos = $pdo->prepare("SELECT id, department_id, name FROM positions WHERE tenant_id = ? ORDER BY name ASC");
    $stmtPos->execute([$tenant_id]);
    $positions = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

    $stmtLoc = $pdo->prepare("SELECT id, name FROM locations WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmtLoc->execute([$tenant_id]);
    $locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

    $stmtShift = $pdo->prepare("SELECT * FROM shifts WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmtShift->execute([$tenant_id]);
    $shifts = $stmtShift->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan jika error
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

require_once __DIR__ . '/components/head.php';

// MEMUAT ASSETS JQUERY & SELECT2
echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';

require_once __DIR__ . '/components/sidebar.php';
?>

<!-- STYLE CUSTOM UNTUK MENYATUKAN SELECT2 DENGAN DESAIN TAILWIND -->
<style>
    .select2-container--default .select2-selection--single {
        background-color: #f9fafb !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 0.75rem !important;
        height: 42px !important;
        display: flex !important;
        align-items: center !important;
        outline: none !important;
        transition: all 0.2s ease-in-out;
    }
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #ea3800 !important; /* Warna primary UI Anda */
        box-shadow: 0 0 0 1px #ea3800 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1f2937 !important;
        font-size: 0.75rem !important;
        padding-left: 1rem !important;
        padding-right: 2rem !important;
        line-height: normal !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
        right: 10px !important;
    }
    .select2-dropdown {
        border: 1px solid #e5e7eb !important;
        border-radius: 0.75rem !important;
        overflow: hidden !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
        margin-top: 4px !important;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border-radius: 0.5rem !important;
        border: 1px solid #e5e7eb !important;
        padding: 0.5rem !important;
        font-size: 0.75rem !important;
        outline: none !important;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        border-color: #ea3800 !important;
    }
    .select2-results__option {
        font-size: 0.75rem !important;
        padding: 8px 16px !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #ea3800 !important;
        color: white !important;
    }
</style>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <!-- pb-24 agar scroll bebas dari toolbar mobile -->
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6">
        
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="../../employee" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Edit Karyawan</h2>
                    <p class="text-[11px] text-gray-500">Ubah data untuk <?= htmlspecialchars($editUser['name']) ?>.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- KOLOM KIRI: Informasi Akun -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Akun</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                                <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $editUser['name']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: Budi Santoso">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Email</label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $editUser['email']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="budi@perusahaan.com">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor WhatsApp</label>
                                <input type="text" name="whatsapp" value="<?= htmlspecialchars($_POST['whatsapp'] ?? $editUser['whatsapp']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="08123456789">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Ubah Kata Sandi</label>
                                <input type="password" name="password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Biarkan kosong jika tidak diubah">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Hak Akses (Role)</label>
                                <div class="relative">
                                    <?php $currentRole = $_POST['role'] ?? strtolower($editUser['role_name']); ?>
                                    <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none">
                                        <option value="employee" <?= ($currentRole == 'employee') ? 'selected' : '' ?>>Karyawan Standar (Employee)</option>
                                        <option value="manager" <?= ($currentRole == 'manager') ? 'selected' : '' ?>>Manajer (Manager)</option>
                                        <option value="hr" <?= ($currentRole == 'hr') ? 'selected' : '' ?>>HR Manager</option>
                                        <option value="admin" <?= ($currentRole == 'admin') ? 'selected' : '' ?>>Admin Perusahaan</option>
                                    </select>
                                    <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Penempatan Kerja -->
                        <div class="space-y-4 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Penempatan Kerja</h3>
                            
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Departemen</label>
                                <?php $currentDept = $_POST['department_id'] ?? $editUser['department_id'] ?? ''; ?>
                                <select name="department_id" id="department_id" class="select2 w-full" data-placeholder="-- Pilih Departemen --">
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= ($currentDept == $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Posisi / Jabatan</label>
                                <?php $currentPos = $_POST['position_id'] ?? $editUser['position_id'] ?? ''; ?>
                                <select name="position_id" id="position_id" class="select2 w-full" data-placeholder="-- Pilih Posisi --">
                                    <option value="">-- Pilih Posisi --</option>
                                    <?php foreach($positions as $pos): ?>
                                        <option value="<?= $pos['id'] ?>" data-dept="<?= $pos['department_id'] ?>" <?= ($currentPos == $pos['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pos['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Lokasi Kantor</label>
                                <?php $currentLoc = $_POST['location_id'] ?? $editUser['location_id'] ?? ''; ?>
                                <select name="location_id" class="select2 w-full" data-placeholder="-- Pilih Lokasi --">
                                    <option value="">-- Pilih Lokasi --</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= ($currentLoc == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Jadwal Shift</label>
                                <?php $currentShift = $_POST['shift_id'] ?? $editUser['shift_id'] ?? ''; ?>
                                <select name="shift_id" class="select2 w-full" data-placeholder="-- Pilih Shift --">
                                    <option value="">-- Pilih Shift --</option>
                                    <?php foreach($shifts as $shift): 
                                        $shift_label = $shift['name'] . ' (' . date('H:i', strtotime($shift['start'])) . ' - ' . date('H:i', strtotime($shift['end'])) . ')';
                                    ?>
                                        <option value="<?= $shift['id'] ?>" <?= ($currentShift == $shift['id']) ? 'selected' : '' ?>><?= htmlspecialchars($shift_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="../../employee" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<!-- Komponen Toast Global (Local/Validation Error) -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // Trigger local validation error bila ada
    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg && typeof window.showToast === 'function') {
        window.showToast(phpMsg, phpType);
    }

    // ==========================================
    // LOGIKA SELECT2 & CASCADING DROPDOWN
    // ==========================================
    $(document).ready(function() {
        $('.select2').select2({
            width: '100%'
        });

        // Cascading Department -> Position
        const posSelect = $('#position_id');
        const originalPosOptions = posSelect.find('option').clone(); 
        
        function filterPositions() {
            const deptId = $('#department_id').val();
            const currentVal = posSelect.val(); 
            
            posSelect.empty(); 
            
            originalPosOptions.each(function() {
                if ($(this).val() === "" || !deptId || $(this).attr('data-dept') === String(deptId)) {
                    posSelect.append($(this).clone());
                }
            });
            
            if (posSelect.find(`option[value="${currentVal}"]`).length > 0) {
                posSelect.val(currentVal);
            } else {
                posSelect.val("");
            }
        }

        $('#department_id').on('change', function() {
            filterPositions();
            posSelect.trigger('change.select2'); 
        });

        filterPositions();
        posSelect.trigger('change.select2');
    });
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>