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

// Tangkap session toast
$toast_msg = '';
$toast_type = '';
if (isset($_SESSION['toast_msg'])) {
    $toast_msg = $_SESSION['toast_msg'];
    $toast_type = $_SESSION['toast_type'] ?? 'info';
    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// ==========================================
// DETEKSI DEPARTEMEN USER SAAT INI
// ==========================================
$stmtMe = $pdo->prepare("
    SELECT u.department_id, d.name as department_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
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
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.tenant_id = ? AND u.deleted_at IS NULL
";
$params_main = [$tenant_id];

// Jika user punya departemen, filter hanya teman satu departemen
if ($my_dept_id) {
    $sql_main .= " AND u.department_id = ?";
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

// 2. QUERY DINAMIS: KARYAWAN YANG TIDAK MASUK HARI INI (SATU DEPARTEMEN)
$today_date = date('Y-m-d');
$sql_absent = "
    SELECT u.id, u.name, u.avatar 
    FROM users u 
    WHERE u.tenant_id = ? 
      AND u.deleted_at IS NULL 
      AND u.id != ? 
";
$params_absent = [$tenant_id, $user_id];

// Filter teman satu departemen untuk status absen
if ($my_dept_id) {
    $sql_absent .= " AND u.department_id = ?";
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
                        $abs_avatar = !empty($absent['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($absent['avatar']) : "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($absent['name']);
                    ?>
                    <div class="flex flex-col items-center gap-1.5 shrink-0 w-16">
                        <div class="w-14 h-14 rounded-full border-[2.5px] border-failed p-0.5 relative bg-white">
                            <img src="<?= $abs_avatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="text-[9px] font-medium text-gray-600 w-full text-center truncate"><?= explode(' ', htmlspecialchars($absent['name']))[0] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="md:grid md:grid-cols-3 md:gap-6 relative z-0">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <?php if($currentUser): 
                        $curr_avatar = !empty($currentUser['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($currentUser['avatar']) : "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($currentUser['name']);
                    ?>
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative z-0 overflow-hidden flex items-center gap-4">
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="award" class="w-32 h-32 md:w-48 md:h-48 -mt-4 md:-mt-8 -mr-4 md:-mr-8"></i>
                        </div>
                        
                        <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-surface shrink-0 p-0.5 relative z-10 shadow-sm">
                            <img src="<?= $curr_avatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        
                        <div class="relative z-10 flex-1 min-w-0">
                            <h3 class="text-base md:text-xl font-bold tracking-tight truncate"><?= htmlspecialchars($currentUser['name']) ?> <span class="text-xs font-medium bg-surface/20 px-2 py-0.5 rounded-md ml-1 inline-block align-middle">Anda</span></h3>
                            <p class="text-xs text-surface/80 mt-0.5 font-medium truncate"><?= htmlspecialchars($currentUser['position_name'] ?? $currentUser['role_display'] ?? ucfirst($currentUser['role_name'] ?? 'Employee')) ?></p>
                            
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
                                $emp_avatar = !empty($emp['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($emp['avatar']) : "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($emp['name']);
                                $emp_position = htmlspecialchars($emp['position_name'] ?? $emp['role_display'] ?? ucfirst($emp['role_name'] ?? 'Employee'));
                                $emp_department = htmlspecialchars($emp['department_name'] ?? 'Belum ada departemen');
                            ?>
                                <!-- CARD KLIKABEL (Dengan Border Avatar Kustom) -->
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
    </main>
</div>

<!-- ================= MODAL DETAIL KARYAWAN ================= -->
<div id="employeeDetailModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="employeeDetailOverlay" onclick="closeEmployeeDetail()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="employeeDetailCard" class="bg-surface w-full max-w-sm rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col p-6">
            
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
            <div class="grid grid-cols-2 gap-3 mb-6">
                <a id="btnDetEmail" href="#" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 hover:bg-primary hover:text-white hover:border-primary transition group shadow-sm active:scale-95">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                    <span class="text-[10px] font-bold">Email</span>
                </a>
                <a id="btnDetWa" href="#" target="_blank" class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-2xl bg-success/10 border border-success/20 text-success hover:bg-success hover:text-white transition group shadow-sm active:scale-95">
                    <i class="fa-brands fa-whatsapp text-xl"></i>
                    <span class="text-[10px] font-bold">WhatsApp</span>
                </a>
            </div>

            <!-- Tombol Edit & Delete (Akses Khusus) -->
            <?php if($can_manage_employee): ?>
            <div class="border-t border-gray-100 pt-5 flex gap-3">
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

<!-- ================= BOTTOM SHEET REQUEST ================= -->
<div id="requestBottomSheet" class="fixed inset-0 z-50 hidden">
    <div id="requestOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300"></div>
    <div id="requestSheet" class="absolute bottom-0 left-0 right-0 bg-surface rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-in-out pb-safe">
        <div class="p-5 pb-8 md:max-w-md md:mx-auto">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5"></div>
            <h3 class="text-sm font-semibold text-gray-800 mb-5 text-center">Buat Pengajuan</h3>
            <div class="grid grid-cols-3 gap-4">
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm"><i data-lucide="calendar-off" class="w-5 h-5"></i></div>
                    <span class="text-[11px] font-medium text-gray-600">Leave</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm"><i data-lucide="stethoscope" class="w-5 h-5"></i></div>
                    <span class="text-[11px] font-medium text-gray-600">Sick</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm"><i data-lucide="clock-4" class="w-5 h-5"></i></div>
                    <span class="text-[11px] font-medium text-gray-600">Overtime</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    // ==========================================
    // LOGIKA MODAL DETAIL KARYAWAN
    // ==========================================
    let currentDetailEmpId = null;
    let currentDetailEmpUuid = null;

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

        const m = document.getElementById('employeeDetailModal');
        const o = document.getElementById('employeeDetailOverlay');
        const c = document.getElementById('employeeDetailCard');
        
        m.classList.remove('hidden');
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

        fetch('', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    window.showToast(data.message, 'error');
                }
            })
            .catch(() => {
                window.showToast('Gagal terhubung ke server', 'error');
            });
    }

    // ==========================================
    // PULL TO REFRESH (PWA)
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

    // ==========================================
    // REQUEST BOTTOM SHEET
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

        function openSheet() {
            bottomSheet.classList.remove('hidden');
            setTimeout(() => { overlay.classList.remove('opacity-0'); sheet.classList.remove('translate-y-full'); }, 10);
        }
        function closeSheet() {
            overlay.classList.add('opacity-0'); sheet.classList.add('translate-y-full');
            setTimeout(() => { bottomSheet.classList.add('hidden'); }, 300);
        }

        if (requestBtn) requestBtn.addEventListener('click', (e) => { e.preventDefault(); openSheet(); });
        if (overlay) overlay.addEventListener('click', closeSheet);
    });
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>