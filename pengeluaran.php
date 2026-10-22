<?php
/**
 * MYFINANCE — Manajemen Pengeluaran
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$filterCategory = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$filterMethod = trim($_GET['payment_method'] ?? '');
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Base query
$sql = "SELECT t.*, c.name as category_name, a.name as account_name 
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = :uid AND t.type = 'expense'";
$params = [':uid' => $userId];

if (!empty($search)) {
    $sql .= " AND t.title LIKE :search";
    $params[':search'] = "%{$search}%";
}

if ($filterCategory) {
    $sql .= " AND t.category_id = :cid";
    $params[':cid'] = $filterCategory;
}

if (!empty($filterMethod)) {
    $sql .= " AND t.payment_method = :pm";
    $params[':pm'] = $filterMethod;
}

if (!empty($startDate)) {
    $sql .= " AND t.transaction_date >= :sdate";
    $params[':sdate'] = $startDate;
}

if (!empty($endDate)) {
    $sql .= " AND t.transaction_date <= :edate";
    $params[':edate'] = $endDate;
}

$sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Total pengeluaran terfilter
$totalFilteredExpense = 0;
foreach ($expenses as $exp) {
    $totalFilteredExpense += (float)$exp['amount'];
}

// Kategori & Akun
$categories = get_user_categories($pdo, $userId, 'expense');
$accounts = get_user_accounts($pdo, $userId);

$pageTitle = 'Catatan Pengeluaran';
$pageHeading = 'Manajemen Pengeluaran';
$currentPage = 'pengeluaran';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">Daftar Pengeluaran</h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Total pengeluaran periode ini: <strong style="color: var(--color-red); font-size: 15px;"><?= format_rupiah($totalFilteredExpense) ?></strong> (<?= count($expenses) ?> transaksi)
        </p>
    </div>
    <button type="button" class="btn btn-danger" onclick="openAddExpenseModal()">
        <?= get_icon('plus', 'w-4 h-4') ?>
        <span>Tambah Pengeluaran Baru</span>
    </button>
</div>

<!-- Kartu Filter & Pencarian -->
<div class="card" style="padding: 18px 20px; margin-bottom: 20px;">
    <form method="GET" action="pengeluaran.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) 110px; gap: 12px; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Cari Pengeluaran</label>
            <input type="text" name="search" class="form-control" placeholder="Nama transaksi..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Kategori</label>
            <select name="category_id" class="form-control">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Metode Pembayaran</label>
            <select name="payment_method" class="form-control">
                <option value="">Semua Metode</option>
                <option value="Tunai" <?= $filterMethod === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
                <option value="Transfer bank" <?= $filterMethod === 'Transfer bank' ? 'selected' : '' ?>>Transfer bank</option>
                <option value="E-wallet" <?= $filterMethod === 'E-wallet' ? 'selected' : '' ?>>E-wallet</option>
                <option value="Kartu debit" <?= $filterMethod === 'Kartu debit' ? 'selected' : '' ?>>Kartu debit</option>
                <option value="Lainnya" <?= $filterMethod === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= get_icon('filter', 'w-4 h-4') ?> Filter
            </button>
        </div>
    </form>
</div>

<!-- Tabel Pengeluaran -->
<div class="card">
    <?php if (empty($expenses)): ?>
        <div class="empty-state">
            <div class="empty-icon" style="background-color: var(--color-red-light); color: var(--color-red);">
                <?= get_icon('arrow-up-right', 'w-8 h-8') ?>
            </div>
            <h4 class="empty-title">Tidak Ada Pengeluaran Ditemukan</h4>
            <p class="empty-description">Belum ada transaksi pengeluaran yang sesuai dengan kriteria filter Anda.</p>
            <button class="btn btn-danger btn-sm" onclick="openAddExpenseModal()">Catat Pengeluaran Sekarang</button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Transaksi</th>
                        <th>Kategori</th>
                        <th>Rekening / Sumber</th>
                        <th>Metode</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $row): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: 500;">
                                <?= format_date_id($row['transaction_date']) ?>
                            </td>
                            <td>
                                <strong style="color: var(--color-navy);"><?= htmlspecialchars($row['title']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-expense">
                                    <?= htmlspecialchars($row['category_name'] ?: 'Lainnya') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--color-navy);">
                                    <?= htmlspecialchars($row['account_name'] ?: 'Kas') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-neutral">
                                    <?= htmlspecialchars($row['payment_method'] ?: 'Tunai') ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap; font-weight: 700; color: var(--color-red);">
                                -<?= format_rupiah($row['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px; max-width: 200px;">
                                <?= htmlspecialchars($row['notes'] ?: '-') ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditExpenseModal(<?= json_encode($row) ?>)'>
                                    <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                                </button>
                                <form method="POST" action="actions/transaksi_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus pengeluaran ini? Anggaran dan saldo Anda akan diperbarui.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="pengeluaran.php">
                                    <button type="submit" class="btn btn-secondary btn-icon" title="Hapus" style="color: var(--color-red);">
                                        <?= get_icon('trash', 'w-4 h-4') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL FORM TAMBAH / EDIT PENGELUARAN -->
<div class="modal-overlay" id="modalExpenseForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="expenseModalTitle">Catat Pengeluaran Baru</h3>
            <button class="modal-close" onclick="closeModal('modalExpenseForm')">&times;</button>
        </div>
        <form method="POST" action="actions/transaksi_action.php" id="formExpenseAction">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="expenseFormAction" value="create">
            <input type="hidden" name="id" id="expenseFormId" value="">
            <input type="hidden" name="type" value="expense">
            <input type="hidden" name="redirect_to" value="pengeluaran.php">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tanggal Pengeluaran <span class="required">*</span></label>
                    <input type="date" name="transaction_date" id="expenseDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Transaksi <span class="required">*</span></label>
                    <input type="text" name="title" id="expenseTitle" class="form-control" placeholder="Contoh: Belanja Bulanan, Bensin Motor" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" id="expenseAmount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Pengeluaran</label>
                    <select name="category_id" id="expenseCategoryId" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Sumber Dana / Rekening</label>
                    <select name="account_id" id="expenseAccountId" class="form-control">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="payment_method" id="expenseMethod" class="form-control">
                        <option value="Tunai">Tunai</option>
                        <option value="Transfer bank">Transfer bank</option>
                        <option value="E-wallet">E-wallet</option>
                        <option value="Kartu debit">Kartu debit</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="expenseNotes" class="form-control" rows="2" placeholder="Catatan transaksi..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalExpenseForm')">Batal</button>
                <button type="submit" class="btn btn-danger" id="expenseSubmitBtn">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddExpenseModal() {
    document.getElementById('expenseModalTitle').innerText = 'Catat Pengeluaran Baru';
    document.getElementById('expenseFormAction').value = 'create';
    document.getElementById('expenseFormId').value = '';
    document.getElementById('expenseDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('expenseTitle').value = '';
    document.getElementById('expenseAmount').value = '';
    document.getElementById('expenseNotes').value = '';
    document.getElementById('expenseSubmitBtn').innerText = 'Simpan Pengeluaran';
    openModal('modalExpenseForm');
}

function openEditExpenseModal(data) {
    document.getElementById('expenseModalTitle').innerText = 'Edit Transaksi Pengeluaran';
    document.getElementById('expenseFormAction').value = 'update';
    document.getElementById('expenseFormId').value = data.id;
    document.getElementById('expenseDate').value = data.transaction_date;
    document.getElementById('expenseTitle').value = data.title;
    document.getElementById('expenseAmount').value = formatThousands(Math.round(data.amount));
    if (data.category_id) {
        document.getElementById('expenseCategoryId').value = data.category_id;
    }
    if (data.account_id) {
        document.getElementById('expenseAccountId').value = data.account_id;
    }
    if (data.payment_method) {
        document.getElementById('expenseMethod').value = data.payment_method;
    }
    document.getElementById('expenseNotes').value = data.notes || '';
    document.getElementById('expenseSubmitBtn').innerText = 'Perbarui Pengeluaran';
    openModal('modalExpenseForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

