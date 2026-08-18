<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin
if (!in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: employee");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$toast_msg = '';
$toast_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';

    if (empty($name) || empty($email) || empty($password)) {
        $toast_msg = "Semua kolom wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $toast_msg = "Email sudah terdaftar di sistem. Gunakan email lain.";
                $toast_type = "failed";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (tenant_id, role, name, email, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_id, $role, $name, $email, $hashed_password]);

                $_SESSION['toast_msg'] = "Karyawan $name berhasil ditambahkan!";
                $_SESSION['toast_type'] = "success";
                header("Location: employee");
                exit;
            }
        } catch (Exception $e) {
            $toast_msg = "Kesalahan sistem: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan';

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

        <div id="toast" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-full flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg border text-xs font-medium">
            <i id="toastIcon" class="w-4 h-4"></i>
            <span id="toastMsg"></span>
        </div>

        <!-- PAGE CONTENT: Margin top disesuaikan untuk mobile krn header hilang -->
        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <!-- Judul & Back Button -->
            <div class="flex items-center gap-3 px-1 mb-6">
                <a href="employee" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Tambah Karyawan</h2>
                    <p class="text-[11px] text-gray-500">Tambahkan akun baru ke dalam perusahaan.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="">
                    <div class="space-y-4">
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: Budi Santoso">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Email</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="budi@perusahaan.com">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Kata Sandi Awal</label>
                            <input type="password" name="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Minimal 8 karakter">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Hak Akses (Role)</label>
                            <div class="relative">
                                <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none">
                                    <option value="employee" <?= (isset($_POST['role']) && $_POST['role'] == 'employee') ? 'selected' : '' ?>>Karyawan Standar (Employee)</option>
                                    <option value="manager" <?= (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : '' ?>>Manajer (Manager)</option>
                                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Admin Perusahaan</option>
                                </select>
                                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <a href="employee" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" class="flex-1 bg-primary text-surface py-3 rounded-xl text-sm font-semibold hover:opacity-90 transition shadow-sm flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Data
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

    const phpMsg = <?= json_encode($toast_msg) ?>;
    const phpType = <?= json_encode($toast_type) ?>;
    if (phpMsg) showToast(phpMsg, phpType);
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>