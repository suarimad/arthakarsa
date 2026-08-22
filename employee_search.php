<?php
// Panggil Konfigurasi Global & Database
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Keamanan: Hanya untuk user yang sudah login
if (!isset($_SESSION['user_id'])) {
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$q = $_GET['q'] ?? '';

// PENYESUAIAN: Menambahkan JOIN ke tabel roles
$sql = "
    SELECT u.id, u.name, u.email, 
           r.name as role_name, r.display_name as role_display, 
           p.name as position_name 
    FROM users u 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.tenant_id = ? AND u.id != ?
";
$params = [$tenant_id, $user_id];

if (!empty($q)) {
    // LIKE agar support MySQL
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR p.name LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$sql .= " ORDER BY u.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$theme_color = str_replace('#', '', $app_settings['theme_color'] ?? 'ea3800');

if(empty($employees)) {
    echo '
    <div class="col-span-full bg-surface border border-dashed border-gray-200 rounded-2xl p-8 text-center">
        <div class="w-12 h-12 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
            <i data-lucide="users" class="w-6 h-6"></i>
        </div>
        <h4 class="text-sm font-bold text-gray-800">Tidak ada hasil</h4>
        <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">Pencarian tidak menemukan karyawan yang cocok.</p>
    </div>';
    exit;
}

// Render Hasil
foreach($employees as $emp) {
    $name = htmlspecialchars($emp['name']);
    $email = htmlspecialchars($emp['email']);
    
    // PENYESUAIAN: Membaca role_display
    $position = htmlspecialchars($emp['position_name'] ?? $emp['role_display'] ?? ucfirst($emp['role_name'] ?? 'Employee'));
    
    $avatar = "https://ui-avatars.com/api/?name=".urlencode($name)."&background={$theme_color}&color=fff&rounded=true";
    
    echo '
    <div class="bg-surface border border-gray-100 rounded-xl p-4 shadow-sm flex items-center gap-3.5 transition hover:border-gray-200 group cursor-pointer relative z-0">
        <img src="'.$avatar.'" alt="Profile" class="w-12 h-12 md:w-14 md:h-14 rounded-full shadow-sm shrink-0 group-hover:scale-105 transition-transform">
        
        <div class="flex-1 min-w-0">
            <h4 class="text-sm font-bold text-gray-800 truncate group-hover:text-primary transition-colors">'.$name.'</h4>
            <p class="text-[11px] text-gray-500 font-medium truncate mt-0.5">'.$position.'</p>
            <p class="text-[10px] text-gray-400 truncate mt-1.5 flex items-center gap-1">
                <i data-lucide="mail" class="w-3 h-3"></i> '.$email.'
            </p>
        </div>
        
        <a href="mailto:'.$email.'" class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-gray-50 text-gray-400 flex items-center justify-center hover:text-primary hover:bg-primary/10 transition shrink-0" title="Kirim Email">
            <i data-lucide="message-square" class="w-4 h-4 md:w-4.5 md:h-4.5"></i>
        </a>
    </div>';
}
?>