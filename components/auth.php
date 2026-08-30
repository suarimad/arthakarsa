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

$user_tenant_id = $_SESSION['tenant_id'] ?? null;
$user_role_id = $_SESSION['role_id'] ?? 0;

// =====================================================================
// 1. PENGECEKAN STATUS TENANT (PENDING / ACTIVE / SUSPENDED)
// (Dijalankan diawal sebelum pengecekan password default)
// =====================================================================
if ($user_tenant_id && isset($pdo) && $user_role_id != 1) {
    try {
        $stmtTenantAuth = $pdo->prepare("SELECT status, active_until FROM tenants WHERE id = ? LIMIT 1");
        $stmtTenantAuth->execute([$user_tenant_id]);
        $tenantAuthData = $stmtTenantAuth->fetch(PDO::FETCH_ASSOC);

        if ($tenantAuthData) {
            $tenant_status_current = strtolower($tenantAuthData['status'] ?? 'active');
            $tenant_active_until = $tenantAuthData['active_until'] ?? null;
            $now_datetime = date('Y-m-d H:i:s');

            // Jika status active tetapi active_until telah terlewati, ubah status menjadi suspended
            if ($tenant_status_current === 'active' && !empty($tenant_active_until) && $now_datetime > $tenant_active_until) {
                $stmtSuspend = $pdo->prepare("UPDATE tenants SET status = 'suspended', updated_at = ? WHERE id = ?");
                $stmtSuspend->execute([$now_datetime, $user_tenant_id]);
                $tenant_status_current = 'suspended';
            }

            // Arahkan ke pending_tenant jika status pending atau suspended
            if (in_array($tenant_status_current, ['pending', 'suspended'])) {
                if ($current_page_auth !== 'pending_tenant' && $current_page_auth !== 'logout') {
                    // Bersihkan toast lama agar tidak terbawa ke halaman pending_tenant
                    unset($_SESSION['toast_msg'], $_SESSION['toast_type']);
                    header("Location: " . ($base_url ?? '') . "/pending_tenant");
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        // Abaikan error jika kueri gagal
    }
}

// =====================================================================
// 2. CEK KEAMANAN PASSWORD DEFAULT
// (Kecualikan pending_tenant agar tidak memicu toast saat tenant pending/suspended)
// =====================================================================
if (
    isset($_SESSION['is_password_default']) && 
    $_SESSION['is_password_default'] == 1 && 
    !in_array($current_page_auth, ['change_password', 'pending_tenant', 'logout'])
) {
    $_SESSION['toast_msg'] = "Peringatan Keamanan: Harap ubah kata sandi default Anda sebelum melanjutkan.";
    $_SESSION['toast_type'] = "warning";
    header("Location: " . ($base_url ?? '') . "/change_password");
    exit;
}

// =====================================================================
// DYNAMIC ROLE-BASED ACCESS CONTROL (RBAC) & MENU BUILDER
// =====================================================================
try {
    // Ambil data menu yang aktif dari database
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
    $allowed_roles = array_map('trim', explode(',', $menu['allowed_roles']));
    
    if (in_array((string)$user_role_id, $allowed_roles) || $user_role_id == 1) {
        if ($menu['is_show_on_nav'] == 1) {
            $accessible_menus[$menu['category']][] = $menu;
        }
        if ($menu['url'] === $current_page_auth) {
            $has_access = true;
        }
    }
    
    if ($menu['url'] === $current_page_auth) {
        $is_page_restricted = true;
    }
}

// GLOBAL GUARD: Tolak jika halaman diregistrasikan di DB, tapi role_id user tidak diizinkan.
if ($is_page_restricted && !$has_access && $user_role_id != 1 && $current_page_auth !== 'pending_tenant') {
    $_SESSION['toast_msg'] = "Akses Ditolak: Anda tidak memiliki izin untuk halaman tersebut.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

/**
 * FUNGSI PENGECEKAN MANUAL (FALLBACK)
 */
function requireRole($allowed_role_ids = []) {
    $user_role_id = $_SESSION['role_id'] ?? 0;
    
    if ($user_role_id == 1) return true; 

    if (!in_array($user_role_id, $allowed_role_ids)) {
        $_SESSION['toast_msg'] = "Akses Ditolak: Anda tidak memiliki izin untuk operasi tersebut.";
        $_SESSION['toast_type'] = "error";
        header("Location: " . ($base_url ?? '') . "/index");
        exit;
    }
}

/**
 * HELPER NOTIFIKASI MULTI-USER ROLE (FAN-OUT)
 */
if (!function_exists('notifyRoles')) {
    function notifyRoles($pdo, $tenant_id, $target_roles = [], $title, $message, $url, $icon = 'bell', $sender_id = 0) {
        if (empty($target_roles)) return;
        
        $placeholders = implode(',', array_fill(0, count($target_roles), '?'));
        $sql = "SELECT id, tenant_id FROM users WHERE (role_id = 1 OR (tenant_id = ? AND role_id IN ($placeholders))) AND id != ?";
        $params = array_merge([$tenant_id], $target_roles, [$sender_id]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($users)) {
            $insertSql = "INSERT INTO notifications (tenant_id, user_id, title, message, url, icon) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            foreach ($users as $u) {
                $insertStmt->execute([$u['tenant_id'], $u['id'], $title, $message, $url, $icon]);
            }
        }
    }
}

/**
 * HELPER NOTIFIKASI SINGLE USER
 */
if (!function_exists('notifyUser')) {
    function notifyUser($pdo, $tenant_id, $target_user_id, $title, $message, $url, $icon = 'bell') {
        $sql = "INSERT INTO notifications (tenant_id, user_id, title, message, url, icon) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$tenant_id, $target_user_id, $title, $message, $url, $icon]);
    }
}