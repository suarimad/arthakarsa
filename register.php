<?php
// Load konfigurasi utama (koneksi DB & $app_settings)
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
    <title>Daftar Perusahaan - <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS') ?></title>
    <meta name="description" content="Pendaftaran Tenant Baru <?= htmlspecialchars($app_settings['app_name'] ?? 'HRIS') ?>">
    <meta name="theme-color" content="<?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>">
    <link rel="icon" type="image/png" href="<?= $logo_path . ($app_settings['favicon'] ?? 'default_favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= $logo_path . ($app_settings['pwa_icon_192'] ?? 'icon-192.png') ?>">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/output.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --color-primary: <?= htmlspecialchars($app_settings['theme_color'] ?? '#ea3800') ?>; }
        button, a, input, select, textarea { touch-action: manipulation; }
    </style>
</head>
<body class="bg-background font-poppins flex items-center justify-center min-h-screen md:p-5 relative">

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-background/80 md:bg-gray-900/40 z-[100] hidden items-center justify-center flex-col backdrop-blur-sm transition-all duration-300">
        <i data-lucide="loader-2" class="w-8 h-8 text-primary animate-spin mb-3"></i>
        <p class="text-xs font-semibold text-gray-700 md:text-surface">Membuat akun tenant...</p>
    </div>

    <!-- Toast Component -->
    <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
        <i id="toastIcon" class="w-4 h-4"></i>
        <span id="toastMsg"></span>
    </div>

    <div class="w-full max-w-sm bg-transparent md:bg-surface px-6 py-8 md:p-8 rounded-none md:rounded-3xl shadow-none md:shadow-sm border-none md:border md:border-gray-100 my-8 md:my-0">
        <div class="text-center mb-8">
            <!-- Menampilkan Logo Aplikasi Dinamis -->
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 overflow-hidden shadow-sm md:shadow-none bg-surface md:bg-gray-50 border md:border-transparent border-gray-100">
                <img src="<?= $logo_path . ($app_settings['logo'] ?? 'default_logo.png') ?>" alt="Logo Aplikasi" class="w-full h-full object-contain" onerror="this.src='https://ui-avatars.com/api/?name=HR&background=ea3800&color=fff'">
            </div>

            <h1 class="text-xl font-bold text-gray-800">Daftar Perusahaan</h1>
            <p class="text-[11px] md:text-xs text-gray-500 mt-1">Buat ruang kerja perusahaan Anda.</p>
        </div>

        <form id="registerForm">
            <div class="space-y-3.5">
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Perusahaan</label>
                    <input type="text" name="company_name" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="PT Inovasi Digital">
                </div>
                
                <hr class="border-gray-100 my-3 md:my-4">
                
                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap Admin</label>
                    <input type="text" name="admin_name" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Budi Santoso">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Email Akses</label>
                    <input type="email" name="email" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="admin@perusahaan.com">
                </div>

                <div>
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-surface md:bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Min. 8 karakter">
                </div>
            </div>

            <button type="submit" class="w-full bg-primary text-surface text-sm font-semibold py-3 rounded-xl mt-6 hover:opacity-90 transition shadow-sm md:shadow-none">
                Daftar & Buat Akun
            </button>
        </form>

        <p class="text-center text-[11px] text-gray-500 mt-6">
            Sudah punya akun? <a href="login" class="text-primary font-semibold hover:underline">Masuk di sini</a>
        </p>
    </div>

    <script>
        lucide.createIcons();

        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toastMsg');
            const iconEl = document.getElementById('toastIcon');

            msgEl.textContent = msg;
            toast.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium';

            if (type === 'failed' || type === 'error') {
                toast.classList.add('bg-failed/10', 'text-failed', 'border-failed/20');
                iconEl.setAttribute('data-lucide', 'alert-circle');
            } else if (type === 'warning') {
                toast.classList.add('bg-pending/10', 'text-pending', 'border-pending/20');
                iconEl.setAttribute('data-lucide', 'alert-triangle');
            } else {
                toast.classList.add('bg-success/10', 'text-success', 'border-success/20');
                iconEl.setAttribute('data-lucide', 'check-circle');
            }
            lucide.createIcons();

            setTimeout(() => toast.classList.remove('opacity-0', '-translate-y-full'), 100);
            setTimeout(() => toast.classList.add('opacity-0', '-translate-y-full'), 4000);
        }

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');

            const formData = new FormData(this);

            fetch('register_process', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Redirect ke login, pesan sukses akan ditangkap oleh PHP Auth Guard
                    setTimeout(() => { window.location.href = 'login'; }, 1000);
                } else {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                    showToast(data.message, data.status);
                }
            })
            .catch(err => {
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                showToast('Terjadi kesalahan koneksi', 'error');
            });
        });
    </script>
</body>
</html>