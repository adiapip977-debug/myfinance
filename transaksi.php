<?php
/**
 * MYFINANCE — Riwayat Seluruh Transaksi (Unified Transactions)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Filter & Param
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterCategory = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$filterAccount = !empty($_GET['account_id']) ? (int)$_GET['account_id'] : null;
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$sortOrder = ($_GET['sort'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Query builder
$whereClause = "WHERE t.user_id = :uid";
$params = [':uid' => $userId];

if (!empty($search)) {
    $whereClause .= " AND t.title LIKE :search";
    $params[':search'] = "%{$search}%";
}

if (!empty($filterType) && in_array($filterType, ['income', 'expense'])) {
    $whereClause .= " AND t.type = :type";
    $params[':type'] = $filterType;
}

if ($filterCategory) {
    $whereClause .= " AND t.category_id = :cid";
    $params[':cid'] = $filterCategory;
}

if ($filterAccount) {
    $whereClause .= " AND t.account_id = :aid";
    $params[':aid'] = $filterAccount;
}

if (!empty($startDate)) {
    $whereClause .= " AND t.transaction_date >= :sdate";
    $params[':sdate'] = $startDate;
}

if (!empty($endDate)) {
    $whereClause .= " AND t.transaction_date <= :edate";
    $params[':edate'] = $endDate;
}

// Hitung total baris untuk pagination
$countSql = "SELECT COUNT(*) FROM transactions t {$whereClause}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Ambil data transaksi
$sql = "SELECT t.*, c.name as category_name, a.name as account_name 
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN accounts a ON t.account_id = a.id
        {$whereClause}
        ORDER BY t.transaction_date {$sortOrder}, t.id {$sortOrder}
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Kategori & Akun untuk filter & modal
$allCategories = get_user_categories($pdo, $userId);
$accounts = get_user_accounts($pdo, $userId);

$pageTitle = 'Semua Transaksi';
$pageHeading = 'Riwayat Transaksi';
$currentPage = 'transaksi';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">Semua Transaksi</h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Menampilkan <?= count($transactions) ?> dari <?= $totalRows ?> total transaksi tercatat.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="export/export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
            <?= get_icon('download', 'w-4 h-4') ?>
            <span>Ekspor CSV</span>
        </a>
        <button type="button" class="btn btn-primary" onclick="openNewTxModal()">
            <?= get_icon('plus', 'w-4 h-4') ?>
            <span>Catat Transaksi</span>
        </button>
    </div>
</div>

<!-- Kartu Filter Lengkap -->
<div class="card" style="padding: 18px 20px; margin-bottom: 20px;">
    <form method="GET" action="transaksi.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 100px; gap: 12px; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Cari Transaksi</label>
            <input type="text" name="search" class="form-control" placeholder="Kata kunci..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Jenis</label>
            <select name="type" class="form-control">
                <option value="">Semua Jenis</option>
                <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Pemasukan (+)</option>
                <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Pengeluaran (-)</option>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Kategori</label>
            <select name="category_id" class="form-control">
                <option value="">Semua Kategori</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                        <?= ($cat['type'] === 'income' ? '[+] ' : '[-] ') . htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Rekening</label>
            <select name="account_id" class="form-control">
                <option value="">Semua Rekening</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $filterAccount == $acc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($acc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Urutan</label>
            <select name="sort" class="form-control">
                <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
                <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Terlama</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <?= get_icon('filter', 'w-4 h-4') ?> Filter
            </button>
        </div>
    </form>
</div>

<!-- Tabel Transaksi Lengkap -->
<div class="card">
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon"><?= get_icon('file-text', 'w-8 h-8') ?></div>
            <h4 class="empty-title">Tidak Ada Transaksi yang Cocok</h4>
            <p class="empty-description">Coba ubah filter pencarian Anda atau tambahkan transaksi baru.</p>
            <button class="btn btn-primary btn-sm" onclick="openNewTxModal()">Tambah Transaksi</button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Transaksi</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Rekening / Metode</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: 500;">
                                <?= format_date_id($tx['transaction_date']) ?>
                            </td>
                            <td>
                                <strong style="color: var(--color-navy);"><?= htmlspecialchars($tx['title']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $tx['type'] === 'income' ? 'badge-income' : 'badge-expense' ?>">
                                    <?= $tx['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran' ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($tx['category_name'] ?: 'Umum') ?>
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 500; color: var(--color-navy);">
                                    <?= htmlspecialchars($tx['account_name'] ?: 'Umum') ?>
                                </div>
                                <small style="color: var(--color-text-muted);"><?= htmlspecialchars($tx['payment_method'] ?: '-') ?></small>
                            </td>
                            <td style="white-space: nowrap; font-weight: 700; color: <?= $tx['type'] === 'income' ? 'var(--color-green)' : 'var(--color-red)' ?>;">
                                <?= ($tx['type'] === 'income' ? '+' : '-') . format_rupiah($tx['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px; max-width: 180px;">
                                <?= htmlspecialchars($tx['notes'] ?: '-') ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditTxModal(<?= json_encode($tx) ?>)'>
                                    <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                                </button>
                                <form method="POST" action="actions/transaksi_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus transaksi ini? Seluruh saldo terkait akan diperbarui.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $tx['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="transaksi.php">
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

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">&laquo; Sebelumnya</a>
                <?php endif; ?>

                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">Selanjutnya &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- MODAL FORM TRANSAKSI FLUID (TAMBAH / EDIT) -->
<div class="modal-overlay" id="modalTxForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="txModalTitle">Catat Transaksi</h3>
            <button class="modal-close" onclick="closeModal('modalTxForm')">&times;</button>
        </div>
        <form method="POST" action="actions/transaksi_action.php" id="formTxAction">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="txFormAction" value="create">
            <input type="hidden" name="id" id="txFormId" value="">
            <input type="hidden" name="redirect_to" value="transaksi.php">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Jenis Transaksi <span class="required">*</span></label>
                    <select name="type" id="txType" class="form-control" onchange="updateCategoryOptions()">
                        <option value="expense">Pengeluaran (-)</option>
                        <option value="income">Pemasukan (+)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tanggal Transaksi <span class="required">*</span></label>
                    <input type="date" name="transaction_date" id="txDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Transaksi <span class="required">*</span></label>
                    <input type="text" name="title" id="txTitle" class="form-control" placeholder="Contoh: Belanja atau Gaji" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" id="txAmount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" id="txCategoryId" class="form-control"></select>
                </div>

                <div class="form-group">
                    <label class="form-label">Rekening / Dompet</label>
                    <select name="account_id" id="txAccountId" class="form-control">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="payment_method" id="txPaymentMethod" class="form-control">
                        <option value="Tunai">Tunai</option>
                        <option value="Transfer bank">Transfer bank</option>
                        <option value="E-wallet">E-wallet</option>
                        <option value="Kartu debit">Kartu debit</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="txNotes" class="form-control" rows="2" placeholder="Catatan transaksi..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTxForm')">Batal</button>
                <button type="submit" class="btn btn-primary" id="txSubmitBtn">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<script>
const allCategoriesData = <?= json_encode($allCategories) ?>;

function updateCategoryOptions(selectedId = null) {
    const currentType = document.getElementById('txType').value;
    const catSelect = document.getElementById('txCategoryId');
    catSelect.innerHTML = '';

    const filtered = allCategoriesData.filter(c => c.type === currentType);
    filtered.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        if (selectedId && selectedId == cat.id) {
            opt.selected = true;
        }
        catSelect.appendChild(opt);
    });
}

function openNewTxModal() {
    document.getElementById('txModalTitle').innerText = 'Catat Transaksi Baru';
    document.getElementById('txFormAction').value = 'create';
    document.getElementById('txFormId').value = '';
    document.getElementById('txType').value = 'expense';
    document.getElementById('txDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('txTitle').value = '';
    document.getElementById('txAmount').value = '';
    document.getElementById('txNotes').value = '';
    document.getElementById('txSubmitBtn').innerText = 'Simpan Transaksi';
    updateCategoryOptions();
    openModal('modalTxForm');
}

function openEditTxModal(data) {
    document.getElementById('txModalTitle').innerText = 'Edit Transaksi';
    document.getElementById('txFormAction').value = 'update';
    document.getElementById('txFormId').value = data.id;
    document.getElementById('txType').value = data.type;
    document.getElementById('txDate').value = data.transaction_date;
    document.getElementById('txTitle').value = data.title;
    document.getElementById('txAmount').value = formatThousands(Math.round(data.amount));
    updateCategoryOptions(data.category_id);
    if (data.account_id) {
        document.getElementById('txAccountId').value = data.account_id;
    }
    if (data.payment_method) {
        document.getElementById('txPaymentMethod').value = data.payment_method;
    }
    document.getElementById('txNotes').value = data.notes || '';
    document.getElementById('txSubmitBtn').innerText = 'Perbarui Transaksi';
    openModal('modalTxForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

