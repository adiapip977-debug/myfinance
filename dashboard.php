<?php
/**
 * MYFINANCE — Dashboard Utama
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

// 1. Data Ringkasan Keuangan Bulan Ini & Keseluruhan
$summary = get_financial_summary($pdo, $userId, $currentMonth, $currentYear);

// 2. Lima Transaksi Terbaru
$stmtRecent = $pdo->prepare("SELECT t.*, c.name as category_name, a.name as account_name 
                             FROM transactions t 
                             LEFT JOIN categories c ON t.category_id = c.id 
                             LEFT JOIN accounts a ON t.account_id = a.id 
                             WHERE t.user_id = ? 
                             ORDER BY t.transaction_date DESC, t.id DESC 
                             LIMIT 5");
$stmtRecent->execute([$userId]);
$recentTransactions = $stmtRecent->fetchAll();

// 3. Data Grafik Tren Harian Bulan Ini
$stmtDaily = $pdo->prepare("SELECT 
    DAY(transaction_date) as day_num,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as daily_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as daily_expense
    FROM transactions 
    WHERE user_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?
    GROUP BY DAY(transaction_date)
    ORDER BY day_num ASC");
$stmtDaily->execute([$userId, $currentMonth, $currentYear]);
$dailyRows = $stmtDaily->fetchAll();

// Susun array untuk 1 bulan penuh
$daysInMonth = (int)date('t');
$chartDays = [];
$chartDailyIncome = [];
$chartDailyExpense = [];

$dailyDataMap = [];
foreach ($dailyRows as $dr) {
    $dailyDataMap[$dr['day_num']] = [
        'income'  => (float)$dr['daily_income'],
        'expense' => (float)$dr['daily_expense']
    ];
}

for ($d = 1; $d <= $daysInMonth; $d++) {
    $chartDays[] = "Tgl " . $d;
    $chartDailyIncome[] = $dailyDataMap[$d]['income'] ?? 0;
    $chartDailyExpense[] = $dailyDataMap[$d]['expense'] ?? 0;
}

// 4. Data Diagram Pengeluaran berdasarkan Kategori Bulan Ini
$stmtCatExpense = $pdo->prepare("SELECT c.name as category_name, SUM(t.amount) as total_cat 
                                 FROM transactions t 
                                 JOIN categories c ON t.category_id = c.id 
                                 WHERE t.user_id = ? AND t.type = 'expense' AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?
                                 GROUP BY c.id, c.name 
                                 ORDER BY total_cat DESC");
$stmtCatExpense->execute([$userId, $currentMonth, $currentYear]);
$catExpenseRows = $stmtCatExpense->fetchAll();

$chartCatLabels = [];
$chartCatTotals = [];
foreach ($catExpenseRows as $cr) {
    $chartCatLabels[] = $cr['category_name'];
    $chartCatTotals[] = (float)$cr['total_cat'];
}

// 5. Ringkasan Anggaran yang Mendekati Batas (>= 80%)
$stmtBudgets = $pdo->prepare("SELECT b.*, c.name as category_name,
    (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t 
     WHERE t.user_id = b.user_id AND t.category_id = b.category_id AND t.type = 'expense' AND MONTH(t.transaction_date) = b.month AND YEAR(t.transaction_date) = b.year) as spent
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND b.month = ? AND b.year = ?");
$stmtBudgets->execute([$userId, $currentMonth, $currentYear]);
$allBudgets = $stmtBudgets->fetchAll();

$warningBudgets = [];
foreach ($allBudgets as $b) {
    $limit = (float)$b['amount'];
    $spent = (float)$b['spent'];
    $pct = $limit > 0 ? ($spent / $limit) * 100 : 0;
    if ($pct >= 80) {
        $b['percent'] = $pct;
        $warningBudgets[] = $b;
    }
}

// 6. Ringkasan Target Tabungan
$stmtGoals = $pdo->prepare("SELECT g.*, 
    (SELECT COALESCE(SUM(c.amount), 0) FROM savings_contributions c WHERE c.goal_id = g.id) as collected
    FROM savings_goals g
    WHERE g.user_id = ? AND g.status = 'in_progress'
    ORDER BY g.id DESC
    LIMIT 3");
$stmtGoals->execute([$userId]);
$savingsGoals = $stmtGoals->fetchAll();

// Kategori & Akun untuk Modal Quick Add
$userCategoriesIncome = get_user_categories($pdo, $userId, 'income');
$userCategoriesExpense = get_user_categories($pdo, $userId, 'expense');
$userAccounts = get_user_accounts($pdo, $userId);

$pageTitle = 'Dashboard Keuangan';
$pageHeading = 'Dashboard Keuangan';
$currentPage = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Tombol Aksi Cepat (Quick Actions) -->
<div class="quick-actions">
    <button type="button" class="btn btn-success" onclick="openModal('modalIncome')">
        <?= get_icon('plus', 'w-4 h-4') ?>
        <span>Tambah Pemasukan</span>
    </button>
    <button type="button" class="btn btn-danger" onclick="openModal('modalExpense')">
        <?= get_icon('plus', 'w-4 h-4') ?>
        <span>Tambah Pengeluaran</span>
    </button>
    <a href="transaksi.php" class="btn btn-secondary">
        <?= get_icon('file-text', 'w-4 h-4') ?>
        <span>Lihat Semua Transaksi</span>
    </a>
    <a href="laporan.php" class="btn btn-secondary">
        <?= get_icon('trending-up', 'w-4 h-4') ?>
        <span>Lihat Laporan</span>
    </a>
</div>

<!-- 4 Kartu Metrik Keuangan Utama -->
<div class="stats-grid">
    <!-- Total Saldo -->
    <div class="stat-card balance">
        <div class="stat-top">
            <span class="stat-label">Total Saldo Saat Ini</span>
            <div class="stat-icon purple"><?= get_icon('wallet') ?></div>
        </div>
        <div class="stat-amount"><?= format_rupiah($summary['total_balance']) ?></div>
        <div class="stat-meta">
            Saldo awal: <?= format_rupiah($summary['user_initial']) ?>
        </div>
    </div>

    <!-- Pemasukan Bulan Ini -->
    <div class="stat-card income">
        <div class="stat-top">
            <span class="stat-label">Pemasukan (<?= date('M Y') ?>)</span>
            <div class="stat-icon green"><?= get_icon('trending-up') ?></div>
        </div>
        <div class="stat-amount" style="color: var(--color-green);"><?= format_rupiah($summary['period_income']) ?></div>
        <div class="stat-meta">
            Total penerimaan bulan ini
        </div>
    </div>

    <!-- Pengeluaran Bulan Ini -->
    <div class="stat-card expense">
        <div class="stat-top">
            <span class="stat-label">Pengeluaran (<?= date('M Y') ?>)</span>
            <div class="stat-icon red"><?= get_icon('trending-down') ?></div>
        </div>
        <div class="stat-amount" style="color: var(--color-red);"><?= format_rupiah($summary['period_expense']) ?></div>
        <div class="stat-meta">
            <?= $summary['expense_percentage'] ?>% dari total pemasukan
        </div>
    </div>

    <!-- Arus Kas Bersih -->
    <div class="stat-card cashflow">
        <div class="stat-top">
            <span class="stat-label">Arus Kas Bersih</span>
            <div class="stat-icon blue"><?= get_icon('pie-chart') ?></div>
        </div>
        <div class="stat-amount" style="color: <?= $summary['net_cash_flow'] >= 0 ? 'var(--color-navy)' : 'var(--color-red)' ?>;">
            <?= format_rupiah($summary['net_cash_flow']) ?>
        </div>
        <div class="stat-meta">
            <?= $summary['transaction_count'] ?> transaksi tercatat bulan ini
        </div>
    </div>
</div>

<!-- Kondisi Finansial & Peringatan Anggaran -->
<?php if (!empty($warningBudgets)): ?>
<div class="card" style="border-left: 5px solid var(--color-orange); background-color: #FFFDF5; margin-bottom: 24px;">
    <div style="display: flex; align-items: flex-start; gap: 14px;">
        <div style="color: var(--color-orange); margin-top: 2px;">
            <?= get_icon('alert-triangle', 'w-6 h-6') ?>
        </div>
        <div style="flex: 1;">
            <h4 style="font-size: 15px; font-weight: 700; color: #92400E; margin-bottom: 6px;">Peringatan Batas Anggaran Kategori</h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($warningBudgets as $wb): ?>
                    <?php 
                        $isExceeded = $wb['percent'] >= 100;
                        $badgeColor = $isExceeded ? 'var(--color-red)' : 'var(--color-orange)';
                    ?>
                    <div style="font-size: 13px; color: #78350F; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                        <span>Kategori <strong><?= htmlspecialchars($wb['category_name']) ?></strong> terpakai <strong><?= round($wb['percent'], 1) ?>%</strong> (<?= format_rupiah($wb['spent']) ?> dari <?= format_rupiah($wb['amount']) ?>)</span>
                        <span class="badge" style="background-color: <?= $isExceeded ? 'var(--color-red-light)' : 'var(--color-orange-light)' ?>; color: <?= $badgeColor ?>;">
                            <?= $isExceeded ? 'Melebihi Anggaran!' : 'Mendekati Batas' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bagian Grafik: Tren Pemasukan vs Pengeluaran & Distribusi Kategori -->
<div class="grid-2" style="margin-bottom: 24px;">
    <!-- Grafik Tren Harian -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= get_icon('trending-up', 'w-5 h-5 text-purple-600') ?> Arus Kas Harian (<?= date('F Y') ?>)</h3>
                <p class="card-subtitle">Perbandingan pemasukan dan pengeluaran per tanggal</p>
            </div>
        </div>
        <div style="height: 280px; position: relative;">
            <canvas id="dailyTrendChart"></canvas>
        </div>
    </div>

    <!-- Diagram Pengeluaran per Kategori -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title"><?= get_icon('pie-chart', 'w-5 h-5 text-purple-600') ?> Pengeluaran per Kategori</h3>
                <p class="card-subtitle">Distribusi pengeluaran Anda bulan ini</p>
            </div>
        </div>
        <div style="height: 280px; position: relative; display: flex; align-items: center; justify-content: center;">
            <?php if (empty($catExpenseRows)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <p style="color: var(--color-text-muted); font-size: 14px;">Belum ada data pengeluaran di bulan ini.</p>
                </div>
            <?php else: ?>
                <canvas id="categoryDonutChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bagian Bawah: 5 Transaksi Terbaru & Target Tabungan -->
<div class="grid-2">
    <!-- 5 Transaksi Terbaru -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= get_icon('file-text', 'w-5 h-5 text-purple-600') ?> 5 Transaksi Terbaru</h3>
            <a href="transaksi.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>

        <?php if (empty($recentTransactions)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= get_icon('file-text', 'w-8 h-8') ?></div>
                <h4 class="empty-title">Belum Ada Transaksi</h4>
                <p class="empty-description">Mulai catat pemasukan dan pengeluaran harian Anda agar laporan keuangan terbentuk.</p>
                <div style="display: flex; gap: 8px; justify-content: center;">
                    <button class="btn btn-primary btn-sm" onclick="openModal('modalIncome')">Catat Pemasukan</button>
                    <button class="btn btn-danger btn-sm" onclick="openModal('modalExpense')">Catat Pengeluaran</button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Kategori</th>
                            <th>Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $tx): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= format_date_id($tx['transaction_date']) ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($tx['title']) ?></div>
                                    <small style="color: var(--color-text-muted);"><?= htmlspecialchars($tx['account_name'] ?: 'Umum') ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $tx['type'] === 'income' ? 'badge-income' : 'badge-expense' ?>">
                                        <?= htmlspecialchars($tx['category_name'] ?: 'Lainnya') ?>
                                    </span>
                                </td>
                                <td style="font-weight: 700; white-space: nowrap; color: <?= $tx['type'] === 'income' ? 'var(--color-green)' : 'var(--color-red)' ?>;">
                                    <?= ($tx['type'] === 'income' ? '+' : '-') . format_rupiah($tx['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ringkasan Target Tabungan -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= get_icon('target', 'w-5 h-5 text-purple-600') ?> Target Tabungan Aktif</h3>
            <a href="tabungan.php" class="btn btn-secondary btn-sm">Kelola Tabungan</a>
        </div>

        <?php if (empty($savingsGoals)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?= get_icon('target', 'w-8 h-8') ?></div>
                <h4 class="empty-title">Belum Ada Target Tabungan</h4>
                <p class="empty-description">Rencanakan impian Anda seperti dana darurat, liburan, atau gadget baru dengan target tabungan.</p>
                <a href="tabungan.php" class="btn btn-primary btn-sm">Buat Target Sekarang</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($savingsGoals as $goal): ?>
                    <?php
                        $target = (float)$goal['target_amount'];
                        $collected = (float)$goal['collected'];
                        $percent = $target > 0 ? min(100, round(($collected / $target) * 100, 1)) : 0;
                    ?>
                    <div style="border: 1px solid var(--color-border); padding: 16px; border-radius: var(--radius-md); background: #FFFFFF;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <div>
                                <h4 style="font-size: 15px; font-weight: 700; color: var(--color-navy);"><?= htmlspecialchars($goal['title']) ?></h4>
                                <small style="color: var(--color-text-muted);"><?= $goal['target_date'] ? 'Target: ' . format_date_id($goal['target_date']) : 'Tanpa batas waktu' ?></small>
                            </div>
                            <span class="badge badge-transfer"><?= $percent ?>%</span>
                        </div>

                        <div class="progress-container">
                            <div class="progress-bar purple" style="width: <?= $percent ?>%;"></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 6px;">
                            <span style="color: var(--color-green); font-weight: 600;">Terkumpul: <?= format_rupiah($collected) ?></span>
                            <span style="color: var(--color-text-muted);">Target: <?= format_rupiah($target) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL QUICK ADD PEMASUKAN -->
<div class="modal-overlay" id="modalIncome">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Catat Pemasukan Baru</h3>
            <button class="modal-close" onclick="closeModal('modalIncome')">&times;</button>
        </div>
        <form method="POST" action="actions/transaksi_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="income">
            <input type="hidden" name="redirect_to" value="dashboard.php">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tanggal Pemasukan <span class="required">*</span></label>
                    <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Sumber / Nama Pemasukan <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Gaji Bulanan, Bonus Freelance" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Pemasukan</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($userCategoriesIncome as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Masuk ke Rekening / Dompet</label>
                    <select name="account_id" class="form-control">
                        <?php foreach ($userAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan singkat..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalIncome')">Batal</button>
                <button type="submit" class="btn btn-success">Simpan Pemasukan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL QUICK ADD PENGELUARAN -->
<div class="modal-overlay" id="modalExpense">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Catat Pengeluaran Baru</h3>
            <button class="modal-close" onclick="closeModal('modalExpense')">&times;</button>
        </div>
        <form method="POST" action="actions/transaksi_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="expense">
            <input type="hidden" name="redirect_to" value="dashboard.php">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tanggal Pengeluaran <span class="required">*</span></label>
                    <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Transaksi Pengeluaran <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Belanja Bulanan, Makan Siang" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Pengeluaran</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($userCategoriesExpense as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Sumber Dana / Rekening</label>
                    <select name="account_id" class="form-control">
                        <?php foreach ($userAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="payment_method" class="form-control">
                        <option value="Tunai">Tunai</option>
                        <option value="Transfer bank">Transfer bank</option>
                        <option value="E-wallet">E-wallet</option>
                        <option value="Kartu debit">Kartu debit</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Keterangan singkat..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalExpense')">Batal</button>
                <button type="submit" class="btn btn-danger">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>
</div>

<?php
$chartDaysJson = json_encode($chartDays);
$chartIncomeJson = json_encode($chartDailyIncome);
$chartExpenseJson = json_encode($chartDailyExpense);
$catLabelsJson = json_encode($chartCatLabels);
$catTotalsJson = json_encode($chartCatTotals);

$extraScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js belum termuat.');
        return;
    }

    // 1. Line/Bar Chart Tren Harian
    const trendCtx = document.getElementById('dailyTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: {$chartDaysJson},
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: {$chartIncomeJson},
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderRadius: 4
                    },
                    {
                        label: 'Pengeluaran',
                        data: {$chartExpenseJson},
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatRupiahJS(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatRupiahJS(value);
                            },
                            font: { size: 11 }
                        },
                        grid: { color: 'rgba(226, 232, 240, 0.6)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // 2. Donut Chart Kategori Pengeluaran
    const donutCtx = document.getElementById('categoryDonutChart');
    const catLabels = {$catLabelsJson};
    const catTotals = {$catTotalsJson};

    if (donutCtx && catLabels.length > 0) {
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catTotals,
                    backgroundColor: [
                        '#7C3AED', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', 
                        '#EC4899', '#8B5CF6', '#14B8A6', '#F97316', '#64748B'
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + formatRupiahJS(context.parsed);
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
?>

