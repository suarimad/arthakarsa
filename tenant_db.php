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
// PENANGANAN AJAX (CRUD TENANT)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_REQUEST['ajax_action'];

        // GET DATA UNTUK EDIT
        if ($action === 'get') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tenant tidak ditemukan.']);
            }
            exit;
        }

        // DELETE TENANT
        if ($action === 'delete') {
            $id = $_POST['id'];
            
            // Proteksi: Jangan hapus tenant diri sendiri jika sedang aktif di tenant tersebut
            if ($id == $tenant_id) {
                echo json_encode(['status' => 'error', 'message' => 'Tidak dapat menghapus Tenant yang sedang Anda gunakan saat ini.']);
                exit;
            }

            // Hapus Tenant
            $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$id]);
            
            $_SESSION['toast_msg'] = "Data Tenant berhasil dihapus dari sistem.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // ADD & EDIT TENANT DASAR
        if (in_array($action, ['add', 'edit'])) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name)) {
                throw new Exception("Nama Tenant wajib diisi.");
            }

            if ($action === 'add') {
                $pdo->beginTransaction();
                
                // Insert Tenant Baru
                $stmt = $pdo->prepare("INSERT INTO tenants (name, email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $address]);
                $new_tenant_id = $pdo->lastInsertId();
                
                // Otomatis buatkan data pengaturan default di tenant_settings
                $stmtSet = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, attendance_method, timezone) VALUES (?, 'geo_face', 'Asia/Jakarta')");
                $stmtSet->execute([$new_tenant_id]);
                
                $pdo->commit();
                $_SESSION['toast_msg'] = "Tenant baru berhasil dibuat.";
            } else {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("UPDATE tenants SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $address, $id]);
                $_SESSION['toast_msg'] = "Data Tenant berhasil diperbarui.";
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

// MENGAMBIL DATA UNTUK TABS DATATABLES
// 1. Data Tenants
$stmtTenants = $pdo->query("SELECT * FROM tenants ORDER BY id ASC");
$tenants = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);

// 2. Data Tenant Settings
$stmtSettings = $pdo->query("
    SELECT ts.*, t.name as tenant_name 
    FROM tenant_settings ts 
    LEFT JOIN tenants t ON ts.tenant_id = t.id 
    ORDER BY ts.tenant_id ASC
");
$tenant_settings_list = $stmtSettings->fetchAll(PDO::FETCH_ASSOC);

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
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Database Tenant</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola data perusahaan (Tenant) dan konfigurasi sistem.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="openFormModal('add')" class="bg-primary/10 text-primary px-3 md:px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm active:scale-95">
                        <i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden md:inline">Tambah Tenant</span>
                    </button>
                </div>
            </div>

            <!-- TABS HEADER -->
            <div class="flex gap-6 border-b border-gray-200 px-1 overflow-x-auto whitespace-nowrap" style="scrollbar-width: none;">
                <button id="tabBtn-tenants" onclick="switchTab('tenants')" class="pb-3 border-b-2 font-bold text-sm transition-colors border-primary text-primary">Data Tenants</button>
                <button id="tabBtn-settings" onclick="switchTab('settings')" class="pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800">Tenant Settings</button>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari ID, nama tenant, atau pengaturan..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            
                            <!-- TAB CONTENT: TENANTS -->
                            <div id="tabContent-tenants" class="overflow-x-auto">
                                <table id="tableTenants" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-12">ID</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Profil Perusahaan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Kontak</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Alamat</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tenants as $t): 
                                            // LOGIKA MENGAMBIL LOGO DARI TABEL TENANTS (PATH: assets/img/tenants/)
                                            $logo_url = !empty($t['logo']) 
                                                ? ($base_url ?? '') . '/assets/img/tenants/' . htmlspecialchars($t['logo']) 
                                                : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($t['name']);
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center">
                                                    <span class="text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto"><?= $t['id'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center shrink-0 border border-gray-200 overflow-hidden">
                                                            <!-- MENAMPILKAN LOGO / INISIAL -->
                                                            <img src="<?= $logo_url ?>" alt="Logo" class="w-full h-full object-cover">
                                                        </div>
                                                        <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($t['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col gap-0.5">
                                                        <span class="text-[11px] font-semibold text-gray-700"><?= htmlspecialchars($t['email'] ?: '-') ?></span>
                                                        <span class="text-[10px] text-gray-500 font-mono"><?= htmlspecialchars($t['phone'] ?: '-') ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-[10px] font-medium text-gray-500 truncate max-w-[200px] inline-block" title="<?= htmlspecialchars($t['address']) ?>">
                                                        <?= htmlspecialchars($t['address'] ?: '-') ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openFormModal('edit', <?= $t['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Edit Tenant Dasar"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
                                                        <button onclick="deleteTenant(<?= $t['id'] ?>)" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Hapus Tenant"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- TAB CONTENT: TENANT SETTINGS -->
                            <div id="tabContent-settings" class="hidden overflow-x-auto">
                                <table id="tableSettings" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center w-12">T_ID</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Perusahaan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Metode Absen</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Timezone</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Payroll Cutoff</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">BPJS (Kes/TK)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tenant_settings_list as $ts): ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center">
                                                    <span class="text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto"><?= $ts['tenant_id'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($ts['tenant_name'] ?? 'Unknown Tenant') ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-[10px] font-bold text-primary bg-primary/5 px-2 py-1 rounded-md border border-primary/10 uppercase">
                                                        <?= htmlspecialchars($ts['attendance_method']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-[11px] font-medium text-gray-600">
                                                        <?= htmlspecialchars($ts['timezone']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="text-[11px] font-semibold text-gray-700">Tgl <?= $ts['payroll_cutoff_start'] ?> - <?= $ts['payroll_cutoff_end'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="flex items-center justify-center gap-2">
                                                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded"><?= floatval($ts['bpjs_kesehatan_percent']) ?>%</span>
                                                        <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded"><?= floatval($ts['bpjs_ketenagakerjaan_percent']) ?>%</span>
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

<!-- ================= HYBRID MODAL/BOTTOM SHEET (FORM ADD/EDIT TENANT) ================= -->
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
                    <h3 id="modalTitle" class="text-base md:text-lg font-bold text-gray-800">Tambah Tenant</h3>
                    <p class="text-[11px] text-gray-500 mt-1">Isi informasi dasar perusahaan baru.</p>
                </div>
                
                <form id="tenantForm">
                    <input type="hidden" name="ajax_action" id="formAction" value="add">
                    <input type="hidden" name="id" id="tenantId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Nama Perusahaan (Tenant)</label>
                            <input type="text" name="name" id="tenantName" required placeholder="PT. Inovasi Cemerlang" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Email Perusahaan</label>
                                <input type="email" name="email" id="tenantEmail" placeholder="info@perusahaan.com" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Nomor Telepon</label>
                                <input type="text" name="phone" id="tenantPhone" placeholder="021-..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Alamat Lengkap</label>
                            <textarea name="address" id="tenantAddress" rows="3" placeholder="Jl. Sudirman No..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-800 focus:outline-none focus:border-primary"></textarea>
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

    let tableTenants, tableSettings;

    $(document).ready(function() {
        const dtConfig = {
            "dom": 't<"bottom"ip>', 
            "pageLength": 15,
            "ordering": true,
            "language": {
                "emptyTable": "Belum ada data tenant.",
                "info": "Menampilkan _START_ s/d _END_",
                "paginate": { "previous": "Sebelumnya", "next": "Selanjutnya" }
            }
        };

        tableTenants = $('#tableTenants').DataTable(dtConfig);
        tableSettings = $('#tableSettings').DataTable(dtConfig);

        $('#dtSearchInput').on('keyup', function() { 
            tableTenants.search(this.value).draw(); 
            tableSettings.search(this.value).draw(); 
        });

        tableTenants.on('draw', function() { lucide.createIcons(); });
        tableSettings.on('draw', function() { lucide.createIcons(); });
    });

    // ==========================================
    // LOGIKA TABS
    // ==========================================
    function switchTab(tabName) {
        document.getElementById('tabBtn-tenants').className = "pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800";
        document.getElementById('tabBtn-settings').className = "pb-3 border-b-2 font-bold text-sm transition-colors border-transparent text-gray-500 hover:text-gray-800";
        
        document.getElementById('tabContent-tenants').classList.add('hidden');
        document.getElementById('tabContent-settings').classList.add('hidden');

        document.getElementById('tabBtn-' + tabName).className = "pb-3 border-b-2 font-bold text-sm transition-colors border-primary text-primary";
        document.getElementById('tabContent-' + tabName).classList.remove('hidden');
        
        if(tabName === 'tenants' && tableTenants) tableTenants.columns.adjust().draw(false);
        if(tabName === 'settings' && tableSettings) tableSettings.columns.adjust().draw(false);
    }

    // ==========================================
    // LOGIKA MODAL FORM (ADD/EDIT TENANT)
    // ==========================================
    const formModal = document.getElementById('formModal');
    const formOverlay = document.getElementById('formOverlay');
    const formCard = document.getElementById('formCard');
    
    document.body.appendChild(formModal); 

    window.openFormModal = function(mode, id = null) {
        const form = document.getElementById('tenantForm');
        form.reset();
        
        document.getElementById('formAction').value = mode;
        document.getElementById('modalTitle').innerText = mode === 'add' ? 'Tambah Tenant Baru' : 'Edit Data Tenant';

        if (mode === 'edit' && id) {
            const fd = new FormData();
            fd.append('ajax_action', 'get');
            fd.append('id', id);

            fetch(window.location.href, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        document.getElementById('tenantId').value = res.data.id;
                        document.getElementById('tenantName').value = res.data.name;
                        document.getElementById('tenantEmail').value = res.data.email;
                        document.getElementById('tenantPhone').value = res.data.phone;
                        document.getElementById('tenantAddress').value = res.data.address;
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

    document.getElementById('tenantForm').addEventListener('submit', function(e) {
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
    // LOGIKA MODAL CONFIRM DELETE TENANT
    // ==========================================
    const confirmModal = document.getElementById('confirmModal');
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmCard = document.getElementById('confirmCard');
    const confirmContent = document.getElementById('confirmContent');
    document.body.appendChild(confirmModal);

    window.deleteTenant = function(id) {
        confirmContent.innerHTML = `
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Hapus Tenant?</h3>
                <p class="text-xs text-failed font-bold mt-2">PERINGATAN KERAS!</p>
                <p class="text-[11px] text-gray-500 mt-1 px-4">
                    Tindakan ini akan menghapus **SELURUH DATA** (User, Pengaturan, Cuti, Lembur) yang berelasi dengan Tenant ini secara permanen. Tindakan ini tidak dapat dibatalkan.
                </p>
                
                <div class="flex gap-3 mt-8">
                    <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                    <button onclick="submitDelete(${id})" class="flex-1 py-3 bg-failed hover:bg-failed/90 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">Ya, Hapus Permanen</button>
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