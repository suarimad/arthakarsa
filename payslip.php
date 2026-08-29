<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==========================================
// PENANGANAN AJAX: VIEW STRUK GAJI DI MODAL
// ==========================================
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'view') {
    header('Content-Type: application/json');
    $id = $_POST['id'];
    
    // Pastikan user hanya bisa melihat slip gajinya sendiri yang sudah berstatus 'paid'
    $query = "
        SELECT p.*, u.name as employee_name, pos.name as position_name, d.name as department_name, us.bank_name, us.bank_account
        FROM payslips p 
        LEFT JOIN users u ON p.user_id = u.id 
        LEFT JOIN positions pos ON u.position_id = pos.id
        LEFT JOIN departments d ON pos.department_id = d.id
        LEFT JOIN user_salaries us ON u.id = us.user_id
        WHERE p.id = ? AND p.tenant_id = ? AND p.user_id = ? AND p.status = 'paid'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id, $tenant_id, $user_id]);
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
        echo json_encode(['status' => 'error', 'message' => 'Slip gaji tidak ditemukan atau belum dibayar.']);
    }
    exit;
}

// ==========================================
// MENGAMBIL DATA PAYSLIP USER
// ==========================================
$stmt = $pdo->prepare("
    SELECT p.*, u.name as employee_name, u.avatar, d.name as department_name, pos.name as position_name
    FROM payslips p 
    LEFT JOIN users u ON p.user_id = u.id 
    LEFT JOIN positions pos ON u.position_id = pos.id
    LEFT JOIN departments d ON pos.department_id = d.id
    WHERE p.tenant_id = ? AND p.user_id = ? AND p.status = 'paid'
    ORDER BY p.year DESC, p.month DESC
");
$stmt->execute([$tenant_id, $user_id]);
$payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';
$month_names = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];

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
</style>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden">
    <main class="w-full bg-surface md:bg-transparent min-h-screen pb-24 md:pb-8 md:px-6">
        <div class="hidden md:block"><?php require_once __DIR__ . '/components/header.php'; ?></div>

        <div class="px-5 md:px-0 space-y-5 md:space-y-6 mt-6 md:mt-2 relative z-0">
            <div class="flex items-center gap-3 px-1 mb-2">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Slip Gaji Saya</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Riwayat slip gaji Anda yang telah diterbitkan.</p>
                </div>
            </div>

            <div class="relative z-0">
                <i data-lucide="search" class="w-4 h-4 absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="dtSearchInput" placeholder="Cari periode bulan atau tahun..." class="w-full pl-11 pr-4 py-3 bg-surface md:bg-white border border-gray-200 md:border-gray-100 rounded-2xl text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition shadow-sm">
            </div>

            <section class="bg-surface md:border border-gray-100 rounded-2xl md:shadow-sm overflow-hidden pb-2 md:pb-0">
                <div class="overflow-x-auto">
                    <table id="payslipTable" class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Periode</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Take Home Pay</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-center">Status</th>
                                <th class="text-[10px] font-bold text-gray-500 uppercase tracking-wider text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payslips as $p): 
                                $period_str = $month_names[$p['month']] . " " . $p['year'];
                                $net_str = "Rp " . number_format($p['net_salary'], 0, ',', '.');
                                $payment_date = !empty($p['payment_date']) ? date('d M Y', strtotime($p['payment_date'])) : '-';
                            ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                                <i data-lucide="calendar-check" class="w-4 h-4"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-widest"><?= $period_str ?></h4>
                                                <p class="text-[10px] text-gray-500 font-medium mt-0.5 truncate">Dibayar: <?= $payment_date ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right"><span class="text-xs font-bold text-primary"><?= $net_str ?></span></td>
                                    <td class="text-center">
                                        <span class="text-[9px] font-bold px-2 py-1 rounded-md bg-success/10 text-success uppercase">Dibayar</span>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1.5">
                                            <!-- Lihat Detail Modal -->
                                            <button onclick="openViewModal(<?= $p['id'] ?>, '<?= $period_str ?>')" class="p-2 bg-gray-50 text-gray-600 rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm" title="Lihat Slip">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            </button>
                                            
                                            <!-- Download PDF -->
                                            <a href="<?= ($base_url ?? '') ?>/payslip_view?id=<?= $p['id'] ?>" target="_blank" class="p-2 bg-primary/10 text-primary rounded-xl text-xs font-semibold hover:bg-primary hover:text-white transition shadow-sm flex items-center justify-center gap-1.5 px-3" title="Download PDF">
                                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                                <span class="hidden md:inline">Download</span>
                                            </a>
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
<div id="crudModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="crudOverlay" onclick="closeCrud()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
        <div id="crudCard" class="bg-surface w-full md:max-w-lg rounded-t-3xl md:rounded-3xl shadow-2xl transform translate-y-full md:translate-y-0 md:scale-95 opacity-100 md:opacity-0 transition-all duration-300 pointer-events-auto relative flex flex-col max-h-[90vh]">
            <div class="pt-5 pb-2 md:hidden flex justify-center cursor-pointer shrink-0" onclick="closeCrud()"><div class="w-12 h-1.5 bg-gray-200 rounded-full"></div></div>
            <button onclick="closeCrud()" class="hidden md:flex absolute top-4 right-4 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-200 transition p-1.5 rounded-full z-10"><i data-lucide="x" class="w-5 h-5"></i></button>
            <div id="crudContent" class="px-5 pb-8 md:p-8 overflow-y-auto w-full"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/bottom-nav.php'; ?>
<?php require_once __DIR__ . '/components/toast.php'; ?>

<script>
    lucide.createIcons();
    const formatRp = (angka) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    const baseUrl = "<?= $base_url ?? '' ?>";

    $(document).ready(function() {
        const table = $('#payslipTable').DataTable({
            "dom": 't<"bottom"ip>', 
            "pageLength": 10, 
            "ordering": false,
            "language": { 
                "emptyTable": "Belum ada riwayat slip gaji.", 
                "info": "Menampilkan _START_ s/d _END_", 
                "paginate": { "previous": "Kembali", "next": "Lanjut" } 
            }
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

    window.openViewModal = function(id, periodStr) {
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
                        <p class="text-[10px] font-bold text-primary bg-primary/10 inline-block px-3 py-1 rounded-full mt-2 tracking-widest uppercase">Periode ${periodStr}</p>
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
                    <a href="${baseUrl}/payslip_view?id=${data.id}" target="_blank" class="w-full mt-6 py-3 bg-primary text-surface rounded-xl text-sm font-bold hover:bg-primary/90 transition shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i> Download PDF
                    </a>
                    <button onclick="closeCrud()" class="w-full mt-3 py-3 border border-gray-200 text-gray-600 rounded-xl text-sm font-bold hover:bg-gray-50 transition uppercase tracking-wider">Tutup</button>
                `;
                lucide.createIcons();
            } else {
                window.showToast(res.message, 'error');
                closeCrud();
            }
        }).catch(() => {
            window.showToast("Gagal mengambil data", 'error');
            closeCrud();
        });
    }

    window.closeCrud = function() {
        crudOverlay.classList.add('opacity-0'); crudCard.classList.remove('translate-y-0', 'md:scale-100', 'md:opacity-100'); crudCard.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0'); 
        setTimeout(() => { crudModal.classList.add('hidden'); }, 300);
    }
</script>

<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>