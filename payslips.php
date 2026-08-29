<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/components/auth.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// ==============================================================================
// LOGIKA HAK AKSES (ROLE-BASED)
// ==============================================================================
$role_name_session = strtolower($_SESSION['role'] ?? '');
$can_view_all = in_array($role_name_session, ['admin', 'superadmin', 'hr', 'finance', 'manager']);
$can_manage_payroll = in_array($role_name_session, ['admin', 'superadmin', 'hr', 'finance']);

// ==============================================================================
// PENANGANAN AJAX GENERATE PAYROLL (KHUSUS HR/FINANCE)
// ==============================================================================
if (isset($_REQUEST['ajax_action']) && $_REQUEST['ajax_action'] === 'generate') {
    header('Content-Type: application/json');
    try {
        if (!$can_manage_payroll) throw new Exception("Akses ditolak!");
        
        $month = (int)$_POST['month'];
        $year = (int)$_POST['year'];
        
        $stmtTS = $pdo->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ?");
        $stmtTS->execute([$tenant_id]);
        $t_set = $stmtTS->fetch(PDO::FETCH_ASSOC);
        if (!$t_set) throw new Exception("Pengaturan Perusahaan belum diisi.");

        $co_start = (int)($t_set['payroll_cutoff_start'] ?? 1);
        $co_end = (int)($t_set['payroll_cutoff_end'] ?? 31);
        
        if ($co_start > $co_end) {
            $start_dt = new DateTime("$year-$month-01");
            $start_dt->modify('-1 month');
            $start_dt->setDate((int)$start_dt->format('Y'), (int)$start_dt->format('m'), $co_start);
            $end_dt = new DateTime("$year-$month-01");
            $end_dt->setDate((int)$end_dt->format('Y'), (int)$end_dt->format('m'), $co_end);
        } else {
            $start_dt = new DateTime("$year-$month-01");
            $start_dt->setDate((int)$start_dt->format('Y'), (int)$start_dt->format('m'), $co_start);
            $end_dt = new DateTime("$year-$month-01");
            $last_day = (int)$end_dt->format('t'); 
            $end_dt->setDate((int)$end_dt->format('Y'), (int)$end_dt->format('m'), min($co_end, $last_day));
        }
        
        $start_date = $start_dt->format('Y-m-d');
        $end_date = $end_dt->format('Y-m-d');

        $total_work_days = 0;
        $current_dt = clone $start_dt;
        while ($current_dt <= $end_dt) {
            if ((int)$current_dt->format('N') <= 5) $total_work_days++;
            $current_dt->modify('+1 day');
        }
        if ($total_work_days == 0) $total_work_days = 22; 

        $stmtEmp = $pdo->prepare("SELECT u.id, us.basic_salary, us.overtime_rate FROM users u JOIN user_salaries us ON u.id = us.user_id WHERE u.tenant_id = ? AND u.deleted_at IS NULL");
        $stmtEmp->execute([$tenant_id]);
        $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
        
        $generated_count = 0; $skipped_count = 0;
        $pdo->beginTransaction();
        
        foreach ($employees as $emp) {
            $uid = $emp['id'];
            
            $stmtCheck = $pdo->prepare("SELECT id FROM payslips WHERE user_id = ? AND month = ? AND year = ? AND tenant_id = ?");
            $stmtCheck->execute([$uid, $month, $year, $tenant_id]);
            if ($stmtCheck->fetch()) { $skipped_count++; continue; }

            $stmtAtt = $pdo->prepare("SELECT COUNT(DISTINCT date) FROM attendances WHERE user_id = ? AND date BETWEEN ? AND ? AND tenant_id = ?");
            $stmtAtt->execute([$uid, $start_date, $end_date, $tenant_id]);
            $total_attendances = (int)$stmtAtt->fetchColumn();

            $stmtLeave = $pdo->prepare("SELECT SUM(total_days) FROM leave_requests WHERE user_id = ? AND start_date BETWEEN ? AND ? AND status = 'approved' AND tenant_id = ?");
            $stmtLeave->execute([$uid, $start_date, $end_date, $tenant_id]);
            $total_leaves = (int)$stmtLeave->fetchColumn();

            if ($total_attendances == 0 && $total_leaves == 0) {
                $basic_salary = 0; 
                $ot_amount = 0; $rb_amount = 0; $meal_amount = 0; $transport_amount = 0;
                $deduction_alpha = 0; $deduction_bpjs_kes = 0; $deduction_bpjs_tk = 0; $total_ot_minutes = 0;
            } else {
                $alpha_days = max(0, $total_work_days - ($total_attendances + $total_leaves));
                $basic_salary = (float)$emp['basic_salary'];

                $stmtOt = $pdo->prepare("SELECT SUM(duration_minutes) FROM overtime_requests WHERE user_id = ? AND date BETWEEN ? AND ? AND status = 'approved' AND tenant_id = ? AND deleted_at IS NULL");
                $stmtOt->execute([$uid, $start_date, $end_date, $tenant_id]);
                $total_ot_minutes = (int)$stmtOt->fetchColumn();
                $ot_amount = ($total_ot_minutes / 60) * (float)$emp['overtime_rate'];

                $stmtRb = $pdo->prepare("SELECT SUM(amount) FROM reimbursement_requests WHERE user_id = ? AND date BETWEEN ? AND ? AND status = 'approved' AND tenant_id = ? AND deleted_at IS NULL");
                $stmtRb->execute([$uid, $start_date, $end_date, $tenant_id]);
                $rb_amount = (float)$stmtRb->fetchColumn();

                $meal_amount = $total_attendances * (float)($t_set['payroll_meal_allowance'] ?? 0);
                $transport_amount = $total_attendances * (float)($t_set['payroll_transport_allowance'] ?? 0);

                $deduction_alpha = 0;
                $alpha_method = $t_set['payroll_alpha_method'] ?? 'none';
                if ($alpha_method === 'prorata') $deduction_alpha = ($basic_salary / $total_work_days) * $alpha_days;
                else if ($alpha_method === 'fixed') $deduction_alpha = (float)($t_set['payroll_alpha_nominal'] ?? 0) * $alpha_days;

                $deduction_bpjs_kes = 0; $deduction_bpjs_tk = 0;
                if (!empty($t_set['payroll_bpjs_enabled'])) {
                    $deduction_bpjs_kes = $basic_salary * ((float)($t_set['bpjs_kesehatan_percent'] ?? 1) / 100);
                    $deduction_bpjs_tk = $basic_salary * ((float)($t_set['bpjs_ketenagakerjaan_percent'] ?? 3) / 100);
                }
            }

            $total_earnings = $ot_amount + $rb_amount + $meal_amount + $transport_amount;
            $total_deductions = $deduction_alpha + $deduction_bpjs_kes + $deduction_bpjs_tk;
            $net_salary = max(0, $basic_salary + $total_earnings - $total_deductions); 

            if ($net_salary < 0) {
                $deduction_bpjs_kes = 0; $deduction_bpjs_tk = 0;
                $total_deductions = $deduction_alpha;
                $net_salary = $basic_salary + $total_earnings - $total_deductions;
                if ($net_salary < 0) {
                    $deduction_alpha = $basic_salary + $total_earnings;
                    $total_deductions = $deduction_alpha;
                    $net_salary = 0;
                }
            }

            $stmtInsert = $pdo->prepare("INSERT INTO payslips (tenant_id, user_id, month, year, basic_salary, total_earnings, total_deductions, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'generated')");
            $stmtInsert->execute([$tenant_id, $uid, $month, $year, $basic_salary, $total_earnings, $total_deductions, $net_salary]);
            $payslip_id = $pdo->lastInsertId();
            
            $stmtDet = $pdo->prepare("INSERT INTO payslip_details (payslip_id, type, name, amount) VALUES (?, ?, ?, ?)");
            
            if ($total_attendances == 0 && $total_leaves == 0) {
                $stmtDet->execute([$payslip_id, 'earning', 'Gaji Pokok (Tidak Masuk)', 0]);
            } else {
                $stmtDet->execute([$payslip_id, 'earning', 'Gaji Pokok', $basic_salary]);
                if ($ot_amount > 0) $stmtDet->execute([$payslip_id, 'earning', 'Uang Lembur', $ot_amount]);
                if ($rb_amount > 0) $stmtDet->execute([$payslip_id, 'earning', 'Reimbursement', $rb_amount]);
                if ($meal_amount > 0) $stmtDet->execute([$payslip_id, 'earning', "Tunjangan Makan", $meal_amount]);
                if ($transport_amount > 0) $stmtDet->execute([$payslip_id, 'earning', "Tunjangan Transport", $transport_amount]);
                
                if ($deduction_alpha > 0) $stmtDet->execute([$payslip_id, 'deduction', "Potongan Mangkir", $deduction_alpha]);
                if ($deduction_bpjs_kes > 0) $stmtDet->execute([$payslip_id, 'deduction', "BPJS Kesehatan", $deduction_bpjs_kes]);
                if ($deduction_bpjs_tk > 0) $stmtDet->execute([$payslip_id, 'deduction', "BPJS Ketenagakerjaan", $deduction_bpjs_tk]);
            }
            $generated_count++;
        }
        $pdo->commit();
        
        // Simpan pesan ke session agar dibaca komponen toast.php setelah reload
        if ($generated_count > 0) {
            $_SESSION['toast_msg'] = "$generated_count Slip berhasil dibuat. ($skipped_count terlewati)";
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_msg'] = "Tidak ada data baru yang diproses.";
            $_SESSION['toast_type'] = "warning";
        }
        echo json_encode(['status' => 'success']);
        exit;
    } catch (Exception $e) {
        if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ==============================================================================
// MENGAMBIL DATA UNTUK VIEW BATCH
// ==============================================================================
if ($can_view_all) {
    $stmt = $pdo->prepare("SELECT month, year, COUNT(id) as total_employees, SUM(net_salary) as total_net FROM payslips WHERE tenant_id = ? GROUP BY month, year ORDER BY year DESC, month DESC");
    $stmt->execute([$tenant_id]);
} else {
    $stmt = $pdo->prepare("SELECT month, year, 1 as total_employees, net_salary as total_net FROM payslips WHERE tenant_id = ? AND user_id = ? ORDER BY year DESC, month DESC");
    $stmt->execute([$tenant_id, $user_id]);
}
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/head.php';
$month_names = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
?>

<?php require_once __DIR__ . '/components/sidebar.php'; ?>

<div class="flex-1 overflow-y-auto relative w-full overflow-x-hidden bg-surface md:bg-transparent">
    <main class="w-full min-h-screen pb-24 md:pb-8 md:px-6">
        <div class="hidden md:block"><?php require_once __DIR__ . '/components/header.php'; ?></div>

        <div class="px-5 md:px-0 mt-6 md:mt-2 w-full mx-auto">
            <div class="flex justify-between items-center px-1 mb-6">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 tracking-tight">Daftar Payroll</h2>
                    <p class="text-[11px] md:text-xs text-gray-500 mt-0.5">Pilih periode untuk melihat slip gaji.</p>
                </div>
                <?php if ($can_manage_payroll): ?>
                    <button onclick="openGenerateModal()" class="bg-primary/10 text-primary px-4 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 hover:bg-primary hover:text-surface transition active:scale-95">
                        <i data-lucide="zap" class="w-4 h-4"></i> <span class="hidden md:inline">Generate Payroll</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- List of Batches (Cards) -->
            <div class="space-y-4">
                <?php if(empty($batches)): ?>
                    <div class="text-center bg-gray-50 border border-gray-100 rounded-2xl py-10">
                        <i data-lucide="folder-open" class="w-10 h-10 text-gray-300 mx-auto mb-3"></i>
                        <p class="text-sm font-semibold text-gray-500">Belum ada riwayat gaji.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($batches as $b): ?>
                        <a href="<?= ($base_url ?? '') ?>/payslips_detail/<?= $b['month'] ?>/<?= $b['year'] ?>" class="block bg-surface md:border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition group">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-primary/5 text-primary rounded-full flex items-center justify-center border border-primary/10">
                                        <i data-lucide="calendar-check" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest"><?= $month_names[$b['month']] ?> <?= $b['year'] ?></h3>
                                        <p class="text-[10px] text-gray-500 font-semibold mt-1">Total: <?= $b['total_employees'] ?> Karyawan</p>
                                    </div>
                                </div>
                                <div class="text-right flex items-center gap-4">
                                    <div class="hidden md:block">
                                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Total Take Home Pay</p>
                                        <p class="text-sm font-bold text-primary">Rp <?= number_format($b['total_net'], 0, ',', '.') ?></p>
                                    </div>
                                    <i data-lucide="chevron-right" class="w-5 h-5 text-gray-300 group-hover:text-primary transition"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- MODAL GENERATE -->
<div id="generateModal" class="fixed inset-0 hidden" style="z-index: 99999;">
    <div id="genOverlay" onclick="closeGenerateModal()" class="absolute inset-0 bg-gray-900/40 opacity-0 transition-opacity duration-300 backdrop-blur-sm cursor-pointer"></div>
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
        <div id="genCard" class="bg-surface w-full max-w-sm rounded-3xl shadow-2xl transform scale-95 opacity-0 transition-all duration-300 pointer-events-auto relative p-6">
            <div class="text-center mb-6"><h3 class="text-lg font-bold text-gray-800">Generate Payroll Baru</h3></div>
            <form id="generateForm">
                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Bulan</label>
                        <select name="month" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800">
                            <?php $currM = date('n'); foreach($month_names as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $currM ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-600 mb-1.5 uppercase">Tahun</label>
                        <select name="year" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800">
                            <?php $currY = date('Y'); for($i = $currY - 1; $i <= $currY; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $currY ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeGenerateModal()" class="flex-1 py-3 bg-gray-100 text-gray-600 rounded-xl text-sm font-semibold">Batal</button>
                    <button type="submit" id="btnGenSubmit" class="flex-1 py-3 bg-primary text-white rounded-xl text-sm font-bold flex items-center justify-center">Mulai Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/toast.php'; ?>
<script>
    lucide.createIcons();
    const generateModal = document.getElementById('generateModal');
    const genOverlay = document.getElementById('genOverlay');
    const genCard = document.getElementById('genCard');
    
    function openGenerateModal() {
        generateModal.classList.remove('hidden');
        setTimeout(() => { genOverlay.classList.remove('opacity-0'); genCard.classList.remove('scale-95', 'opacity-0'); genCard.classList.add('scale-100', 'opacity-100'); }, 10);
    }
    function closeGenerateModal() {
        genOverlay.classList.add('opacity-0'); genCard.classList.remove('scale-100', 'opacity-100'); genCard.classList.add('scale-95', 'opacity-0'); 
        setTimeout(() => { generateModal.classList.add('hidden'); }, 300);
    }

    document.getElementById('generateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnGenSubmit');
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Memproses...';
        lucide.createIcons();
        
        const formData = new FormData(this);
        formData.append('ajax_action', 'generate');

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json()).then(res => {
            if (res.status === 'success') { 
                // Reload halaman, session toast akan muncul setelah reload selesai via components/toast.php
                window.location.reload(); 
            } else { 
                if(typeof window.showToast === 'function') window.showToast(res.message, 'warning'); 
                btn.disabled = false; btn.innerHTML = 'Mulai Proses'; 
            }
        }).catch(() => { 
            if(typeof window.showToast === 'function') window.showToast("Error server", 'error'); 
            btn.disabled = false; btn.innerHTML = 'Mulai Proses'; 
        });
    });
</script>
<?php require_once __DIR__ . '/components/pwa_init.php'; ?>
</body>
</html>