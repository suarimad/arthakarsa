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
?>