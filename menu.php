<?php
// Panggil Konfigurasi Global
require_once __DIR__ . '/config/config.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

// Data Karyawan & Tenant (Dinamis dari Session)
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// Identifikasi Role String untuk kondisional menu
$role_name_session = strtolower($_SESSION['role'] ?? '');

// 1. Load Head (Metadata & CSS)
require_once __DIR__ . '/components/head.php';

// 2. Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <!-- Diubah pb-36 agar menu terbawah tidak tertutup bottom navigation di mobile -->
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-36 md:pb-8 md:px-6">
        
        <!-- 3. Load Komponen Header (Navigasi Atas) -->
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <!-- PAGE CONTENT -->
        <div class="px-5 md:px-0 space-y-8 mt-2 relative z-0">
            
            <!-- ============================================== -->
            <!-- SECTION 1: MENU UMUM (EKSPLORASI FITUR) -->
            <!-- ============================================== -->
            <div>
                <!-- Padding disamakan dengan padding dalam card agar sejajar rata kiri -->
                <div class="flex justify-between items-center px-2 md:px-8 mb-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Eksplorasi Fitur</h2>
                </div>

                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-2 md:p-8">
                    <!-- Grid 4 kolom di mobile, 6 di tablet, 8 di desktop -->
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <!-- 1. Izin -->
                        <a href="leave" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center group-hover:bg-violet-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="calendar-off" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-violet-600 transition-colors">Izin</span>
                        </a>

                        <!-- 2. Lembur -->
                        <a href="overtime" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center group-hover:bg-orange-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="clock-4" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-600 transition-colors">Lembur</span>
                        </a>

                        <!-- 3. Reimburse -->
                        <a href="reimbursement" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="receipt" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-emerald-600 transition-colors">Reimburse</span>
                        </a>

                        <!-- 4. Log Absensi -->
                        <a href="attendance" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="clipboard-list" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-blue-600 transition-colors">Log<br>Absensi</span>
                        </a>

                        <!-- 5. Slip Gaji -->
                        <a href="payslip" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:bg-teal-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="banknote" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-teal-600 transition-colors">Slip<br>Gaji</span>
                        </a>

                        <!-- 6. Kalender -->
                        <a href="calendar" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="calendar-days" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-indigo-600 transition-colors">Kalender</span>
                        </a>

                        <!-- 7. Review -->
                        <a href="review" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="star" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-amber-600 transition-colors">Review</span>
                        </a>

                        <!-- 8. Proyek -->
                        <a href="project" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:bg-rose-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="briefcase" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-rose-600 transition-colors">Proyek</span>
                        </a>

                    </div>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- SECTION 2: ADMIN MENU (Khusus Admin/Superadmin)-->
            <!-- ============================================== -->
            <?php if (in_array($role_name_session, ['admin', 'superadmin'])): ?>
            <div>
                <div class="flex justify-between items-center px-2 md:px-8 mb-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Admin Menu</h2>
                </div>

                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-2 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="setting_company" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="building" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-blue-600 transition-colors">Perusahaan</span>
                        </a>

                        <a href="setting_app" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:bg-slate-200 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="settings" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-slate-800 transition-colors">Aplikasi</span>
                        </a>

                        <a href="department" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center group-hover:bg-pink-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="network" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-pink-600 transition-colors">Department</span>
                        </a>

                        <a href="position" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center group-hover:bg-orange-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="user-cog" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-600 transition-colors">Posisi</span>
                        </a>

                        <a href="location" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center group-hover:bg-red-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="map-pin" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-red-600 transition-colors">Lokasi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:bg-cyan-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="clock" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-cyan-600 transition-colors">Data Shift</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="shield" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-indigo-600 transition-colors">Role Akses</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center group-hover:bg-slate-200 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="server" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-slate-800 transition-colors">Tenant DB</span>
                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================== -->
            <!-- SECTION 3: HR MENU (Khusus HR)                 -->
            <!-- ============================================== -->
            <?php if ($role_name_session === 'hr'): ?>
            <div>
                <div class="flex justify-between items-center px-2 md:px-8 mb-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">HR Menu</h2>
                </div>

                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-2 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="hr_payslip" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:bg-teal-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="file-text" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-teal-600 transition-colors">Slip Gaji</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="file-spreadsheet" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-blue-600 transition-colors">Rekap<br>Absensi</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center group-hover:bg-violet-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="check-square" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-violet-600 transition-colors">Approval<br>Cuti</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="hand-coins" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-emerald-600 transition-colors">Kasbon<br>Karyawan</span>
                        </a>

                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================== -->
            <!-- SECTION 4: MANAGER MENU (Khusus Manager)       -->
            <!-- ============================================== -->
            <?php if ($role_name_session === 'manager'): ?>
            <div>
                <div class="flex justify-between items-center px-2 md:px-8 mb-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Manager Menu</h2>
                </div>

                <div class="bg-surface md:border border-gray-100 rounded-3xl md:shadow-sm p-2 md:p-8">
                    <div class="grid grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-y-7 gap-x-2 md:gap-x-6">
                        
                        <a href="review" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="star-half" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-amber-600 transition-colors">Employee<br>Review</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="check-circle" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-blue-600 transition-colors">Approval<br>Tim</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:bg-rose-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="pie-chart" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-rose-600 transition-colors">Laporan<br>Proyek</span>
                        </a>

                        <a href="#" class="flex flex-col items-center gap-1.5 group">
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center group-hover:bg-orange-100 transition-all shadow-sm group-hover:scale-110">
                                <i data-lucide="timer" class="w-6 h-6 md:w-7 md:h-7"></i>
                            </div>
                            <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight group-hover:text-orange-600 transition-colors">Timesheet<br>Tim</span>
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