<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Guard: Hanya superadmin, admin, dan hr yang bisa edit karyawan
if (!in_array($role_id, [1, 2, 3]) && !in_array($role_name_session, ['superadmin', 'admin', 'hr'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: ../../employee");
    exit;
}

// Logika: Hanya role 1 & 2 yang bisa mengubah/menentukan hak akses
$can_assign_role = in_array($role_id, [1, 2]) || in_array($role_name_session, ['superadmin', 'admin']);

$tenant_id = $_SESSION['tenant_id'];
$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    header("Location: ../../employee");
    exit;
}

// Ambil Timezone dari tenant_settings
$stmtTS = $pdo->prepare("SELECT timezone FROM tenant_settings WHERE tenant_id = ?");
$stmtTS->execute([$tenant_id]);
$tz_setting = $stmtTS->fetchColumn() ?: 'Asia/Jakarta';
date_default_timezone_set($tz_setting);

$current_time = date('Y-m-d H:i:s');

// Ambil data karyawan terkait + department_id dari relasi tabel positions
// Serta gabungkan tabel user_details dan user_salaries
$stmtUser = $pdo->prepare("
    SELECT u.*, r.name as role_name, p.department_id,
           ud.legal_id, ud.id_image, ud.emergency_contact, ud.join_date,
           us.basic_salary, us.overtime_rate, us.bank_name, us.bank_account
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN user_details ud ON u.id = ud.user_id
    LEFT JOIN user_salaries us ON u.id = us.user_id
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

$target_user_id = $editUser['id'];

$toast_msg = '';
$toast_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = $_POST['password'] ?? ''; 
    
    // Jika bisa menentukan role, ambil dari POST, jika tidak paksa gunakan role eksisting
    $role = $_POST['role'] ?? 'employee';
    if (!$can_assign_role) {
        $role = strtolower($editUser['role_name'] ?? 'employee');
    }
    
    // Tangkap data relasi
    $position_id = !empty($_POST['position_id']) ? $_POST['position_id'] : null;
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $location_id = !empty($_POST['location_id']) ? $_POST['location_id'] : null;
    $shift_id = !empty($_POST['shift_id']) ? $_POST['shift_id'] : null;

    // Tangkap data personal (user_details) opsional
    $legal_id = trim($_POST['legal_id'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $join_date = !empty($_POST['join_date']) ? $_POST['join_date'] : null;

    // Tangkap data gaji (user_salaries) opsional
    $basic_salary = !empty($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0;
    $overtime_rate = !empty($_POST['overtime_rate']) ? (float)$_POST['overtime_rate'] : 0;
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_account = trim($_POST['bank_account'] ?? '');

    if (empty($name) || empty($email)) {
        $toast_msg = "Nama dan Email wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            $pdo->beginTransaction();

            // Cek duplikasi email
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $target_user_id]);
            
            if ($stmtCheck->fetch()) {
                throw new Exception("Email sudah terdaftar di akun lain. Gunakan email berbeda.");
            }

            // Handle Upload Foto KTP
            $id_image_filename = $editUser['id_image'] ?? null; 
            if (isset($_FILES['id_image']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/assets/img/employees/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($ext, $allowed_ext)) {
                    $id_image_filename = 'ktp_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['id_image']['tmp_name'], $upload_dir . $id_image_filename);
                } else {
                    throw new Exception("Format foto KTP tidak valid.");
                }
            }

            // 1. UPDATE TABLE USERS
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, whatsapp = ?, password = ?, 
                        role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1),
                        position_id = ?, manager_id = ?, location_id = ?, shift_id = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$name, $email, $whatsapp, $hashed_password, $role, $position_id, $manager_id, $location_id, $shift_id, $target_user_id, $tenant_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, whatsapp = ?, 
                        role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1),
                        position_id = ?, manager_id = ?, location_id = ?, shift_id = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$name, $email, $whatsapp, $role, $position_id, $manager_id, $location_id, $shift_id, $target_user_id, $tenant_id]);
            }

            // 2. UPDATE ATAU INSERT TABLE USER_DETAILS
            // Cek apakah data user_details sudah ada
            $stmtCheckDetails = $pdo->prepare("SELECT id FROM user_details WHERE user_id = ?");
            $stmtCheckDetails->execute([$target_user_id]);
            
            if ($stmtCheckDetails->fetch()) {
                $pdo->prepare("UPDATE user_details SET legal_id = ?, id_image = ?, emergency_contact = ?, join_date = ?, updated_at = ? WHERE user_id = ?")
                    ->execute([$legal_id, $id_image_filename, $emergency_contact, $join_date, $current_time, $target_user_id]);
            } else {
                $pdo->prepare("INSERT INTO user_details (user_id, legal_id, id_image, emergency_contact, join_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$target_user_id, $legal_id, $id_image_filename, $emergency_contact, $join_date, $current_time, $current_time]);
            }

            // 3. UPDATE ATAU INSERT TABLE USER_SALARIES
            $stmtCheckSalaries = $pdo->prepare("SELECT id FROM user_salaries WHERE user_id = ?");
            $stmtCheckSalaries->execute([$target_user_id]);

            if ($stmtCheckSalaries->fetch()) {
                $pdo->prepare("UPDATE user_salaries SET basic_salary = ?, overtime_rate = ?, bank_name = ?, bank_account = ?, updated_at = ? WHERE user_id = ?")
                    ->execute([$basic_salary, $overtime_rate, $bank_name, $bank_account, $current_time, $target_user_id]);
            } else {
                // Insert jika belum ada, asalkan ada field yang diisi
                if ($basic_salary > 0 || $overtime_rate > 0 || !empty($bank_name) || !empty($bank_account)) {
                    $pdo->prepare("INSERT INTO user_salaries (user_id, basic_salary, overtime_rate, bank_name, bank_account, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$target_user_id, $basic_salary, $overtime_rate, $bank_name, $bank_account, $current_time, $current_time]);
                }
            }

            $pdo->commit();

            $_SESSION['toast_msg'] = "Data $name berhasil diperbarui!";
            $_SESSION['toast_type'] = "success";
            header("Location: ../../employee");
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $toast_msg = "Kesalahan sistem: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

// AMBIL DATA DROPDOWN DARI DATABASE
$departments = []; $positions = []; $managers = []; $locations = []; $shifts = [];
try {
    $stmtDept = $pdo->prepare("SELECT id, name FROM departments WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmtDept->execute([$tenant_id]);
    $departments = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

    $stmtPos = $pdo->prepare("SELECT id, department_id, name FROM positions WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmtPos->execute([$tenant_id]);
    $positions = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

    // Ambil HANYA data user dengan role 'manager' + nama departemennya
    $stmtMgr = $pdo->prepare("
        SELECT u.id, u.name, p.department_id, d.name as department_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id 
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.tenant_id = ? AND r.name = 'manager' AND u.deleted_at IS NULL AND u.id != ?
        ORDER BY u.name ASC
    ");
    $stmtMgr->execute([$tenant_id, $editUser['id']]);
    $managers = $stmtMgr->fetchAll(PDO::FETCH_ASSOC);

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

// MEMUAT ASSETS JQUERY, SELECT2 & DROPIFY
echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/css/dropify.min.css" />';
echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/Dropify/0.2.2/js/dropify.min.js"></script>';

require_once __DIR__ . '/components/sidebar.php';
?>

<!-- STYLE CUSTOM -->
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
        border-color: #ea3800 !important;
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
    .dropify-wrapper {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
    }
</style>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
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
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <!-- BAGIAN 1: INFO AKUN & PENEMPATAN KERJA -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
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
                                <input type="tel" inputmode="numeric" pattern="[0-9\-\+]*" name="whatsapp" value="<?= htmlspecialchars($_POST['whatsapp'] ?? $editUser['whatsapp']) ?>" oninput="this.value = this.value.replace(/[^0-9\-\+]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="08123456789">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Ubah Kata Sandi</label>
                                <input type="password" name="password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Biarkan kosong jika tidak diubah">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Hak Akses (Role)</label>
                                <?php 
                                $currentRole = $_POST['role'] ?? strtolower($editUser['role_name']); 
                                if ($can_assign_role): 
                                ?>
                                    <div class="relative">
                                        <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none">
                                            <option value="employee" <?= ($currentRole == 'employee') ? 'selected' : '' ?>>Karyawan Standar (Employee)</option>
                                            <option value="manager" <?= ($currentRole == 'manager') ? 'selected' : '' ?>>Manajer (Manager)</option>
                                            <option value="hr" <?= ($currentRole == 'hr') ? 'selected' : '' ?>>HR Manager</option>
                                            <option value="admin" <?= ($currentRole == 'admin') ? 'selected' : '' ?>>Admin Perusahaan</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                <?php else: 
                                    $display_roles = [
                                        'employee'   => 'Karyawan Standar (Employee)',
                                        'manager'    => 'Manajer (Manager)',
                                        'hr'         => 'HR Manager',
                                        'admin'      => 'Admin Perusahaan',
                                        'superadmin' => 'Super Admin'
                                    ];
                                    $displayRoleName = $display_roles[$currentRole] ?? ucfirst($currentRole);
                                ?>
                                    <input type="hidden" name="role" value="<?= htmlspecialchars($currentRole) ?>">
                                    <input type="text" value="<?= htmlspecialchars($displayRoleName) ?>" readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-500 cursor-not-allowed">
                                    <p class="text-[9px] text-gray-400 mt-1.5">Hak akses hanya dapat diubah oleh Admin.</p>
                                <?php endif; ?>
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
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Atasan Langsung (Manager)</label>
                                <?php $currentMgr = $_POST['manager_id'] ?? $editUser['manager_id'] ?? ''; ?>
                                <select name="manager_id" id="manager_id" class="select2 w-full" data-placeholder="-- Pilih Atasan (Opsional) --">
                                    <option value="">-- Pilih Atasan (Opsional) --</option>
                                    <?php foreach($managers as $mgr): 
                                        $mgr_dept = !empty($mgr['department_name']) ? ' - ' . $mgr['department_name'] : ' - Tanpa Departemen';
                                        $mgr_label = $mgr['name'] . $mgr_dept;
                                    ?>
                                        <option value="<?= $mgr['id'] ?>" data-dept="<?= $mgr['department_id'] ?>" <?= ($currentMgr == $mgr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($mgr_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[9px] text-gray-400 mt-1.5">Opsi atasan akan difilter otomatis berdasarkan departemen.</p>
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

                    <!-- BAGIAN 2: PERSONAL & GAJI (OPSIONAL) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8 pt-6 border-t border-gray-100">
                        
                        <!-- KOLOM KIRI: Data Personal & Identitas -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Personal & Identitas (Opsional)</h3>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor KTP (NIK)</label>
                                    <input type="tel" inputmode="numeric" name="legal_id" value="<?= htmlspecialchars($_POST['legal_id'] ?? $editUser['legal_id'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="16 digit angka">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kontak Darurat</label>
                                    <input type="tel" inputmode="numeric" name="emergency_contact" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? $editUser['emergency_contact'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9\-\+]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="No. HP">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Bergabung</label>
                                <input type="date" name="join_date" value="<?= htmlspecialchars($_POST['join_date'] ?? $editUser['join_date'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Foto KTP / Identitas</label>
                                <?php $id_image_url = !empty($editUser['id_image']) ? ($base_url ?? '') . '/assets/img/employees/' . $editUser['id_image'] : ''; ?>
                                <input type="file" name="id_image" class="dropify" data-default-file="<?= $id_image_url ?>" data-max-file-size="3M" data-allowed-file-extensions="jpg jpeg png webp" />
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Penggajian -->
                        <div class="space-y-4 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Penggajian (Opsional)</h3>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Gaji Pokok (Rp)</label>
                                    <input type="tel" inputmode="numeric" name="basic_salary" value="<?= htmlspecialchars($_POST['basic_salary'] ?? (isset($editUser['basic_salary']) ? (int)$editUser['basic_salary'] : '')) ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Tanpa titik">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Rate Lembur / Jam (Rp)</label>
                                    <input type="tel" inputmode="numeric" name="overtime_rate" value="<?= htmlspecialchars($_POST['overtime_rate'] ?? (isset($editUser['overtime_rate']) ? (int)$editUser['overtime_rate'] : '')) ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Tanpa titik">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Bank</label>
                                <input type="text" name="bank_name" value="<?= htmlspecialchars($_POST['bank_name'] ?? $editUser['bank_name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Misal: BCA / Mandiri">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor Rekening</label>
                                <input type="tel" inputmode="numeric" name="bank_account" value="<?= htmlspecialchars($_POST['bank_account'] ?? $editUser['bank_account'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Nomor rekening">
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

<!-- Komponen Toast Global -->
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
    // LOGIKA PLUGIN DROPIFY & SELECT2
    // ==========================================
    $(document).ready(function() {
        // Init Dropify
        $('.dropify').dropify({
            messages: {
                'default': 'Pilih Foto KTP',
                'replace': 'Ganti',
                'remove':  'Hapus',
                'error':   'Error.'
            }
        });

        // Init Select2
        $('.select2').select2({ width: '100%' });

        // Cascading Department -> Position & Manager
        const posSelect = $('#position_id');
        const originalPosOptions = posSelect.find('option').clone(); 
        
        const mgrSelect = $('#manager_id');
        const originalMgrOptions = mgrSelect.find('option').clone(); 
        
        function filterDropdowns() {
            const deptId = $('#department_id').val();
            
            // 1. Filter Positions
            const currentPos = posSelect.val(); 
            posSelect.empty(); 
            originalPosOptions.each(function() {
                if ($(this).val() === "" || !deptId || $(this).attr('data-dept') === String(deptId)) {
                    posSelect.append($(this).clone());
                }
            });
            if (posSelect.find(`option[value="${currentPos}"]`).length > 0) {
                posSelect.val(currentPos);
            } else {
                posSelect.val("");
            }
            
            // 2. Filter Managers
            const currentMgr = mgrSelect.val(); 
            mgrSelect.empty(); 
            originalMgrOptions.each(function() {
                if ($(this).val() === "" || !deptId || $(this).attr('data-dept') === String(deptId)) {
                    mgrSelect.append($(this).clone());
                }
            });
            if (mgrSelect.find(`option[value="${currentMgr}"]`).length > 0) {
                mgrSelect.val(currentMgr);
            } else {
                mgrSelect.val("");
            }
        }

        $('#department_id').on('change', function() {
            filterDropdowns();
            posSelect.trigger('change.select2'); 
            mgrSelect.trigger('change.select2');
        });

        filterDropdowns();
        posSelect.trigger('change.select2');
        mgrSelect.trigger('change.select2');
    });
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>