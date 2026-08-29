<?php 
// Deteksi halaman saat ini (tanpa ekstensi .php)
$current_page = basename($_SERVER['PHP_SELF'], '.php'); 
// Jika halaman kosong (root), set sebagai index
if ($current_page == '') $current_page = 'index';

// Identifikasi Role String untuk kondisional render Menu
$sb_role_name = strtolower($_SESSION['role'] ?? '');
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
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto" style="scrollbar-width: thin;">
        
        <!-- ================= MENU DASAR ================= -->
        <a href="<?= $base_url ?? '' ?>/index" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'index') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="home" class="w-4 h-4"></i> Beranda
        </a>
        
        <a href="<?= $base_url ?? '' ?>/employee" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'employee') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Karyawan
        </a>
        
        <!-- Pengajuan (Tombol Desktop Modal) -->
        <a href="#" id="desktopRequestBtn" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Pengajuan
        </a>

        <!-- ================= EKSPLORASI FITUR ================= -->
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Eksplorasi Fitur</div>
        
        <a href="<?= $base_url ?? '' ?>/leave" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= in_array($current_page, ['leave', 'leave_add']) ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="calendar-off" class="w-4 h-4"></i> Izin
        </a>
        <a href="<?= $base_url ?? '' ?>/overtime" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= in_array($current_page, ['overtime', 'overtime_add']) ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="clock-4" class="w-4 h-4"></i> Lembur
        </a>
        <a href="<?= $base_url ?? '' ?>/reimbursement" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= in_array($current_page, ['reimbursement', 'reimbursement_add']) ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="receipt" class="w-4 h-4"></i> Reimburse
        </a>
        <a href="<?= $base_url ?? '' ?>/attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'attendance') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i> Log Absensi
        </a>
        <a href="<?= $base_url ?? '' ?>/payslip" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'payslip') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="banknote" class="w-4 h-4"></i> Slip Gaji
        </a>
        <a href="<?= $base_url ?? '' ?>/calendar" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'calendar') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="calendar-days" class="w-4 h-4"></i> Kalender
        </a>
        <a href="<?= $base_url ?? '' ?>/review" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'review') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="star" class="w-4 h-4"></i> Review
        </a>
        <a href="<?= $base_url ?? '' ?>/project" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'project') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="briefcase" class="w-4 h-4"></i> Proyek
        </a>

        <!-- ================= ADMIN MENU ================= -->
        <?php if (in_array($sb_role_name, ['admin', 'superadmin'])): ?>
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Admin Menu</div>
        <a href="<?= $base_url ?? '' ?>/setting_company" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'setting_company') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="building" class="w-4 h-4"></i> Perusahaan
        </a>
        <a href="<?= $base_url ?? '' ?>/setting_app" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'setting_app') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="settings" class="w-4 h-4"></i> Aplikasi
        </a>
        <a href="<?= $base_url ?? '' ?>/department" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'department') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="network" class="w-4 h-4"></i> Department
        </a>
        <a href="<?= $base_url ?? '' ?>/position" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'position') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="user-cog" class="w-4 h-4"></i> Posisi
        </a>
        <a href="<?= $base_url ?? '' ?>/location" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'location') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="map-pin" class="w-4 h-4"></i> Lokasi
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="clock" class="w-4 h-4"></i> Data Shift
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="shield" class="w-4 h-4"></i> Role Akses
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="server" class="w-4 h-4"></i> Tenant DB
        </a>
        <?php endif; ?>

        <!-- ================= HR MENU ================= -->
        <?php if ($sb_role_name === 'hr'): ?>
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">HR Menu</div>
        <a href="<?= $base_url ?? '' ?>/hr_payslip" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'hr_payslip') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="file-text" class="w-4 h-4"></i> Slip Gaji
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Rekap Absensi
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="check-square" class="w-4 h-4"></i> Approval Cuti
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="hand-coins" class="w-4 h-4"></i> Kasbon Karyawan
        </a>
        <?php endif; ?>

        <!-- ================= MANAGER MENU ================= -->
        <?php if ($sb_role_name === 'manager'): ?>
        <div class="px-3 mt-6 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Manager Menu</div>
        <a href="<?= $base_url ?? '' ?>/review" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'review') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
            <i data-lucide="star-half" class="w-4 h-4"></i> Employee Review
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="check-circle" class="w-4 h-4"></i> Approval Tim
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="pie-chart" class="w-4 h-4"></i> Laporan Proyek
        </a>
        <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-gray-500 hover:bg-gray-50 hover:text-primary rounded-xl text-sm font-medium transition">
            <i data-lucide="timer" class="w-4 h-4"></i> Timesheet Tim
        </a>
        <?php endif; ?>

    </nav>
    
    <!-- Profil & Keluar (Tetap diam di paling bawah tanpa perlu di-scroll) -->
    <div class="p-3 border-t border-gray-100 space-y-1 shrink-0 bg-surface">
        <a href="<?= $base_url ?? '' ?>/profile" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition <?= ($current_page == 'profile') ? 'bg-primary/10 text-primary' : 'text-gray-500 hover:bg-gray-50 hover:text-primary' ?>">
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
            
            <!-- Tombol Close -->
            <button id="closeDesktopRequestBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5 md:hidden"></div>
            <h3 class="text-base font-bold text-gray-800 mb-6 text-center">Buat Pengajuan</h3>
            
            <!-- GRID Disesuaikan menjadi grid-cols-2 agar pas menampung 4 menu -->
            <div class="grid grid-cols-2 gap-4">
                <a href="<?= $base_url ?? '' ?>/leave_add/cuti" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm">
                        <i data-lucide="calendar-off" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Cuti</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/sakit" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Sakit</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/izin" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition shadow-sm">
                        <i data-lucide="user-minus" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Izin</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/overtime_add" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm">
                        <i data-lucide="clock-4" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-600">Lembur</span>
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