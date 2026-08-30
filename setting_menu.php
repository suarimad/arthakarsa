<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// ==============================================================================
// LOGIKA HAK AKSES (GUARD HALAMAN)
// ==============================================================================
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

// Guard Halaman: Hanya Superadmin (1) dan Admin (2) yang bisa akses
if (!in_array($role_id, [1, 2]) && !in_array($role_name_session, ['superadmin', 'admin'])) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke pengaturan menu.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

// ==============================================================================
// PENANGANAN AJAX (CRUD & SORTING MENU)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_REQUEST['ajax_action'];

        // GET DATA UNTUK EDIT
        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data menu tidak ditemukan.']);
            }
            exit;
        }

        // DELETE MENU
        if ($action === 'delete') {
            $id = $_POST['id'];
            $pdo->prepare("DELETE FROM menus WHERE id = ?")->execute([$id]);
            
            $_SESSION['toast_msg'] = "Menu berhasil dihapus.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // UPDATE URUTAN (SORTING)
        if ($action === 'update_order') {
            $order_data = json_decode($_POST['order_data'], true);
            
            if (is_array($order_data)) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE menus SET order_num = ? WHERE id = ?");
                foreach ($order_data as $item) {
                    $stmt->execute([(int)$item['order_num'], (int)$item['id']]);
                }
                $pdo->commit();
                
                $_SESSION['toast_msg'] = "Urutan menu berhasil diperbarui.";
                $_SESSION['toast_type'] = "success";
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data urutan tidak valid.']);
            }
            exit;
        }

        // ADD & EDIT MENU
        if (in_array($action, ['add', 'edit'])) {
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $icon = trim($_POST['icon'] ?? 'circle');
            $category = trim($_POST['category'] ?? 'main');
            $allowed_roles = trim($_POST['allowed_roles'] ?? '1');
            $order_num = (int)($_POST['order_num'] ?? 0);
            $is_show_on_nav = (int)($_POST['is_show_on_nav'] ?? 1);
            $is_active = (int)($_POST['is_active'] ?? 1);

            if (empty($title) || empty($url)) {
                throw new Exception("Judul dan URL Menu wajib diisi.");
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO menus (title, url, icon, category, allowed_roles, order_num, is_show_on_nav, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $url, $icon, $category, $allowed_roles, $order_num, $is_show_on_nav, $is_active]);
                $_SESSION['toast_msg'] = "Menu baru berhasil ditambahkan.";
            } else {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("UPDATE menus SET title = ?, url = ?, icon = ?, category = ?, allowed_roles = ?, order_num = ?, is_show_on_nav = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$title, $url, $icon, $category, $allowed_roles, $order_num, $is_show_on_nav, $is_active, $id]);
                $_SESSION['toast_msg'] = "Data menu berhasil diperbarui.";
            }

            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
// ==============================================================================

// MENGAMBIL DATA MENU (DIPISAH PER KATEGORI UNTUK TABS)
$stmtMain = $pdo->query("SELECT * FROM menus WHERE category = 'main' ORDER BY order_num ASC");
$menus_main = $stmtMain->fetchAll(PDO::FETCH_ASSOC);

$stmtSupport = $pdo->query("SELECT * FROM menus WHERE category = 'support' ORDER BY order_num ASC");
$menus_support = $stmtSupport->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';

// LOAD JQUERY, DATATABLES, & SORTABLEJS
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>';
?>

<style>
    table.dataTable.no-footer { border-bottom: none !important; }
    table.dataTable thead th { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; background-color: #f9fafb; background-image: none !important; }
    table.dataTable tbody td { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; vertical-align: middle; }
    .dataTables_wrapper .bottom { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1rem; background-color: #ffffff; border-top: 1px solid #f3f4f6; }
    .dataTables_info { font-size: 0.65rem !important; color: #6b7280 !important; padding-top: 0 !important; }
    .dataTables_paginate { display: flex; gap: 0.25rem; padding-top: 0 !important; }
    .dataTables_paginate .paginate_button { padding: 0.25rem 0.6rem !important; border-radius: 0.5rem !important; font-size: 0.7rem !important; font-weight: 600 !important; background: white !important; border: 1px solid #e5e7eb !important; color: #4b5563 !important; cursor: pointer; margin-left: 0.25rem !important; }
    .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f9fafb !important; color: #111827 !important; }
    .dataTables_paginate .paginate_button.current { background: #ea3800 !important; color: white !important; border-color: #ea3800 !important; }
    
    div[id*="toast"], div[class*="toast"], #toast-container { z-index: 999999 !important; }
    
    /* Style saat baris ditarik (Drag) */
    .sortable-ghost { opacity: 0.4; background-color: #f3f4f6; }
    .sorting-active tbody tr { cursor: grab; }
    .sorting-active tbody tr:active { cursor: grabbing; }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Pengaturan Menu</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Atur struktur menu, icon, dan hak akses peran.</p>
                </div>
                <div class="flex gap-2">
                    <!-- Tombol Ubah Sortir -->
                    <button id="btnToggleSort" onclick="toggleSortMode()" class="bg-gray-100 text-gray-600 px-3 md:px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-gray-200 transition shadow-sm active:scale-95">
                        <i data-lucide="arrow-up-down" class="w-4 h-4"></i> <span class="hidden md:inline">Ubah Sortir</span>
                    </button>
                    <!-- Tombol Tambah Menu -->
                    <button onclick="openFormModal('add')" class="bg-primary/10 text-primary px-3 md:px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm active:scale-95">
                        <i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden md:inline">Tambah Menu</span>
                    </button>
                </div>
            </div>

            <!-- TABS HEADER -->
            <div class="flex gap-6 border-b border-gray-200 px-1 overflow-x-auto whitespace-nowrap" style="scrollbar-width: none;">
                <button id="tabBtn-main" onclick="switchTab('main')" class="pb-3 border-b-2 font-bold text-sm transition-colors border-primary text-primary">Menu Utama (Main)</button>
                <button id="tabBtn-support" onclick="switchTab('support')" class="pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800">Menu Support (Role-Based)</button>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari nama menu, url, atau kategori..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            
                            <!-- TAB CONTENT: MAIN MENU -->
                            <div id="tabContent-main" class="overflow-x-auto">
                                <table id="menuTableMain" class="w-full text-left whitespace-nowrap table-sorting-container">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Urutan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Menu</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">URL / Endpoint</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Role Akses</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right aksi-col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($menus_main as $m): ?>
                                            <tr data-id="<?= $m['id'] ?>" class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center sorting-handle">
                                                    <span class="order-number text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto transition-colors group-hover:bg-gray-200"><?= $m['order_num'] ?></span>
                                                    <i data-lucide="grip-vertical" class="drag-icon hidden w-5 h-5 mx-auto text-gray-400"></i>
                                                </td>
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center shrink-0 border border-gray-200"><i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-4 h-4"></i></div>
                                                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($m['title']) ?></span>
                                                    </div>
                                                </td>
                                                <td><span class="text-[11px] font-mono text-primary bg-primary/5 px-2 py-1 rounded-md border border-primary/10">/<?= htmlspecialchars($m['url']) ?></span></td>
                                                <td><span class="text-[10px] font-medium text-gray-500 truncate max-w-[150px] inline-block" title="<?= htmlspecialchars($m['allowed_roles']) ?>">Role ID: <?= htmlspecialchars($m['allowed_roles']) ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($m['is_active'] && $m['is_show_on_nav']): ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-success/10 text-success uppercase">Aktif</span>
                                                    <?php elseif ($m['is_active'] && !$m['is_show_on_nav']): ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-pending/10 text-pending uppercase">Hidden</span>
                                                    <?php else: ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-failed/10 text-failed uppercase">Nonaktif</span><?php endif; ?>
                                                </td>
                                                <td class="text-right aksi-col">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openFormModal('edit', <?= $m['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Edit Menu"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
                                                        <button onclick="deleteMenu(<?= $m['id'] ?>)" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Hapus Menu"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- TAB CONTENT: SUPPORT MENU -->
                            <div id="tabContent-support" class="hidden overflow-x-auto">
                                <table id="menuTableSupport" class="w-full text-left whitespace-nowrap table-sorting-container">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Urutan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Menu</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">URL / Endpoint</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Role Akses</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right aksi-col">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($menus_support as $m): ?>
                                            <tr data-id="<?= $m['id'] ?>" class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center sorting-handle">
                                                    <span class="order-number text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto transition-colors group-hover:bg-gray-200"><?= $m['order_num'] ?></span>
                                                    <i data-lucide="grip-vertical" class="drag-icon hidden w-5 h-5 mx-auto text-gray-400"></i>
                                                </td>
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-xl bg-gray-100 text-gray-600 flex items-center justify-center shrink-0 border border-gray-200"><i data-lucide="<?= htmlspecialchars($m['icon']) ?>" class="w-4 h-4"></i></div>
                                                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($m['title']) ?></span>
                                                    </div>
                                                </td>
                                                <td><span class="text-[11px] font-mono text-primary bg-primary/5 px-2 py-1 rounded-md border border-primary/10">/<?= htmlspecialchars($m['url']) ?></span></td>
                                                <td><span class="text-[10px] font-medium text-gray-500 truncate max-w-[150px] inline-block" title="<?= htmlspecialchars($m['allowed_roles']) ?>">Role ID: <?= htmlspecialchars($m['allowed_roles']) ?></span></td>
                                                <td class="text-center">
                                                    <?php if ($m['is_active'] && $m['is_show_on_nav']): ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-success/10 text-success uppercase">Aktif</span>
                                                    <?php elseif ($m['is_active'] && !$m['is_show_on_nav']): ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-pending/10 text-pending uppercase">Hidden</span>
                                                    <?php else: ?><span class="text-[9px] font-bold px-2 py-1 rounded-md bg-failed/10 text-failed uppercase">Nonaktif</span><?php endif; ?>
                                                </td>
                                                <td class="text-right aksi-col">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openFormModal('edit', <?= $m['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Edit Menu"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
                                                        <button onclick="deleteMenu(<?= $m['id'] ?>)" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Hapus Menu"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>
</div>

<!-- ================= HYBRID MODAL/BOTTOM SHEET (FORM ADD/EDIT) ================= -->
<div id="formModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="formOverlay" onclick="closeFormModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="formCard" class="bg-surface w-full md:max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeFormModal()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <button onclick="closeFormModal()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            
            <div class="px-6 pb-8 md:p-8 overflow-y-auto w-full">
                <div class="text-center mb-6 mt-2 md:mt-0">
                    <h3 id="modalTitle" class="text-base md:text-lg font-bold text-gray-800">Tambah Menu</h3>
                    <p class="text-[11px] text-gray-500 mt-1">Konfigurasi endpoint dan akses navigasi.</p>
                </div>
                
                <form id="menuForm">
                    <input type="hidden" name="ajax_action" id="formAction" value="add">
                    <input type="hidden" name="id" id="menuId">
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Label Menu</label>
                                <input type="text" name="title" id="menuTitle" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Endpoint URL</label>
                                <input type="text" name="url" id="menuUrl" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Kategori</label>
                                <select name="category" id="menuCategory" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary appearance-none">
                                    <option value="main">Menu Utama (Main)</option>
                                    <option value="support">Menu Role (Support)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Urutan (Sort)</label>
                                <input type="number" name="order_num" id="menuOrder" value="0" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary text-center">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Icon (Lucide)</label>
                                <input type="text" name="icon" id="menuIcon" placeholder="home, users, file..." required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase text-failed">Akses Role ID</label>
                                <input type="text" name="allowed_roles" id="menuRoles" placeholder="1,2,3,4,5,6" required class="w-full px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-xs font-bold text-failed focus:outline-none focus:border-failed font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Tampil di Navigasi?</label>
                                <select name="is_show_on_nav" id="menuShow" class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-800 focus:outline-none">
                                    <option value="1">Ya, Tampilkan</option>
                                    <option value="0">Tidak (Hidden)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Status Menu</label>
                                <select name="is_active" id="menuActive" class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-800 focus:outline-none">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <button type="button" onclick="closeFormModal()" class="w-1/3 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Batal</button>
                        <button type="submit" id="btnSubmitForm" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary/90 transition shadow-sm flex justify-center items-center gap-2">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI DELETE ================= -->
<div id="confirmModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="confirmOverlay" onclick="closeConfirm()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="confirmCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh] p-6">
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeConfirm()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <div id="confirmContent"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    let tableMain, tableSupport;
    let sortableMain, sortableSupport;
    let isSorting = false;

    $(document).ready(function() {
        const dtConfig = {
            "dom": 't<"bottom"ip>', 
            "pageLength": 15,
            "ordering": false,
            "language": {
                "emptyTable": "Belum ada data menu.",
                "info": "Menampilkan _START_ s/d _END_",
                "paginate": { "previous": "Sebelumnya", "next": "Selanjutnya" }
            }
        };

        tableMain = $('#menuTableMain').DataTable(dtConfig);
        tableSupport = $('#menuTableSupport').DataTable(dtConfig);

        $('#dtSearchInput').on('keyup', function() { 
            tableMain.search(this.value).draw(); 
            tableSupport.search(this.value).draw(); 
        });

        tableMain.on('draw', function() { lucide.createIcons(); });
        tableSupport.on('draw', function() { lucide.createIcons(); });
    });

    // ==========================================
    // LOGIKA TABS
    // ==========================================
    function switchTab(tabName) {
        // Reset styles
        document.getElementById('tabBtn-main').className = "pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800";
        document.getElementById('tabBtn-support').className = "pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800";
        
        document.getElementById('tabContent-main').classList.add('hidden');
        document.getElementById('tabContent-support').classList.add('hidden');

        // Active state
        document.getElementById('tabBtn-' + tabName).className = "pb-3 border-b-2 font-bold text-sm transition-colors border-primary text-primary";
        document.getElementById('tabContent-' + tabName).classList.remove('hidden');
        
        // Adjust width & re-apply icons if sorting is active
        if(tabName === 'main' && tableMain) {
            tableMain.columns.adjust().draw(false);
            if(isSorting) enableVisualSortMode('#menuTableMain');
        }
        if(tabName === 'support' && tableSupport) {
            tableSupport.columns.adjust().draw(false);
            if(isSorting) enableVisualSortMode('#menuTableSupport');
        }
    }

    // ==========================================
    // LOGIKA SORTING (DRAG & DROP)
    // ==========================================
    function toggleSortMode() {
        const btn = document.getElementById('btnToggleSort');
        
        if (!isSorting) {
            isSorting = true;
            btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> <span class="hidden md:inline">Simpan Urutan</span>';
            btn.classList.replace('bg-gray-100', 'bg-success/10');
            btn.classList.replace('text-gray-600', 'text-success');
            lucide.createIcons();

            // Disable pagination for sorting (show all rows)
            tableMain.page.len(-1).draw(false);
            tableSupport.page.len(-1).draw(false);

            // Give visual cue to tables
            enableVisualSortMode('#menuTableMain');
            enableVisualSortMode('#menuTableSupport');

            // Initialize SortableJS
            setTimeout(() => {
                const elMain = document.querySelector('#menuTableMain tbody');
                sortableMain = Sortable.create(elMain, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    handle: '.sorting-handle'
                });

                const elSupport = document.querySelector('#menuTableSupport tbody');
                sortableSupport = Sortable.create(elSupport, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    handle: '.sorting-handle'
                });

                window.showToast('Mode sortir aktif. Tarik icon (drag) untuk mengubah urutan.', 'info');
            }, 100);

        } else {
            // SIMPAN URUTAN BARU (AJAX)
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> <span class="hidden md:inline">Menyimpan...</span>';
            lucide.createIcons();

            const mainRows = document.querySelectorAll('#menuTableMain tbody tr');
            const supportRows = document.querySelectorAll('#menuTableSupport tbody tr');
            
            let orderData = [];
            let orderIndex = 1;
            mainRows.forEach(row => {
                if(row.dataset.id) { orderData.push({ id: row.dataset.id, order_num: orderIndex++ }); }
            });
            
            orderIndex = 1;
            supportRows.forEach(row => {
                if(row.dataset.id) { orderData.push({ id: row.dataset.id, order_num: orderIndex++ }); }
            });

            const fd = new FormData();
            fd.append('ajax_action', 'update_order');
            fd.append('order_data', JSON.stringify(orderData));

            fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    window.location.reload();
                } else {
                    window.showToast(res.message, 'error');
                    isSorting = false;
                    window.location.reload();
                }
            });
        }
    }

    function enableVisualSortMode(tableId) {
        document.querySelector(tableId).classList.add('sorting-active');
        document.querySelectorAll(`${tableId} .order-number`).forEach(el => el.classList.add('hidden'));
        document.querySelectorAll(`${tableId} .drag-icon`).forEach(el => el.classList.remove('hidden'));
        document.querySelectorAll(`${tableId} .aksi-col`).forEach(el => el.classList.add('opacity-30', 'pointer-events-none'));
    }

    // ==========================================
    // LOGIKA MODAL FORM (ADD/EDIT)
    // ==========================================
    const formModal = document.getElementById('formModal');
    const formOverlay = document.getElementById('formOverlay');
    const formCard = document.getElementById('formCard');
    
    document.body.appendChild(formModal); 

    window.openFormModal = function(mode, id = null) {
        if(isSorting) return; // Cegah edit saat mode sortir

        const form = document.getElementById('menuForm');
        form.reset();
        
        document.getElementById('formAction').value = mode;
        document.getElementById('modalTitle').innerText = mode === 'add' ? 'Tambah Menu Baru' : 'Edit Data Menu';

        if (mode === 'edit' && id) {
            const fd = new FormData();
            fd.append('ajax_action', 'get');
            fd.append('id', id);

            fetch(window.location.href, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        document.getElementById('menuId').value = res.data.id;
                        document.getElementById('menuTitle').value = res.data.title;
                        document.getElementById('menuUrl').value = res.data.url;
                        document.getElementById('menuIcon').value = res.data.icon;
                        document.getElementById('menuCategory').value = res.data.category;
                        document.getElementById('menuRoles').value = res.data.allowed_roles;
                        document.getElementById('menuOrder').value = res.data.order_num;
                        document.getElementById('menuShow').value = res.data.is_show_on_nav;
                        document.getElementById('menuActive').value = res.data.is_active;
                        showFormModal();
                    } else {
                        window.showToast(res.message, 'error');
                    }
                });
        } else {
            showFormModal();
        }
    }

    function showFormModal() {
        formModal.classList.remove('hidden');
        setTimeout(() => {
            formOverlay.classList.remove('opacity-0');
            formCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            formCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    }

    window.closeFormModal = function() {
        formOverlay.classList.add('opacity-0');
        formCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        formCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { formModal.classList.add('hidden'); }, 300);
    }

    document.getElementById('menuForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitForm');
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Menyimpan...';
        lucide.createIcons();
        
        const fd = new FormData(this);
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') { window.location.reload(); } 
                else { window.showToast(res.message, 'error'); btn.disabled = false; btn.innerHTML = 'Simpan'; }
            });
    });

    // ==========================================
    // LOGIKA MODAL CONFIRM DELETE
    // ==========================================
    const confirmModal = document.getElementById('confirmModal');
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmCard = document.getElementById('confirmCard');
    const confirmContent = document.getElementById('confirmContent');
    document.body.appendChild(confirmModal);

    window.deleteMenu = function(id) {
        if(isSorting) return; // Cegah hapus saat mode sortir

        confirmContent.innerHTML = `
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Hapus Menu</h3>
                <p class="text-xs text-gray-500 mt-1">Apakah Anda yakin ingin menghapus menu ini? Navigasi yang berhubungan dengan URL ini mungkin akan hilang.</p>
                
                <div class="flex gap-3 mt-8">
                    <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                    <button onclick="submitDelete(${id})" class="flex-1 py-3 bg-failed hover:bg-failed/90 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">Ya, Hapus</button>
                </div>
            </div>
        `;
        confirmModal.classList.remove('hidden');
        lucide.createIcons();
        setTimeout(() => {
            confirmOverlay.classList.remove('opacity-0');
            confirmCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            confirmCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);
    }

    window.closeConfirm = function() {
        confirmOverlay.classList.add('opacity-0');
        confirmCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        confirmCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { confirmModal.classList.add('hidden'); }, 300);
    }

    window.submitDelete = function(id) {
        const fd = new FormData();
        fd.append('ajax_action', 'delete');
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') { window.location.reload(); } 
            else { window.showToast(res.message, 'error'); }
        });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>