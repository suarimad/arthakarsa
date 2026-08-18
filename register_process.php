<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak valid']);
    exit;
}

$company_name = trim($_POST['company_name'] ?? '');
$admin_name   = trim($_POST['admin_name'] ?? '');
$email        = trim($_POST['email'] ?? '');
$password     = $_POST['password'] ?? '';

if (empty($company_name) || empty($admin_name) || empty($email) || empty($password)) {
    echo json_encode(['status' => 'warning', 'message' => 'Semua kolom wajib diisi!']);
    exit;
}

try {
    // Cek duplikasi email
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email sudah terdaftar! Gunakan email lain.']);
        exit;
    }

    $pdo->beginTransaction();

    $stmtTenant = $pdo->prepare("INSERT INTO tenants (name) VALUES (?) RETURNING id");
    $stmtTenant->execute([$company_name]);
    $tenant_id = $stmtTenant->fetchColumn();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO users (tenant_id, role, name, email, password) VALUES (?, 'admin', ?, ?, ?)");
    $stmtUser->execute([$tenant_id, $admin_name, $email, $hashed_password]);

    $pdo->commit();

    // Set toast untuk halaman login
    $_SESSION['toast_msg'] = "Perusahaan berhasil didaftarkan! Silakan masuk.";
    $_SESSION['toast_type'] = "success";

    echo json_encode(['status' => 'success', 'message' => 'Pendaftaran berhasil! Mengalihkan...']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan sistem: ' . $e->getMessage()]);
}