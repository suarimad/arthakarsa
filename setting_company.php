<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');
if (!in_array($role_id, [1, 2]) && !in_array($role_name_session, ['admin', 'superadmin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: menu");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$toast_msg = '';
$toast_type = '';

// ==========================================
// PENANGANAN POST: UPDATE DATA PERUSAHAAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $toast_msg = "Nama perusahaan wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE tenants 
                SET name = ?, email = ?, phone = ?, address = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $address, $tenant_id]);

            // Perbarui session nama tenant agar langsung berubah di UI Header
            $_SESSION['tenant_name'] = $name;

            $toast_msg = "Profil Perusahaan berhasil diperbarui!";
            $toast_type = "success";
        } catch (Exception $e) {
            $toast_msg = "Kesalahan sistem: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

// ==========================================
// AMBIL DATA PERUSAHAAN (TENANT) TERBARU
// ==========================================
try {
    $stmtTenant = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmtTenant->execute([$tenant_id]);
    $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tenantData = [];
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
// Kita panggil nama tenant langsung dari database agar selalu fresh, jika gagal fallback ke session
$tenant_name = $tenantData['name'] ?? $_SESSION['tenant_name'] ?? 'Perusahaan';

require_once __DIR__ . '/components/head.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <!-- pb-6 di mobile, pb-8 di desktop -->
    <main class="w-full min-h-screen pb-6 md:pb-8 md:px-6">
        
        <!-- HEADER HANYA TAMPIL DI DESKTOP -->
        <div class="hidden md:block">
            <?php require_once __DIR__ . '/components/header.php'; ?>
        </div>

        <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-[999] transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
            <i id="toastIcon" class="w-4 h-4"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- PAGE CONTENT: Margin top disesuaikan untuk mobile krn header hilang -->
        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full max-w-2xl mx-auto">
            
            <!-- Judul & Back Button -->
            <div class="flex items-center gap-3 px-1 mb-6">
                <!-- Kembali diarahkan ke menu -->
                <a href="<?= ($base_url ?? '') ?>/menu" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Profil Perusahaan</h2>
                    <p class="text-[11px] text-gray-500">Ubah informasi dasar dan kontak organisasi Anda.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="">
                    <div class="space-y-4">
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Perusahaan / Organisasi</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $tenantData['name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: PT Teknologi Nusantara">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Email Resmi Perusahaan</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $tenantData['email'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="admin@perusahaan.com">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor Telepon / Kantor</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $tenantData['phone'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="021-1234567">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Lengkap Perusahaan</label>
                            <textarea name="address" rows="3" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 resize-none" placeholder="Tuliskan alamat lengkap..."><?= htmlspecialchars($_POST['address'] ?? $tenantData['address'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <a href="<?= ($base_url ?? '') ?>/menu" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<!-- Bottom Nav sengaja TIDAK dipanggil agar bersih di layar form (mobile) -->

<script>
    lucide.createIcons();

    function showToast(msg, type) {
        const toast = document.getElementById('toast');
        const msgEl = document.getElementById('toastMsg');
        const iconEl = document.getElementById('toastIcon');

        msgEl.textContent = msg;
        toast.className = 'fixed top-5 left-1/2 transform -translate-x-1/2 z-[999] transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium';

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

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg) showToast(phpMsg, phpType);
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>