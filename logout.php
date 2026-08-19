<?php
// Panggil Konfigurasi Global (untuk mendapatkan $base_url)
require_once __DIR__ . '/config/config.php';

// Mulai session jika belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Kosongkan semua data session saat ini
$_SESSION = array();

// 2. Hapus cookie session di browser (Praktik Keamanan Terbaik)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session yang lama
session_destroy();

// 4. Mulai session BARU khusus untuk mengirim pesan Toast ke halaman login
session_start();
$_SESSION['toast_msg']  = "Anda telah berhasil keluar.";
$_SESSION['toast_type'] = "success";

// 5. Arahkan kembali ke halaman login (tanpa ekstensi .php)
header("Location: " . $base_url . "/login");
exit;