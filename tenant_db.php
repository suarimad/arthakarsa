<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

// SET TIMEZONE JAKARTA
date_default_timezone_set('Asia/Jakarta');
$current_time = date('Y-m-d H:i:s');

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
// PENANGANAN AJAX (CRUD & APPROVAL TENANT)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    try {
        $action = $_REQUEST['ajax_action'];

        // GET DATA UNTUK EDIT / VIEW
        if ($action === 'get' || $action === 'view') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("
                SELECT t.*, a.name as approver_name 
                FROM tenants t 
                LEFT JOIN users a ON t.approved_by = a.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tenant tidak ditemukan.']);
            }
            exit;
        }

        // APPROVAL TENANT (SETUJUI)
        if ($action === 'approve') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$t) {
                echo json_encode(['status' => 'error', 'message' => 'Data tenant tidak ditemukan.']);
                exit;
            }

            $dur = strtolower($t['duration'] ?? '');
            $pkg = strtolower($t['package_type'] ?? 'demo');

            // Hitung active_until dari waktu saat ini
            if ($pkg === 'demo' || $dur === '7_days' || empty($dur)) {
                $calc_active = date('Y-m-d H:i:s', strtotime('+7 days'));
            } else if ($dur === '6_months') {
                $calc_active = date('Y-m-d H:i:s', strtotime('+6 months'));
            } else if ($dur === '1_year') {
                $calc_active = date('Y-m-d H:i:s', strtotime('+1 year'));
            } else if ($dur === '5_years') {
                $calc_active = date('Y-m-d H:i:s', strtotime('+5 years'));
            } else {
                $calc_active = date('Y-m-d H:i:s', strtotime('+1 month'));
            }

            $stmtUp = $pdo->prepare("
                UPDATE tenants 
                SET status = 'active', approved_by = ?, active_until = ?, updated_at = ? 
                WHERE id = ?
            ");
            $stmtUp->execute([$user_id, $calc_active, $current_time, $id]);

            $_SESSION['toast_msg'] = "Tenant " . htmlspecialchars($t['name']) . " berhasil disetujui & aktif hingga " . date('d M Y H:i', strtotime($calc_active)) . " WIB.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // REJECT / SUSPEND TENANT
        if ($action === 'reject') {
            $id = $_POST['id'];
            $stmtUp = $pdo->prepare("
                UPDATE tenants 
                SET status = 'suspended', approved_by = ?, updated_at = ? 
                WHERE id = ?
            ");
            $stmtUp->execute([$user_id, $current_time, $id]);

            $_SESSION['toast_msg'] = "Pengajuan Tenant berhasil ditolak / ditangguhkan.";
            $_SESSION['toast_type'] = "success";
            echo json_encode(['status' => 'success']);
            exit;
        }

        // DELETE TENANT
        if ($action === 'delete') {
            $id = $_POST['id'];
            if ($id == $tenant_id) {
                echo json_encode(['status' => 'error', 'message' => 'Tidak dapat menghapus Tenant yang sedang Anda gunakan saat ini.']);
                exit;
            }
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
            $status_input = $_POST['status'] ?? 'pending';
            $total_users_input = (int)($_POST['total_users'] ?? 0);
            $package_type_input = $_POST['package_type'] ?? 'demo';
            $duration_input = $_POST['duration'] ?? '6_months';
            $total_price_input = (float)($_POST['total_price'] ?? 0);

            if (empty($name)) throw new Exception("Nama Tenant wajib diisi.");

            if ($action === 'add') {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (name, email, phone, address, status, total_users, package_type, duration, total_price, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $email, $phone, $address, $status_input, $total_users_input, $package_type_input, $duration_input, $total_price_input, $current_time, $current_time]);
                $new_tenant_id = $pdo->lastInsertId();
                
                $stmtSet = $pdo->prepare("INSERT INTO tenant_settings (tenant_id, attendance_method, timezone, created_at, updated_at) VALUES (?, 'geo_face', 'Asia/Jakarta', ?, ?)");
                $stmtSet->execute([$new_tenant_id, $current_time, $current_time]);
                
                $pdo->commit();
                $_SESSION['toast_msg'] = "Tenant baru berhasil dibuat.";
            } else {
                $id = $_POST['id'];
                $stmt = $pdo->prepare("
                    UPDATE tenants 
                    SET name = ?, email = ?, phone = ?, address = ?, status = ?, total_users = ?, package_type = ?, duration = ?, total_price = ?, updated_at = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $phone, $address, $status_input, $total_users_input, $package_type_input, $duration_input, $total_price_input, $current_time, $id]);
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
$stmtTenants = $pdo->query("
    SELECT t.*, a.name as approver_name 
    FROM tenants t 
    LEFT JOIN users a ON t.approved_by = a.id 
    ORDER BY t.id DESC
");
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
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola data perusahaan (Tenant) dan verifikasi langganan SaaS.</p>
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
                <input type="text" id="dtSearchInput" placeholder="Cari ID, nama tenant, paket, atau status..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
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
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Perusahaan & Paket</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Kontak</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Status & Masa Aktif</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tenants as $t): 
                                            $logo_url = !empty($t['logo']) 
                                                ? ($base_url ?? '') . '/assets/img/tenants/' . htmlspecialchars($t['logo']) 
                                                : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($t['name']);

                                            $st_val = strtolower($t['status'] ?? 'pending');
                                            $badge_bg = 'bg-pending/10'; $badge_text = 'text-pending'; $badge_label = 'Menunggu';
                                            if ($st_val === 'active') { $badge_bg = 'bg-success/10'; $badge_text = 'text-success'; $badge_label = 'Aktif'; }
                                            if ($st_val === 'suspended') { $badge_bg = 'bg-failed/10'; $badge_text = 'text-failed'; $badge_label = 'Ditangguhkan'; }
                                            if ($st_val === 'inactive') { $badge_bg = 'bg-gray-100'; $badge_text = 'text-gray-500'; $badge_label = 'Nonaktif'; }

                                            $active_until_str = !empty($t['active_until']) ? date('d M Y H:i', strtotime($t['active_until'])) . " WIB" : 'Belum Ditentukan';
                                            
                                            $dur_raw = $t['duration'] ?? '';
                                            $dur_text = '7 Hari';
                                            if ($dur_raw === '6_months') $dur_text = '6 Bulan';
                                            elseif ($dur_raw === '1_year') $dur_text = '1 Tahun';
                                            elseif ($dur_raw === '5_years') $dur_text = '5 Tahun';

                                            $total_price_str = "Rp " . number_format((float)($t['total_price'] ?? 0), 0, ',', '.');
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <td class="text-center">
                                                    <span class="text-xs font-black text-gray-400 bg-gray-100 w-8 h-8 rounded-lg flex items-center justify-center mx-auto"><?= $t['id'] ?></span>
                                                </td>
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center shrink-0 border border-gray-200 overflow-hidden">
                                                            <img src="<?= $logo_url ?>" alt="Logo" class="w-full h-full object-cover">
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($t['name']) ?></span>
                                                            <span class="text-[10px] text-gray-500 font-medium">
                                                                <span class="capitalize font-semibold text-primary"><?= htmlspecialchars($t['package_type'] ?? 'Demo') ?></span> • <?= (int)$t['total_users'] ?> Karyawan • <?= $dur_text ?>
                                                            </span>
                                                            <span class="text-[10px] text-emerald-600 font-bold mt-0.5"><?= $total_price_str ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col gap-0.5">
                                                        <span class="text-[11px] font-semibold text-gray-700"><?= htmlspecialchars($t['email'] ?: '-') ?></span>
                                                        <span class="text-[10px] text-gray-500 font-mono"><?= htmlspecialchars($t['phone'] ?: '-') ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col gap-1 items-start">
                                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-md <?= $badge_bg ?> <?= $badge_text ?>"><?= $badge_label ?></span>
                                                        <span class="text-[10px] text-gray-500 font-medium"><i data-lucide="calendar" class="w-3 h-3 inline-block -mt-0.5 mr-0.5"></i> s/d <?= $active_until_str ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openViewModal(<?= $t['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Detail / Approval"><i data-lucide="eye" class="w-3.5 h-3.5"></i></button>
                                                        <button onclick="openFormModal('edit', <?= $t['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Edit Data Tenant"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></button>
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

<!-- ================= HYBRID MODAL/BOTTOM SHEET (VIEW DETAIL & APPROVAL) ================= -->
<div id="viewModal" class="fixed inset-0 hidden" style="z-index: 99997;">
    <div id="viewOverlay" onclick="closeViewModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="viewCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeViewModal()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <button onclick="closeViewModal()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div id="viewContent" class="px-6 pb-8 md:p-8 overflow-y-auto"></div>
        </div>
    </div>
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
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Nomor WhatsApp</label>
                                <input type="text" name="phone" id="tenantPhone" placeholder="08123456..." class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Status</label>
                                <select name="status" id="tenantStatus" class="w-full px-3 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Tipe Paket</label>
                                <select name="package_type" id="tenantPackageType" class="w-full px-3 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                                    <option value="demo">Demo</option>
                                    <option value="premium">Premium</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Karyawan</label>
                                <input type="number" name="total_users" id="tenantTotalUsers" min="1" placeholder="10" class="w-full px-3 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Durasi</label>
                                <select name="duration" id="tenantDuration" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
                                    <option value="7_days">7 Hari (Demo)</option>
                                    <option value="6_months">6 Bulan</option>
                                    <option value="1_year">1 Tahun</option>
                                    <option value="5_years">5 Tahun</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Total Biaya (Rp)</label>
                                <input type="number" name="total_price" id="tenantTotalPrice" min="0" placeholder="0" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:outline-none focus:border-primary">
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
    // LOGIKA MODAL VIEW / APPROVAL TENANT
    // ==========================================
    const viewModal = document.getElementById('viewModal');
    const viewOverlay = document.getElementById('viewOverlay');
    const viewCard = document.getElementById('viewCard');
    const viewContent = document.getElementById('viewContent');
    document.body.appendChild(viewModal);

    window.openViewModal = function(id) {
        viewContent.innerHTML = `<div class="flex justify-center py-10"><i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i></div>`;
        viewModal.classList.remove('hidden');
        lucide.createIcons();

        setTimeout(() => {
            viewOverlay.classList.remove('opacity-0');
            viewCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            viewCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);

        const fd = new FormData();
        fd.append('ajax_action', 'view');
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const data = res.data;
                    const st = (data.status || 'pending').toLowerCase();

                    let statusBadge = '<span class="px-2.5 py-1 bg-pending/10 text-pending font-bold text-[10px] rounded-md uppercase tracking-wider">Menunggu Verifikasi</span>';
                    if (st === 'active') statusBadge = '<span class="px-2.5 py-1 bg-success/10 text-success font-bold text-[10px] rounded-md uppercase tracking-wider">Aktif</span>';
                    else if (st === 'suspended') statusBadge = '<span class="px-2.5 py-1 bg-failed/10 text-failed font-bold text-[10px] rounded-md uppercase tracking-wider">Ditangguhkan</span>';
                    else if (st === 'inactive') statusBadge = '<span class="px-2.5 py-1 bg-gray-100 text-gray-500 font-bold text-[10px] rounded-md uppercase tracking-wider">Nonaktif</span>';

                    let durText = "7 Hari (Demo)";
                    const dur = data.duration || '';
                    if (dur === '6_months') durText = "6 Bulan";
                    else if (dur === '1_year') durText = "1 Tahun";
                    else if (dur === '5_years') durText = "5 Tahun (Diskon 50%)";
                    else if (dur === '7_days') durText = "7 Hari (Demo)";

                    const formatRp = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
                    const totalPriceText = formatRp(data.total_price || 0);

                    const formatDate = (dateStr) => {
                        if (!dateStr) return 'Belum Ditentukan';
                        const d = new Date(dateStr);
                        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' WIB';
                    };

                    let approverHtml = data.approver_name 
                        ? `<div class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-center"><p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Diverifikasi Oleh</p><p class="text-xs font-bold text-gray-800 mt-0.5">${data.approver_name}</p></div>` 
                        : '';

                    viewContent.innerHTML = `
                        <div class="text-center mb-6 mt-2 md:mt-0">
                            <h3 class="text-base md:text-lg font-bold text-gray-800">${data.name}</h3>
                            <p class="text-xs text-primary font-medium mt-0.5">${data.email || '-'}</p>
                            <div class="mt-3">${statusBadge}</div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Paket SaaS</p>
                                    <p class="text-xs font-bold text-primary capitalize mt-1">${data.package_type || 'Demo'}</p>
                                </div>
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Masa Aktif Durasi</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${durText}</p>
                                </div>
                            </div>

                            <div class="bg-primary/5 border border-primary/20 p-4 rounded-xl shadow-sm text-center">
                                <p class="text-[10px] font-bold text-primary uppercase tracking-wider">Total Biaya Paket</p>
                                <p class="text-lg font-black text-primary mt-1">${totalPriceText}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Batas Karyawan</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${data.total_users || 0} Karyawan</p>
                                </div>
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Nomor WA Admin</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${data.phone || '-'}</p>
                                </div>
                            </div>

                            <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Aktif Sampai Dengan</p>
                                <p class="text-xs font-bold text-emerald-600 mt-1">${formatDate(data.active_until)}</p>
                            </div>

                            ${approverHtml}
                        </div>

                        <div class="flex gap-3 mt-6 border-t border-gray-100 pt-6">
                            <button onclick="submitApproval(${data.id}, 'reject')" class="flex-1 py-3 bg-failed/10 text-failed rounded-xl text-xs font-bold hover:bg-failed hover:text-white transition active:scale-95 shadow-sm">Tolak / Tangguhkan</button>
                            <button onclick="submitApproval(${data.id}, 'approve')" class="flex-1 py-3 bg-success/10 text-success rounded-xl text-xs font-bold hover:bg-success hover:text-white transition active:scale-95 shadow-sm">Setujui Tenant</button>
                        </div>

                        <button onclick="closeViewModal()" class="w-full mt-3 py-3 border border-gray-200 text-gray-600 rounded-xl text-xs font-semibold hover:bg-gray-50 transition active:scale-95">Tutup</button>
                    `;
                    lucide.createIcons();
                } else {
                    viewContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">${res.message}</div>`;
                }
            })
            .catch(() => {
                viewContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">Gagal memuat data dari server.</div>`;
            });
    }

    window.closeViewModal = function() {
        viewOverlay.classList.add('opacity-0');
        viewCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        viewCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { viewModal.classList.add('hidden'); }, 300);
    }

    window.submitApproval = function(id, action) {
        const fd = new FormData();
        fd.append('ajax_action', action);
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    window.location.reload();
                } else {
                    if (typeof window.showToast === 'function') window.showToast(res.message, 'error');
                }
            })
            .catch(() => {
                if (typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server", 'error');
            });
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
                        document.getElementById('tenantStatus').value = res.data.status || 'pending';
                        document.getElementById('tenantPackageType').value = res.data.package_type || 'demo';
                        document.getElementById('tenantTotalUsers').value = res.data.total_users || 10;
                        document.getElementById('tenantDuration').value = res.data.duration || '6_months';
                        document.getElementById('tenantTotalPrice').value = res.data.total_price || 0;
                        showFormModal();
                    } else {
                        if (typeof window.showToast === 'function') window.showToast(res.message, 'error');
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
                else { 
                    if (typeof window.showToast === 'function') window.showToast(res.message, 'error'); 
                    btn.disabled = false; btn.innerHTML = 'Simpan'; 
                }
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
                    Tindakan ini akan menghapus **SELURUH DATA** (User, Pengaturan, Cuti, Lembur) yang berelasi dengan Tenant ini secara permanen.
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
            else { 
                if (typeof window.showToast === 'function') window.showToast(res.message, 'error'); 
                closeConfirm(); 
            }
        });
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>