<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

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

// PENYESUAIAN: Menambahkan JOIN ke tabel roles
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, 
           r.name as role_name, r.display_name as role_display, 
           p.name as position_name, d.name as department_name 
    FROM users u 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.tenant_id = ?
    ORDER BY u.name ASC
");
$stmt->execute([$tenant_id]);
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

// MOCKUP: Ambil max 5 karyawan untuk dijadikan data "Teman yang tidak masuk" (Simulasi)
$absentEmployees = array_slice($otherEmployees, 0, 5); 

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
            <i id="toastIcon" class="w-4 h-4"></i>
            <span id="toastMsg"></span>
        </div>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Direktori Karyawan</h2>
                
                <?php if($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin'): ?>
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

            <!-- STORY IG STYLE: Teman yang Tidak Masuk -->
            <?php if(!empty($absentEmployees)): ?>
            <section class="mb-2">
                <h3 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-3 px-1 uppercase tracking-wider">Tidak Masuk Hari Ini</h3>
                <!-- Menyembunyikan Scrollbar namun tetap bisa di-swipe -->
                <div class="flex overflow-x-auto gap-3 pb-2 px-1" style="scrollbar-width: none;">
                    <?php foreach($absentEmployees as $absent): ?>
                    <div class="flex flex-col items-center gap-1.5 shrink-0 w-16">
                        <div class="w-14 h-14 rounded-full border-[2.5px] border-failed p-0.5 relative">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($absent['name']) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="text-[9px] font-medium text-gray-600 w-full text-center truncate"><?= explode(' ', htmlspecialchars($absent['name']))[0] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <?php if($currentUser): ?>
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative z-0 overflow-hidden flex items-center gap-4">
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="award" class="w-32 h-32 md:w-48 md:h-48 -mt-4 md:-mt-8 -mr-4 md:-mr-8"></i>
                        </div>
                        
                        <div class="w-14 h-14 md:w-16 md:h-16 rounded-full bg-surface shrink-0 p-0.5 relative z-10 shadow-sm">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($currentUser['name']) ?>&background=fff&color=<?= str_replace('#','', $app_settings['theme_color'] ?? 'ea3800') ?>&rounded=true" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        
                        <div class="relative z-10 flex-1 min-w-0">
                            <h3 class="text-base md:text-xl font-bold tracking-tight truncate"><?= htmlspecialchars($currentUser['name']) ?> <span class="text-xs font-medium bg-surface/20 px-2 py-0.5 rounded-md ml-1 inline-block align-middle">Anda</span></h3>
                            <!-- PENYESUAIAN: Membaca role_display -->
                            <p class="text-xs text-surface/80 mt-0.5 font-medium truncate"><?= htmlspecialchars($currentUser['position_name'] ?? $currentUser['role_display'] ?? ucfirst($currentUser['role_name'] ?? 'Employee')) ?></p>
                            
                            <div class="mt-3 flex items-center gap-3 text-[10px] md:text-xs text-surface/90 font-medium">
                                <span class="flex items-center gap-1 truncate"><i data-lucide="mail" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($currentUser['email']) ?></span>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>

                    <section class="relative z-0">
                        <div class="flex justify-between items-center mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Rekan Kerja</h3>
                            <span class="text-[10px] font-medium bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full" id="employeeCount"><?= count($otherEmployees) ?> orang</span>
                        </div>
                        
                        <!-- Kontainer AJAX Render -->
                        <div id="employeeListContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 relative z-0">
                            <?php foreach($otherEmployees as $emp): ?>
                                <div class="bg-surface border border-gray-100 rounded-xl p-4 shadow-sm flex items-center gap-3.5 transition hover:border-gray-200 group cursor-pointer relative z-0">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($emp['name']) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="w-12 h-12 md:w-14 md:h-14 rounded-full shadow-sm shrink-0 group-hover:scale-105 transition-transform">
                                    
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-gray-800 truncate group-hover:text-primary transition-colors"><?= htmlspecialchars($emp['name']) ?></h4>
                                        <!-- PENYESUAIAN: Membaca role_display -->
                                        <p class="text-[11px] text-gray-500 font-medium truncate mt-0.5"><?= htmlspecialchars($emp['position_name'] ?? $emp['role_display'] ?? ucfirst($emp['role_name'] ?? 'Employee')) ?></p>
                                        <p class="text-[10px] text-gray-400 truncate mt-1.5 flex items-center gap-1">
                                            <i data-lucide="mail" class="w-3 h-3"></i> <?= htmlspecialchars($emp['email']) ?>
                                        </p>
                                    </div>
                                    
                                    <a href="mailto:<?= htmlspecialchars($emp['email']) ?>" class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-gray-50 text-gray-400 flex items-center justify-center hover:text-primary hover:bg-primary/10 transition shrink-0" title="Kirim Email">
                                        <i data-lucide="message-square" class="w-4 h-4 md:w-4.5 md:h-4.5"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($otherEmployees)): ?>
                                <div class="col-span-full bg-surface border border-dashed border-gray-200 rounded-2xl p-8 text-center">
                                    <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="users" class="w-6 h-6"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-gray-800">Belum ada rekan kerja</h4>
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">Hanya Anda yang berada di tenant ini. Undang karyawan lain untuk mulai berkolaborasi.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>
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
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm">
                        <i data-lucide="calendar-off" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Leave</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Sick</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm">
                        <i data-lucide="clock-4" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Overtime</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<script>
    lucide.createIcons();

    function showToast(msg, type) {
        const toast = document.getElementById('toast');
        const msgEl = document.getElementById('toastMsg');
        const iconEl = document.getElementById('toastIcon');

        msgEl.textContent = msg;
        toast.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium';

        if (type === 'failed' || type === 'error') {
            toast.classList.add('bg-failed/10', 'text-failed', 'border-failed/20');
            iconEl.setAttribute('data-lucide', 'alert-circle');
        } else if (type === 'warning') {
            toast.classList.add('bg-pending/10', 'text-pending', 'border-pending/20');
            iconEl.setAttribute('data-lucide', 'alert-triangle');
        } else {
            toast.classList.add('bg-success/10', 'text-success', 'border-success/20');
            iconEl.setAttribute('data-lucide', 'check-circle');
        }
        lucide.createIcons();

        setTimeout(() => toast.classList.remove('opacity-0', '-translate-y-full'), 100);
        setTimeout(() => toast.classList.add('opacity-0', '-translate-y-full'), 4000);
    }

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg) showToast(phpMsg, phpType);

    // Fitur AJAX Search Debounce
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const container = document.getElementById('employeeListContainer');

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value;
        
        // Render Loading State (Opsional)
        container.innerHTML = '<div class="col-span-full py-8 text-center"><i data-lucide="loader-2" class="w-6 h-6 text-gray-400 animate-spin mx-auto"></i></div>';
        lucide.createIcons();

        searchTimeout = setTimeout(() => {
            fetch('employee_search?q=' + encodeURIComponent(q))
                .then(res => res.text())
                .then(html => {
                    container.innerHTML = html;
                    lucide.createIcons();
                })
                .catch(err => console.error("Gagal mengambil data", err));
        }, 400); // 400ms debounce
    });

    // Logika Bottom Sheet (Request)
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