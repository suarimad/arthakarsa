<?php
// Panggil Konfigurasi Global
require_once __DIR__ . '/config/config.php';

// Data Mockup Karyawan & Tenant (SaaS)
$user_name = "Budi Santoso";
$user_role = "Product Designer";
$tenant_name = "PT Inovasi Digital"; 

// Load Header (yang sudah berisi link ke file output.css dari Tailwind CLI lokal)
require_once __DIR__ . '/components/header.php';

// Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <!-- HEADER TENANT & PROFILE -->
        <header class="p-5 md:pt-6 md:px-0 flex justify-between items-center bg-surface md:bg-transparent sticky top-0 z-10 md:static">
            
            <!-- Kiri: Hamburger Menu (Desktop) / Avatar (Mobile) -->
            <div class="flex items-center gap-3 md:gap-4">
                <button id="sidebarToggle" class="hidden md:flex w-10 h-10 rounded-xl hover:bg-gray-200 bg-gray-100 items-center justify-center transition-colors shadow-sm border border-gray-100 text-gray-600 shrink-0">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="md:hidden w-10 h-10 rounded-full shadow-sm shrink-0">
                
                <div>
                    <h1 class="text-base md:text-lg font-semibold text-gray-800 leading-tight">Halo, <?= htmlspecialchars($user_name) ?>!</h1>
                    <p class="text-[11px] md:text-xs text-gray-500 font-medium mt-0.5">
                        <?= htmlspecialchars($user_role) ?> <span class="text-gray-300 mx-1">•</span> <span class="text-primary font-semibold"><?= htmlspecialchars($tenant_name) ?></span>
                    </p>
                </div>
            </div>

            <!-- Kanan: Notifikasi & Avatar (Desktop) -->
            <div class="flex items-center gap-3">
                <button class="w-10 h-10 rounded-full hover:bg-gray-200 bg-gray-100 md:bg-surface flex items-center justify-center relative transition shadow-sm border border-gray-100 shrink-0">
                    <i data-lucide="bell" class="w-5 h-5 text-gray-600"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-pending rounded-full border border-surface"></span>
                </button>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&rounded=true" alt="Profile" class="hidden md:block w-10 h-10 rounded-full shadow-sm shrink-0 cursor-pointer hover:opacity-90 transition">
            </div>
        </header>

        <!-- DASHBOARD CONTENT -->
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                
                <!-- Kiri (2 Kolom di Desktop) -->
                <div class="md:col-span-2 space-y-5 md:space-y-6">
                    <!-- ATTENDANCE CARD -->
                    <section class="bg-primary rounded-2xl p-5 text-surface shadow-md relative overflow-hidden">
                        <div class="absolute top-0 right-0 opacity-10 pointer-events-none">
                            <i data-lucide="fingerprint" class="w-40 h-40 md:w-56 md:h-56 -mt-6 -mr-6"></i>
                        </div>
                        <div class="relative z-10">
                            <!-- Menampilkan Tanggal Sesuai Realita (Contoh statis, nanti bisa diganti dinamis pakai PHP date) -->
                            <p class="text-xs md:text-sm text-surface/80 mb-1">Selasa, 18 Agustus 2026</p>
                            <h2 class="text-2xl md:text-3xl font-bold mb-5 tracking-tight">08:45 <span class="text-sm md:text-base font-normal text-surface/80">WIB</span></h2>
                            
                            <div class="flex gap-2 md:w-1/2">
                                <button class="flex-1 bg-surface text-primary text-sm font-semibold py-2.5 md:py-3 rounded-xl flex items-center justify-center gap-2 hover:bg-gray-50 transition shadow-sm">
                                    <i data-lucide="log-in" class="w-4 h-4"></i> Clock In
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- RIWAYAT ABSENSI -->
                    <section>
                        <div class="flex justify-between items-end mb-3 px-1">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Riwayat Absensi</h3>
                            <a href="#" class="text-[11px] text-primary font-medium hover:underline">Lihat Semua</a>
                        </div>
                        <div class="space-y-3">
                            <!-- History Item -->
                            <div class="bg-surface border border-gray-100 rounded-xl p-3 md:p-4 shadow-sm flex items-center justify-between transition hover:border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-xl bg-gray-50 flex flex-col items-center justify-center border border-gray-100 shrink-0">
                                        <span class="text-[9px] text-gray-500 font-medium">AGS</span>
                                        <span class="text-xs font-bold text-gray-800">17</span>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-gray-500 font-medium">Senin</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                <i data-lucide="log-in" class="w-3 h-3 text-success"></i> 08:00
                                            </span>
                                            <span class="text-gray-300 text-[10px]">-</span>
                                            <span class="text-xs font-semibold text-gray-800 flex items-center gap-1">
                                                <i data-lucide="log-out" class="w-3 h-3 text-failed"></i> 17:05
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <span class="px-2 py-1 bg-success/10 text-success text-[9px] font-bold rounded-md">Tepat Waktu</span>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Kanan (1 Kolom di Desktop): RECENT ACTIVITY -->
                <div class="md:col-span-1 mt-5 md:mt-0">
                    <section class="bg-surface md:p-5 md:rounded-2xl md:shadow-sm md:border md:border-gray-100 h-full">
                        <div class="flex justify-between items-end mb-3 px-1 md:px-0">
                            <h3 class="text-xs md:text-sm font-semibold text-gray-800">Aktivitas Terakhir</h3>
                            <a href="#" class="text-[11px] text-primary font-medium hover:underline">Semua</a>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex items-center gap-3 p-3 md:p-0 md:py-2.5 bg-gray-50 md:bg-transparent rounded-xl md:border-none border border-gray-100 md:border-b md:rounded-none md:border-gray-50">
                                <div class="w-8 h-8 rounded-full bg-success/10 text-success flex items-center justify-center shrink-0">
                                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-[12px] font-semibold text-gray-800">Cuti Disetujui</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">20-22 Agustus</p>
                                </div>
                                <span class="text-[9px] text-gray-400 font-medium">2j lalu</span>
                            </div>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- ================= BOTTOM SHEET REQUEST ================= -->
<div id="requestBottomSheet" class="fixed inset-0 z-50 hidden">
    <!-- Overlay Gelap (Background blur/darken) -->
    <div id="requestOverlay" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300"></div>
    
    <!-- Kotak Bottom Sheet -->
    <div id="requestSheet" class="absolute bottom-0 left-0 right-0 bg-surface rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-in-out pb-safe">
        <div class="p-5 pb-8 md:max-w-md md:mx-auto">
            <!-- Garis Handle Sheet -->
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5"></div>
            
            <h3 class="text-sm font-semibold text-gray-800 mb-5 text-center">Buat Pengajuan</h3>
            
            <div class="grid grid-cols-3 gap-4">
                <!-- 1: Leave -->
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm">
                        <i data-lucide="calendar-off" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Leave</span>
                </a>
                
                <!-- 2: Sick -->
                <a href="#" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5"></i>
                    </div>
                    <span class="text-[11px] font-medium text-gray-600">Sick</span>
                </a>
                
                <!-- 3: Overtime -->
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
    // Init Lucide Icons
    lucide.createIcons();

    // Logika Bottom Sheet
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