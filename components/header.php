<?php
// Ambil data dinamis dari Session atau tangkap dari file induk (seperti profile.php / employee.php)
$header_user_name = $user_name ?? $_SESSION['user_name'] ?? 'User';
$header_user_role = $user_role ?? $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$header_tenant_name = $tenant_name ?? $_SESSION['tenant_name'] ?? 'Sistem Pusat';
$header_user_avatar = $user_avatar ?? $_SESSION['avatar'] ?? null;

// Generator Avatar Dinamis (Cek apakah ada file avatar, jika tidak gunakan DiceBear Pixel-Art)
if (!empty($header_user_avatar)) {
    $header_avatar_url = "assets/img/avatars/" . htmlspecialchars($header_user_avatar);
} else {
    $header_avatar_url = "https://api.dicebear.com/9.x/pixel-art/svg?seed=" . urlencode($header_user_name);
}
?>

<header class="p-5 md:pt-6 md:px-0 flex justify-between items-center bg-surface md:bg-transparent sticky top-0 z-10 md:static">
    
    <!-- Kiri: Hamburger Menu (Desktop) / Avatar (Mobile) -->
    <div class="flex items-center gap-3 md:gap-4">
        <button id="sidebarToggle" class="hidden md:flex w-10 h-10 rounded-xl hover:bg-gray-200 bg-gray-100 items-center justify-center transition-colors text-gray-600 shrink-0">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Avatar Mobile -->
        <img src="<?= $header_avatar_url ?>" alt="Profile" class="md:hidden w-10 h-10 rounded-full shadow-sm shrink-0 bg-gray-50 object-cover">
        
        <div>
            <h1 class="text-base md:text-lg font-semibold text-gray-800 leading-tight">Halo, <?= htmlspecialchars($header_user_name) ?>!</h1>
            <p class="text-[11px] md:text-xs text-gray-500 font-medium mt-0.5">
                <?= htmlspecialchars($header_user_role) ?> <span class="text-gray-300 mx-1">•</span> <span class="text-primary font-semibold"><?= htmlspecialchars($header_tenant_name) ?></span>
            </p>
        </div>
    </div>

    <!-- Kanan: Notifikasi & Avatar (Desktop) -->
    <div class="flex items-center gap-3">
        <!-- Tombol Notifikasi -->
        <button id="notificationBtn" class="w-10 h-10 rounded-full hover:bg-gray-200 bg-gray-100 md:bg-surface flex items-center justify-center relative transition shadow-sm border border-gray-100 shrink-0">
            <i data-lucide="bell" class="w-5 h-5 text-gray-600"></i>
            <span class="absolute top-2 right-2 w-2 h-2 bg-pending rounded-full border border-surface"></span>
        </button>
        
        <!-- Avatar Desktop -->
        <img id="profileDesktopBtn" src="<?= $header_avatar_url ?>" alt="Profile" class="hidden md:block w-10 h-10 rounded-full shadow-sm shrink-0 cursor-pointer hover:opacity-90 transition bg-gray-50 object-cover">
    </div>

</header>

<!-- Script Khusus Komponen Header -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const notifBtn = document.getElementById('notificationBtn');
        if(notifBtn) {
            notifBtn.addEventListener('click', () => {
                console.log('Notifikasi diklik');
            });
        }
    });
</script>