<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Guard: Parameter wajib
if (!isset($_GET['month']) || !isset($_GET['year'])) {
    header("Location: " . ($base_url ?? '') . "/payslips");
    exit;
}

$month = (int)$_GET['month'];
$year = (int)$_GET['year'];
$month_names = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
$period_str = $month_names[$month] . " " . $year;

$role_name_session = strtolower($_SESSION['role'] ?? '');
$can_view_all = in_array($role_name_session, ['admin', 'superadmin', 'hr', 'finance', 'manager']);
$can_manage_payroll = in_array($role_name_session, ['admin', 'superadmin', 'hr', 'finance']);

// ==========================================
// PENANGANAN AJAX: POST SAJA AGAR AMAN DARI URL REWRITE
// ==========================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    // AJAX: VIEW STRUK
    if ($action === 'view') {
        $id = $_POST['id'];
        $query = "
            SELECT p.*, u.name as employee_name, pos.name as position_name, d.name as department_name, us.bank_name, us.bank_account
            FROM payslips p 
            LEFT JOIN users u ON p.user_id = u.id 
            LEFT JOIN positions pos ON u.position_id = pos.id
            LEFT JOIN departments d ON pos.department_id = d.id
            LEFT JOIN user_salaries us ON u.id = us.user_id
            WHERE p.id = ? AND p.tenant_id = ?
        ";
        $params = [$id, $tenant_id];
        if (!$can_view_all) { $query .= " AND p.user_id = ?"; $params[] = $user_id; }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payslip) {
            $stmtDet = $pdo->prepare("SELECT type, name, amount FROM payslip_details WHERE payslip_id = ? ORDER BY type DESC, id ASC");
            $stmtDet->execute([$id]);
            $payslip['details'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtLogo = $pdo->prepare("SELECT logo, name as tenant_name FROM tenants WHERE id = ?");
            $stmtLogo->execute([$tenant_id]);
            $tenantInfo = $stmtLogo->fetch(PDO::FETCH_ASSOC);
            $payslip['tenant_logo'] = $tenantInfo['logo'] ?? null;
            $payslip['tenant_name'] = $tenantInfo['tenant_name'] ?? 'Perusahaan';

            echo json_encode(['status' => 'success', 'data' => $payslip]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Slip gaji tidak ditemukan.']);
        }
        exit;
    }

    // AJAX: LOAD DATA UNTUK EDIT MANUAL
    if ($action === 'get_edit') {
        if (!$can_manage_payroll) exit;
        $id = $_POST['id'];
        $stmt = $pdo->prepare("SELECT id, basic_salary, total_earnings, total_deductions, net_salary, status FROM payslips WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data && in_array($data['status'], ['draft', 'generated'])) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan atau sudah dibayar.']);
        }
        exit;
    }

    // AJAX: SIMPAN EDIT MANUAL
    if ($action === 'save_edit') {
        if (!$can_manage_payroll) exit;
        $id = $_POST['id'];
        $basic = (float)$_POST['basic_salary'];
        $earn = (float)$_POST['total_earnings'];
        $ded = (float)$_POST['total_deductions'];
        $net = max(0, $basic + $earn - $ded); // Kalkulasi server-side untuk anti bypass

        $pdo->prepare("UPDATE payslips SET basic_salary = ?, total_earnings = ?, total_deductions = ?, net_salary = ? WHERE id = ? AND tenant_id = ? AND status != 'paid'")
            ->execute([$basic, $earn, $ded, $net, $id, $tenant_id]);

        $_SESSION['toast_msg'] = 'Data payslip berhasil diperbarui secara manual.';
        $_SESSION['toast_type'] = 'success';
        echo json_encode(['status' => 'success']);
        exit;
    }

    // AJAX: TANDAI DIBAYAR ATAU DRAFT
    if (in_array($action, ['mark_paid', 'mark_draft'])) {
        if (!$can_manage_payroll) exit;
        $id = $_POST['id'];
        $status = $action === 'mark_paid' ? 'paid' : 'draft';
        $payment_date = $action === 'mark_paid' ? 'CURRENT_DATE' : 'NULL';
        
        $pdo->prepare("UPDATE payslips SET status = ?, payment_date = $payment_date WHERE id = ? AND tenant_id = ?")->execute([$status, $id, $tenant_id]);
        
        $_SESSION['toast_msg'] = $action === 'mark_paid' ? 'Slip gaji telah ditandai Lunas/Dibayar.' : 'Status dikembalikan ke Draft.';
        $_SESSION['toast_type'] = 'success';
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// ==========================================
// MENGAMBIL DATA UNTUK DATATABLES
// ==========================================
$base_query = "
    SELECT p.*, u.name as employee_name, u.avatar, d.name as department_name, pos.name as position_name
    FROM payslips p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN positions pos ON u.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    WHERE p.tenant_id = ? AND p.month = ? AND p.year = ?
";
if ($can_view_all) {
    $stmt = $pdo->prepare($base_query . " ORDER BY u.name ASC");
    $stmt->execute([$tenant_id, $month, $year]);
} else {
    $stmt = $pdo->prepare($base_query . " AND p.user_id = ?");
    $stmt->execute([$tenant_id, $month, $year, $user_id]);
}
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';
echo '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">';
echo '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
echo '<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>';
?>

<style>
    table.dataTable.no-footer { border-bottom: none !important; }
    table.dataTable thead th { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; background-color: #f9fafb; background-image: none !important;}
    table.dataTable tbody td { border-bottom: 1px solid #f3f4f6 !important; padding: 0.75rem 1rem !important; vertical-align: middle; }
    .dataTables_wrapper .bottom { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1rem; background-color: #ffffff; border-top: 1px solid #f3f4f6; }
    .dataTables_info { font-size: 0.65rem !important; color: #6b7280 !important; padding-top: 0 !important; }
    .dataTables_paginate { display: flex; gap: 0.25rem; padding-top: 0 !important; }
    .dataTables_paginate .paginate_button { padding: 0.25rem 0.6rem !important; border-radius: 0.5rem !important; font-size: 0.7rem !important; font-weight: 600 !important; background: white !important; border: 1px solid #e5e7eb !important; color: #4b5563 !important; cursor: pointer; margin-left: 0.25rem !important; }
    .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f9fafb !important; color: #111827 !important; }
    .dataTables_paginate .paginate_button.current { background: #ea3800 !important; color: white !important; border-color: #ea3800 !important; }
    .receipt-bg { background: #f9fafb; background-image: radial-gradient(#e5e7eb 1px, transparent 1px); background-size: 20px 20px; }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        <div class="hidden md:block"><?php require_once __DIR__ . '/components/header.php'; ?></div>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-6 md:mt-2 relative z-0">
            <div class="flex items-center gap-3 px-1 mb-2">
                <a href="<?= ($base_url ?? '') ?>/payslips" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition"><i data-lucide="chevron-left" class="w-5 h-5"></i></a>
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Rincian Payroll</h2>
                    <p class="text-[11px] md:text-xs text-primary font-bold mt-0.5 uppercase tracking-widest"><?= $period_str ?></p>
                </div>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari nama karyawan..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <section class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                <div class="overflow-x-auto">
                    <table id="detailTable" class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Karyawan</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Take Home Pay</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payslips as $p): 
                                $safe_name = htmlspecialchars($p['employee_name'] ?? 'Unknown');
                                
                                $d_name = !empty($p['department_name']) ? $p['department_name'] : 'Tanpa Departemen';
                                $pos_name = !empty($p['position_name']) ? $p['position_name'] : 'Tanpa Posisi';
                                $dept_pos = htmlspecialchars($d_name . " • " . $pos_name);
                                
                                $avatar = !empty($p['avatar']) ? ($base_url ?? '') . "/assets/img/avatars/" . htmlspecialchars($p['avatar']) : "https://api.dicebear.com/9.x/bottts-neutral/svg?seed=" . urlencode($safe_name);
                                $net_str = "Rp " . number_format($p['net_salary'], 0, ',', '.');
                                
                                $status = $p['status'];
                                $badge_bg = 'bg-gray-100'; $badge_text = 'text-gray-500'; $badge_label = 'Unknown';
                                if ($status === 'draft') { $badge_bg = 'bg-gray-100'; $badge_text = 'text-gray-500'; $badge_label = 'Draft'; }
                                if ($status === 'generated') { $badge_bg = 'bg-pending/10'; $badge_text = 'text-pending'; $badge_label = 'Diproses'; }
                                if ($status === 'paid') { $badge_bg = 'bg-success/10'; $badge_text = 'text-success'; $badge_label = 'Dibayar'; }
                            ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-gray-100 overflow-hidden shrink-0"><img src="<?= $avatar ?>" class="w-full h-full object-cover"></div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-bold text-gray-800"><?= $safe_name ?></h4>
                                                <p class="text-[10px] text-gray-500 font-medium mt-0.5 truncate"><?= $dept_pos ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right"><span class="text-xs font-bold text-primary"><?= $net_str ?></span></td>
                                    <td class="text-center"><span class="text-[9px] font-bold px-2 py-1 rounded-md <?= $badge_bg ?> <?= $badge_text ?> uppercase"><?= $badge_label ?></span></td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <!-- Lihat Detail -->
                                            <button onclick="openViewModal(<?= $p['id'] ?>)" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm" title="Lihat Slip"><i data-lucide="file-text" class="w-3.5 h-3.5"></i></button>
                                            
                                            <?php if ($can_manage_payroll && in_array($status, ['draft', 'generated'])): ?>
                                                <!-- Edit Manual -->
                                                <button onclick="openEditModal(<?= $p['id'] ?>)" class="p-2 bg-primary/10 text-primary rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm" title="Edit Manual"><i data-lucide="edit-3" class="w-3.5 h-3.5"></i></button>
                                            <?php endif; ?>
                                            
                                            <?php if ($can_manage_payroll && in_array($status, ['generated', 'paid'])): ?>
                                                <!-- Kembalikan ke Draft -->
                                                <button onclick="openConfirmModal(<?= $p['id'] ?>, 'mark_draft')" class="p-2 bg-pending/10 text-pending rounded-xl text-xs font-semibold hover:bg-pending hover:text-white transition shadow-sm" title="Kembalikan ke Draft"><i data-lucide="file-edit" class="w-3.5 h-3.5"></i></button>
                                            <?php endif; ?>

                                            <?php if ($can_manage_payroll && in_array($status, ['draft', 'generated'])): ?>
                                                <!-- Tandai Lunas -->
                                                <button onclick="openConfirmModal(<?= $p['id'] ?>, 'mark_paid')" class="p-2 bg-success/10 text-success rounded-xl text-xs font-semibold hover:bg-success hover:text-white transition shadow-sm" title="Tandai Dibayar"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<!-- ================= HYBRID MODAL (VIEW STRUK PAYSLIP) ================= -->
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="crudOverlay" onclick="closeCrud()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="crudCard" class="bg-surface w-full md:max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10"><i data-lucide="x" class="w-5 h-5"></i></button>
            <div id="crudContent" class="px-5 pb-8 md:p-8 overflow-y-auto w-full"></div>
        </div>
    </div>
</div>

<!-- ================= HYBRID MODAL (EDIT MANUAL) ================= -->
<div id="editModal" class="fixed inset-0 hidden" style="z-index: 99998;">
    <div id="editOverlay" onclick="closeEditModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="editCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeEditModal()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <button onclick="closeEditModal()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10"><i data-lucide="x" class="w-5 h-5"></i></button>
            
            <div class="px-6 pb-8 md:p-8 overflow-y-auto w-full">
                <div class="text-center mb-6 mt-2 md:mt-0">
                    <h3 class="text-base md:text-lg font-bold text-gray-800">Edit Manual Payslip</h3>
                    <p class="text-[11px] text-gray-500 mt-1">Ubah ringkasan pendapatan & potongan</p>
                </div>
                
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_payslip_id">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Gaji Pokok</label>
                            <input type="number" id="edit_basic" name="basic_salary" required onkeyup="calcNet()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Total Pendapatan Tambahan</label>
                            <input type="number" id="edit_earn" name="total_earnings" required onkeyup="calcNet()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Total Potongan</label>
                            <input type="number" id="edit_ded" name="total_deductions" required onkeyup="calcNet()" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-failed">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Take Home Pay (Otomatis)</label>
                            <input type="number" id="edit_net" readonly class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-xs font-black text-primary cursor-not-allowed">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-8 border-t border-gray-100 pt-6">
                        <button type="button" onclick="closeEditModal()" class="w-1/3 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">Batal</button>
                        <button type="submit" id="btnSaveEdit" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary/90 transition shadow-sm flex justify-center items-center gap-2">Simpan Edit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL KONFIRMASI (MARK AS PAID / DRAFT) ================= -->
<div id="confirmModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="confirmOverlay" onclick="closeConfirm()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="confirmCard" class="bg-surface w-full md:max-w-md rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh] p-6">
            <div class="pt-2 pb-4 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeConfirm()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <div id="confirmContent">
                <div class="text-center">
                    <div id="confirmIconBox" class="w-12 h-12 rounded-full mx-auto flex items-center justify-center mb-4"></div>
                    <h3 id="confirmTitle" class="text-lg font-bold text-gray-800"></h3>
                    <p id="confirmDesc" class="text-xs text-gray-500 mt-1 leading-relaxed"></p>
                    <div class="flex gap-3 mt-8">
                        <button onclick="closeConfirm()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold hover:bg-gray-200 transition active:scale-95">Batal</button>
                        <button id="btnConfirmAction" class="flex-1 py-3 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95"></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/toast.php'; ?>
<script>
    lucide.createIcons();
    const formatRp = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    const period = "<?= $period_str ?>";
    const baseUrl = "<?= $base_url ?? '' ?>";

    $(document).ready(function() {
        const table = $('#detailTable').DataTable({
            "dom": 't<"bottom"ip>', "pageLength": 15, "ordering": false,
            "language": { "emptyTable": "Tidak ada data gaji", "info": "Menampilkan _START_ s/d _END_", "paginate": { "previous": "Kembali", "next": "Lanjut" } }
        });
        $('#dtSearchInput').on('keyup', function() { table.search(this.value).draw(); });
        table.on('draw', function() { lucide.createIcons(); });
    });

    // ==========================================
    // LOGIKA MODAL VIEW STRUK GAJI
    // ==========================================
    const crudModal = document.getElementById('crudModal');
    const crudOverlay = document.getElementById('crudOverlay');
    const crudCard = document.getElementById('crudCard');
    const crudContent = document.getElementById('crudContent');

    window.openViewModal = function(id) {
        crudContent.innerHTML = `<div class="flex justify-center py-10"><i data-lucide="loader-2" class="w-8 h-8 animate-spin text-primary"></i></div>`;
        crudModal.classList.remove('hidden');
        lucide.createIcons();
        setTimeout(() => { crudOverlay.classList.remove('opacity-0'); crudCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0'); crudCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100'); }, 10);

        const formData = new FormData();
        formData.append('ajax_action', 'view'); formData.append('id', id);

        fetch(window.location.href, { method: 'POST', body: formData }).then(res => res.json()).then(res => {
            if(res.status === 'success') {
                const data = res.data;
                let earningsHtml = ''; let deductionsHtml = '';
                
                if(data.details && data.details.length > 0) {
                    data.details.forEach(det => {
                        if (det.type === 'earning') { earningsHtml += `<div class="flex justify-between py-1.5"><span class="text-[11px] font-medium text-gray-600">${det.name}</span><span class="text-xs font-bold text-gray-800">${formatRp(det.amount)}</span></div>`; } 
                        else { deductionsHtml += `<div class="flex justify-between py-1.5"><span class="text-[11px] font-medium text-gray-600">${det.name}</span><span class="text-xs font-bold text-failed">- ${formatRp(det.amount)}</span></div>`; }
                    });
                }
                if (!earningsHtml) earningsHtml = `<p class="text-[11px] text-gray-400 italic">Tidak ada pendapatan tambahan.</p>`;
                if (!deductionsHtml) deductionsHtml = `<p class="text-[11px] text-gray-400 italic py-1.5">Tidak ada potongan.</p>`;

                const bankInfo = (data.bank_name && data.bank_account) ? `${data.bank_name} - ${data.bank_account}` : 'Tunai / Belum Diatur';
                const logoHtml = data.tenant_logo ? `<img src="${baseUrl}/assets/img/tenants/${data.tenant_logo}" class="h-8 md:h-10 object-contain mx-auto mb-3">` : `<div class="w-10 h-10 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center mb-2"><i data-lucide="building" class="w-5 h-5"></i></div>`;
                const dName = data.department_name ? data.department_name : 'Tanpa Departemen';
                const pName = data.position_name ? data.position_name : 'Tanpa Posisi';

                crudContent.innerHTML = `
                    <div class="text-center mb-6 pt-2">
                        ${logoHtml}
                        <h3 class="text-lg font-black text-gray-800 tracking-tight uppercase">SLIP GAJI</h3>
                        <p class="text-[10px] font-bold text-primary bg-primary/10 inline-block px-3 py-1 rounded-full mt-2 tracking-widest uppercase">Periode ${period}</p>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 mb-5 border border-gray-100 flex justify-between items-center">
                        <div><h4 class="text-sm font-bold text-gray-800 mb-0.5">${data.employee_name}</h4><p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">${dName} • ${pName}</p></div>
                    </div>
                    <div class="receipt-bg rounded-2xl p-5 mb-5 shadow-inner border border-gray-100/50">
                        <h5 class="text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Pendapatan</h5>
                        <div class="space-y-1 mb-4 border-b border-dashed border-gray-200 pb-3">${earningsHtml}</div>
                        <h5 class="text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">Potongan</h5>
                        <div class="space-y-1 mb-4 border-b border-dashed border-gray-200 pb-3">${deductionsHtml}</div>
                        <div class="mt-2 pt-2 border-t border-gray-800 flex justify-between items-center">
                            <span class="text-[11px] font-black text-gray-800 uppercase tracking-widest">TAKE HOME PAY</span>
                            <span class="text-lg font-black text-primary">${formatRp(data.net_salary)}</span>
                        </div>
                    </div>
                    <div class="bg-blue-50/50 rounded-xl p-3 text-center border border-blue-100">
                        <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider">Metode Pembayaran</p>
                        <p class="text-xs font-bold text-gray-700 mt-0.5">${bankInfo}</p>
                    </div>
                    <button onclick="closeCrud()" class="w-full mt-6 py-3 border border-gray-200 text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-50 transition uppercase tracking-wider">Tutup Slip</button>
                `;
                lucide.createIcons();
            }
        });
    }

    window.closeCrud = function() {
        crudOverlay.classList.add('opacity-0'); crudCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); crudCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { crudModal.classList.add('hidden'); }, 300);
    }

    // ==========================================
    // LOGIKA MODAL EDIT MANUAL
    // ==========================================
    const editModal = document.getElementById('editModal');
    const editOverlay = document.getElementById('editOverlay');
    const editCard = document.getElementById('editCard');

    window.openEditModal = function(id) {
        document.getElementById('editForm').reset();
        document.getElementById('edit_payslip_id').value = id;
        
        editModal.classList.remove('hidden');
        setTimeout(() => { editOverlay.classList.remove('opacity-0'); editCard.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0'); editCard.classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100'); }, 10);

        const fd = new FormData(); fd.append('ajax_action', 'get_edit'); fd.append('id', id);
        fetch(window.location.href, { method: 'POST', body: fd }).then(res => res.json()).then(res => {
            if(res.status === 'success') {
                document.getElementById('edit_basic').value = parseFloat(res.data.basic_salary);
                document.getElementById('edit_earn').value = parseFloat(res.data.total_earnings);
                document.getElementById('edit_ded').value = parseFloat(res.data.total_deductions);
                calcNet();
            } else { window.showToast(res.message, 'error'); closeEditModal(); }
        });
    }

    window.closeEditModal = function() {
        editOverlay.classList.add('opacity-0'); editCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); editCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { editModal.classList.add('hidden'); }, 300);
    }

    window.calcNet = function() {
        let b = parseFloat(document.getElementById('edit_basic').value) || 0;
        let e = parseFloat(document.getElementById('edit_earn').value) || 0;
        let d = parseFloat(document.getElementById('edit_ded').value) || 0;
        document.getElementById('edit_net').value = Math.max(0, b + e - d);
    }

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSaveEdit');
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Menyimpan...'; lucide.createIcons();
        
        const fd = new FormData(this); fd.append('ajax_action', 'save_edit');
        fetch(window.location.href, { method: 'POST', body: fd }).then(res => res.json()).then(res => {
            if (res.status === 'success') { window.location.reload(); } else { window.showToast(res.message, 'error'); btn.disabled = false; btn.innerHTML = 'Simpan Edit'; }
        });
    });

    // ==========================================
    // LOGIKA MODAL KONFIRMASI (MARK AS PAID / DRAFT)
    // ==========================================
    let selectedPayslipId = null;
    let selectedAction = null;

    window.openConfirmModal = function(id, action) {
        selectedPayslipId = id;
        selectedAction = action;
        
        const isPaid = (action === 'mark_paid');
        document.getElementById('confirmIconBox').className = isPaid ? 'w-12 h-12 rounded-full mx-auto flex items-center justify-center mb-4 bg-success/10 text-success' : 'w-12 h-12 rounded-full mx-auto flex items-center justify-center mb-4 bg-pending/10 text-pending';
        document.getElementById('confirmIconBox').innerHTML = isPaid ? '<i data-lucide="check-circle" class="w-6 h-6"></i>' : '<i data-lucide="file-edit" class="w-6 h-6"></i>';
        document.getElementById('confirmTitle').innerText = isPaid ? 'Tandai Dibayar' : 'Kembalikan ke Draft';
        document.getElementById('confirmDesc').innerText = isPaid ? 'Apakah Anda yakin ingin mengubah status slip gaji ini menjadi Lunas/Ditransfer?' : 'Ubah status menjadi Draft agar data dapat diedit kembali?';
        
        const btn = document.getElementById('btnConfirmAction');
        btn.className = isPaid ? 'flex-1 py-3 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95 bg-success hover:bg-success/90' : 'flex-1 py-3 text-white rounded-xl text-sm font-bold transition shadow-sm active:scale-95 bg-pending hover:bg-pending/90';
        btn.innerText = isPaid ? 'Ya, Dibayar' : 'Ya, Draft';

        document.getElementById('confirmModal').classList.remove('hidden');
        lucide.createIcons();
        setTimeout(() => { document.getElementById('confirmOverlay').classList.remove('opacity-0'); document.getElementById('confirmCard').classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0'); document.getElementById('confirmCard').classList.add('translate-y-0', 'md:scale-100', 'md:opacity-100'); }, 10);
    }

    window.closeConfirm = function() {
        document.getElementById('confirmOverlay').classList.add('opacity-0'); document.getElementById('confirmCard').classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); document.getElementById('confirmCard').classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { document.getElementById('confirmModal').classList.add('hidden'); selectedPayslipId = null; }, 300);
    }

    document.getElementById('btnConfirmAction').addEventListener('click', function() {
        if (!selectedPayslipId) return;
        this.disabled = true; this.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline-block"></i> Memproses...'; lucide.createIcons();

        const fd = new FormData(); fd.append('ajax_action', selectedAction); fd.append('id', selectedPayslipId);
        fetch(window.location.href, { method: 'POST', body: fd }).then(res => res.json()).then(res => {
            if (res.status === 'success') { window.location.reload(); } else { window.showToast(res.message, 'error'); this.disabled = false; this.innerHTML = 'Gagal'; closeConfirm(); }
        });
    });
</script>
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>