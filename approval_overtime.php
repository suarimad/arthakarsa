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

// Generate waktu DATETIME saat ini berdasarkan timezone tenant
$current_time = date('Y-m-d H:i:s');

// ==============================================================================
// LOGIKA HAK AKSES (GUARD HALAMAN APPROVAL)
// ==============================================================================
$role_id = $_SESSION['role_id'] ?? null;
$role_name_session = strtolower($_SESSION['role'] ?? '');

$allowed_roles = ['superadmin', 'admin', 'hr', 'manager'];
$allowed_role_ids = [1, 2, 3, 4];

if (!in_array($role_name_session, $allowed_roles) && !in_array($role_id, $allowed_role_ids)) {
    $_SESSION['toast_msg'] = "Anda tidak memiliki akses ke halaman Approval Lembur.";
    $_SESSION['toast_type'] = "error";
    header("Location: " . ($base_url ?? '') . "/overtime");
    exit;
}

$can_delete_all = in_array($role_name_session, ['admin', 'superadmin', 'hr']);

// ==============================================================================
// PENANGANAN AJAX (VIEW DETAIL, APPROVE, REJECT, DELETE)
// ==============================================================================
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_REQUEST['ajax_action'];

        // AJAX 1: VIEW DETAIL
        if ($action === 'view') {
            $id = $_REQUEST['id'];
            
            $query = "
                SELECT o.*, u.name as employee_name, d.name as department_name, a.name as approver_name
                FROM overtime_requests o 
                LEFT JOIN users u ON o.user_id = u.id 
                LEFT JOIN positions p ON u.position_id = p.id
                LEFT JOIN departments d ON p.department_id = d.id
                LEFT JOIN users a ON o.approved_by = a.id
                WHERE o.id = ? AND o.tenant_id = ?
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id, $tenant_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
            }
            exit;
        }

        // AJAX 2: APPROVE / REJECT / DELETE (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];

            // PROSES DELETE (KHUSUS SUPERADMIN / HR / ADMIN)
            if ($action === 'delete') {
                $stmt = $pdo->prepare("SELECT id FROM overtime_requests WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                $req = $stmt->fetch();

                if ($req) {
                    if ($can_delete_all) {
                        $pdo->prepare("UPDATE overtime_requests SET deleted_at = ?, updated_at = ? WHERE id = ? AND tenant_id = ?")
                            ->execute([$current_time, $current_time, $id, $tenant_id]);

                        $_SESSION['toast_msg'] = "Data lembur dihapus secara sistem.";
                        $_SESSION['toast_type'] = "success";
                        echo json_encode(['status' => 'success']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak untuk menghapus data ini.']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
                }
                exit;
            }

            // PROSES APPROVE / REJECT
            if (in_array($action, ['approve', 'reject'])) {
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                $note = $_POST['note'] ?? null;

                $pdo->prepare("UPDATE overtime_requests SET status = ?, approved_by = ?, approved_at = ?, rejection_note = ?, updated_at = ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$status, $user_id, $current_time, $note, $current_time, $id, $tenant_id]);

                $_SESSION['toast_msg'] = "Pengajuan lembur berhasil di" . ($action === 'approve' ? 'setujui' : 'tolak') . ".";
                $_SESSION['toast_type'] = "success";
                echo json_encode(['status' => 'success']);
                exit;
            }
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

// MENGAMBIL DATA PENGAJUAN LEMBUR UNTUK DATATABLES (Hanya Status Pending dari Semua User)
$base_query = "
    SELECT o.*, u.name as employee_name, u.avatar, d.name as department_name
    FROM overtime_requests o 
    LEFT JOIN users u ON o.user_id = u.id 
    LEFT JOIN positions p ON u.position_id = p.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE o.tenant_id = ? AND o.deleted_at IS NULL AND o.status = 'pending'
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($base_query);
$stmt->execute([$tenant_id]);
$overtime_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    div[id*="toast"], div[class*="toast"], #toast-container {
        z-index: 999999 !important;
    }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        
        <?php require_once __DIR__ . '/components/header.php'; ?>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-2 relative z-0">
            
            <div class="flex justify-between items-center px-1">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Approval Lembur</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Daftar pengajuan lembur karyawan yang butuh persetujuan.</p>
                </div>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari nama karyawan..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-3 space-y-5 md:space-y-6">
                    
                    <section class="relative z-0">
                        <div class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                            <div class="overflow-x-auto">
                                <table id="overtimeTable" class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tanggal & Waktu</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Durasi</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($overtime_requests as $or): 
                                            $safe_name = htmlspecialchars($or['employee_name'] ?? 'Unknown');
                                            $dept_name = htmlspecialchars($or['department_name'] ?? '-');
                                            $avatar = !empty($or['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($or['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($safe_name);
                                            
                                            $date_str = date('d M Y', strtotime($or['date']));
                                            $time_str = date('H:i', strtotime($or['start_time'])) . ' - ' . date('H:i', strtotime($or['end_time']));
                                            
                                            $dur_m = $or['duration_minutes'];
                                            $hours = floor($dur_m / 60);
                                            $minutes = $dur_m % 60;
                                            $duration_str = ($hours > 0 ? "{$hours}j " : "") . "{$minutes}m";

                                            $badge_bg = 'bg-pending/10'; $badge_text = 'text-pending'; $badge_label = 'Menunggu';
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
                                                        <span class="text-xs font-bold text-gray-800 flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-primary"></i> <?= $date_str ?></span>
                                                        <span class="text-[10px] font-medium text-gray-500"><i data-lucide="clock" class="w-3 h-3 inline-block -mt-0.5 mr-0.5"></i> <?= $time_str ?></span>
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <span class="text-xs font-bold text-gray-700 bg-gray-100 px-2 py-1 rounded-md"><?= $duration_str ?></span>
                                                </td>
                                                
                                                <td>
                                                    <span class="text-[9px] font-bold px-2 py-1 rounded-md <?= $badge_bg ?> <?= $badge_text ?>"><?= $badge_label ?></span>
                                                </td>
                                                
                                                <td class="text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button onclick="openViewModal(<?= $or['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-primary hover:text-white transition shadow-sm active:scale-95" title="Lihat Detail">
                                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                        
                                                        <button onclick="openConfirmModal(<?= $or['id'] ?>, 'approve')" class="p-2 bg-success/10 text-success rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-success hover:text-white transition shadow-sm active:scale-95" title="Setujui">
                                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                        <button onclick="openConfirmModal(<?= $or['id'] ?>, 'reject')" class="p-2 bg-failed/10 text-failed rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Tolak">
                                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                                        </button>

                                                        <?php if ($can_delete_all): ?>
                                                            <button onclick="deleteOvertime(<?= $or['id'] ?>)" class="p-2 bg-gray-100 text-gray-400 rounded-xl text-xs font-semibold flex items-center justify-center hover:bg-failed hover:text-white transition shadow-sm active:scale-95" title="Hapus Pengajuan (Sistem)">
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

<!-- ================= MODAL KONFIRMASI APPROVE/REJECT/DELETE ================= -->
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

    $(document).ready(function() {
        const table = $('#overtimeTable').DataTable({
            "dom": 't<"bottom"ip>', 
            "pageLength": 10,
            "ordering": false,
            "language": {
                "emptyTable": "Belum ada pengajuan lembur yang menunggu persetujuan.",
                "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                "infoEmpty": "Menampilkan 0 s/d 0 dari 0 data",
                "paginate": {
                    "previous": "Sebelumnya",
                    "next": "Selanjutnya"
                }
            }
        });

        $('#dtSearchInput').on('keyup', function() {
            table.search(this.value).draw();
        });

        table.on('draw', function() {
            lucide.createIcons();
        });
    });

    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');
    const crudContent = document.getElementById('crudContent');
    const baseUrl = '<?= $base_url ?? '' ?>';
    
    document.body.appendChild(crudModal);

    window.openViewModal = function(id) {
        crudContent.innerHTML = `
            <div class="flex justify-center py-10">
                <i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i>
            </div>
        `;
        
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
                    const formatTime = (timeStr) => {
                        if(!timeStr) return '--:--';
                        return timeStr.substring(0, 5); 
                    };

                    const attUrl = data.attachment ? `${baseUrl}/assets/img/overtime_requests/${data.attachment}` : null;
                    
                    let attachmentHtml = attUrl 
                        ? `<a href="${attUrl}" target="_blank" class="text-xs font-bold text-primary hover:underline flex items-center justify-center gap-1.5 mt-3 py-2 bg-primary/10 rounded-lg"><i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Lihat Lampiran</a>` 
                        : '<p class="text-[10px] text-gray-400 mt-2 italic flex items-center gap-1"><i data-lucide="file-x-2" class="w-3 h-3"></i> Tidak ada lampiran</p>';

                    const durHours = Math.floor(data.duration_minutes / 60);
                    const durMins = data.duration_minutes % 60;
                    const durStr = (durHours > 0 ? durHours + " Jam " : "") + (durMins > 0 ? durMins + " Menit" : "");

                    let statusBadge = '<span class="px-2.5 py-1 bg-pending/10 text-pending font-bold text-[10px] rounded-md uppercase tracking-wider">Menunggu</span>';

                    let actionButtons = `
                        <div class="flex gap-3 mt-6 border-t border-gray-100 pt-6">
                            <button onclick="openConfirmModal(${data.id}, 'reject')" class="flex-1 py-3 bg-failed/10 text-failed rounded-xl text-sm font-bold hover:bg-failed hover:text-white transition active:scale-95 shadow-sm">Tolak</button>
                            <button onclick="openConfirmModal(${data.id}, 'approve')" class="flex-1 py-3 bg-success/10 text-success rounded-xl text-sm font-bold hover:bg-success hover:text-white transition active:scale-95 shadow-sm">Setujui</button>
                        </div>
                    `;

                    crudContent.innerHTML = `
                        <div class="text-center mb-6 mt-2 md:mt-0">
                            <h3 class="text-base md:text-lg font-bold text-gray-800">Detail Pengajuan Lembur</h3>
                            <p class="text-xs text-primary font-medium mt-0.5">${data.employee_name} <span class="text-gray-400 mx-1">•</span> ${data.department_name || 'Tanpa Departemen'}</p>
                            <div class="mt-3">${statusBadge}</div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center col-span-2 md:col-span-1">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Tgl Pelaksanaan</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${formatDate(data.date)}</p>
                                </div>
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center col-span-2 md:col-span-1">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Total Durasi</p>
                                    <p class="text-xs font-bold text-primary mt-1">${durStr}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jam Mulai</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${formatTime(data.start_time)} WIB</p>
                                </div>
                                <div class="bg-gray-50 border border-gray-100 p-3 rounded-xl shadow-sm text-center">
                                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Jam Selesai</p>
                                    <p class="text-xs font-bold text-gray-800 mt-1">${formatTime(data.end_time)} WIB</p>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl shadow-sm">
                                <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Tugas / Pekerjaan</p>
                                <p class="text-xs font-medium text-gray-700 leading-relaxed">${data.reason}</p>
                                ${attachmentHtml}
                            </div>
                        </div>
                        
                        ${actionButtons}
                        
                        <button onclick="closeCrud()" class="w-full mt-4 py-3 border border-gray-200 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-50 transition active:scale-95 shadow-sm">Tutup Detail</button>
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

    window.openConfirmModal = function(id, action) {
        closeCrud();
        
        setTimeout(() => {
            let title = action === 'approve' ? 'Konfirmasi Persetujuan' : 'Konfirmasi Penolakan';
            let desc = action === 'approve' ? 'Apakah Anda yakin ingin menyetujui pengajuan lembur ini?' : 'Apakah Anda yakin ingin menolak pengajuan lembur ini?';
            let iconClass = action === 'approve' ? 'bg-success/10 text-success' : 'bg-failed/10 text-failed';
            let iconType = action === 'approve' ? 'check' : 'x';
            let btnClass = action === 'approve' ? 'bg-success hover:bg-success/90' : 'bg-failed hover:bg-failed/90';
            let btnText = action === 'approve' ? 'Ya, Setujui' : 'Ya, Tolak';

            let inputHtml = action === 'reject' ? `
                <div class="mt-4 text-left">
                    <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase tracking-wider">Alasan Penolakan</label>
                    <textarea id="rejectionNote" rows="3" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-failed focus:ring-1 focus:ring-failed transition text-xs text-gray-800" placeholder="Wajib diisi..."></textarea>
                </div>
            ` : '';

            confirmContent.innerHTML = `
                <div class="text-center">
                    <div class="w-12 h-12 rounded-full ${iconClass} mx-auto flex items-center justify-center mb-4">
                        <i data-lucide="${iconType}" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">${title}</h3>
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">${desc}</p>
                    
                    ${inputHtml}
                    
                    <div class="flex gap-3 mt-8">
                        <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                        <button onclick="submitAction(${id}, '${action}')" class="flex-1 py-3 ${btnClass} text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">${btnText}</button>
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
        }, 300);
    }

    window.deleteOvertime = function(id) {
        confirmContent.innerHTML = `
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-failed/10 text-failed mx-auto flex items-center justify-center mb-4">
                    <i data-lucide="trash-2" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Hapus Permanen</h3>
                <p class="text-xs text-gray-500 mt-1">Apakah Anda yakin ingin menghapus data lembur ini secara permanen dari sistem?</p>
                
                <div class="flex gap-3 mt-8">
                    <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                    <button onclick="submitAction(${id}, 'delete')" class="flex-1 py-3 bg-failed hover:bg-failed/90 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95">Ya, Hapus</button>
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
        let note = '';
        if (action === 'reject') {
            const noteInput = document.getElementById('rejectionNote');
            note = noteInput.value.trim();
            
            if (!note) {
                if(typeof window.showToast === 'function') window.showToast('Alasan penolakan wajib diisi!', 'warning');
                noteInput.classList.add('border-failed', 'ring-failed', 'bg-failed/5');
                noteInput.focus();
                return;
            }
        }

        const formData = new FormData();
        formData.append('ajax_action', action);
        formData.append('id', id);
        if (note) formData.append('note', note);

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                window.location.reload(); 
            } else {
                if(typeof window.showToast === 'function') window.showToast(res.message, 'error');
            }
        }).catch(() => {
            if(typeof window.showToast === 'function') window.showToast("Gagal terhubung ke server", 'error');
        });
    }

</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>