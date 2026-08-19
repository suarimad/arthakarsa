<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];

// Ambil data user terbaru dari database
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, u.role, p.name as position_name, t.name as tenant_name 
        FROM users u 
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_name = $user_data['name'];
        $user_email = $user_data['email'];
        $user_role_display = $user_data['position_name'] ?? ucfirst($user_data['role']);
        $tenant_name_display = $user_data['tenant_name'] ?? 'Sistem Pusat';
    } else {
        // Fallback jika gagal fetch
        $user_name = $_SESSION['user_name'] ?? 'User';
        $user_email = '';
        $user_role_display = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
        $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
    }
} catch (Exception $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_email = '';
    $user_role_display = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
    $tenant_name_display = $_SESSION['tenant_name'] ?? 'Perusahaan'; 
}

// 1. Load Head
require_once __DIR__ . '/components/head.php';

// 2. Load Sidebar (Desktop)
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-28 md:pb-8 md:px-6">
        
        <!-- 3. Load Komponen Header (Navigasi Atas) -->
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <!-- PAGE CONTENT -->
        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2">
            
            <div class="flex justify-between items-center px-1">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Profil Saya</h2>
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                
                <!-- Kiri (2 Kolom di Desktop): PROFIL & DETAIL -->
                <div class="md:col-span-2 space-y-5 md:space-y-6">
                    
                    <!-- KARTU PROFIL UTAMA -->
                    <section class="bg-surface border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col md:flex-row items-center md:items-start gap-5 text-center md:text-left relative overflow-hidden">
                        <!-- Dekorasi Background -->
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full pointer-events-none -z-0"></div>
                        
                        <!-- Avatar -->
                        <div class="w-24 h-24 md:w-28 md:h-28 rounded-full border-4 border-surface shadow-md relative z-2 shrink-0">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=<?= str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800') ?>&color=fff&size=150&rounded=true" alt="Profile" class="w-full h-full rounded-full object-cover">
                            <!-- Tombol Edit Avatar (Visual saja) -->
                            <button class="absolute bottom-0 right-0 w-8 h-8 bg-primary text-surface rounded-full flex items-center justify-center border-2 border-surface shadow-sm hover:scale-105 transition">
                                <i data-lucide="camera" class="w-4 h-4"></i>
                            </button>
                        </div>
                        
                        <!-- Info Singkat -->
                        <div class="relative z-10 flex-1 mt-2 md:mt-4">
                            <h3 class="text-xl md:text-2xl font-bold text-gray-800 tracking-tight"><?= htmlspecialchars($user_name) ?></h3>
                            <p class="text-sm font-medium text-primary mt-1"><?= htmlspecialchars($user_role_display) ?></p>
                            <div class="flex items-center justify-center md:justify-start gap-1.5 text-xs text-gray-500 mt-2 font-medium">
                                <i data-lucide="building-2" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($tenant_name_display) ?>
                            </div>
                        </div>
                    </section>

                    <!-- DETAIL AKUN -->
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-50 flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-gray-800">Informasi Pribadi</h3>
                            <button class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                                <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                            </button>
                        </div>
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Nama Lengkap</label>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Email Utama</label>
                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($user_email) ?></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Peran Akses (Role)</label>
                                <p class="text-sm font-medium text-gray-800 capitalize"><?= htmlspecialchars($_SESSION['role'] ?? 'employee') ?></p>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- Kanan (1 Kolom di Desktop): PENGATURAN & LOGOUT -->
                <div class="md:col-span-1 mt-5 md:mt-0 space-y-5 md:space-y-6">
                    
                    <!-- MENU PENGATURAN -->
                    <section class="bg-surface border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-50">
                            <h3 class="text-sm font-semibold text-gray-800">Pengaturan</h3>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="lock" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Ubah Kata Sandi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>
                            
                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="bell" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Notifikasi</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>

                            <a href="#" class="flex items-center justify-between p-4 hover:bg-gray-50 transition group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center group-hover:bg-primary/10 group-hover:text-primary transition">
                                        <i data-lucide="help-circle" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700 group-hover:text-primary transition">Pusat Bantuan</span>
                                </div>
                                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
                            </a>
                        </div>
                    </section>

                    <!-- TOMBOL LOGOUT -->
                    <a href="logout" class="w-full bg-surface border border-failed/30 text-failed text-sm font-semibold py-3.5 rounded-2xl flex items-center justify-center gap-2 hover:bg-failed hover:text-surface transition shadow-sm group">
                        <i data-lucide="log-out" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> Keluar Aplikasi
                    </a>

                </div>

            </div>
        </div>
    </main>
</div>

<!-- ================= BOTTOM SHEET REQUEST ================= -->
<!-- Dibutuhkan agar Bottom Nav bagian FAB (Request) tetap berfungsi di halaman ini -->
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

<!-- Panggil Komponen Toast Secara Global -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<!-- Script Interaktif -->
<script>
    lucide.createIcons();

    // Logika Modal Request (Bottom Sheet)
    document.addEventListener('DOMContentLoaded', () => {
        const requestBtn = document.getElementById('requestBtn');
        const bottomSheet = document.getElementById('requestBottomSheet');
        const overlay = document.getElementById('requestOverlay');
        const sheet = document.getElementById('requestSheet');

        if (requestBtn && bottomSheet && overlay && sheet) {
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

            requestBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openSheet();
            });

            overlay.addEventListener('click', closeSheet);
        }
    });
</script>

<!-- Load Script PWA -->
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>