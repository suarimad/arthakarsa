<?php
// Pastikan session sudah berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast_msg'] = "Sesi Anda telah berakhir. Silakan login kembali.";
    $_SESSION['toast_type'] = "warning";
    header("Location: " . ($base_url ?? '') . "/login");
    exit;
}

$current_page_auth = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page_auth == '') $current_page_auth = 'index';

// Cek Keamanan Password Default
if (isset($_SESSION['is_password_default']) && $_SESSION['is_password_default'] == 1 && $current_page_auth !== 'change_password') {
    $_SESSION['toast_msg'] = "Peringatan Keamanan: Harap ubah kata sandi default Anda sebelum melanjutkan.";
    $_SESSION['toast_type'] = "warning";
    header("Location: " . ($base_url ?? '') . "/change_password");
    exit;
}

// =====================================================================
// DYNAMIC ROLE-BASED ACCESS CONTROL (RBAC) & MENU BUILDER
// =====================================================================
$user_role_id = $_SESSION['role_id'] ?? 0;

try {
    // Ambil data menu yang aktif dari database (Pastikan variabel $pdo tersedia dari database.php)
    if (isset($pdo)) {
        $stmtMenu = $pdo->query("SELECT * FROM menus WHERE is_active = 1 ORDER BY order_num ASC");
        $global_menus = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $global_menus = [];
    }
} catch (Exception $e) {
    $global_menus = [];
}

$accessible_menus = [];
$is_page_restricted = false;
$has_access = false;

// Proses mapping menu berdasarkan role_id
foreach ($global_menus as $menu) {
    // Ubah string "1,2,3" menjadi array [1, 2, 3]
    $allowed_roles = array_map('trim', explode(',', $menu['allowed_roles']));
    
    // Apakah user punya hak akses ke menu ini? (Superadmin ID = 1 otomatis bypass)
    if (in_array((string)$user_role_id, $allowed_roles) || $user_role_id == 1) {
        if ($menu['is_show_on_nav'] == 1) {
            $accessible_menus[$menu['category']][] = $menu;
        }
        if ($menu['url'] === $current_page_auth) {
            $has_access = true;
        }
    }
    
    // Jika halaman yang sedang diakses ada di tabel menus
    if ($menu['url'] === $current_page_auth) {
        $is_page_restricted = true;
    }
}

// GLOBAL GUARD: Tolak jika halaman diregistrasikan di DB, tapi role_id user tidak diizinkan.
if ($is_page_restricted && !$has_access && $user_role_id != 1) {
    $_SESSION['toast_msg'] = "Akses Ditolak: Anda tidak memiliki izin untuk halaman tersebut.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

/**
 * FUNGSI PENGECEKAN MANUAL (FALLBACK)
 * Tetap disediakan untuk memeriksa role yang tidak terikat dengan menu URL tertentu.
 */
function requireRole($allowed_role_ids = []) {
    $user_role_id = $_SESSION['role_id'] ?? 0;
    
    // Superadmin bypass
    if ($user_role_id == 1) return true; 

    if (!in_array($user_role_id, $allowed_role_ids)) {
        $_SESSION['toast_msg'] = "Akses Ditolak: Anda tidak memiliki izin untuk operasi tersebut.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($base_url ?? '') . "/index");
        exit;
    }
}
?>