<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// SET TIMEZONE JAKARTA
date_default_timezone_set('Asia/Jakarta');
$current_time = date('Y-m-d H:i:s');

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
    // 1. Cek duplikasi email
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email sudah terdaftar! Gunakan email lain.']);
        exit;
    }

    $pdo->beginTransaction();

    // 2. Insert Tenant Baru (Perusahaan) dengan status 'pending' & timezone Jakarta
    $stmtTenant = $pdo->prepare("INSERT INTO tenants (name, email, status, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?)");
    $stmtTenant->execute([$company_name, $email, $current_time, $current_time]);
    $tenant_id = $pdo->lastInsertId();

    // 3. Insert Admin User Baru (role_id = 2 untuk Company Admin)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO users (tenant_id, role_id, name, email, password, created_at) VALUES (?, 2, ?, ?, ?, ?)");
    $stmtUser->execute([$tenant_id, $admin_name, $email, $hashed_password, $current_time]);
    $user_id = $pdo->lastInsertId();

    // 4. Insert Default Tenant Settings
    $stmtSettings = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, attendance_method, timezone, created_at, updated_at) VALUES (?, 'geo_face', 'Asia/Jakarta', ?, ?)");
    $stmtSettings->execute([$tenant_id, $current_time, $current_time]);

    $pdo->commit();

    // Simpan data session untuk alur pemilihan paket SaaS
    $_SESSION['pending_tenant_id'] = $tenant_id;
    $_SESSION['pending_company_name'] = $company_name;
    $_SESSION['pending_admin_name'] = $admin_name;
    $_SESSION['pending_email'] = $email;

    echo json_encode([
        'status' => 'success', 
        'message' => 'Pendaftaran berhasil! Mengalihkan ke pemilihan paket...',
        'redirect' => 'pending_tenant'
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error', 
        'message' => 'Kesalahan sistem: ' . $e->getMessage()
    ]);
    exit;
}