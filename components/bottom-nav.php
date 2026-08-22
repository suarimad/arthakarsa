<?php 
// Deteksi halaman saat ini (tanpa ekstensi .php)
$current_page = basename($_SERVER['PHP_SELF'], '.php'); 
// Jika halaman kosong (root), set sebagai index
if ($current_page == '') $current_page = 'index';

// Identifikasi Role String untuk kondisional render Menu
$bn_role_name = strtolower($_SESSION['role'] ?? '');
?>

<!-- 1. NAVIGATION BAR (Hanya Tampil di Mobile) -->
<nav class="md:hidden fixed bottom-0 w-full bg-surface border-t border-gray-200 pb-safe shadow-[0_-4px_20px_rgba(0,0,0,0.04)] z-40">
    <div class="flex justify-between items-center px-5 py-4 relative">
        
        <!-- 1. Home -->
        <a href="<?= $base_url ?? '' ?>/index" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'index') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="home" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'index') ? 'font-semibold' : 'font-medium' ?>">Home</span>
        </a>
        
        <!-- 2. Employees -->
        <a href="<?= $base_url ?? '' ?>/employee" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'employee') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'employee') ? 'font-semibold' : 'font-medium' ?>">Employees</span>
        </a>
        
        <!-- 3. MENU UTAMA (FAB Tengah Mengambang) -->
        <div class="w-12 flex justify-center">
            <button onclick="openMainMenu()" class="absolute -top-5 flex items-center justify-center w-14 h-14 bg-primary text-surface rounded-full shadow-lg hover:bg-accent transition transform hover:scale-105 border-[5px] border-surface">
                <i data-lucide="layout-grid" class="w-6 h-6"></i>
            </button>
            <span class="text-[9px] font-medium text-gray-400 mt-7 pt-0.5">Menu</span>
        </div>
        
        <!-- 4. REQUEST (Akan memicu Bottom Sheet "Buat Pengajuan" dari file halaman induk) -->
        <a href="#" id="requestBtn" class="flex flex-col items-center gap-1.5 w-12 text-gray-400 hover:text-primary transition">
            <i data-lucide="file-plus" class="w-5 h-5"></i>
            <span class="text-[9px] font-medium">Request</span>
        </a>
        
        <!-- 5. Profile -->
        <a href="profile" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page == 'profile') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="user" class="w-5 h-5"></i>
            <span class="text-[9px] <?= ($current_page == 'profile') ? 'font-semibold' : 'font-medium' ?>">Profile</span>
        </a>

    </div>
</nav>

<!-- ========================================================================= -->
<!-- 2. MODAL / BOTTOM SHEET MENU UTAMA (Tersedia di Mobile & Desktop)         -->
<!-- ========================================================================= -->
<!-- PENYESUAIAN: z-index disamakan menjadi z-50 seperti "Buat Pengajuan" -->
<div id="mainMenuModal" class="fixed inset-0 z-50 hidden">
    <!-- Overlay -->
    <div id="mainMenuOverlay" onclick="closeMainMenu()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <!-- Modal Container -->
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="mainMenuCard" class="bg-surface w-full md:max-w-2xl lg:max-w-3xl rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[85vh] md:max-h-[80vh]">
            
            <!-- Handle Geser untuk Mobile -->
            <div class="pt-3 pb-3 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeMainMenu()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>

            <!-- Header Khusus Desktop -->
            <div class="hidden md:flex justify-between items-center px-6 py-4 border-b border-gray-100 shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Menu Utama</h3>
                    <p class="text-[11px] text-gray-500">Pilih fitur yang ingin Anda akses</p>
                </div>
                <button onclick="closeMainMenu()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-2 rounded-full">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Area Konten Menu (Scrollable) -->
            <!-- PENYESUAIAN: Ditambahkan pb-12 agar scroll bisa turun lebih dalam dan tidak mentok -->
            <div class="overflow-y-auto p-5 pb-12 md:p-8 md:pb-8 space-y-8 flex-1 overscroll-y-contain">
                
                <!-- SECTION 1: EKSPLORASI FITUR (Muncul untuk semua orang) -->
                <div>
                    <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider">Eksplorasi Fitur</h4>
                    <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="leave" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-off" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Izin</span>
                        </a>

                        <a href="overtime" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clock-4" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Lembur</span>
                        </a>

                        <a href="reimbursement" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="receipt" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Reimburse</span>
                        </a>

                        <a href="attendance" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clipboard-list" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Log<br>Absensi</span>
                        </a>

                        <a href="payslip" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="banknote" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Slip<br>Gaji</span>
                        </a>

                        <a href="calendar" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-days" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Kalender</span>
                        </a>

                        <a href="review" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="star" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Review</span>
                        </a>

                        <a href="project" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="briefcase" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Proyek</span>
                        </a>

                    </div>
                </div>

                <!-- SECTION 2: ADMIN MENU -->
                <?php if (in_array($bn_role_name, ['admin', 'superadmin'])): ?>
                <div>
                    <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider">Admin Menu</h4>
                    <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="setting_company" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="building" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Perusahaan</span>
                        </a>

                        <a href="setting_app" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="settings" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Aplikasi</span>
                        </a>

                        <a href="department" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="network" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Department</span>
                        </a>

                        <a href="position" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="user-cog" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Posisi</span>
                        </a>

                        <a href="location" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="map-pin" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Lokasi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clock" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Data Shift</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="shield" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Role Akses</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="server" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Tenant DB</span>
                        </a>

                    </div>
                </div>
                <?php endif; ?>

                <!-- SECTION 3: HR MENU -->
                <?php if ($bn_role_name === 'hr'): ?>
                <div>
                    <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider">HR Menu</h4>
                    <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="hr_payslip" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="file-text" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Slip Gaji</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="file-spreadsheet" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Rekap<br>Absensi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="check-square" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Approval<br>Cuti</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="hand-coins" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Kasbon<br>Karyawan</span>
                        </a>

                    </div>
                </div>
                <?php endif; ?>

                <!-- SECTION 4: MANAGER MENU -->
                <?php if ($bn_role_name === 'manager'): ?>
                <div>
                    <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider">Manager Menu</h4>
                    <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="review" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="star-half" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Employee<br>Review</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="check-circle" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Approval<br>Tim</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="pie-chart" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Laporan<br>Proyek</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="timer" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Timesheet<br>Tim</span>
                        </a>

                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. SCRIPT GLOBAL UNTUK MENGENDALIKAN MODAL MENU                           -->
<!-- ========================================================================= -->
<script>
    // Memastikan fungsi dapat dipanggil secara global
    window.openMainMenu = function() {
        const m = document.getElementById('mainMenuModal');
        const o = document.getElementById('mainMenuOverlay');
        const c = document.getElementById('mainMenuCard');
        
        m.classList.remove('hidden');
        
        // Memastikan icon-lucide ter-render jika dipanggil via event
        if (typeof lucide !== 'undefined') lucide.createIcons();
        
        setTimeout(() => {
            o.classList.remove('opacity-0');
            c.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            c.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    }

    window.closeMainMenu = function() {
        const m = document.getElementById('mainMenuModal');
        const o = document.getElementById('mainMenuOverlay');
        const c = document.getElementById('mainMenuCard');
        
        o.classList.add('opacity-0');
        c.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100');
        c.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
        
        setTimeout(() => { 
            m.classList.add('hidden'); 
        }, 300);
    }
</script>