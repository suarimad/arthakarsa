<?php
// ==============================================================================
// PENANGANAN AJAX NOTIFIKASI (MARK AS READ)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    if ($_REQUEST['ajax_action'] === 'mark_all_notif_read') {
        header('Content-Type: application/json');
        if (isset($pdo) && isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND tenant_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($_REQUEST['ajax_action'] === 'mark_single_notif_read') {
        header('Content-Type: application/json');
        $notif_id = (int)($_POST['notif_id'] ?? 0);
        if (isset($pdo) && isset($_SESSION['user_id']) && isset($_SESSION['tenant_id']) && $notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND tenant_id = ?");
            $stmt->execute([$notif_id, $_SESSION['user_id'], $_SESSION['tenant_id']]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Helper Format Waktu Notifikasi
if (!function_exists('header_time_ago')) {
    function header_time_ago($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return floor($diff / 60) . 'm lalu';
        if ($diff < 86400) return floor($diff / 3600) . 'j lalu';
        if ($diff < 604800) return floor($diff / 86400) . 'h lalu';
        return date('d M Y', $time);
    }
}

// Ambil data dinamis dari Session / Variabel Induk
$header_user_name = $user_name ?? $_SESSION['user_name'] ?? 'User';
$header_user_role = $user_role ?? $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$header_tenant_name = $tenant_name ?? $_SESSION['tenant_name'] ?? 'Perusahaan';

$header_role_id = $_SESSION['role_id'] ?? null;
$header_role_name_session = strtolower($_SESSION['role'] ?? '');

// LOGIKA OTOMATIS: Ambil avatar dari DB/Session di header
$header_user_avatar = $user_avatar ?? $_SESSION['avatar'] ?? null;

if (empty($header_user_avatar) && isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $stmtHeader = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmtHeader->execute([$_SESSION['user_id']]);
        $headerUser = $stmtHeader->fetch(PDO::FETCH_ASSOC);
        if ($headerUser && !empty($headerUser['avatar'])) {
            $header_user_avatar = $headerUser['avatar'];
            $_SESSION['avatar'] = $headerUser['avatar'];
        }
    } catch (Exception $e) {
        // Abaikan error jika query gagal
    }
}

// Generator Avatar Dinamis
if (!empty($header_user_avatar)) {
    $header_avatar_url = ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($header_user_avatar);
} else {
    $header_avatar_url = "https://api.dicebear.com/10.x/shadows/svg?seed=" . urlencode($header_user_name);
}

// ==============================================================================
// QUERY DATA NOTIFIKASI DINAMIS UNTUK USER
// ==============================================================================
$unread_count = 0;
$header_notifications = [];

if (isset($pdo) && isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
    try {
        // Count Notifikasi Belum Dibaca
        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND tenant_id = ? AND is_read = 0");
        $stmtUnread->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
        $unread_count = (int)$stmtUnread->fetchColumn();

        // Ambil 5 Notifikasi Terbaru
        $stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmtNotif->execute([$_SESSION['user_id'], $_SESSION['tenant_id']]);
        $header_notifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Abaikan error jika tabel notifikasi belum ada
    }
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

    <!-- Kanan: Debugger, Notifikasi & Avatar (Desktop) -->
    <div class="flex items-center gap-3">
        
        <!-- Tombol Debugger Khusus Superadmin -->
        <?php if ($header_role_id == 1 || $header_role_name_session === 'superadmin'): ?>
        <button onclick="openGlobalDebugger()" class="w-10 h-10 rounded-full hover:bg-gray-200 bg-gray-100 md:bg-surface flex items-center justify-center relative transition shadow-sm border border-gray-100 shrink-0 outline-none">
            <i data-lucide="bug" class="w-5 h-5 <?= !empty($debug_error) ? 'text-red-500 animate-pulse' : 'text-gray-600' ?>"></i>
            <?php if (!empty($debug_error)): ?>
                <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border border-surface"></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>

        <!-- Tombol & Dropdown Notifikasi -->
        <div class="relative">
            <button id="notificationBtn" class="w-10 h-10 rounded-full hover:bg-gray-200 bg-gray-100 md:bg-surface flex items-center justify-center relative transition shadow-sm border border-gray-100 shrink-0 outline-none">
                <i data-lucide="bell" class="w-5 h-5 text-gray-600"></i>
                <?php if ($unread_count > 0): ?>
                    <span id="notifBadgeCount" class="absolute -top-1 -right-1 bg-primary text-white text-[9px] font-bold rounded-full w-5 h-5 flex items-center justify-center border-2 border-surface shadow-sm">
                        <?= $unread_count > 99 ? '99+' : $unread_count ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- DROPDOWN PANEL NOTIFIKASI -->
            <div id="notificationDropdown" class="absolute right-0 top-12 w-80 sm:w-96 bg-surface rounded-3xl shadow-2xl border border-gray-100 hidden z-50 overflow-hidden transform opacity-0 scale-95 transition-all duration-200">
                <!-- Header Dropdown -->
                <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-surface">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-gray-800">Notifikasi</h3>
                        <?php if ($unread_count > 0): ?>
                            <span class="bg-primary/10 text-primary text-[10px] font-extrabold px-2 py-0.5 rounded-full"><?= $unread_count ?> Baru</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unread_count > 0): ?>
                        <button onclick="markAllNotifRead()" class="text-[10px] font-bold text-primary hover:underline outline-none">Tandai semua dibaca</button>
                    <?php endif; ?>
                </div>

                <!-- List Content -->
                <div class="max-h-80 overflow-y-auto divide-y divide-gray-50" style="scrollbar-width: thin;">
                    <?php if (empty($header_notifications)): ?>
                        <div class="p-6 text-center text-gray-400">
                            <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                            <p class="text-xs font-medium">Belum ada notifikasi</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($header_notifications as $notif): 
                            $is_unread = (int)$notif['is_read'] === 0;
                            $target_url = !empty($notif['url']) ? ($base_url ?? '') . '/' . htmlspecialchars($notif['url']) : '#';
                            $notif_icon = htmlspecialchars($notif['icon'] ?? 'bell');
                        ?>
                            <div onclick="clickNotifItem(<?= $notif['id'] ?>, '<?= $target_url ?>')" class="p-3.5 flex items-start gap-3 hover:bg-gray-50 transition cursor-pointer relative group <?= $is_unread ? 'bg-primary/[0.03]' : '' ?>">
                                <div class="w-9 h-9 rounded-2xl <?= $is_unread ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500' ?> flex items-center justify-center shrink-0 mt-0.5">
                                    <i data-lucide="<?= $notif_icon ?>" class="w-4 h-4"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($notif['title']) ?></h4>
                                        <span class="text-[9px] font-medium text-gray-400 shrink-0"><?= header_time_ago($notif['created_at']) ?></span>
                                    </div>
                                    <p class="text-[11px] text-gray-500 font-medium line-clamp-2 mt-0.5 leading-snug"><?= htmlspecialchars($notif['message']) ?></p>
                                </div>
                                <?php if ($is_unread): ?>
                                    <span class="w-2 h-2 rounded-full bg-primary shrink-0 self-center"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer Dropdown -->
                <a href="<?= ($base_url ?? '') ?>/notification" class="block p-3 bg-gray-50 hover:bg-gray-100 transition text-center text-xs font-bold text-gray-600 border-t border-gray-100">
                    Lihat Semua
                </a>
            </div>
        </div>
        
        <!-- Avatar Desktop -->
        <a href="<?= ($base_url ?? '') ?>/profile" class="hidden md:block w-10 h-10 rounded-full shadow-sm shrink-0 cursor-pointer hover:opacity-90 transition bg-gray-50 overflow-hidden">
            <img id="profileDesktopBtn" src="<?= $header_avatar_url ?>" alt="Profile" class="w-full h-full object-cover">
        </a>
    </div>

</header>

<!-- Panggil Modal Global Debugger -->
<?php require_once __DIR__ . '/debugger.php'; ?>

<!-- Script Khusus Komponen Header -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const notifBtn = document.getElementById('notificationBtn');
        const notifDropdown = document.getElementById('notificationDropdown');

        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isHidden = notifDropdown.classList.contains('hidden');
                
                if (isHidden) {
                    notifDropdown.classList.remove('hidden');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    setTimeout(() => {
                        notifDropdown.classList.remove('opacity-0', 'scale-95');
                        notifDropdown.classList.add('opacity-100', 'scale-100');
                    }, 10);
                } else {
                    closeNotifDropdown();
                }
            });

            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    closeNotifDropdown();
                }
            });
        }

        function closeNotifDropdown() {
            if (!notifDropdown) return;
            notifDropdown.classList.remove('opacity-100', 'scale-100');
            notifDropdown.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                notifDropdown.classList.add('hidden');
            }, 200);
        }
    });

    // Tandai Semua Notifikasi Dibaca via AJAX
    function markAllNotifRead() {
        const fd = new FormData();
        fd.append('ajax_action', 'mark_all_notif_read');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    window.location.reload();
                }
            })
            .catch(() => {
                window.location.reload();
            });
    }

    // Klik Notifikasi Satuan: Tandai Dibaca lalu Redirect ke URL
    function clickNotifItem(id, url) {
        const fd = new FormData();
        fd.append('ajax_action', 'mark_single_notif_read');
        fd.append('notif_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(() => {
                if (url && url !== '#') {
                    window.location.href = url;
                } else {
                    window.location.reload();
                }
            })
            .catch(() => {
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });
    }
</script>