<aside id="desktop-sidebar" class="hidden md:flex flex-col w-64 bg-surface border-r border-gray-200 z-20 shrink-0 transition-all duration-300 ease-in-out">
    <div class="p-5 flex items-center gap-3 border-b border-gray-100">
        <!-- Logo Perusahaan Dinamis -->
        <div class="w-8 h-8 rounded bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 shadow-sm">
            <img src="<?= $logo_path . ($app_settings['logo'] ?? 'default_logo.png') ?>" alt="Logo" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=HR&background=ea3800&color=fff'">
        </div>
        <h1 class="font-bold text-lg text-gray-800 tracking-tight leading-tight">
            <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS Pro') ?>
        </h1>
    </div>
    
    <!-- Menu Sidebar -->
    <nav class="flex-1 p-3 space-y-1">
        <a href="<?= $base_url ?>/." class="flex items-center gap-3 px-3 py-2.5 bg-primary/10 text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="home" class="w-4 h-4"></i> Beranda
        </a>
        <a href="<?= $base_url ?>/employee" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="users" class="w-4 h-4"></i> Karyawan
        </a>
        <a href="#" id="desktopRequestBtn" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Pengajuan
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="layout-grid" class="w-4 h-4"></i> Menu Lain
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="user" class="w-4 h-4"></i> Profil Saya
        </a>
    </nav>
    
    <div class="p-3 border-t border-gray-100">
        <a href="<?= $base_url ?>/logout.php" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-failed/10 hover:text-failed rounded-xl text-sm font-medium transition">
            <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
    </div>
</aside>

<!-- Script Modular Khusus Sidebar -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Logika Sidebar Toggle Desktop (Hamburger Menu)
        const sidebar = document.getElementById('desktop-sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (sidebar && toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('-ml-64');
            });
        }

        // Logika Tombol Request Desktop untuk memicu Bottom Sheet
        const desktopReqBtn = document.getElementById('desktopRequestBtn');
        const mobileReqBtn = document.getElementById('requestBtn');
        
        if (desktopReqBtn && mobileReqBtn) {
            desktopReqBtn.addEventListener('click', (e) => {
                e.preventDefault();
                mobileReqBtn.click();
            });
        }
    });
</script>