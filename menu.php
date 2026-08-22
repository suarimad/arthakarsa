<?php
// Panggil Konfigurasi Global
require_once __DIR__ . '/config/config.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

// Data Karyawan & Tenant (Dinamis dari Session)
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
$role_key = strtolower($_SESSION['role'] ?? 'employee'); // Kunci untuk logika menu dinamis

// 1. Load Head (Metadata & CSS)
require_once __DIR__ . '/components/head.php';

// 2. Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <!-- 3. Load Komponen Header (Navigasi Atas) -->
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <!-- PAGE CONTENT -->
        <div class="px-5 md:px-0 mt-2 pb-8">
            
            <!-- ============================================== -->
            <!-- SECTION 1: EKSPLORASI FITUR (UNTUK SEMUA ROLE) -->
            <!-- ============================================== -->
            <div class="space-y-4 md:space-y-6">
                <!-- Judul -->
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Eksplorasi Fitur</h2>
                </div>

                <!-- GRID MENU BOX -->
                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-6 md:p-8">
                    <!-- Grid 4 kolom di mobile, 6 di tablet, 8 di desktop -->
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <!-- 1. Izin -->
                        <a href="leave" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-off" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Izin</span>
                        </a>

                        <!-- 2. Lembur -->
                        <a href="overtime" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clock-4" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Lembur</span>
                        </a>

                        <!-- 3. Reimburse -->
                        <a href="reimbursement" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="receipt" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Reimburse</span>
                        </a>

                        <!-- 4. Log Absensi -->
                        <a href="attendance" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clipboard-list" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Log<br>Absensi</span>
                        </a>

                        <!-- 5. Slip Gaji -->
                        <a href="payslip" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="banknote" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Slip<br>Gaji</span>
                        </a>

                        <!-- 6. Kalender -->
                        <a href="calendar" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-days" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Kalender</span>
                        </a>

                        <!-- 7. Review -->
                        <a href="review" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="star" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Review</span>
                        </a>

                        <!-- 8. Proyek -->
                        <a href="project" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary group-hover:border-primary/20 transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="briefcase" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary transition-colors">Proyek</span>
                        </a>

                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- SECTION 2: ADMIN MENU (ROLE 1 & 2)             -->
            <!-- ============================================== -->
            <?php if (in_array($role_key, ['admin', 'superadmin'])): ?>
            <div class="space-y-4 md:space-y-6 mt-8">
                <!-- Judul -->
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Admin Menu</h2>
                </div>

                <!-- GRID MENU BOX -->
                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-6 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="building" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Perusahaan</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="settings" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Aplikasi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="network" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Department</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="award" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Posisi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="map-pin" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Lokasi</span>
                        </a>

                        <!-- Tambahan Ide Menu Admin -->
                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-clock" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Jadwal Shift</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="shield-check" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Hak Akses</span>
                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================== -->
            <!-- SECTION 3: HR MENU (ROLE 3)                    -->
            <!-- ============================================== -->
            <?php if ($role_key === 'hr'): ?>
            <div class="space-y-4 md:space-y-6 mt-8">
                <!-- Judul -->
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">HR Menu</h2>
                </div>

                <!-- GRID MENU BOX -->
                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-6 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="hr_payslip" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="file-text" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Slip Gaji</span>
                        </a>

                        <!-- Tambahan Ide Menu HR -->
                        <a href="employee" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="users" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Karyawan</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clipboard-check" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Rekap<br>Absensi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-check" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Approval<br>Cuti</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="wallet" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Kasbon</span>
                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================== -->
            <!-- SECTION 4: MANAGER MENU (ROLE 4)               -->
            <!-- ============================================== -->
            <?php if ($role_key === 'manager'): ?>
            <div class="space-y-4 md:space-y-6 mt-8">
                <!-- Judul -->
                <div class="flex justify-between items-center px-1">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Manager Menu</h2>
                </div>

                <!-- GRID MENU BOX -->
                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-6 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="review" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="star" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Employee<br>Review</span>
                        </a>

                        <!-- Tambahan Ide Menu Manager -->
                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="calendar-check" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Cuti Tim</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="clock" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Lembur Tim</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-2.5 group">
                            <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-gray-50 border border-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition-all shadow-sm group-hover:scale-105">
                                <i data-lucide="trending-up" class="w-5 h-5 md:w-6 md:h-6"></i>
                            </div>
                            <span class="text-[10px] md:text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-primary">Kinerja Tim</span>
                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- ================= BOTTOM SHEET REQUEST ================= -->
<!-- Dipanggil agar tombol '+' di Bottom Nav tetap berfungsi -->
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

<!-- Load Bottom Nav (Mobile) -->
<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>

<!-- Script Animasi Level Index & Icon Render -->
<script>
    lucide.createIcons();

    // Logika Bottom Sheet Request
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

        function openSheet() {
            bottomSheet.classList.remove('hidden');
            setTimeout(() => {
                overlay.classList.remove('opacity-0');
                sheet.classList.remove('translate-y-full');
            }, 10);
        }

        function closeSheet() {
            overlay.classList.add('opacity-0');
            sheet.classList.add('translate-y-full');
            setTimeout(() => {
                bottomSheet.classList.add('hidden');
            }, 300);
        }

        if (requestBtn) {
            requestBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openSheet();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSheet);
        }
    });
</script>

<!-- Load Script PWA -->
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>