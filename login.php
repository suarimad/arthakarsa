<?php
// Load konfigurasi utama (koneksi DB & $app_settings)
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Redirect ke dashboard jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: index");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- Meta Data & Favicon dari app_settings -->
    <title>Login - <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS') ?></title>
    <meta name="description" content="<?= htmlspecialchars($app_settings['description'] ?? 'Login ke HRIS') ?>">
    <meta name="theme-color" content="<?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>">
    <link rel="icon" type="image/png" href="<?= $logo_path . ($app_settings['favicon'] ?? 'default_favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= $logo_path . ($app_settings['pwa_icon_192'] ?? 'icon-192.png') ?>">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --color-primary: <?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>; }
        /* CSS Mencegah Double Tap Zoom & Memaksa elemen agar aman disentuh */
        button, a, input, select, textarea {
            touch-action: manipulation;
        }
    </style>
</head>
<body class="bg-background font-poppins flex items-center justify-center min-h-screen md:p-5 relative">

    <!-- Loading Overlay (Muncul saat proses AJAX) -->
    <div id="loadingOverlay" class="fixed inset-0 bg-background/80 md:bg-gray-900/40 z-[100] hidden items-center justify-center flex-col backdrop-blur-sm transition-all duration-300">
        <i data-lucide="loader-2" class="w-8 h-8 text-primary animate-spin mb-3"></i>
        <p class="text-xs font-semibold text-gray-700 md:text-surface">Memproses data...</p>
    </div>

    <!-- Wrapper Responsif: Tidak ada background/box di mobile, berbentuk box di desktop -->
    <div class="w-full max-w-sm bg-transparent md:bg-surface px-6 py-8 md:p-8 rounded-none md:rounded-3xl shadow-none md:shadow-sm border-none md:border md:border-gray-100">
        <div class="text-center mb-8">
            <!-- Menampilkan Logo Aplikasi Dinamis -->
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 overflow-hidden shadow-sm md:shadow-none bg-surface md:bg-gray-50 border md:border-transparent border-gray-100">
                <img src="<?= $logo_path . ($app_settings['logo'] ?? 'default_logo.png') ?>" alt="Logo Aplikasi" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=HR&background=ea3800&color=fff'">
            </div>
            
            <h1 class="text-xl font-bold text-gray-800">Selamat Datang</h1>
            <p class="text-[11px] md:text-xs text-gray-500 mt-1">Masuk ke <?= htmlspecialchars($app_settings['app_name'] ?? 'Sistem HRIS') ?>.</p>
        </div>

        <form id="loginForm">
            <div class="space-y-3.5">
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Email Akses</label>
                    <input type="email" name="email" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="nama@perusahaan.com">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full bg-primary text-surface text-sm font-semibold py-3 rounded-xl mt-6 hover:opacity-90 transition shadow-sm md:shadow-none flex justify-center items-center gap-2">
                Masuk Sekarang
            </button>
        </form>

        <p class="text-center text-[11px] text-gray-500 mt-6">
            Admin Perusahaan Baru? <a href="register" class="text-primary font-semibold hover:underline">Daftar Tenant</a>
        </p>
    </div>

    <!-- 1. Memanggil Komponen Toast secara Global -->
    <?php require_once __DIR__ . '/components/toast.php'; ?>

    <script>
        lucide.createIcons();

        // Proses AJAX Form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('hidden'); // Tampilkan loading
            overlay.classList.add('flex');

            const formData = new FormData(this);

            fetch('login_process', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Karena login_process.php sudah menset Session Toast,
                    // Kita cukup mengalihkan halaman, toast akan diurus oleh index.php
                    setTimeout(() => { window.location.href = 'index'; }, 500); 
                } else {
                    overlay.classList.add('hidden'); // Matikan loading jika gagal
                    overlay.classList.remove('flex');
                    // Tampilkan pesan error langsung di halaman ini menggunakan fungsi dari toast.php
                    if(typeof showToast === 'function') {
                        showToast(data.message, data.status);
                    }
                }
            })
            .catch(err => {
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                if(typeof showToast === 'function') {
                    showToast('Terjadi kesalahan koneksi', 'error');
                }
            });
        });
    </script>
</body>
</html>