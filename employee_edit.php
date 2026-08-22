<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// Guard: Hanya admin & superadmin & hr yang boleh masuk (sesuai role_id atau nama role)
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');
if (!in_array($role_id, [1, 2, 3]) && !in_array($role_name_session, ['superadmin', 'admin', 'hr'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['toast_type'] = "failed";
    header("Location: ../../employee");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    header("Location: ../../employee");
    exit;
}

// 1. Ambil data karyawan terkait
$stmtUser = $pdo->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.uuid = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
");
$stmtUser->execute([$uuid, $tenant_id]);
$editUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$editUser) {
    $_SESSION['toast_msg'] = "Data karyawan tidak ditemukan.";
    $_SESSION['toast_type'] = "failed";
    header("Location: ../../employee");
    exit;
}

$toast_msg = '';
$toast_type = '';

// 2. Proses Pembaruan Data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $password = $_POST['password'] ?? ''; // Opsional
    $role = $_POST['role'] ?? 'employee';

    if (empty($name) || empty($email)) {
        $toast_msg = "Nama dan Email wajib diisi!";
        $toast_type = "warning";
    } else {
        try {
            // Cek apakah email sudah dipakai orang lain
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $editUser['id']]);
            
            if ($stmtCheck->fetch()) {
                $toast_msg = "Email sudah terdaftar di akun lain. Gunakan email berbeda.";
                $toast_type = "failed";
            } else {
                if (!empty($password)) {
                    // Update dengan ganti password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, whatsapp = ?, password = ?, role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1)
                        WHERE uuid = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$name, $email, $whatsapp, $hashed_password, $role, $uuid, $tenant_id]);
                } else {
                    // Update tanpa mengganti password
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, whatsapp = ?, role_id = (SELECT id FROM roles WHERE name = ? LIMIT 1)
                        WHERE uuid = ? AND tenant_id = ?
                    ");
                    $stmt->execute([$name, $email, $whatsapp, $role, $uuid, $tenant_id]);
                }

                $_SESSION['toast_msg'] = "Data $name berhasil diperbarui!";
                $_SESSION['toast_type'] = "success";
                header("Location: ../../employee");
                exit;
            }
        } catch (Exception $e) {
            $toast_msg = "Kesalahan sistem: " . $e->getMessage();
            $toast_type = "error";
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
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

        <!-- PAGE CONTENT: Margin top disesuaikan untuk mobile krn header hilang -->
        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            
            <!-- Judul & Back Button (Menggunakan url ../../employee karena depth URL kita sekarang bertambah) -->
            <div class="flex items-center gap-3 px-1 mb-6">
                <!-- <a href="../../employee" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 hover:text-gray-800 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a> -->
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight leading-tight">Edit Karyawan</h2>
                    <p class="text-[11px] text-gray-500">Ubah data untuk <?= htmlspecialchars($editUser['name']) ?>.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-surface md:border border-gray-100 md:rounded-3xl md:shadow-sm md:p-6 p-1">
                <form method="POST" action="">
                    <div class="space-y-4">
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nama Lengkap</label>
                            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $editUser['name']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Misal: Budi Santoso">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alamat Email</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $editUser['email']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="budi@perusahaan.com">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Nomor WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?= htmlspecialchars($_POST['whatsapp'] ?? $editUser['whatsapp']) ?>" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="08123456789">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Ubah Kata Sandi</label>
                            <input type="password" name="password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800" placeholder="Biarkan kosong jika tidak ingin mengubah sandi">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Hak Akses (Role)</label>
                            <div class="relative">
                                <?php $currentRole = $_POST['role'] ?? strtolower($editUser['role_name']); ?>
                                <select name="role" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition text-xs text-gray-800 appearance-none">
                                    <option value="employee" <?= ($currentRole == 'employee') ? 'selected' : '' ?>>Karyawan Standar (Employee)</option>
                                    <option value="manager" <?= ($currentRole == 'manager') ? 'selected' : '' ?>>Manajer (Manager)</option>
                                    <option value="hr" <?= ($currentRole == 'hr') ? 'selected' : '' ?>>HR Manager</option>
                                    <option value="admin" <?= ($currentRole == 'admin') ? 'selected' : '' ?>>Admin Perusahaan</option>
                                </select>
                                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <a href="../../employee" class="w-1/3 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-semibold text-center hover:bg-gray-50 transition">
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

<!-- Menggunakan pemanggilan Toast Component secara global (Agar lebih bersih) -->
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>