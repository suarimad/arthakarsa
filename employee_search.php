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

// PENYESUAIAN: Menambahkan kolom uuid, avatar, whatsapp, department, dan filter deleted_at
$sql = "
    SELECT u.id, u.uuid, u.name, u.email, u.whatsapp, u.avatar, 
           r.name as role_name, r.display_name as role_display, 
           p.name as position_name, d.name as department_name 
    FROM users u 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.tenant_id = ? AND u.id != ? AND u.deleted_at IS NULL
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

// Render Hasil Pencarian (Bentuk Card sama persis dengan default employee.php)
foreach($employees as $emp) {
    $emp_id = $emp['id'];
    $emp_uuid = htmlspecialchars($emp['uuid']);
    $emp_name = htmlspecialchars($emp['name']);
    $emp_email = htmlspecialchars($emp['email']);
    $emp_wa = htmlspecialchars($emp['whatsapp'] ?? '');
    
    // Fallback jabatan & departemen
    $emp_position = htmlspecialchars($emp['position_name'] ?? $emp['role_display'] ?? ucfirst($emp['role_name'] ?? 'Employee'));
    $emp_department = htmlspecialchars($emp['department_name'] ?? 'Belum ada departemen');
    
    // Logika Avatar PWA iOS (Absolute Path)
    $emp_avatar = !empty($emp['avatar']) 
        ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($emp['avatar']) 
        : "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($emp['name']);
    
    echo '
    <div class="bg-surface border border-gray-100 rounded-xl p-4 shadow-sm flex items-center gap-3.5 transition hover:border-gray-200 hover:shadow-md cursor-pointer relative z-0 group"
         onclick="openEmployeeDetail(this)"
         data-id="' . $emp_id . '"
         data-uuid="' . $emp_uuid . '"
         data-name="' . $emp_name . '"
         data-email="' . $emp_email . '"
         data-whatsapp="' . $emp_wa . '"
         data-avatar="' . $emp_avatar . '"
         data-position="' . $emp_position . '"
         data-department="' . $emp_department . '"
    >
        <div class="w-12 h-12 md:w-14 md:h-14 rounded-full border-[2.5px] border-failed p-0.5 relative bg-white shrink-0 group-hover:scale-105 transition-transform">
            <img src="' . $emp_avatar . '" alt="Profile" class="w-full h-full rounded-full object-cover">
        </div>
        
        <div class="flex-1 min-w-0">
            <h4 class="text-sm font-bold text-gray-800 truncate group-hover:text-primary transition-colors">' . $emp_name . '</h4>
            <p class="text-[11px] text-gray-500 font-medium truncate mt-0.5">' . $emp_position . '</p>
            <span class="inline-block mt-1.5 px-2 py-0.5 bg-gray-50 border border-gray-100 text-gray-400 text-[9px] font-semibold rounded-md truncate max-w-full">
                ' . $emp_department . '
            </span>
        </div>
        
        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 group-hover:text-primary transition-colors"></i>
    </div>';
}
?>