<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Menangkap payload untuk Ingat Saya (3 bulan)
$remember = isset($_POST['remember']);

if ($remember) {
    // Set cookie lifetime to 3 months (90 days)
    $lifetime = 90 * 24 * 60 * 60;
    session_set_cookie_params($lifetime);
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak valid']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'warning', 'message' => 'Email dan password wajib diisi!']);
    exit;
}

try {
    // JOIN ke tabel roles menggunakan role_id dan ambil is_password_default
    $stmt = $pdo->prepare("
        SELECT u.*, t.name as tenant_name, p.name as position_name, 
               r.name as role_name, r.display_name as role_display 
        FROM users u 
        LEFT JOIN tenants t ON u.tenant_id = t.id
        LEFT JOIN positions p ON u.position_id = p.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['tenant_name'] = $user['tenant_name'] ?? 'Pusat (Dev)';
        
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['role_display'] = $user['role_display'];
        $_SESSION['position_name'] = $user['position_name'] ?? $user['role_display'];
        
        // Simpan status password default
        $_SESSION['is_password_default'] = $user['is_password_default'];

        // Tentukan Redirect Tujuan
        if ($user['is_password_default'] == 1) {
            $redirect_url = 'change_password';
            $_SESSION['toast_msg'] = "Peringatan Keamanan: Harap ubah kata sandi default Anda.";
            $_SESSION['toast_type'] = "warning";
        } else {
            $redirect_url = 'index';
            $_SESSION['toast_msg'] = "Selamat datang kembali, " . explode(' ', $user['name'])[0] . "!";
            $_SESSION['toast_type'] = "success";
        }

        echo json_encode(['status' => 'success', 'message' => 'Login berhasil! Mengalihkan...', 'redirect' => $redirect_url]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email atau password salah!']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem.']);
}
?>