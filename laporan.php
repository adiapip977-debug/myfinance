<?php
/**
 * MYFINANCE — Laporan Keuangan Lengkap
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Periode Filter
$period = $_GET['period'] ?? 'this_month';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

$startDate = date('Y-m-01');
$endDate = date('Y-m-t');
$periodTitle = 'Bulan Ini (' . date('F Y') . ')';

switch ($period) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $periodTitle = 'Hari Ini (' . format_date_id($startDate) . ')';
        break;
    case 'this_week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $periodTitle = 'Minggu Ini (' . format_date_id($startDate) . ' s/d ' . format_date_id($endDate) . ')';
        break;
    case 'this_month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $periodTitle = 'Bulan Ini (' . date('F Y') . ')';
        break;
    case 'last_month':
        $startDate = date('Y-m-01', strtotime('first day of last month'));
        $endDate = date('Y-m-t', strtotime('last day of last month'));
        $periodTitle = 'Bulan Sebelumnya (' . date('F Y', strtotime('last month')) . ')';
        break;
    case 'this_year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        $periodTitle = 'Tahun Ini (' . date('Y') . ')';
        break;
    case 'custom':
        if (!empty($customStart) && !empty($customEnd)) {
            $startDate = $customStart;
            $endDate = $customEnd;
            $periodTitle = 'Periode ' . format_date_id($startDate) . ' s/d ' . format_date_id($endDate);
        }
        break;
}

// 1. Ringkasan Periode Terpilih
$stmtSummary = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
    COUNT(*) as total_count
    FROM transactions 
    WHERE user_id = ? AND transaction_date >= ? AND transaction_date <= ?");
$stmtSummary->execute([$userId, $startDate, $endDate]);
$reportSummary = $stmtSummary->fetch();

$totalIncome = (float)$reportSummary['total_income'];
$totalExpense = (float)$reportSummary['total_expense'];
$netCashFlow = $totalIncome - $totalExpense;
$txCount = (int)$reportSummary['total_count'];

// 2. Pengeluaran per Kategori pada Periode Terpilih
$stmtCat = $pdo->prepare("SELECT c.name as category_name, SUM(t.amount) as total_cat 
                          FROM transactions t 
                          JOIN categories c ON t.category_id = c.id 
                          WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date >= ? AND t.transaction_date <= ?
                          GROUP BY c.id, c.name 
                          ORDER BY total_cat DESC");
$stmtCat->execute([$userId, $startDate, $endDate]);
$categoryBreakdown = $stmtCat->fetchAll();

$topExpenseCategory = !empty($categoryBreakdown) ? $categoryBreakdown[0]['category_name'] : '-';

// Susun data untuk Chart Kategori
$catLabels = [];
$catAmounts = [];
foreach ($categoryBreakdown as $cb) {
    $catLabels[] = $cb['category_name'];
    $catAmounts[] = (float)$cb['total_cat'];
}

// 3. Tren Keuangan Bulanan Sepanjang Tahun Berjalan
$currentYear = (int)date('Y');
$stmtYearly = $pdo->prepare("SELECT 
    MONTH(transaction_date) as m_num,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as m_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as m_expense
    FROM transactions 
    WHERE user_id = ? AND YEAR(transaction_date) = ?
    GROUP BY MONTH(transaction_date)
    ORDER BY m_num ASC");
$stmtYearly->execute([$userId, $currentYear]);
$yearlyRows = $stmtYearly->fetchAll();

$monthsLabel = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$yearlyIncome = array_fill(0, 12, 0);
$yearlyExpense = array_fill(0, 12, 0);

foreach ($yearlyRows as $yr) {
    $idx = (int)$yr['m_num'] - 1;
    if ($idx >= 0 && $idx < 12) {
        $yearlyIncome[$idx] = (float)$yr['m_income'];
        $yearlyExpense[$idx] = (float)$yr['m_expense'];
    }
}

// 4. Daftar Transaksi pada Periode Ini
$stmtTx = $pdo->prepare("SELECT t.*, c.name as category_name, a.name as account_name 
                         FROM transactions t 
                         LEFT JOIN categories c ON t.category_id = c.id 
                         LEFT JOIN accounts a ON t.account_id = a.id 
                         WHERE t.user_id = ? AND t.transaction_date >= ? AND t.transaction_date <= ?
                         ORDER BY t.transaction_date DESC, t.id DESC");
$stmtTx->execute([$userId, $startDate, $endDate]);
$periodTransactions = $stmtTx->fetchAll();

$pageTitle = 'Laporan Keuangan';
$pageHeading = 'Laporan Keuangan';
$currentPage = 'laporan';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Kontrol Filter & Tombol Ekspor/Cetak -->
<div class="card no-print" style="padding: 20px; margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <!-- Filter Cepat Periode -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?period=today" class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-secondary' ?>">Hari Ini</a>
            <a href="?period=this_week" class="btn btn-sm <?= $period === 'this_week' ? 'btn-primary' : 'btn-secondary' ?>">Minggu Ini</a>
            <a href="?period=this_month" class="btn btn-sm <?= $period === 'this_month' ? 'btn-primary' : 'btn-secondary' ?>">Bulan Ini</a>
            <a href="?period=last_month" class="btn btn-sm <?= $period === 'last_month' ? 'btn-primary' : 'btn-secondary' ?>">Bulan Lalu</a>
            <a href="?period=this_year" class="btn btn-sm <?= $period === 'this_year' ? 'btn-primary' : 'btn-secondary' ?>">Tahun Ini</a>
        </div>

        <!-- Tombol Aksi Ekspor -->
        <div style="display: flex; gap: 10px;">
            <a href="export/export_csv.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-secondary btn-sm">
                <?= get_icon('download', 'w-4 h-4') ?>
                <span>Ekspor CSV</span>
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <?= get_icon('printer', 'w-4 h-4') ?>
                <span>Cetak Laporan</span>
            </button>
        </div>
    </div>

    <!-- Form Rentang Tanggal Kustom -->
    <form method="GET" action="laporan.php" style="display: flex; align-items: center; gap: 12px; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-border); flex-wrap: wrap;">
        <input type="hidden" name="period" value="custom">
        <span style="font-size: 13px; font-weight: 600; color: var(--color-navy);">Rentang Kustom:</span>
        <input type="date" name="start_date" class="form-control" style="width: auto; padding: 6px 12px;" value="<?= htmlspecialchars($startDate) ?>" required>
        <span style="font-size: 13px; color: var(--color-text-muted);">s/d</span>
        <input type="date" name="end_date" class="form-control" style="width: auto; padding: 6px 12px;" value="<?= htmlspecialchars($endDate) ?>" required>
        <button type="submit" class="btn btn-secondary btn-sm">Terapkan Periode</button>
    </form>
</div>

<!-- Kop Cetak Laporan (Hanya muncul saat cetak) -->
<div style="display: none;" class="print-header">
    <h2 style="font-size: 22px; font-weight: 800; color: #111827; margin-bottom: 4px;">MYFINANCE — Laporan Keuangan Pribadi</h2>
    <p style="font-size: 13px; color: #64748B; margin-bottom: 20px;">
        Pemilik Akun: <?= htmlspecialchars($currentUser['name']) ?> (<?= htmlspecialchars($currentUser['email']) ?>) &bull; Periode: <?= htmlspecialchars($periodTitle) ?> &bull; Tanggal Cetak: <?= format_date_id(date('Y-m-d')) ?>
    </p>
</div>

<!-- Kartu Ringkasan Metrik Laporan -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card income">
        <div class="stat-top">
            <span class="stat-label">Total Pemasukan</span>
            <div class="stat-icon green"><?= get_icon('trending-up') ?></div>
        </div>
        <div class="stat-amount" style="color: var(--color-green);"><?= format_rupiah($totalIncome) ?></div>
        <div class="stat-meta">
            <?= htmlspecialchars($periodTitle) ?>
        </div>
    </div>

    <div class="stat-card expense">
        <div class="stat-top">
            <span class="stat-label">Total Pengeluaran</span>
            <div class="stat-icon red"><?= get_icon('trending-down') ?></div>
        </div>
        <div class="stat-amount" style="color: var(--color-red);"><?= format_rupiah($totalExpense) ?></div>
        <div class="stat-meta">
            Pengeluaran Terbesar: <strong><?= htmlspecialchars($topExpenseCategory) ?></strong>
        </div>
    </div>

    <div class="stat-card balance">
        <div class="stat-top">
            <span class="stat-label">Arus Kas Bersih (Net)</span>
            <div class="stat-icon purple"><?= get_icon('pie-chart') ?></div>
        </div>
        <div class="stat-amount" style="color: <?= $netCashFlow >= 0 ? 'var(--color-navy)' : 'var(--color-red)' ?>;">
            <?= format_rupiah($netCashFlow) ?>
        </div>
        <div class="stat-meta">
            <?= $txCount ?> transaksi dalam periode ini
        </div>
    </div>
</div>

<!-- Bagian Grafik: Tren Tahunan & Distribusi Kategori -->
<div class="grid-2 no-print" style="margin-bottom: 24px;">
    <!-- Tren Keuangan Bulanan Tahun Ini -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><?= get_icon('trending-up', 'w-5 h-5 text-purple-600') ?> Tren Keuangan Bulanan Tahun <?= $currentYear ?></h3>
        </div>
        <div style="height: 280px; position: relative;">
            <canvas id="yearlyTrendChart"></canvas>
        </div>
    </div>

    <!-- Diagram Pengeluaran per Kategori Periode Ini -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><?= get_icon('pie-chart', 'w-5 h-5 text-purple-600') ?> Komposisi Pengeluaran Periode Ini</h3>
        </div>
        <div style="height: 280px; position: relative; display: flex; align-items: center; justify-content: center;">
            <?php if (empty($categoryBreakdown)): ?>
                <p style="color: var(--color-text-muted); font-size: 14px;">Belum ada pengeluaran pada periode ini.</p>
            <?php else: ?>
                <canvas id="reportCategoryChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabel Rincian Pengeluaran per Kategori -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><?= get_icon('grid', 'w-5 h-5 text-purple-600') ?> Rincian Pengeluaran per Kategori (<?= htmlspecialchars($periodTitle) ?>)</h3>
    </div>

    <?php if (empty($categoryBreakdown)): ?>
        <p style="color: var(--color-text-muted); font-size: 14px; text-align: center; padding: 20px;">Tidak ada catatan pengeluaran.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Total Pengeluaran</th>
                        <th>Persentase dari Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryBreakdown as $cat): ?>
                        <?php
                            $catPct = $totalExpense > 0 ? round(($cat['total_cat'] / $totalExpense) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                            <td style="font-weight: 700; color: var(--color-red); white-space: nowrap;">
                                <?= format_rupiah($cat['total_cat']) ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="progress-container" style="flex: 1; height: 8px; margin: 0;">
                                        <div class="progress-bar purple" style="width: <?= $catPct ?>%;"></div>
                                    </div>
                                    <span style="font-size: 12px; font-weight: 600; min-width: 45px; text-align: right;"><?= $catPct ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Tabel Daftar Transaksi Periode Terpilih -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= get_icon('file-text', 'w-5 h-5 text-purple-600') ?> Daftar Transaksi pada Periode Ini (<?= count($periodTransactions) ?> Transaksi)</h3>
    </div>

    <?php if (empty($periodTransactions)): ?>
        <p style="color: var(--color-text-muted); font-size: 14px; text-align: center; padding: 20px;">Tidak ada transaksi pada periode yang dipilih.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Transaksi</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Rekening / Sumber</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periodTransactions as $t): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= format_date_id($t['transaction_date']) ?></td>
                            <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                            <td>
                                <span class="badge <?= $t['type'] === 'income' ? 'badge-income' : 'badge-expense' ?>">
                                    <?= $t['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($t['category_name'] ?: 'Umum') ?></td>
                            <td><?= htmlspecialchars($t['account_name'] ?: 'Kas') ?></td>
                            <td style="white-space: nowrap; font-weight: 700; color: <?= $t['type'] === 'income' ? 'var(--color-green)' : 'var(--color-red)' ?>;">
                                <?= ($t['type'] === 'income' ? '+' : '-') . format_rupiah($t['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px;"><?= htmlspecialchars($t['notes'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$monthsLabelJson = json_encode($monthsLabel);
$yearlyIncomeJson = json_encode($yearlyIncome);
$yearlyExpenseJson = json_encode($yearlyExpense);
$catLabelsJson = json_encode($catLabels);
$catAmountsJson = json_encode($catAmounts);

$extraScripts = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    // 1. Line / Bar Chart Tren Tahunan
    const yearlyCtx = document.getElementById('yearlyTrendChart');
    if (yearlyCtx) {
        new Chart(yearlyCtx, {
            type: 'bar',
            data: {
                labels: {$monthsLabelJson},
                datasets: [
                    {
                        label: 'Pemasukan',
                        data: {$yearlyIncomeJson},
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 4
                    },
                    {
                        label: 'Pengeluaran',
                        data: {$yearlyExpenseJson},
                        backgroundColor: 'rgba(239, 68, 68, 0.85)',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + formatRupiahJS(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return formatRupiahJS(val); }
                        }
                    }
                }
            }
        });
    }

    // 2. Donut Chart Kategori Periode
    const repDonutCtx = document.getElementById('reportCategoryChart');
    const repCatLabels = {$catLabelsJson};
    const repCatData = {$catAmountsJson};

    if (repDonutCtx && repCatLabels.length > 0) {
        new Chart(repDonutCtx, {
            type: 'doughnut',
            data: {
                labels: repCatLabels,
                datasets: [{
                    data: repCatData,
                    backgroundColor: [
                        '#7C3AED', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', 
                        '#EC4899', '#8B5CF6', '#14B8A6', '#F97316', '#64748B'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12 } },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + formatRupiahJS(ctx.parsed);
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }
});
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
?>

