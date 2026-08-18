<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
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
    $stmt = $pdo->prepare("
        SELECT u.*, t.name as tenant_name, p.name as position_name 
        FROM users u 
        LEFT JOIN tenants t ON u.tenant_id = t.id
        LEFT JOIN positions p ON u.position_id = p.id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['tenant_name'] = $user['tenant_name'] ?? 'Pusat (Dev)';
        $_SESSION['position_name'] = $user['position_name'] ?? ucfirst($user['role']);

        echo json_encode(['status' => 'success', 'message' => 'Login berhasil! Mengalihkan...']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Email atau password salah!']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem.']);
}