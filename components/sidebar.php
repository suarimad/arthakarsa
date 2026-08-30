<?php 
// Pastikan variabel string URL page disiapkan
$current_page_auth = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page_auth == '') $current_page_auth = 'index';

// Render Label "Menu {Role}" berdasarkan Role Display
$sidebar_role_label = $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Karyawan');
?>
<aside id="desktop-sidebar" class="hidden md:flex flex-col w-64 bg-surface border-r border-gray-200 z-20 shrink-0 transition-all duration-300 ease-in-out">
    <div class="p-5 flex items-center gap-3 border-b border-gray-100 shrink-0">
        <!-- Logo Perusahaan Dinamis -->
        <div class="w-8 h-8 rounded bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 ">
            <img src="<?= $logo_path . ($app_settings['logo'] ?? 'default_logo.png') ?>" alt="Logo" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=HR&background=ea3800&color=fff'">
        </div>
        <h1 class="font-bold text-lg text-gray-800 tracking-tight leading-tight">
            <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS Pro') ?>
        </h1>
    </div>
    
    <!-- Menu Sidebar (Scrollable area) -->
    <nav id="sidebar-scroll-container" class="flex-1 p-3 space-y-1 overflow-y-auto relative" style="scrollbar-width: thin;">
        
        <!-- ================= KATEGORI: MAIN ================= -->
        <?php if (!empty($accessible_menus['main'])): ?>
            <?php foreach($accessible_menus['main'] as $m): 
                // Cek apakah halaman saat ini ada di dalam list active_urls
                $active_urls = !empty($m['active_urls']) ? array_map('trim', explode(',', $m['active_urls'])) : [$m['url']];
                $is_active = in_array($current_page_auth, $active_urls);
            ?>
                <a href="<?= $base_url ?? '' ?>/<?= htmlspecialchars($m['url']) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $is_active ? 'bg-primary/10 text-primary active-menu-item' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
                    <i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-4 h-4"></i> <?= htmlspecialchars($m['title']) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Pengajuan (Tombol Desktop Modal - Hardcoded Base Core JS) -->
        <a href="#" id="desktopRequestBtn" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Pengajuan
        </a>

        <!-- ================= KATEGORI: SUPPORT ================= -->
        <?php if (!empty($accessible_menus['support'])): ?>
            <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                Menu <?= htmlspecialchars($sidebar_role_label) ?>
            </div>
            
            <?php foreach($accessible_menus['support'] as $m): 
                // Cek apakah halaman saat ini ada di dalam list active_urls
                $active_urls = !empty($m['active_urls']) ? array_map('trim', explode(',', $m['active_urls'])) : [$m['url']];
                $is_active = in_array($current_page_auth, $active_urls);
            ?>
                <a href="<?= $base_url ?? '' ?>/<?= htmlspecialchars($m['url']) ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= $is_active ? 'bg-primary/10 text-primary active-menu-item' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
                    <i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-4 h-4"></i> <?= htmlspecialchars($m['title']) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

    </nav>
    
    <!-- Profil & Keluar (Tetap diam di paling bawah) -->
    <div class="p-3 border-t border-gray-100 space-y-1 shrink-0 bg-surface">
        <a href="<?= $base_url ?? '' ?>/profile" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= (in_array($current_page_auth, ['profile', 'profile_edit', 'change_password'])) ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="user" class="w-4 h-4"></i> Profil Saya
        </a>
        <a href="<?= $base_url ?? '' ?>/logout" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-failed/10 hover:text-failed rounded-xl text-sm font-medium transition">
            <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
    </div>
</aside>

<!-- ================= MODAL DESKTOP REQUEST ================= -->
<div id="desktopRequestModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay -->
    <div id="desktopRequestOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm"></div>
    <!-- Modal Content Centered -->
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div id="desktopRequestCard" class="bg-surface rounded-3xl shadow-2xl p-6 md:p-8 w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative">
            <button id="closeDesktopRequestBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full"><i data-lucide="x" class="w-5 h-5"></i></button>
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5 md:hidden"></div>
            <h3 class="text-base font-bold text-gray-800 mb-6 text-center">Buat Pengajuan</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="<?= $base_url ?? '' ?>/leave_add/cuti" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm"><i data-lucide="calendar-off" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Cuti</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/sakit" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm"><i data-lucide="stethoscope" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Sakit</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/izin" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition shadow-sm"><i data-lucide="user-minus" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Izin</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/overtime_add" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm"><i data-lucide="clock-4" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Lembur</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Logika Sidebar Toggle Mobile/Desktop
        const sidebar = document.getElementById('desktop-sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (sidebar && toggleBtn) {
            toggleBtn.addEventListener('click', () => { 
                sidebar.classList.toggle('-ml-64'); 
            });
        }

        // Auto-Scroll ke Menu Aktif
        const sidebarScroll = document.getElementById('sidebar-scroll-container');
        if (sidebarScroll) {
            const activeMenu = sidebarScroll.querySelector('.active-menu-item');
            if (activeMenu) {
                setTimeout(() => {
                    activeMenu.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 150);
            }
        }

        // Logika Desktop Modal Request
        const desktopModal = document.getElementById('desktopRequestModal');
        if (desktopModal) document.body.appendChild(desktopModal);

        const desktopReqBtn = document.getElementById('desktopRequestBtn');
        const desktopOverlay = document.getElementById('desktopRequestOverlay');
        const desktopCard = document.getElementById('desktopRequestCard');
        const closeBtn = document.getElementById('closeDesktopRequestBtn');
        
        function openDesktopModal() {
            desktopModal.classList.remove('hidden');
            setTimeout(() => { 
                desktopOverlay.classList.remove('opacity-0'); 
                desktopCard.classList.remove('scale-95', 'opacity-0');
                desktopCard.classList.add('scale-100', 'opacity-100');
            }, 10);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        
        function closeDesktopModal() {
            desktopOverlay.classList.add('opacity-0');
            desktopCard.classList.remove('scale-100', 'opacity-100');
            desktopCard.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { desktopModal.classList.add('hidden'); }, 300);
        }

        if (desktopReqBtn) desktopReqBtn.addEventListener('click', (e) => { e.preventDefault(); openDesktopModal(); });
        if (desktopOverlay) desktopOverlay.addEventListener('click', closeDesktopModal);
        if (closeBtn) closeBtn.addEventListener('click', closeDesktopModal);
    });
</script>