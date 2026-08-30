<?php 
$current_page_auth = basename($_SERVER['PHP_SELF'], '.php'); 
if ($current_page_auth == '') $current_page_auth = 'index';

$bn_role_label = $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Karyawan');

// PEMETAAN WARNA UNTUK BOTTOM SHEET GRID ICONS
// Memastikan Tailwind JIT compiler tetap bekerja
$tailwind_colors = [
    'violet'  => ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'hover_bg' => 'group-hover:bg-violet-100', 'hover_text' => 'group-hover:text-violet-600'],
    'orange'  => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'hover_bg' => 'group-hover:bg-orange-100', 'hover_text' => 'group-hover:text-orange-600'],
    'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'hover_bg' => 'group-hover:bg-emerald-100', 'hover_text' => 'group-hover:text-emerald-600'],
    'blue'    => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'hover_bg' => 'group-hover:bg-blue-100', 'hover_text' => 'group-hover:text-blue-600'],
    'teal'    => ['bg' => 'bg-teal-50', 'text' => 'text-teal-600', 'hover_bg' => 'group-hover:bg-teal-100', 'hover_text' => 'group-hover:text-teal-600'],
    'indigo'  => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'hover_bg' => 'group-hover:bg-indigo-100', 'hover_text' => 'group-hover:text-indigo-600'],
    'amber'   => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'hover_bg' => 'group-hover:bg-amber-100', 'hover_text' => 'group-hover:text-amber-600'],
    'rose'    => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'hover_bg' => 'group-hover:bg-rose-100', 'hover_text' => 'group-hover:text-rose-600'],
    'slate'   => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'hover_bg' => 'group-hover:bg-slate-200', 'hover_text' => 'group-hover:text-slate-800'],
    'pink'    => ['bg' => 'bg-pink-50', 'text' => 'text-pink-600', 'hover_bg' => 'group-hover:bg-pink-100', 'hover_text' => 'group-hover:text-pink-600'],
    'red'     => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'hover_bg' => 'group-hover:bg-red-100', 'hover_text' => 'group-hover:text-red-600'],
    'cyan'    => ['bg' => 'bg-cyan-50', 'text' => 'text-cyan-600', 'hover_bg' => 'group-hover:bg-cyan-100', 'hover_text' => 'group-hover:text-cyan-600'],
    'gray'    => ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'hover_bg' => 'group-hover:bg-gray-100', 'hover_text' => 'group-hover:text-gray-700']
];

$menu_color_map = [
    'leave' => 'violet', 'approval_leave' => 'violet', 'hr_leave' => 'violet',
    'overtime' => 'orange', 'approval_overtime' => 'orange', 'mgr_timesheet' => 'orange', 'position' => 'orange',
    'reimbursement' => 'emerald', 'approval_reimbursement' => 'emerald', 'hr_cash_advance' => 'emerald',
    'attendance' => 'blue', 'setting_company' => 'blue', 'mgr_approval' => 'blue', 'hr_attendance' => 'blue',
    'payslip' => 'teal', 'payslips' => 'teal', 'hr_payslip' => 'teal',
    'calendar' => 'indigo', 'roles' => 'indigo',
    'review' => 'amber', 'mgr_review' => 'amber',
    'project' => 'rose', 'mgr_project' => 'rose',
    'setting_app' => 'slate', 'tenant_db' => 'slate',
    'department' => 'pink',
    'location' => 'red',
    'shift' => 'cyan'
];
?>

<!-- 1. NAVIGATION BAR (Hanya Tampil di Mobile) -->
<nav id="mobileBottomNav" class="md:hidden fixed bottom-0 left-0 right-0 w-full bg-surface border-t border-gray-200 pb-safe shadow-[0_-4px_20px_rgba(0,0,0,0.08)] z-[90]">
    <div class="flex justify-between items-center px-5 py-4 relative">
        <a href="<?= $base_url ?? '' ?>/index" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page_auth == 'index') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="home" class="w-5 h-5"></i><span class="text-[9px] <?= ($current_page_auth == 'index') ? 'font-semibold' : 'font-medium' ?>">Beranda</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/employee" class="flex flex-col items-center gap-1.5 w-12 <?= ($current_page_auth == 'employee') ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="users" class="w-5 h-5"></i><span class="text-[9px] <?= ($current_page_auth == 'employee') ? 'font-semibold' : 'font-medium' ?>">Karyawan</span>
        </a>
        
        <!-- FAB Tengah -->
        <div class="w-12 flex justify-center">
            <button onclick="openMainMenu()" class="absolute -top-5 flex items-center justify-center w-14 h-14 bg-primary text-surface rounded-full shadow-lg hover:bg-accent transition transform hover:scale-105 border-[5px] border-surface outline-none">
                <i data-lucide="layout-grid" class="w-6 h-6"></i>
            </button>
            <span class="text-[9px] font-medium text-gray-400 mt-7 pt-0.5">Menu</span>
        </div>
        
        <a href="#" id="requestBtn" class="flex flex-col items-center gap-1.5 w-12 text-gray-400 hover:text-primary transition">
            <i data-lucide="file-plus" class="w-5 h-5"></i><span class="text-[9px] font-medium">Pengajuan</span>
        </a>
        <a href="<?= $base_url ?? '' ?>/profile" class="flex flex-col items-center gap-1.5 w-12 <?= (in_array($current_page_auth, ['profile', 'profile_edit', 'change_password'])) ? 'text-primary' : 'text-gray-400 hover:text-primary transition' ?>">
            <i data-lucide="user" class="w-5 h-5"></i><span class="text-[9px] <?= (in_array($current_page_auth, ['profile', 'profile_edit', 'change_password'])) ? 'font-semibold' : 'font-medium' ?>">Profil</span>
        </a>
    </div>
</nav>

<!-- 2. BOTTOM SHEET REQUEST -->
<div id="requestBottomSheet" class="fixed inset-0 hidden" style="z-index: 999999;">
    <div id="requestOverlay" onclick="closeRequestSheet()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div id="requestSheet" class="absolute bottom-0 left-0 right-0 bg-surface rounded-t-3xl shadow-2xl transform translate-y-full transition-transform duration-300 ease-in-out pb-safe pointer-events-auto">
        <div class="p-5 pb-8 md:max-w-md md:mx-auto relative">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-5 cursor-pointer" onclick="closeRequestSheet()"></div>
            <h3 class="text-base font-bold text-gray-800 mb-6 text-center">Buat Pengajuan</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="<?= $base_url ?? '' ?>/leave_add/cuti" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-primary/10 text-primary rounded-full flex items-center justify-center group-hover:bg-primary group-hover:text-surface transition shadow-sm"><i data-lucide="calendar-off" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Cuti</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/sakit" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-pending/10 text-pending rounded-full flex items-center justify-center group-hover:bg-pending group-hover:text-surface transition shadow-sm"><i data-lucide="stethoscope" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Sakit</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/leave_add/izin" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition shadow-sm"><i data-lucide="user-minus" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Izin</span>
                </a>
                <a href="<?= $base_url ?? '' ?>/overtime_add" class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition group">
                    <div class="w-12 h-12 bg-success/10 text-success rounded-full flex items-center justify-center group-hover:bg-success group-hover:text-surface transition shadow-sm"><i data-lucide="clock-4" class="w-5 h-5"></i></div>
                    <span class="text-xs font-medium text-gray-600">Lembur</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 3. BOTTOM SHEET MENU UTAMA (DYNAMIC DB RENDER) -->
<div id="mainMenuModal" class="fixed inset-0 hidden" style="z-index: 999999;">
    <div id="mainMenuOverlay" onclick="closeMainMenu()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="mainMenuCard" class="bg-surface w-full md:max-w-2xl lg:max-w-3xl rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[85vh] md:max-h-[80vh]">
            
            <div class="pt-3 pb-3 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeMainMenu()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>

            <div class="hidden md:flex justify-between items-center px-6 py-4 border-b border-gray-100 shrink-0">
                <div><h3 class="text-lg font-bold text-gray-800">Menu Utama</h3><p class="text-[11px] text-gray-500">Pilih fitur yang ingin Anda akses</p></div>
                <button onclick="closeMainMenu()" class="text-gray-400 hover:text-failed hover:bg-failed/10 transition p-2 rounded-full"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="overflow-y-auto p-5 pb-12 md:p-8 md:pb-8 space-y-8 flex-1 overscroll-y-contain">
                
                <!-- SECTION MAIN -->
                <?php if (!empty($accessible_menus['main'])): ?>
                    <div>
                        <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider px-1">Menu Utama</h4>
                        <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                            <?php foreach($accessible_menus['main'] as $m): 
                                $color_key = $menu_color_map[$m['url']] ?? 'gray';
                                $c = $tailwind_colors[$color_key];
                            ?>
                                <a href="<?= $base_url ?? '' ?>/<?= htmlspecialchars($m['url']) ?>" class="flex flex-col items-center gap-1.5 group">
                                    <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl <?= $c['bg'] ?> <?= $c['text'] ?> flex items-center justify-center <?= $c['hover_bg'] ?> transition-all shadow-sm group-hover:scale-110">
                                        <i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-6 h-6 md:w-7 md:h-7"></i>
                                    </div>
                                    <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight <?= $c['hover_text'] ?> transition-colors">
                                        <?= str_replace(' ', '<br>', htmlspecialchars($m['title'])) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SECTION SUPPORT -->
                <?php if (!empty($accessible_menus['support'])): ?>
                    <div>
                        <h4 class="text-[11px] md:text-xs font-semibold text-gray-500 mb-4 uppercase tracking-wider px-1">Menu <?= htmlspecialchars($bn_role_label) ?></h4>
                        <div class="grid grid-cols-4 md:grid-cols-6 gap-y-7 gap-x-2 md:gap-x-6">
                            <?php foreach($accessible_menus['support'] as $m): 
                                $color_key = $menu_color_map[$m['url']] ?? 'gray';
                                $c = $tailwind_colors[$color_key];
                            ?>
                                <a href="<?= $base_url ?? '' ?>/<?= htmlspecialchars($m['url']) ?>" class="flex flex-col items-center gap-1.5 group">
                                    <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl <?= $c['bg'] ?> <?= $c['text'] ?> flex items-center justify-center <?= $c['hover_bg'] ?> transition-all shadow-sm group-hover:scale-110">
                                        <i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-6 h-6 md:w-7 md:h-7"></i>
                                    </div>
                                    <span class="text-[11px] md:text-[13px] font-semibold text-gray-700 text-center leading-tight <?= $c['hover_text'] ?> transition-colors">
                                        <?= str_replace(' ', '<br>', htmlspecialchars($m['title'])) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const elementsToMove = [
            document.getElementById('mobileBottomNav'),
            document.getElementById('mainMenuModal'),
            document.getElementById('requestBottomSheet')
        ];
        elementsToMove.forEach(el => {
            if (el && el.parentNode !== document.body) {
                document.body.appendChild(el);
            }
        });

        const requestBtn = document.getElementById('requestBtn');
        if (requestBtn) requestBtn.addEventListener('click', (e) => { e.preventDefault(); openRequestSheet(); });
    });

    window.openRequestSheet = function() {
        const b = document.getElementById('requestBottomSheet'), o = document.getElementById('requestOverlay'), s = document.getElementById('requestSheet');
        if(!b) return; b.classList.remove('hidden');
        setTimeout(() => { if(o) o.classList.remove('opacity-0'); if(s) s.classList.remove('translate-y-full'); }, 10);
    }
    window.closeRequestSheet = function() {
        const b = document.getElementById('requestBottomSheet'), o = document.getElementById('requestOverlay'), s = document.getElementById('requestSheet');
        if(!b) return; if(o) o.classList.add('opacity-0'); if(s) s.classList.add('translate-y-full');
        setTimeout(() => { b.classList.add('hidden'); }, 300);
    }

    window.openMainMenu = function() {
        const m = document.getElementById('mainMenuModal'), o = document.getElementById('mainMenuOverlay'), c = document.getElementById('mainMenuCard');
        if(!m) return; m.classList.remove('hidden'); if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => { if(o) o.classList.remove('opacity-0'); if(c) { c.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0'); c.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100'); } }, 10);
    }
    window.closeMainMenu = function() {
        const m = document.getElementById('mainMenuModal'), o = document.getElementById('mainMenuOverlay'), c = document.getElementById('mainMenuCard');
        if(!m) return; if(o) o.classList.add('opacity-0'); if(c) { c.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); c.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); }
        setTimeout(() => { m.classList.add('hidden'); }, 300);
    }
</script>