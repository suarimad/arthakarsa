<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Guard: Hanya superadmin, admin, dan hr yang bisa menambah karyawan
if (!in_array($role_id, [1, 2, 3]) && !in_array($role_name_session, ['superadmin', 'admin', 'hr'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: employee");
    exit;
}

// Logika: Hanya role 1 & 2 yang bisa mengubah/menentukan hak akses
$can_assign_role = in_array($role_id, [1, 2]) || in_array($role_name_session, ['superadmin', 'admin']);

$tenant_id = $_SESSION['tenant_id'];
$toast_msg = '';
$toast_type = '';

// Ambil Timezone dari tenant_settings
$stmtTS = $pdo->prepare("SELECT timezone FROM tenant_settings WHERE tenant_id = ?");
$stmtTS->execute([$tenant_id]);
$tz_setting = $stmtTS->fetchColumn() ?: 'Asia/Jakarta';
date_default_timezone_set($tz_setting);

$current_time = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = $_POST['password'] ?? '';

    // Jika bisa menentukan role, ambil dari POST, jika tidak paksa 'employee' (Role ID 5)
    $role = $_POST['role'] ?? 'employee'; 
    if (!$can_assign_role) {
        $role = 'employee';
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

    if (empty($name) || empty($email) || empty($password)) {
        $toast_msg = "Kolom Nama, Email, dan Kata Sandi wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);

            if ($stmtCheck->fetch()) {
                throw new Exception("Email sudah terdaftar di sistem. Gunakan email lain.");
            }

            // Handle Upload Foto KTP
            $id_image_filename = null;
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

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 1. Insert tabel users
            $stmtUser = $pdo->prepare("
                INSERT INTO users (uuid, tenant_id, role_id, position_id, manager_id, location_id, shift_id, name, email, whatsapp, password, is_password_default, created_at) 
                VALUES (UUID(), ?, (SELECT id FROM roles WHERE name = ? LIMIT 1), ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmtUser->execute([$tenant_id, $role, $position_id, $manager_id, $location_id, $shift_id, $name, $email, $whatsapp, $hashed_password, $current_time]);
            
            $new_user_id = $pdo->lastInsertId();

            // 2. Insert tabel leave_balances (Jatah Cuti)
            $curr_year = date('Y', strtotime($current_time));
            $pdo->prepare("INSERT INTO leave_balances (tenant_id, user_id, year, total_quota, used_quota, created_at, updated_at) VALUES (?, ?, ?, 12, 0, ?, ?)")
                ->execute([$tenant_id, $new_user_id, $curr_year, $current_time, $current_time]);

            // 3. Insert tabel user_details
            $pdo->prepare("INSERT INTO user_details (user_id, legal_id, id_image, emergency_contact, join_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$new_user_id, $legal_id, $id_image_filename, $emergency_contact, $join_date, $current_time, $current_time]);

            // 4. Insert tabel user_salaries (hanya jika ada data yang diisi)
            if ($basic_salary > 0 || $overtime_rate > 0 || !empty($bank_name) || !empty($bank_account)) {
                $pdo->prepare("INSERT INTO user_salaries (user_id, basic_salary, overtime_rate, bank_name, bank_account, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$new_user_id, $basic_salary, $overtime_rate, $bank_name, $bank_account, $current_time, $current_time]);
            }

            $pdo->commit();

            $_SESSION['toast_msg'] = "Karyawan $name berhasil ditambahkan!";
            $_SESSION['toast_type'] = "success";
            header("Location: employee");
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

    // Ambil user dengan Role ID 4 (Manager) ATAU yang tidak memiliki manager_id (manager_id IS NULL)
    $stmtMgr = $pdo->prepare("
        SELECT u.id, u.name, p.department_id, d.name as department_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id 
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE u.tenant_id = ? AND (u.role_id = 4 OR u.manager_id IS NULL) AND u.deleted_at IS NULL
        ORDER BY u.name ASC
    ");
    $stmtMgr->execute([$tenant_id]);
    $managers = $stmtMgr->fetchAll(PDO::FETCH_ASSOC);

    $stmtLoc = $pdo->prepare("SELECT id, name FROM locations WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC");
    $stmtLoc->execute([$tenant_id]);
    $locations = $stmtLoc->fetchAll(PDO::FETCH_ASSOC);

    $stmtShift = $pdo->prepare("SELECT * FROM shifts WHERE tenant_id = ? AND deleted_at IS NULL");
    $stmtShift->execute([$tenant_id]);
    $shifts = $stmtShift->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
                <a href="employee" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Tambah Karyawan</h2>
                    <p class="text-[11px] text-gray-500">Tambahkan akun baru ke dalam sistem.</p>
                </div>
            </div>

            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="" enctype="multipart/form-data">

                    <!-- BAGIAN 1: INFO AKUN & PENEMPATAN KERJA -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- KOLOM KIRI: Informasi Akun -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Akun</h3>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                                <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: Budi Santoso">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Email</label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="budi@perusahaan.com">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor WhatsApp</label>
                                <input type="tel" inputmode="numeric" pattern="[0-9\-\+]*" name="whatsapp" value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9\-\+]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: 08123456789">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi Awal</label>
                                <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Minimal 8 karakter">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Hak Akses (Role)</label>
                                <?php if ($can_assign_role): ?>
                                    <div class="relative">
                                        <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none">
                                            <option value="employee" <?= (isset($_POST['role']) && $_POST['role'] == 'employee') ? 'selected' : '' ?>>Karyawan Standar (Employee)</option>
                                            <option value="manager" <?= (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : '' ?>>Manajer (Manager)</option>
                                            <option value="hr" <?= (isset($_POST['role']) && $_POST['role'] == 'hr') ? 'selected' : '' ?>>HR Manager</option>
                                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Admin Perusahaan</option>
                                        </select>
                                        <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="role" value="employee">
                                    <input type="text" value="Karyawan Standar (Employee)" readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none transition text-xs text-gray-500 cursor-not-allowed">
                                    <p class="text-[9px] text-gray-400 mt-1.5">Hak akses hanya dapat ditentukan oleh Admin.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Penempatan Kerja -->
                        <div class="space-y-4 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Penempatan Kerja</h3>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Departemen</label>
                                <select name="department_id" id="department_id" class="select2 w-full" data-placeholder="-- Pilih Departemen --">
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php foreach($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Posisi / Jabatan</label>
                                <select name="position_id" id="position_id" class="select2 w-full" data-placeholder="-- Pilih Posisi --">
                                    <option value="">-- Pilih Posisi --</option>
                                    <?php foreach($positions as $pos): ?>
                                        <option value="<?= $pos['id'] ?>" data-dept="<?= $pos['department_id'] ?>" <?= (isset($_POST['position_id']) && $_POST['position_id'] == $pos['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pos['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Atasan Langsung (Manager)</label>
                                <select name="manager_id" id="manager_id" class="select2 w-full" data-placeholder="-- Pilih Atasan (Opsional) --">
                                    <option value="">-- Pilih Atasan (Opsional) --</option>
                                    <?php foreach($managers as $mgr): 
                                        $mgr_dept = !empty($mgr['department_name']) ? ' - ' . $mgr['department_name'] : ' - Tanpa Departemen';
                                        $mgr_label = $mgr['name'] . $mgr_dept;
                                    ?>
                                        <option value="<?= $mgr['id'] ?>" <?= (isset($_POST['manager_id']) && $_POST['manager_id'] == $mgr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($mgr_label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[9px] text-gray-400 mt-1.5">Menampilkan user dengan Role Manager atau yang belum memiliki atasan.</p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Lokasi Kantor</label>
                                <select name="location_id" class="select2 w-full" data-placeholder="-- Pilih Lokasi --">
                                    <option value="">-- Pilih Lokasi --</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>" <?= (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Jadwal Shift</label>
                                <select name="shift_id" class="select2 w-full" data-placeholder="-- Pilih Shift --">
                                    <option value="">-- Pilih Shift --</option>
                                    <?php foreach($shifts as $shift): 
                                        $shift_label = $shift['name'] . ' (' . date('H:i', strtotime($shift['start'])) . ' - ' . date('H:i', strtotime($shift['end'])) . ')';
                                    ?>
                                        <option value="<?= $shift['id'] ?>" <?= (isset($_POST['shift_id']) && $_POST['shift_id'] == $shift['id']) ? 'selected' : '' ?>><?= htmlspecialchars($shift_label) ?></option>
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
                                    <input type="tel" inputmode="numeric" name="legal_id" value="<?= htmlspecialchars($_POST['legal_id'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="16 digit angka">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kontak Darurat</label>
                                    <input type="tel" inputmode="numeric" name="emergency_contact" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9\-\+]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="No. HP">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Tanggal Bergabung</label>
                                <input type="date" name="join_date" value="<?= htmlspecialchars($_POST['join_date'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Foto KTP / Identitas</label>
                                <input type="file" name="id_image" class="dropify" data-max-file-size="3M" data-allowed-file-extensions="jpg jpeg png webp" />
                            </div>
                        </div>

                        <!-- KOLOM KANAN: Penggajian -->
                        <div class="space-y-4 mt-6 md:mt-0">
                            <h3 class="text-xs font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3">Informasi Penggajian (Opsional)</h3>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Gaji Pokok (Rp)</label>
                                    <input type="tel" inputmode="numeric" name="basic_salary" value="<?= htmlspecialchars($_POST['basic_salary'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Tanpa titik">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Rate Lembur / Jam (Rp)</label>
                                    <input type="tel" inputmode="numeric" name="overtime_rate" value="<?= htmlspecialchars($_POST['overtime_rate'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Tanpa titik">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Bank</label>
                                <input type="text" name="bank_name" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Misal: BCA / Mandiri">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor Rekening</label>
                                <input type="tel" inputmode="numeric" name="bank_account" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary transition text-xs text-gray-800" placeholder="Nomor rekening">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 mb-12 md:mb-2 border-t border-gray-100 flex gap-3">
                        <a href="employee" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Data
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

        // Cascading Department -> Position
        const posSelect = $('#position_id');
        const originalPosOptions = posSelect.find('option').clone(); 

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
        }

        $('#department_id').on('change', function() {
            filterDropdowns();
            posSelect.trigger('change.select2'); 
        });

        filterDropdowns();
        posSelect.trigger('change.select2');
    });
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>