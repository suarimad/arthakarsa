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
    header("Location: " . ($base_url ?? '') . "/login");
    exit;
}

// Cek Keamanan: Cegah user beraktivitas jika password masih default
// Eksekusi ini tidak berlaku jika halaman yang sedang diakses adalah "change_password"
$current_page_auth = basename($_SERVER['PHP_SELF'], '.php');
if (isset($_SESSION['is_password_default']) && $_SESSION['is_password_default'] == 1 && $current_page_auth !== 'change_password') {
    $_SESSION['toast_msg'] = "Peringatan Keamanan: Harap ubah kata sandi default Anda sebelum melanjutkan.";
    $_SESSION['toast_type'] = "warning";
    header("Location: " . ($base_url ?? '') . "/change_password");
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