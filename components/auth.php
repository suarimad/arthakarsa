<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    // Set Toast Session untuk ditampilkan di login.php
    $_SESSION['toast_msg'] = "Sesi Anda telah berakhir. Silakan login kembali.";
    $_SESSION['toast_type'] = "warning"; // warning, error, success
    
    // Redirect ke halaman login
    header("Location: " . $base_url . "/login.php");
    exit;
}

/**
 * FUNGSI PENGECEKAN HAK AKSES (ROLE BASED ACCESS CONTROL)
 * Gunakan fungsi ini di awal halaman yang butuh proteksi.
 * Contoh: requireRole(['admin', 'hr']);
 */
function requireRole($allowed_roles = []) {
    $user_role = $_SESSION['role'] ?? '';
    
    // Superadmin punya akses "Bypass" ke semua fitur
    if ($user_role === 'superadmin') {
        return true; 
    }

    // Jika role user saat ini tidak ada di dalam array yang diizinkan
    if (!in_array($user_role, $allowed_roles)) {
        // Set notifikasi error dan lempar kembali ke halaman utama
        $_SESSION['toast_msg'] = "Akses Ditolak: Anda tidak memiliki izin untuk membuka halaman tersebut.";
        $_SESSION['toast_type'] = "error";
        
        header("Location: index");
        exit;
    }
}
?>