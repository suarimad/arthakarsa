<?php 
// Deteksi halaman saat ini (tanpa ekstensi .php)
$current_page = basename($_SERVER['PHP_SELF'], '.php'); 
// Jika halaman kosong (root), set sebagai index
if ($current_page == '') $current_page = 'index';
?>
<aside id="desktop-sidebar" class="hidden md:flex flex-col w-64 bg-surface border-r border-gray-200 z-20 shrink-0 transition-all duration-300 ease-in-out">
    <div class="p-5 flex items-center gap-3 border-b border-gray-100">
        <!-- Logo Perusahaan Dinamis -->
        <div class="w-8 h-8 rounded bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 ">
            <img src="<?= $logo_path . ($app_settings['logo'] ?? 'default_logo.png') ?>" alt="Logo" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=HR&background=ea3800&color=fff'">
        </div>
        <h1 class="font-bold text-lg text-gray-800 tracking-tight leading-tight">
            <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS Pro') ?>
        </h1>
    </div>
    
    <!-- Menu Sidebar -->
    <nav class="flex-1 p-3 space-y-1">
        <!-- Beranda -->
        <a href="<?= $base_url ?>/index" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'index') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="home" class="w-4 h-4"></i> Beranda
        </a>
        
        <!-- Karyawan -->
        <a href="<?= $base_url ?>/employee" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'employee') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Karyawan
        </a>
        
        <!-- Pengajuan (Tombol Desktop Modal) -->
        <a href="#" id="desktopRequestBtn" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Pengajuan
        </a>
        
        <!-- Menu Lain -->
        <a href="menu" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="layout-grid" class="w-4 h-4"></i> Menu Lain
        </a>
        
        <!-- Profil Saya -->
        <a href="profile" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="user" class="w-4 h-4"></i> Profil Saya
        </a>
    </nav>
    
    <div class="p-3 border-t border-gray-100">
        <!-- Keluar -->
        <a href="<?= $base_url ?>/logout" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-failed/10 hover:text-failed rounded-xl text-sm font-medium transition">
            <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
        </a>
    </div>
</aside>

<!-- ================= MODAL DESKTOP REQUEST ================= -->
<!-- PENYESUAIAN: Menggunakan style manual untuk z-index memastikan ia berada di atas segalanya -->
<div id="desktopRequestModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <!-- Overlay -->
    <div id="desktopRequestOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm"></div>
    
    <!-- Modal Content Centered -->
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div id="desktopRequestCard" class="bg-surface rounded-3xl shadow-2xl p-6 md:p-8 w-full max-w-sm transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative">
            
            <!-- Tombol Close -->
            <button id="closeDesktopRequestBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5 md:hidden"></div>
            <h3 class="text-base font-bold text-gray-800 mb-6 text-center">Buat Pengajuan</h3>
            
            <div class="grid grid-cols-3 gap-4">
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm">
                        <i data-lucide="calendar-off" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Leave</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Sick</span>
                </a>
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm">
                        <i data-lucide="clock-4" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Overtime</span>
                </a>
            </div>
        </div>
    </div>
</div>

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

        // PENYESUAIAN: Memindahkan elemen Modal ke <body> agar terlepas dari jeratan layout Sidebar/Main
        const desktopModal = document.getElementById('desktopRequestModal');
        if (desktopModal) {
            document.body.appendChild(desktopModal);
        }

        // Logika Modal Request Khusus Desktop
        const desktopReqBtn = document.getElementById('desktopRequestBtn');
        const desktopOverlay = document.getElementById('desktopRequestOverlay');
        const desktopCard = document.getElementById('desktopRequestCard');
        const closeBtn = document.getElementById('closeDesktopRequestBtn');
        
        function openDesktopModal() {
            desktopModal.classList.remove('hidden');
            // Sedikit delay agar transisi CSS berjalan mulus
            setTimeout(() => { 
                desktopOverlay.classList.remove('opacity-0'); 
                desktopCard.classList.remove('scale-95', 'opacity-0');
                desktopCard.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            // Re-render icon lucide dalam modal jika diperlukan
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
        
        function closeDesktopModal() {
            desktopOverlay.classList.add('opacity-0');
            desktopCard.classList.remove('scale-100', 'opacity-100');
            desktopCard.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { 
                desktopModal.classList.add('hidden'); 
            }, 300);
        }

        if (desktopReqBtn) desktopReqBtn.addEventListener('click', (e) => { 
            e.preventDefault(); 
            openDesktopModal(); 
        });
        
        if (desktopOverlay) desktopOverlay.addEventListener('click', closeDesktopModal);
        if (closeBtn) closeBtn.addEventListener('click', closeDesktopModal);
    });
</script>