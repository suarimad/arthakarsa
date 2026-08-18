<header class="p-5 md:pt-6 md:px-0 flex justify-between items-center bg-surface md:bg-transparent sticky top-0 z-10 md:static">
    
    <!-- Kiri: Hamburger Menu (Desktop) / Avatar (Mobile) -->
    <div class="flex items-center gap-3 md:gap-4">
        <button id="sidebarToggle" class="hidden md:flex w-10 h-10 rounded-xl hover:bg-gray-200 bg-gray-100 items-center justify-center transition-colors  text-gray-600 shrink-0">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Avatar Mobile -->
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name ?? 'User') ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="md:hidden w-10 h-10 rounded-full shadow-sm shrink-0">
        
        <div>
            <h1 class="text-base md:text-lg font-semibold text-gray-800 leading-tight">Halo, <?= htmlspecialchars($user_name ?? 'User') ?>!</h1>
            <p class="text-[11px] md:text-xs text-gray-500 font-medium mt-0.5">
                <?= htmlspecialchars($user_role ?? 'Employee') ?> <span class="text-gray-300 mx-1">•</span> <span class="text-primary font-semibold"><?= htmlspecialchars($tenant_name ?? 'Perusahaan') ?></span>
            </p>
        </div>
    </div>

    <!-- Kanan: Notifikasi & Avatar (Desktop) -->
    <div class="flex items-center gap-3">
        <!-- Tombol Notifikasi -->
        <button id="notificationBtn" class="w-10 h-10 rounded-full hover:bg-gray-200 bg-gray-100 md:bg-surface flex items-center justify-center relative transition shadow-sm border border-gray-100 shrink-0">
            <i data-lucide="bell" class="w-5 h-5 text-gray-600"></i>
            <span class="absolute top-2 right-2 w-2 h-2 bg-pending rounded-full border border-surface"></span>
        </button>
        
        <!-- Avatar Desktop -->
        <img id="profileDesktopBtn" src="https://ui-avatars.com/api/?name=<?= urlencode($user_name ?? 'User') ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="hidden md:block w-10 h-10 rounded-full shadow-sm shrink-0 cursor-pointer hover:opacity-90 transition">
    </div>

</header>

<!-- Script Khusus Komponen Header -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Contoh: Fungsi klik Notifikasi di Header
        const notifBtn = document.getElementById('notificationBtn');
        if(notifBtn) {
            notifBtn.addEventListener('click', () => {
                // Nantinya bisa digunakan untuk memunculkan dropdown notifikasi
                console.log('Notifikasi diklik');
            });
        }
        
        // Catatan: Fungsi toggle sidebar (#sidebarToggle) sudah di-handle oleh 
        // file components/sidebar.php agar tidak terjadi duplikasi/konflik.
    });
</script>