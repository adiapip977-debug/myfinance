<?php
/**
 * MYFINANCE — Anggaran Bulanan (Monthly Budgets)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Filter Bulan dan Tahun Anggaran
$selectedMonth = (int)($_GET['month'] ?? date('n'));
$selectedYear = (int)($_GET['year'] ?? date('Y'));

if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('n');
if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = (int)date('Y');

// Ambil semua anggaran pengguna untuk periode ini
$sql = "SELECT b.*, c.name as category_name,
        (SELECT COALESCE(SUM(t.amount), 0) 
         FROM transactions t 
         WHERE t.user_id = b.user_id 
           AND t.category_id = b.category_id 
           AND t.type = 'expense' 
           AND MONTH(t.transaction_date) = b.month 
           AND YEAR(t.transaction_date) = b.year) as spent
        FROM budgets b
        JOIN categories c ON b.category_id = c.id
        WHERE b.user_id = :uid AND b.month = :m AND b.year = :y
        ORDER BY spent DESC, b.amount DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $userId, ':m' => $selectedMonth, ':y' => $selectedYear]);
$budgets = $stmt->fetchAll();

// Hitung total anggaran dan total pengeluaran terealisasi
$totalBudgeted = 0;
$totalSpentOnBudgets = 0;

foreach ($budgets as &$b) {
    $amount = (float)$b['amount'];
    $spent = (float)$b['spent'];
    $remaining = $amount - $spent;
    $percentage = $amount > 0 ? round(($spent / $amount) * 100, 1) : 0;

    $b['remaining'] = $remaining;
    $b['percentage'] = $percentage;

    $totalBudgeted += $amount;
    $totalSpentOnBudgets += $spent;
}
unset($b);

// Daftar Kategori Pengeluaran untuk dropdown
$expenseCategories = get_user_categories($pdo, $userId, 'expense');

$monthsMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$pageTitle = 'Anggaran Bulanan';
$pageHeading = 'Manajemen Anggaran';
$currentPage = 'anggaran';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">
            Anggaran Periode <?= $monthsMap[$selectedMonth] ?> <?= $selectedYear ?>
        </h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Tetapkan batas pengeluaran per kategori agar kondisi finansial tetap terkendali.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <!-- Filter Bulan/Tahun -->
        <form method="GET" action="anggaran.php" style="display: flex; gap: 8px;">
            <select name="month" class="form-control" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                <?php foreach ($monthsMap as $mNum => $mName): ?>
                    <option value="<?= $mNum ?>" <?= $selectedMonth === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                    <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>

        <button type="button" class="btn btn-primary" onclick="openAddBudgetModal()">
            <?= get_icon('plus', 'w-4 h-4') ?>
            <span>Pasang Anggaran</span>
        </button>
    </div>
</div>

<!-- Kartu Ringkasan Anggaran -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card balance">
        <div class="stat-top">
            <span class="stat-label">Total Anggaran Terencana</span>
            <div class="stat-icon purple"><?= get_icon('pie-chart') ?></div>
        </div>
        <div class="stat-amount"><?= format_rupiah($totalBudgeted) ?></div>
        <div class="stat-meta">
            <?= count($budgets) ?> kategori dianggarkan
        </div>
    </div>

    <div class="stat-card expense">
        <div class="stat-top">
            <span class="stat-label">Realisasi Pengeluaran</span>
            <div class="stat-icon red"><?= get_icon('trending-down') ?></div>
        </div>
        <div class="stat-amount" style="color: var(--color-red);"><?= format_rupiah($totalSpentOnBudgets) ?></div>
        <div class="stat-meta">
            <?= $totalBudgeted > 0 ? round(($totalSpentOnBudgets / $totalBudgeted) * 100, 1) : 0 ?>% dari total batas anggaran
        </div>
    </div>

    <div class="stat-card income">
        <div class="stat-top">
            <span class="stat-label">Sisa Anggaran Tersedia</span>
            <div class="stat-icon green"><?= get_icon('check-circle') ?></div>
        </div>
        <div class="stat-amount" style="color: <?= ($totalBudgeted - $totalSpentOnBudgets) >= 0 ? 'var(--color-green)' : 'var(--color-red)' ?>;">
            <?= format_rupiah($totalBudgeted - $totalSpentOnBudgets) ?>
        </div>
        <div class="stat-meta">
            Tersedia untuk sisa hari bulan ini
        </div>
    </div>
</div>

<!-- Grid Kartu Anggaran per Kategori -->
<?php if (empty($budgets)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon"><?= get_icon('pie-chart', 'w-8 h-8') ?></div>
            <h4 class="empty-title">Belum Ada Anggaran untuk Periode Ini</h4>
            <p class="empty-description">Buat batasan anggaran untuk pos-pos pengeluaran utama seperti makanan, transportasi, belanja, dan tagihan.</p>
            <button class="btn btn-primary btn-sm" onclick="openAddBudgetModal()">Mulai Buat Anggaran</button>
        </div>
    </div>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($budgets as $b): ?>
            <?php
                $pct = $b['percentage'];
                $barClass = 'normal';
                if ($pct >= 100) {
                    $barClass = 'danger';
                } elseif ($pct >= 80) {
                    $barClass = 'warning';
                }
            ?>
            <div class="card" style="margin-bottom: 0;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h4 style="font-size: 16px; font-weight: 700; color: var(--color-navy);"><?= htmlspecialchars($b['category_name']) ?></h4>
                        <div style="font-size: 13px; color: var(--color-text-muted); margin-top: 2px;">
                            Batas Anggaran: <strong><?= format_rupiah($b['amount']) ?></strong>
                        </div>
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditBudgetModal(<?= json_encode($b) ?>)'>
                            <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                        </button>
                        <form method="POST" action="actions/anggaran_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus anggaran kategori ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-icon" title="Hapus" style="color: var(--color-red);">
                                <?= get_icon('trash', 'w-4 h-4') ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container" style="height: 10px; margin: 12px 0;">
                    <div class="progress-bar <?= $barClass ?>" style="width: <?= min(100, $pct) ?>%;"></div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-top: 8px;">
                    <div>
                        <span style="color: var(--color-text-muted);">Terpakai: </span>
                        <strong style="color: <?= $pct >= 100 ? 'var(--color-red)' : 'var(--color-navy)' ?>;"><?= format_rupiah($b['spent']) ?></strong>
                        <span style="color: var(--color-text-muted);">(<?= $pct ?>%)</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted);">Sisa: </span>
                        <strong style="color: <?= $b['remaining'] >= 0 ? 'var(--color-green)' : 'var(--color-red)' ?>;">
                            <?= format_rupiah($b['remaining']) ?>
                        </strong>
                    </div>
                </div>

                <?php if ($pct >= 100): ?>
                    <div style="margin-top: 10px; padding: 6px 10px; background-color: var(--color-red-light); color: var(--color-red); font-size: 12px; font-weight: 600; border-radius: var(--radius-sm); display: flex; align-items: center; gap: 6px;">
                        <?= get_icon('alert-circle', 'w-4 h-4') ?>
                        <span>Anggaran telah terlampaui sebesar <?= format_rupiah(abs($b['remaining'])) ?>!</span>
                    </div>
                <?php elseif ($pct >= 80): ?>
                    <div style="margin-top: 10px; padding: 6px 10px; background-color: var(--color-orange-light); color: #92400E; font-size: 12px; font-weight: 600; border-radius: var(--radius-sm); display: flex; align-items: center; gap: 6px;">
                        <?= get_icon('alert-triangle', 'w-4 h-4') ?>
                        <span>Peringatan: Pemakaian telah mencapai <?= $pct ?>% batas anggaran.</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODAL TAMBAH / EDIT ANGGARAN -->
<div class="modal-overlay" id="modalBudgetForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="budgetModalTitle">Pasang Anggaran Bulanan</h3>
            <button class="modal-close" onclick="closeModal('modalBudgetForm')">&times;</button>
        </div>
        <form method="POST" action="actions/anggaran_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="budgetFormAction" value="create">
            <input type="hidden" name="id" id="budgetFormId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Kategori Pengeluaran <span class="required">*</span></label>
                    <select name="category_id" id="budgetCategoryId" class="form-control" required>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Bulan Anggaran <span class="required">*</span></label>
                        <select name="month" id="budgetMonth" class="form-control">
                            <?php foreach ($monthsMap as $mNum => $mName): ?>
                                <option value="<?= $mNum ?>" <?= $selectedMonth === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tahun Anggaran <span class="required">*</span></label>
                        <select name="year" id="budgetYear" class="form-control">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                                <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Batas Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" id="budgetAmount" class="form-control rupiah-input" placeholder="Contoh: 1.000.000" required>
                    </div>
                    <small style="color: #64748B; font-size: 12px; margin-top: 4px; display: block;">Maksimal pengeluaran yang diizinkan untuk kategori ini.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalBudgetForm')">Batal</button>
                <button type="submit" class="btn btn-primary" id="budgetSubmitBtn">Simpan Anggaran</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddBudgetModal() {
    document.getElementById('budgetModalTitle').innerText = 'Pasang Anggaran Bulanan';
    document.getElementById('budgetFormAction').value = 'create';
    document.getElementById('budgetFormId').value = '';
    document.getElementById('budgetAmount').value = '';
    document.getElementById('budgetSubmitBtn').innerText = 'Simpan Anggaran';
    openModal('modalBudgetForm');
}

function openEditBudgetModal(data) {
    document.getElementById('budgetModalTitle').innerText = 'Edit Anggaran Kategori';
    document.getElementById('budgetFormAction').value = 'update';
    document.getElementById('budgetFormId').value = data.id;
    document.getElementById('budgetCategoryId').value = data.category_id;
    document.getElementById('budgetMonth').value = data.month;
    document.getElementById('budgetYear').value = data.year;
    document.getElementById('budgetAmount').value = formatThousands(Math.round(data.amount));
    document.getElementById('budgetSubmitBtn').innerText = 'Perbarui Anggaran';
    openModal('modalBudgetForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

