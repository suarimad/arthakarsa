<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// ==============================================================================
// LOGIKA HAK AKSES (GUARD HALAMAN SUPERADMIN)
// ==============================================================================
$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

$role_id = $_SESSION['role_id'] ?? null;

// Guard Halaman: HANYA Superadmin (role_id = 1) yang bisa akses
if ((string)$role_id !== '1') {
    $_SESSION['toast_msg'] = "Akses Ditolak: Halaman ini khusus untuk Superadmin.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/index");
    exit;
}

// ==============================================================================
// PENANGANAN AJAX (CRUD ROLE)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_REQUEST['ajax_action'];

        // GET DATA UNTUK EDIT
        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data role tidak ditemukan.']);
            }
            exit;
        }

        // DELETE ROLE
        if ($action === 'delete') {
            $id = $_POST['id'];
            
            // Proteksi 1: Jangan hapus Superadmin
            if ($id == 1) {
                echo json_encode(['status' => 'error', 'message' => 'Role Superadmin adalah role sistem utama dan tidak dapat dihapus.']);
                exit;
            }

            // Proteksi 2: Cek apakah masih ada user yang memakai role ini
            $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $stmtCek->execute([$id]);
            if ($stmtCek->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Role ini sedang digunakan oleh satu atau lebih pengguna. Ubah role mereka terlebih dahulu.']);
                exit;
            }

            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
            
            $_SESSION['toast_msg'] = "Role berhasil dihapus.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // ADD & EDIT ROLE
        if (in_array($action, ['add', 'edit'])) {
            $role_name = trim($_POST['role_name'] ?? '');
            $role_display = trim($_POST['role_display'] ?? '');

            if (empty($role_name) || empty($role_display)) {
                throw new Exception("Nama Sistem dan Nama Display wajib diisi.");
            }

            // Pastikan format role_name aman (huruf kecil tanpa spasi)
            $role_name = strtolower(str_replace(' ', '_', $role_name));

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO roles (role_name, role_display) VALUES (?, ?)");
                $stmt->execute([$role_name, $role_display]);
                $_SESSION['toast_msg'] = "Role baru berhasil ditambahkan.";
            } else {
                $id = $_POST['id'];
                
                // Mencegah modifikasi superadmin system name
                if ($id == 1) {
                    $stmt = $pdo->prepare("UPDATE roles SET role_display = ? WHERE id = ?");
                    $stmt->execute([$role_display, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE roles SET role_name = ?, role_display = ? WHERE id = ?");
                    $stmt->execute([$role_name, $role_display, $id]);
                }
                
                $_SESSION['toast_msg'] = "Data Role berhasil diperbarui.";
            }

            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
// ==============================================================================

// MENGAMBIL DATA ROLES
$stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY id ASC");
$roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';

// LOAD JQUERY & DATATABLES
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';
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
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Role Akses</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola tipe hak akses pengguna (Role Based Access Control).</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openFormModal('add')" class="bg-primary/10 text-primary px-3 md:px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm active:scale-95">
                        <i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden md:inline">Tambah Role</span>
                    </button>
                </div>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari ID atau nama role..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            <div class="overflow-x-auto">
                                <table id="tableRoles" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-12">ID</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Nama Role (Sistem)</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Nama Display (Tampilan UI)</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($roles as $r): ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center">
                                                    <span class="text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto"><?= $r['id'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-[11px] font-mono font-bold text-primary bg-primary/5 px-2 py-1 rounded border border-primary/10">
                                                        <?= htmlspecialchars($r['role_name']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <i data-lucide="shield" class="w-4 h-4 text-gray-400"></i>
                                                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($r['role_display']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openFormModal('edit', <?= $r['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Edit Role"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
                                                        <?php if ($r['id'] != 1): // Superadmin tidak boleh dihapus ?>
                                                        <button onclick="deleteRole(<?= $r['id'] ?>)" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Hapus Role"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                                        <?php endif; ?>
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

<!-- ================= HYBRID MODAL/BOTTOM SHEET (FORM ADD/EDIT ROLE) ================= -->
<div id="formModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="formOverlay" onclick="closeFormModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="formCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeFormModal()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            
            <button onclick="closeFormModal()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            
            <div class="px-6 pb-8 md:p-8 overflow-y-auto w-full">
                <div class="text-center mb-6 mt-2 md:mt-0">
                    <h3 id="modalTitle" class="text-base md:text-lg font-bold text-gray-800">Tambah Role Baru</h3>
                    <p class="text-[11px] text-gray-500 mt-1">Konfigurasi nama identifier dan display role.</p>
                </div>
                
                <form id="roleForm">
                    <input type="hidden" name="ajax_action" id="formAction" value="add">
                    <input type="hidden" name="id" id="roleId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Role Name (Sistem)</label>
                            <input type="text" name="role_name" id="roleName" required placeholder="manager" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-bold text-primary focus:outline-none focus:border-primary">
                            <p class="text-[9px] text-gray-500 mt-1">Gunakan huruf kecil tanpa spasi. Digunakan oleh sistem database.</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Role Display (Tampilan UI)</label>
                            <input type="text" name="role_display" id="roleDisplay" required placeholder="Manager Divisi" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            <p class="text-[9px] text-gray-500 mt-1">Nama role yang akan dilihat oleh pengguna di tampilan aplikasi.</p>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-100 flex gap-3">
                        <button type="button" onclick="closeFormModal()" class="w-1/3 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Batal</button>
                        <button type="submit" id="btnSubmitForm" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary/90 transition shadow-sm flex justify-center items-center gap-2">Simpan Role</button>
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

    let tableRoles;

    $(document).ready(function() {
        tableRoles = $('#tableRoles').DataTable({
            "dom": 't<"bottom"ip>', 
            "pageLength": 15,
            "ordering": true,
            "language": {
                "emptyTable": "Belum ada data role.",
                "info": "Menampilkan _START_ s/d _END_",
                "paginate": { "previous": "Sebelumnya", "next": "Selanjutnya" }
            }
        });

        $('#dtSearchInput').on('keyup', function() { 
            tableRoles.search(this.value).draw(); 
        });

        tableRoles.on('draw', function() { lucide.createIcons(); });
    });

    // ==========================================
    // LOGIKA MODAL FORM (ADD/EDIT ROLE)
    // ==========================================
    const formModal = document.getElementById('formModal');
    const formOverlay = document.getElementById('formOverlay');
    const formCard = document.getElementById('formCard');
    
    document.body.appendChild(formModal); 

    window.openFormModal = function(mode, id = null) {
        const form = document.getElementById('roleForm');
        form.reset();
        
        document.getElementById('formAction').value = mode;
        document.getElementById('modalTitle').innerText = mode === 'add' ? 'Tambah Role Baru' : 'Edit Data Role';
        
        // Membuka/Menutup proteksi edit sistem name untuk superadmin
        const roleNameInput = document.getElementById('roleName');
        roleNameInput.disabled = false;
        roleNameInput.classList.remove('bg-gray-200', 'cursor-not-allowed', 'text-gray-500');

        if (mode === 'edit' && id) {
            const fd = new FormData();
            fd.append('ajax_action', 'get');
            fd.append('id', id);

            fetch(window.location.href, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        document.getElementById('roleId').value = res.data.id;
                        roleNameInput.value = res.data.role_name;
                        document.getElementById('roleDisplay').value = res.data.role_display;
                        
                        // Kunci field nama sistem jika role ID = 1 (Superadmin)
                        if (res.data.id == 1) {
                            roleNameInput.disabled = true;
                            roleNameInput.classList.add('bg-gray-200', 'cursor-not-allowed', 'text-gray-500');
                        }
                        
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

    document.getElementById('roleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Lepas disabled sementara jika ada, agar datanya ikut ter-submit (hanya untuk POST parsing, meski di backend diproteksi)
        document.getElementById('roleName').disabled = false;
        
        const btn = document.getElementById('btnSubmitForm');
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Menyimpan...';
        lucide.createIcons();
        
        const fd = new FormData(this);
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') { window.location.reload(); } 
                else { window.showToast(res.message, 'error'); btn.disabled = false; btn.innerHTML = 'Simpan Role'; }
            });
    });

    // ==========================================
    // LOGIKA MODAL CONFIRM DELETE ROLE
    // ==========================================
    const confirmModal = document.getElementById('confirmModal');
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmCard = document.getElementById('confirmCard');
    const confirmContent = document.getElementById('confirmContent');
    document.body.appendChild(confirmModal);

    window.deleteRole = function(id) {
        confirmContent.innerHTML = `
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Hapus Role?</h3>
                <p class="text-[11px] text-gray-500 mt-1 px-4">
                    Menghapus Role akan gagal jika masih ada pengguna/karyawan yang menggunakan Role ini. Pastikan Anda telah mengubah Role karyawan terkait terlebih dahulu.
                </p>
                
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
            else { window.showToast(res.message, 'error'); closeConfirm(); }
        });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>