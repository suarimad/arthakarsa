<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Panggil Komponen Auth (Memastikan user harus login)
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Menangkap Pesan Toast
$toast_msg = '';
$toast_type = '';

// ==============================================================================
// PENANGANAN POST: UPDATE KATA SANDI
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $toast_msg = "Semua kolom kata sandi wajib diisi!";
        $toast_type = "warning";
    } elseif ($new_password !== $confirm_password) {
        $toast_msg = "Konfirmasi kata sandi baru tidak cocok!";
        $toast_type = "failed";
    } elseif (strlen($new_password) < 8) {
        $toast_msg = "Kata sandi baru minimal 8 karakter!";
        $toast_type = "warning";
    } else {
        try {
            // Ambil password lama dari database
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$user_id, $tenant_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifikasi kecocokan password saat ini
            if ($user && password_verify($current_password, $user['password'])) {
                
                // Enkripsi kata sandi baru
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update ke database, dan set is_password_default menjadi 0
                $stmtUpdate = $pdo->prepare("UPDATE users SET password = ?, is_password_default = 0 WHERE id = ? AND tenant_id = ?");
                $stmtUpdate->execute([$hashed_password, $user_id, $tenant_id]);

                // Update session agar terlepas dari redirect paksa auth.php
                $_SESSION['is_password_default'] = 0;

                $_SESSION['toast_msg'] = "Kata sandi berhasil diperbarui!";
                $_SESSION['toast_type'] = "success";
                
                header("Location: index");
                exit;
            } else {
                $toast_msg = "Kata sandi saat ini yang Anda masukkan salah!";
                $toast_type = "failed";
            }
        } catch (Exception $e) {
            $toast_msg = "Gagal memperbarui kata sandi: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

// Fetch Data Standard User (Untuk Header & Sidebar)
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

// Cek apakah user sedang dalam status dipaksa ganti password (default)
$is_forced = (isset($_SESSION['is_password_default']) && $_SESSION['is_password_default'] == 1);

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-8 md:px-6">
        
        <!-- HEADER DESKTOP -->
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <!-- Judul & Back Button -->
            <div class="flex items-center gap-3 px-1 mb-6">
                <?php if (!$is_forced): ?>
                <a href="<?= ($base_url ?? '') ?>/profile" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition shadow-sm active:scale-95">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary shadow-sm">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                </div>
                <?php endif; ?>
                
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Ubah Kata Sandi</h2>
                    <p class="text-[11px] text-gray-500">Perbarui kata sandi akun Anda untuk keamanan.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm space-y-6">
                
                <?php if ($is_forced): ?>
                <div class="bg-pending/10 border border-pending/20 p-4 rounded-xl flex gap-3 items-start">
                    <i data-lucide="shield-alert" class="w-5 h-5 text-pending shrink-0 mt-0.5"></i>
                    <div>
                        <h4 class="text-xs font-bold text-pending uppercase tracking-wider mb-1">Peringatan Keamanan</h4>
                        <p class="text-[11px] text-gray-600 leading-relaxed font-medium">Anda masih menggunakan kata sandi default sistem. Silakan buat kata sandi baru Anda sekarang untuk mengamankan akun dan melanjutkan akses.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FORM EDIT DATA -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="space-y-4">
                        
                        <!-- Current Password -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi Saat Ini</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" placeholder="Masukkan kata sandi lama Anda">
                        </div>

                        <!-- New Password -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi Baru</label>
                            <input type="password" name="new_password" required minlength="8" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" placeholder="Minimal 8 karakter">
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="confirm_password" required minlength="8" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition" placeholder="Ketik ulang kata sandi baru Anda">
                        </div>

                    </div>

                    <!-- Tombol Aksi -->
                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <?php if (!$is_forced): ?>
                        <a href="<?= ($base_url ?? '') ?>/profile" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition active:scale-95 flex items-center justify-center">
                            Batal
                        </a>
                        <?php endif; ?>
                        
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2 active:scale-95">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Sandi
                        </button>
                    </div>
                </form>

            </div>

        </div>
    </main>
</div>

<!-- Bottom Nav sengaja TIDAK dipanggil agar bersih di layar form edit (mobile) -->

<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    
    if (phpMsg && typeof window.showToast === 'function') {
        window.showToast(phpMsg, phpType);
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>