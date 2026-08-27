<?php
// Ambil data dinamis dari Session / Variabel Induk
$header_user_name = $user_name ?? $_SESSION['user_name'] ?? 'User';
$header_user_role = $user_role ?? $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$header_tenant_name = $tenant_name ?? $_SESSION['tenant_name'] ?? 'Perusahaan';

// LOGIKA OTOMATIS: Ambil avatar dari DB/Session di header agar selalu konsisten di SEMUA halaman
$header_user_avatar = $user_avatar ?? $_SESSION['avatar'] ?? null;

if (empty($header_user_avatar) && isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmtHeader = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmtHeader->execute([$_SESSION['user_id']]);
        $headerUser = $stmtHeader->fetch(PDO::FETCH_ASSOC);
        if ($headerUser && !empty($headerUser['avatar'])) {
            $header_user_avatar = $headerUser['avatar'];
            $_SESSION['avatar'] = $headerUser['avatar']; // Simpan ke session untuk mempercepat load berikutnya
        }
    } catch (Exception $e) {
        // Abaikan error jika query gagal
    }
}

// Generator Avatar Dinamis (PERBAIKAN iOS PWA: Tambahkan base_url agar gambar selalu dimuat dengan path absolut)
if (!empty($header_user_avatar)) {
    $header_avatar_url = ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($header_user_avatar);
} else {
    $header_avatar_url = "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($header_user_name);
}
?>

<header class="p-5 md:pt-6 md:px-0 flex justify-between items-center bg-surface md:bg-transparent sticky top-0 z-10 md:static">
    
    <!-- Kiri: Hamburger Menu (Desktop) / Avatar (Mobile) -->
    <div class="flex items-center gap-3 md:gap-4">
        <button id="sidebarToggle" class="hidden md:flex w-10 h-10 rounded-xl hover:bg-gray-200 bg-gray-100 items-center justify-center transition-colors text-gray-600 shrink-0">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Avatar Mobile -->
        <a href="<?= ($base_url ?? '') ?>/profile" class="md:hidden w-10 h-10 rounded-full shadow-sm shrink-0 bg-gray-50 overflow-hidden block">
            <img src="<?= $header_avatar_url ?>" alt="Profile" class="w-full h-full object-cover">
        </a>
        
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
        <a href="<?= ($base_url ?? '') ?>/profile" class="hidden md:block w-10 h-10 rounded-full shadow-sm shrink-0 cursor-pointer hover:opacity-90 transition bg-gray-50 overflow-hidden">
            <img id="profileDesktopBtn" src="<?= $header_avatar_url ?>" alt="Profile" class="w-full h-full object-cover">
        </a>
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