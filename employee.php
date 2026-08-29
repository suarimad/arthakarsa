<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Logika Hak Akses (Hanya Role ID 1, 2, 3 yang bisa kelola Karyawan)
$role_id = $_SESSION['role_id'] ?? null;
$role_name = strtolower($_SESSION['role'] ?? '');
$can_manage_employee = in_array($role_id, [1, 2, 3]) || in_array($role_name, ['superadmin', 'admin', 'hr']);

// Cek apakah mode profil diakses via parameter UUID
$profile_uuid = $_GET['uuid'] ?? null;
$profile_data = null;

// ==========================================
// PENANGANAN AJAX: SOFT DELETE KARYAWAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'delete_employee') {
    header('Content-Type: application/json');
    
    if (!$can_manage_employee) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki akses untuk menghapus data ini.']);
        exit;
    }

    try {
        $del_id = $_POST['id'];
        $stmtDel = $pdo->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?");
        $stmtDel->execute([$del_id, $tenant_id]);
        
        $_SESSION['toast_msg'] = "Data karyawan berhasil dihapus!";
        $_SESSION['toast_type'] = "success";
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// ==========================================

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// ==========================================
// PENGAMBILAN DATA BERDASARKAN MODE (PROFIL vs LIST)
// ==========================================
if ($profile_uuid) {
    // ---- MODE 1: DETAIL PROFIL LENGKAP ----
    $stmtProfile = $pdo->prepare("
        SELECT u.*, 
               p.name as position_name, 
               d.name as department_name, 
               r.display_name as role_display,
               s.name as shift_name,
               s.start as shift_start,
               s.end as shift_end,
               m.name as manager_name,
               m.avatar as manager_avatar
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN shifts s ON u.shift_id = s.id
        LEFT JOIN users m ON u.manager_id = m.id
        WHERE u.uuid = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
    ");
    $stmtProfile->execute([$profile_uuid, $tenant_id]);
    $profile_data = $stmtProfile->fetch(PDO::FETCH_ASSOC);

    // Jika UUID tidak valid, tendang kembali ke daftar
    if (!$profile_data) {
        header("Location: " . ($base_url ?? '') . "/employee");
        exit;
    }
} else {
    // ---- MODE 2: DAFTAR DIREKTORI (LIST EXISTING) ----
    
    // Deteksi departemen user saat ini
    $stmtMe = $pdo->prepare("
        SELECT p.department_id, d.name as department_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id 
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmtMe->execute([$user_id, $tenant_id]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);

    $my_dept_id = $me['department_id'] ?? null;
    $my_dept_name = $me['department_name'] ?? 'Semua Departemen';

    // 1. QUERY UTAMA: KARYAWAN (SATU DEPARTEMEN)
    $sql_main = "
        SELECT u.id, u.uuid, u.name, u.email, u.whatsapp, u.avatar, 
               r.name as role_name, r.display_name as role_display, 
               p.name as position_name, d.name as department_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.deleted_at IS NULL
    ";
    $params_main = [$tenant_id];

    if ($my_dept_id) {
        $sql_main .= " AND p.department_id = ?";
        $params_main[] = $my_dept_id;
    }

    $sql_main .= " ORDER BY u.name ASC";

    $stmt = $pdo->prepare($sql_main);
    $stmt->execute($params_main);
    $all_employees = $stmt->fetchAll();

    $currentUser = null;
    $otherEmployees = [];

    foreach ($all_employees as $emp) {
        if ($emp['id'] == $user_id) {
            $currentUser = $emp;
        } else {
            $otherEmployees[] = $emp;
        }
    }

    // 2. QUERY DINAMIS: KARYAWAN YANG TIDAK MASUK HARI INI
    $today_date = date('Y-m-d');
    $sql_absent = "
        SELECT u.id, u.uuid, u.name, u.email, u.whatsapp, u.avatar, 
               r.name as role_name, r.display_name as role_display, 
               p.name as position_name, d.name as department_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
          AND u.deleted_at IS NULL 
          AND u.id != ? 
    ";
    $params_absent = [$tenant_id, $user_id];

    if ($my_dept_id) {
        $sql_absent .= " AND p.department_id = ?";
        $params_absent[] = $my_dept_id;
    }

    $sql_absent .= "
          AND NOT EXISTS (
              SELECT 1 FROM attendances a 
              WHERE a.user_id = u.id AND a.date = ?
          )
        ORDER BY u.name ASC
        LIMIT 10
    ";
    $params_absent[] = $today_date;

    $stmtAbsent = $pdo->prepare($sql_absent);
    $stmtAbsent->execute($params_absent);
    $absentEmployees = $stmtAbsent->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/components/head.php';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
require_once __DIR__ . '/components/sidebar.php';
?>

<div id="main-scroll-container" class="flex-1 overflow-y-auto overscroll-y-contain relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-36 md:pb-8 md:px-6 relative z-0">
        
        <!-- PULL TO REFRESH INDICATOR -->
        <div id="ptr-indicator" class="w-full flex justify-center items-center h-0 overflow-hidden transition-all duration-300 absolute top-0 left-0 right-0 z-[60] pointer-events-none">
            <div class="bg-surface rounded-full shadow-md p-2 flex items-center justify-center mt-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-primary animate-spin"></i>
            </div>
        </div>

        <?php require_once __DIR__ . '/components/header.php'; ?>

        <?php if ($profile_uuid && $profile_data): 
            // ============================================================
            // TAMPILAN: MODE DETAIL PROFIL LENGKAP
            // ============================================================
            
            $prof_name = htmlspecialchars($profile_data['name']);
            $prof_avatar = !empty($profile_data['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($profile_data['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($prof_name);
            $prof_email = htmlspecialchars($profile_data['email']);
            $prof_wa = htmlspecialchars($profile_data['whatsapp'] ?? '-');
            $prof_dept = htmlspecialchars($profile_data['department_name'] ?? 'Belum ada departemen');
            $prof_pos = htmlspecialchars($profile_data['position_name'] ?? $profile_data['role_display'] ?? 'Karyawan');
            
            // Format Shift
            $shift_name = htmlspecialchars($profile_data['shift_name'] ?? 'Tidak ada shift');
            $shift_time = '';
            if (!empty($profile_data['shift_start']) && !empty($profile_data['shift_end'])) {
                $shift_time = date('H:i', strtotime($profile_data['shift_start'])) . ' - ' . date('H:i', strtotime($profile_data['shift_end']));
            }

            // Format Manager
            $mgr_name = htmlspecialchars($profile_data['manager_name'] ?? 'Tidak ada atasan');
            $mgr_avatar = !empty($profile_data['manager_avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($profile_data['manager_avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($mgr_name);

            // Bersihkan format WA untuk link API
            $wa_clean = preg_replace('/[^0-9]/', '', $profile_data['whatsapp'] ?? '');
            if (str_starts_with($wa_clean, '0')) $wa_clean = '62' . substr($wa_clean, 1);
            $wa_link = !empty($wa_clean) ? "https://wa.me/{$wa_clean}" : "#";
        ?>
        
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-6 md:mt-2 relative z-0">
            <div class="flex items-center gap-3 px-1 mb-2">
                <a href="<?= ($base_url ?? '') ?>/employee" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Profil Lengkap</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Detail informasi karyawan</p>
                </div>
            </div>

            <!-- KARTU PROFIL UTAMA -->
            <div class="bg-surface md:border border-gray-100 rounded-2xl md:rounded-3xl md:shadow-sm overflow-hidden p-6 md:p-8">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                    <!-- Avatar Besar -->
                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full border-4 border-gray-50 p-1 shrink-0 bg-white shadow-sm">
                        <img src="<?= $prof_avatar ?>" alt="<?= $prof_name ?>" class="w-full h-full rounded-full object-cover">
                    </div>
                    
                    <!-- Informasi Singkat -->
                    <div class="flex-1 text-center md:text-left mt-2 md:mt-0">
                        <h3 class="text-xl md:text-2xl font-black text-gray-800 tracking-tight"><?= $prof_name ?></h3>
                        <p class="text-xs md:text-sm font-semibold text-primary mt-1"><?= $prof_pos ?></p>
                        <span class="inline-block mt-2 px-3 py-1 bg-gray-50 border border-gray-100 text-gray-500 text-[10px] md:text-xs font-bold rounded-full">
                            <?= $prof_dept ?>
                        </span>
                        
                        <!-- Tombol Aksi Kontak -->
                        <div class="flex flex-wrap justify-center md:justify-start gap-3 mt-5">
                            <a href="mailto:<?= $prof_email ?>" class="flex items-center gap-2 px-4 py-2 bg-gray-50 border border-gray-200 text-gray-600 rounded-xl text-xs font-bold hover:bg-gray-100 transition shadow-sm active:scale-95">
                                <i data-lucide="mail" class="w-4 h-4"></i> Kirim Email
                            </a>
                            <?php if(!empty($wa_clean)): ?>
                            <a href="<?= $wa_link ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 bg-success/10 border border-success/20 text-success rounded-xl text-xs font-bold hover:bg-success hover:text-white transition shadow-sm active:scale-95">
                                <i class="fa-brands fa-whatsapp text-base"></i> Hubungi WA
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <hr class="my-6 md:my-8 border-gray-100 border-dashed">

                <!-- RINCIAN TAMBAHAN (Grid) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                            <i data-lucide="mail" class="w-4.5 h-4.5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">Alamat Email</p>
                            <p class="text-xs font-semibold text-gray-800 truncate"><?= $prof_email ?></p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                            <i data-lucide="phone" class="w-4.5 h-4.5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">Nomor WhatsApp</p>
                            <p class="text-xs font-semibold text-gray-800 truncate"><?= $prof_wa ?></p>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                            <i data-lucide="clock" class="w-4.5 h-4.5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">Jadwal Shift</p>
                            <p class="text-xs font-semibold text-gray-800"><?= $shift_name ?></p>
                            <?php if($shift_time): ?>
                                <p class="text-[10px] font-medium text-gray-500 mt-1"><?= $shift_time ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 flex items-start gap-3">
                        <?php if ($profile_data['manager_id']): ?>
                            <div class="w-10 h-10 rounded-full bg-white border border-gray-200 shrink-0 overflow-hidden p-px">
                                <img src="<?= $mgr_avatar ?>" class="w-full h-full rounded-full object-cover">
                            </div>
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-white border border-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                                <i data-lucide="user-check" class="w-4.5 h-4.5"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-0.5">Manajer / Atasan</p>
                            <p class="text-xs font-semibold text-gray-800 truncate"><?= $mgr_name ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php else: 
            // ============================================================
            // TAMPILAN: MODE DIREKTORI LIST (EXISTING)
            // ============================================================
        ?>
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Direktori Karyawan</h2>
                
                <?php if($can_manage_employee): ?>
                <a href="employee_add" class="bg-primary/10 text-primary px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Tambah
                </a>
                <?php endif; ?>
            </div>

            <!-- Form Pencarian (AJAX) -->
            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="searchInput" placeholder="Cari nama, email, atau jabatan..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <!-- STORY IG STYLE: Teman yang Tidak Masuk (Dinamis & Terfilter Dept) -->
            <?php if(!empty($absentEmployees)): ?>
            <section class="mb-2 relative z-0">
                <h3 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-3 px-1 uppercase tracking-wider">
                    Tidak Masuk Hari Ini 
                    <span class="text-[9px] bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded ml-1 normal-case capitalize"><?= htmlspecialchars($my_dept_name) ?></span>
                </h3>
                <div class="flex overflow-x-auto gap-3 pb-2 px-1" style="scrollbar-width: none;">
                    <?php foreach($absentEmployees as $absent): 
                        $abs_avatar = !empty($absent['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($absent['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($absent['name']);
                        $abs_position = htmlspecialchars($absent['position_name'] ?? $absent['role_display'] ?? ucfirst($absent['role_name'] ?? 'Employee'));
                        $abs_department = htmlspecialchars($absent['department_name'] ?? 'Belum ada departemen');
                    ?>
                    <!-- Element yang bisa di-klik untuk membuka Modal -->
                    <div class="flex flex-col items-center gap-1.5 shrink-0 w-16 cursor-pointer group"
                         onclick="openEmployeeDetail(this)"
                         data-id="<?= $absent['id'] ?>"
                         data-uuid="<?= htmlspecialchars($absent['uuid']) ?>"
                         data-name="<?= htmlspecialchars($absent['name']) ?>"
                         data-email="<?= htmlspecialchars($absent['email']) ?>"
                         data-whatsapp="<?= htmlspecialchars($absent['whatsapp'] ?? '') ?>"
                         data-avatar="<?= $abs_avatar ?>"
                         data-position="<?= $abs_position ?>"
                         data-department="<?= $abs_department ?>"
                    >
                        <div class="w-14 h-14 rounded-full border-[2.5px] border-failed p-0.5 relative bg-white group-hover:scale-105 transition-transform">
                            <img src="<?= $abs_avatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="text-[9px] font-medium text-gray-600 w-full text-center truncate group-hover:text-primary transition-colors"><?= explode(' ', htmlspecialchars($absent['name']))[0] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="md:grid md:grid-cols-3 md:gap-6 relative z-0">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <?php if($currentUser): 
                        $curr_avatar = !empty($currentUser['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($currentUser['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($currentUser['name']);
                        $curr_position = htmlspecialchars($currentUser['position_name'] ?? $currentUser['role_display'] ?? ucfirst($currentUser['role_name'] ?? 'Employee'));
                        $curr_department = htmlspecialchars($currentUser['department_name'] ?? 'Belum ada departemen');
                    ?>
                    <!-- CARD PROFIL USER KLIKABEL -->
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative z-0 overflow-hidden flex items-center gap-4 cursor-pointer hover:opacity-95 transition-all group active:scale-[0.99]"
                             onclick="openEmployeeDetail(this)"
                             data-id="<?= $currentUser['id'] ?>"
                             data-uuid="<?= htmlspecialchars($currentUser['uuid']) ?>"
                             data-name="<?= htmlspecialchars($currentUser['name']) ?>"
                             data-email="<?= htmlspecialchars($currentUser['email']) ?>"
                             data-whatsapp="<?= htmlspecialchars($currentUser['whatsapp'] ?? '') ?>"
                             data-avatar="<?= $curr_avatar ?>"
                             data-position="<?= $curr_position ?>"
                             data-department="<?= $curr_department ?>"
                    >
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="award" class="w-32 h-32 md:w-48 md:h-48 -mt-4 md:-mt-8 -mr-4 md:-mr-8"></i>
                        </div>
                        
                        <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-surface shrink-0 p-0.5 relative z-10 shadow-sm group-hover:scale-105 transition-transform">
                            <img src="<?= $curr_avatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        
                        <div class="relative z-10 flex-1 min-w-0">
                            <h3 class="text-base md:text-xl font-bold tracking-tight truncate"><?= htmlspecialchars($currentUser['name']) ?> <span class="text-xs font-medium bg-surface/20 px-2 py-0.5 rounded-md ml-1 inline-block align-middle">Anda</span></h3>
                            <p class="text-xs text-surface/80 mt-0.5 font-medium truncate"><?= $curr_position ?></p>
                            
                            <div class="mt-3 flex items-center gap-3 text-[10px] md:text-xs text-surface/90 font-medium">
                                <span class="flex items-center gap-1 truncate"><i data-lucide="mail" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($currentUser['email']) ?></span>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>

                    <section class="relative z-0">
                        <div class="flex justify-between items-center mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">
                                Rekan Kerja 
                                <span class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded ml-1"><?= htmlspecialchars($my_dept_name) ?></span>
                            </h3>
                            <span class="text-[10px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full" id="employeeCount"><?= count($otherEmployees) ?> orang</span>
                        </div>
                        
                        <!-- Kontainer AJAX Render -->
                        <div id="employeeListContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 relative z-0 pb-12">
                            <?php foreach($otherEmployees as $emp): 
                                $emp_avatar = !empty($emp['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($emp['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($emp['name']);
                                $emp_position = htmlspecialchars($emp['position_name'] ?? $emp['role_display'] ?? ucfirst($emp['role_name'] ?? 'Employee'));
                                $emp_department = htmlspecialchars($emp['department_name'] ?? 'Belum ada departemen');
                            ?>
                                <!-- CARD KLIKABEL -->
                                <div class="bg-surface border border-gray-100 rounded-xl p-4 shadow-sm flex items-center gap-3.5 transition hover:border-gray-200 hover:shadow-md cursor-pointer relative z-0 group"
                                     onclick="openEmployeeDetail(this)"
                                     data-id="<?= $emp['id'] ?>"
                                     data-uuid="<?= htmlspecialchars($emp['uuid']) ?>"
                                     data-name="<?= htmlspecialchars($emp['name']) ?>"
                                     data-email="<?= htmlspecialchars($emp['email']) ?>"
                                     data-whatsapp="<?= htmlspecialchars($emp['whatsapp'] ?? '') ?>"
                                     data-avatar="<?= $emp_avatar ?>"
                                     data-position="<?= $emp_position ?>"
                                     data-department="<?= $emp_department ?>"
                                >
                                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-full border-[2.5px] border-failed p-0.5 relative bg-white shrink-0 group-hover:scale-105 transition-transform">
                                        <img src="<?= $emp_avatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-gray-800 truncate group-hover:text-primary transition-colors"><?= htmlspecialchars($emp['name']) ?></h4>
                                        <p class="text-[11px] text-gray-500 font-medium truncate mt-0.5"><?= $emp_position ?></p>
                                        <span class="inline-block mt-1.5 px-2 py-0.5 bg-gray-50 border border-gray-100 text-gray-400 text-[9px] font-semibold rounded-md truncate max-w-full">
                                            <?= $emp_department ?>
                                        </span>
                                    </div>
                                    
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 group-hover:text-primary transition-colors"></i>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($otherEmployees)): ?>
                                <div class="col-span-full bg-surface border border-dashed border-gray-200 rounded-2xl p-8 text-center">
                                    <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800">Belum ada rekan kerja</h4>
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">Hanya Anda yang berada di departemen ini.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php if (!$profile_uuid): ?>
<!-- ================= MODAL DETAIL KARYAWAN (HANYA MUNCUL DI MODE LIST) ================= -->
<div id="employeeDetailModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="employeeDetailOverlay" onclick="closeEmployeeDetail()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <!-- PERBAIKAN: md:max-w-sm memastikan di mobile menjadi w-full, dan desktop max-w-sm -->
        <div id="employeeDetailCard" class="bg-surface w-full md:max-w-sm rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col p-6">
            
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeEmployeeDetail()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <!-- Header Profil -->
            <div class="flex items-center gap-4 mb-6">
                <div class="w-16 h-16 md:w-20 md:h-20 rounded-full border-[2.5px] border-failed p-0.5 relative bg-white shrink-0 shadow-sm">
                    <img id="detAvatar" src="" alt="Avatar" class="w-full h-full rounded-full object-cover">
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="detName" class="text-lg md:text-xl font-bold text-gray-800 truncate">Nama</h3>
                    <p id="detPosition" class="text-xs text-gray-500 font-medium truncate mt-0.5">Posisi</p>
                    <span id="detDepartment" class="inline-block mt-1.5 px-2 py-0.5 bg-primary/10 text-primary text-[10px] font-bold rounded-md truncate max-w-full">Dept</span>
                </div>
            </div>

            <!-- Tombol Kontak -->
            <div class="grid grid-cols-2 gap-3 mb-4">
                <a id="btnDetEmail" href="#" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition group shadow-sm active:scale-95">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                    <span class="text-[10px] font-bold">Email</span>
                </a>
                <a id="btnDetWa" href="#" target="_blank" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-success/10 border border-success/20 text-success hover:bg-success hover:text-white transition group shadow-sm active:scale-95">
                    <i class="fa-brands fa-whatsapp text-xl"></i>
                    <span class="text-[10px] font-bold">WhatsApp</span>
                </a>
            </div>

            <!-- Tombol Lihat Profil Lengkap -->
            <a id="btnDetFullProfile" href="#" class="w-full bg-primary/10 text-primary py-3 rounded-xl text-xs font-bold hover:bg-primary hover:text-white transition shadow-sm active:scale-95 flex items-center justify-center gap-2 mb-2">
                <i data-lucide="user-check" class="w-4 h-4"></i> Lihat Profil Lengkap
            </a>

            <!-- Tombol Edit & Delete (Akses Khusus) -->
            <?php if($can_manage_employee): ?>
            <div class="border-t border-gray-100 pt-4 flex gap-3">
                <button id="btnDetEdit" onclick="editEmployee()" class="flex-1 bg-gray-50 text-gray-700 py-3 rounded-xl text-xs font-semibold hover:bg-gray-100 transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                    <i data-lucide="edit-3" class="w-4 h-4"></i> Edit
                </button>
                <button id="btnDetDelete" onclick="deleteEmployee()" class="flex-1 bg-failed/10 text-failed py-3 rounded-xl text-xs font-semibold hover:bg-failed hover:text-white transition shadow-sm active:scale-95 flex items-center justify-center gap-1.5">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Hapus
                </button>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Load Bottom Nav (PENTING: Di-load agar navigasi muncul di mobile) -->
<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<!-- Komponen Toast Global (Menangkap Session) -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // ==========================================
    // LOGIKA PWA & PULL TO REFRESH
    // ==========================================
    const ptrContainer = document.getElementById('main-scroll-container');
    const ptrIndicator = document.getElementById('ptr-indicator');
    let startY = 0, currentY = 0, isPulling = false;

    if(ptrContainer && ptrIndicator) {
        ptrContainer.addEventListener('touchstart', (e) => {
            if (ptrContainer.scrollTop <= 5) { 
                startY = e.touches[0].clientY;
                isPulling = true;
                ptrIndicator.style.transition = 'none'; 
            }
        }, { passive: true });

        ptrContainer.addEventListener('touchmove', (e) => {
            if (!isPulling) return;
            currentY = e.touches[0].clientY;
            let distance = currentY - startY;

            if (distance > 0 && ptrContainer.scrollTop <= 5) {
                if (distance > 100) distance = 100 + (distance - 100) * 0.2;
                ptrIndicator.style.height = `${distance}px`;
            } else {
                isPulling = false;
            }
        }, { passive: true });

        ptrContainer.addEventListener('touchend', () => {
            if (!isPulling) return;
            isPulling = false;
            ptrIndicator.style.transition = 'height 0.3s ease';

            if (parseFloat(ptrIndicator.style.height) > 60) {
                ptrIndicator.style.height = '60px'; 
                setTimeout(() => { window.location.reload(); }, 400);
            } else {
                ptrIndicator.style.height = '0px';
            }
        });
    }

    <?php if (!$profile_uuid): ?>
    // ==========================================
    // LOGIKA MODAL DETAIL KARYAWAN (KHUSUS MODE LIST)
    // ==========================================
    let currentDetailEmpId = null;
    let currentDetailEmpUuid = null;
    const baseUrl = "<?= $base_url ?? '' ?>";

    function openEmployeeDetail(el) {
        currentDetailEmpId = el.getAttribute('data-id');
        currentDetailEmpUuid = el.getAttribute('data-uuid');
        
        document.getElementById('detName').innerText = el.getAttribute('data-name');
        document.getElementById('detPosition').innerText = el.getAttribute('data-position');
        document.getElementById('detDepartment').innerText = el.getAttribute('data-department');
        document.getElementById('detAvatar').src = el.getAttribute('data-avatar');
        
        // Setup Email
        const email = el.getAttribute('data-email');
        document.getElementById('btnDetEmail').href = "mailto:" + email;

        // Setup WhatsApp
        const wa = el.getAttribute('data-whatsapp');
        const btnWa = document.getElementById('btnDetWa');
        if (wa && wa.trim() !== '') {
            let wa_number = wa.replace(/[^0-9]/g, '');
            if (wa_number.startsWith('0')) {
                wa_number = '62' + wa_number.substring(1);
            }
            btnWa.href = "https://wa.me/" + wa_number;
            btnWa.classList.remove('hidden');
            btnWa.classList.add('flex');
        } else {
            btnWa.classList.add('hidden');
            btnWa.classList.remove('flex');
        }

        // Setup Link Profil Lengkap
        const btnFullProfile = document.getElementById('btnDetFullProfile');
        if (btnFullProfile) {
            btnFullProfile.href = (baseUrl ? baseUrl : '') + "/employee/" + currentDetailEmpUuid;
        }

        const m = document.getElementById('employeeDetailModal');
        const o = document.getElementById('employeeDetailOverlay');
        const c = document.getElementById('employeeDetailCard');
        
        m.classList.remove('hidden');
        lucide.createIcons();
        setTimeout(() => {
            o.classList.remove('opacity-0');
            c.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            c.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    }

    function closeEmployeeDetail() {
        const m = document.getElementById('employeeDetailModal');
        const o = document.getElementById('employeeDetailOverlay');
        const c = document.getElementById('employeeDetailCard');
        
        o.classList.add('opacity-0');
        c.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100');
        c.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }

    function editEmployee() {
        if (!currentDetailEmpUuid) return;
        window.location.href = "employee_edit/user/" + currentDetailEmpUuid;
    }

    function deleteEmployee() {
        if (!currentDetailEmpId) return;
        if (!confirm("Apakah Anda yakin ingin menghapus data karyawan ini?")) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'delete_employee');
        formData.append('id', currentDetailEmpId);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    if(typeof window.showToast === 'function') window.showToast(data.message, 'error');
                }
            })
            .catch(() => {
                if(typeof window.showToast === 'function') window.showToast('Gagal terhubung ke server', 'error');
            });
    }

    // ==========================================
    // SEARCH AJAX (Menembus Batas Departemen)
    // ==========================================
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const container = document.getElementById('employeeListContainer');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const q = this.value;
            
            container.innerHTML = '<div class="col-span-full py-8 text-center"><i data-lucide="loader-2" class="w-6 h-6 text-gray-400 animate-spin mx-auto"></i></div>';
            lucide.createIcons();

            searchTimeout = setTimeout(() => {
                fetch('employee_search?q=' + encodeURIComponent(q))
                    .then(res => res.text())
                    .then(html => {
                        container.innerHTML = html;
                        lucide.createIcons();
                    })
                    .catch(err => console.error("Gagal", err));
            }, 400); 
        });
    }
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>