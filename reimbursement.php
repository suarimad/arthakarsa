<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Ambil Timezone dari tenant_settings untuk Tenant Terkait
$stmtTS = $pdo->prepare("SELECT timezone FROM tenant_settings WHERE tenant_id = ?");
$stmtTS->execute([$tenant_id]);
$tz_setting = $stmtTS->fetchColumn() ?: 'Asia/Jakarta';
date_default_timezone_set($tz_setting);

$current_time = date('Y-m-d H:i:s');

// ==============================================================================
// PENANGANAN AJAX (VIEW DETAIL & DELETE/CANCEL PRIBADI)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_REQUEST['ajax_action'];

        // AJAX 1: VIEW DETAIL PRIBADI
        if ($action === 'view') {
            $id = $_REQUEST['id'];
            
            $query = "
                SELECT r.*, u.name as employee_name, d.name as department_name, a.name as approver_name
                FROM reimbursement_requests r 
                LEFT JOIN users u ON r.user_id = u.id 
                LEFT JOIN positions p ON u.position_id = p.id
                LEFT JOIN departments d ON p.department_id = d.id
                LEFT JOIN users a ON r.approved_by = a.id
                WHERE r.id = ? AND r.tenant_id = ? AND r.user_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id, $tenant_id, $user_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan atau akses ditolak.']);
            }
            exit;
        }

        // AJAX 2: DELETE / BATALKAN PENGAJUAN (Hanya yang statusnya masih pending)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
            $id = $_POST['id'];

            $stmt = $pdo->prepare("SELECT status FROM reimbursement_requests WHERE id = ? AND tenant_id = ? AND user_id = ?");
            $stmt->execute([$id, $tenant_id, $user_id]);
            $req = $stmt->fetch();

            if ($req) {
                if ($req['status'] === 'pending') {
                    $pdo->prepare("UPDATE reimbursement_requests SET deleted_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ? AND user_id = ?")
                        ->execute([$current_time, $current_time, $id, $tenant_id, $user_id]);

                    $_SESSION['toast_msg'] = "Pengajuan klaim berhasil dibatalkan.";
                    $_SESSION['toast_type'] = "success";
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Hanya pengajuan dengan status Menunggu yang dapat dibatalkan.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
            }
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
// ==============================================================================

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['position_name'] ?? $_SESSION['role_display'] ?? ucfirst($_SESSION['role'] ?? 'Employee');
$tenant_name = $_SESSION['tenant_name'] ?? 'Perusahaan'; 

// MENGAMBIL DATA PENGAJUAN REIMBURSE PRIBADI UNTUK DATATABLES
$base_query = "
    SELECT r.*, u.name as employee_name, u.avatar, d.name as department_name
    FROM reimbursement_requests r 
    LEFT JOIN users u ON r.user_id = u.id 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE r.tenant_id = ? AND r.user_id = ? AND r.deleted_at IS NULL 
    ORDER BY r.created_at DESC
";

$stmt = $pdo->prepare($base_query);
$stmt->execute([$tenant_id, $user_id]);
$reimbursement_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }
    
    div[id*="toast"], div[class*="toast"], #toast-container { z-index: 999999 !important; }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Riwayat Reimburse</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Kelola riwayat pengajuan klaim dana Anda</p>
                </div>
                <a href="reimbursement_add" class="bg-primary/10 text-primary px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition shadow-sm active:scale-95">
                    <i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden md:inline">Ajukan Klaim</span>
                </a>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari kategori atau status..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            <div class="overflow-x-auto">
                                <table id="reimburseTable" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tanggal & Kategori</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Nominal</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($reimbursement_requests as $rm): 
                                            $safe_name = htmlspecialchars($rm['employee_name'] ?? 'Unknown');
                                            $dept_name = htmlspecialchars($rm['department_name'] ?? '-');
                                            $avatar = !empty($rm['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($rm['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($safe_name);
                                            
                                            $date_str = date('d M Y', strtotime($rm['date']));
                                            $category = htmlspecialchars($rm['category'] ?? 'Lainnya');
                                            $amount_str = "Rp " . number_format($rm['amount'], 0, ',', '.');

                                            $status = strtolower($rm['status']);
                                            $badge_bg = 'bg-gray-100'; $badge_text = 'text-gray-500'; $badge_label = 'Unknown';
                                            if ($status === 'pending') { $badge_bg = 'bg-pending/10'; $badge_text = 'text-pending'; $badge_label = 'Menunggu'; }
                                            if ($status === 'approved') { $badge_bg = 'bg-success/10'; $badge_text = 'text-success'; $badge_label = 'Disetujui'; }
                                            if ($status === 'rejected') { $badge_bg = 'bg-failed/10'; $badge_text = 'text-failed'; $badge_label = 'Ditolak'; }
                                            if ($status === 'canceled') { $badge_bg = 'bg-gray-100'; $badge_text = 'text-gray-500'; $badge_label = 'Dibatalkan'; }
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 rounded-full bg-gray-100 shrink-0 overflow-hidden border border-gray-200">
                                                            <img src="<?= $avatar ?>" class="w-full h-full object-cover" alt="Avatar">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <h4 class="text-xs font-bold text-gray-800"><?= $safe_name ?></h4>
                                                            <p class="text-[10px] text-gray-500 font-medium truncate mt-0.5"><?= $dept_name ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <div class="flex flex-col gap-1 items-start">
                                                        <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5 capitalize"><i data-lucide="receipt" class="w-3.5 h-3.5 text-primary"></i> <?= $category ?></span>
                                                        <span class="text-[10px] font-medium text-gray-500"><?= $date_str ?></span>
                                                    </div>
                                                </td>

                                                <td class="text-right">
                                                    <span class="text-xs font-bold text-gray-800"><?= $amount_str ?></span>
                                                </td>
                                                
                                                <td class="text-center">
                                                    <span class="text-[9px] font-bold px-2 py-1 rounded-md <?= $badge_bg ?> <?= $badge_text ?>"><?= $badge_label ?></span>
                                                </td>
                                                
                                                <td class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openViewModal(<?= $rm['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Lihat Detail">
                                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                        
                                                        <?php if ($status === 'pending'): ?>
                                                            <button onclick="deleteReimbursement(<?= $rm['id'] ?>)" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Batalkan Pengajuan">
                                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                            </button>
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

<!-- ================= HYBRID MODAL/BOTTOM SHEET (VIEW DETAIL) ================= -->
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="crudOverlay" onclick="closeCrud()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="crudCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div id="crudContent" class="px-6 pb-8 md:p-8 overflow-y-auto"></div>
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI DELETE ================= -->
<div id="confirmModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="confirmOverlay" onclick="closeConfirm()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="confirmCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh] p-6">
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeConfirm()">
                <div class="w-12 h-1.5 bg-gray-200 rounded-full"></div>
            </div>
            <div id="confirmContent"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();

    const formatRp = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);

    $(document).ready(function() {
        const table = $('#reimburseTable').DataTable({
            "dom": 't<"bottom"ip>', 
            "pageLength": 10,
            "ordering": false,
            "language": {
                "emptyTable": "Belum ada riwayat pengajuan reimburse",
                "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                "infoEmpty": "Menampilkan 0 s/d 0 dari 0 data",
                "paginate": { "previous": "Sebelumnya", "next": "Selanjutnya" }
            }
        });
        $('#dtSearchInput').on('keyup', function() { table.search(this.value).draw(); });
        table.on('draw', function() { lucide.createIcons(); });
    });

    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');
    const crudContent = document.getElementById('crudContent');
    const baseUrl = '<?= $base_url ?? '' ?>';
    
    document.body.appendChild(crudModal);

    window.openViewModal = function(id) {
        crudContent.innerHTML = `<div class="flex justify-center py-10"><i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i></div>`;
        crudModal.classList.remove('hidden');
        lucide.createIcons();
        
        setTimeout(() => {
            crudOverlay.classList.remove('opacity-0');
            crudCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            crudCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100');
        }, 10);

        const fd = new FormData();
        fd.append('ajax_action', 'view');
        fd.append('id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    const data = res.data;
                    const formatDate = (dateStr) => {
                        const d = new Date(dateStr);
                        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
                    };

                    const attUrl = data.attachment ? `${baseUrl}/assets/img/reimbursements/${data.attachment}` : null;
                    let attachmentHtml = attUrl 
                        ? `<a href="${attUrl}" target="_blank" class="text-xs font-bold text-primary hover:underline flex items-center justify-center gap-1.5 mt-3 py-2 bg-primary/10 rounded-lg"><i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Lihat Bukti Nota</a>` 
                        : '<p class="text-[10px] text-gray-400 mt-2 italic flex items-center gap-1"><i data-lucide="file-x-2" class="w-3 h-3"></i> Tidak ada lampiran nota</p>';

                    let statusBadge = '';
                    const st = (data.status || '').toLowerCase();
                    if (st === 'pending') statusBadge = '<span class="px-2.5 py-1 bg-pending/10 text-pending font-bold text-[10px] rounded-md uppercase tracking-wider">Menunggu</span>';
                    else if (st === 'approved') statusBadge = '<span class="px-2.5 py-1 bg-success/10 text-success font-bold text-[10px] rounded-md uppercase tracking-wider">Disetujui</span>';
                    else if (st === 'rejected') statusBadge = '<span class="px-2.5 py-1 bg-failed/10 text-failed font-bold text-[10px] rounded-md uppercase tracking-wider">Ditolak</span>';
                    else if (st === 'canceled') statusBadge = '<span class="px-2.5 py-1 bg-gray-100 text-gray-500 font-bold text-[10px] rounded-md uppercase tracking-wider">Dibatalkan</span>';

                    let rejectNoteHtml = data.rejection_note ? `<div class="mt-4 p-3 border border-failed/20 bg-failed/5 rounded-xl"><p class="text-[10px] font-bold text-failed mb-1 uppercase tracking-wider">Alasan Penolakan:</p><p class="text-xs font-medium text-gray-700">${data.rejection_note}</p></div>` : '';
                    let approverHtml = data.approver_name ? `<div class="mt-4 p-3 border border-success/20 bg-success/5 rounded-xl"><p class="text-[10px] font-bold text-success mb-1 uppercase tracking-wider">Disetujui Oleh:</p><p class="text-xs font-bold text-gray-800">${data.approver_name}</p></div>` : '';
                    if(data.status === 'rejected') approverHtml = '';

                    crudContent.innerHTML = `
                        <div class="text-center mb-6 mt-2 md:mt-0">
                            <h3 class="text-base md:text-lg font-bold text-gray-800">Detail Klaim Reimburse</h3>
                            <p class="text-xs text-primary font-medium mt-0.5">${data.employee_name} <span class="text-gray-400 mx-1">•</span> ${data.department_name || 'Tanpa Departemen'}</p>
                            <div class="mt-3">${statusBadge}</div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tgl Transaksi</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${formatDate(data.date)}</p>
                                </div>
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Kategori</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1 capitalize">${data.category}</p>
                                </div>
                            </div>
                            
                            <div class="bg-primary/5 border border-primary/20 p-4 rounded-xl shadow-sm text-center">
                                <p class="text-[10px] font-bold text-primary uppercase tracking-wider">Total Nominal</p>
                                <p class="text-xl font-black text-primary mt-1">${formatRp(data.amount)}</p>
                            </div>
                            
                            <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl shadow-sm">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Keterangan / Keperluan</p>
                                <p class="text-xs font-medium text-gray-700 leading-relaxed">${data.description || '-'}</p>
                                ${attachmentHtml}
                            </div>
                            
                            ${approverHtml}
                            ${rejectNoteHtml}
                        </div>
                        
                        <button onclick="closeCrud()" class="w-full mt-6 py-3 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition active:scale-95 shadow-sm">Tutup Detail</button>
                    `;
                    lucide.createIcons();
                } else {
                    crudContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">${res.message}</div>`;
                }
            })
            .catch(() => {
                crudContent.innerHTML = `<div class="text-center text-failed py-6 text-sm font-semibold">Gagal memuat data dari server.</div>`;
            });
    }

    window.closeCrud = function() {
        crudOverlay.classList.add('opacity-0');
        crudCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); 
        crudCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { crudModal.classList.add('hidden'); }, 300);
    }

    const confirmModal = document.getElementById('confirmModal');
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmCard = document.getElementById('confirmCard');
    const confirmContent = document.getElementById('confirmContent');
    document.body.appendChild(confirmModal);

    window.deleteReimbursement = function(id) {
        confirmContent.innerHTML = `
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="trash-2" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Batalkan Pengajuan</h3>
                <p class="text-xs text-gray-500 mt-1">Apakah Anda yakin ingin membatalkan pengajuan reimburse ini?</p>
                
                <div class="flex gap-3 mt-8">
                    <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                    <button onclick="submitAction(${id}, 'delete')" class="flex-1 py-3 bg-failed hover:bg-failed/90 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">Ya, Batalkan</button>
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

    window.submitAction = function(id, action) {
        const formData = new FormData();
        formData.append('ajax_action', action);
        formData.append('id', id);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') { window.location.reload(); } 
            else { if(typeof window.showToast === 'function') window.showToast(res.message, 'error'); }
        }).catch(() => {
            if(typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server", 'error');
        });
    }

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>