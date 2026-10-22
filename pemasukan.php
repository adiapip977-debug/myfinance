<?php
/**
 * MYFINANCE — Manajemen Pemasukan
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
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Base query
$sql = "SELECT t.*, c.name as category_name, a.name as account_name 
        FROM transactions t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN accounts a ON t.account_id = a.id
        WHERE t.user_id = :uid AND t.type = 'income'";
$params = [':uid' => $userId];

if (!empty($search)) {
    $sql .= " AND t.title LIKE :search";
    $params[':search'] = "%{$search}%";
}

if ($filterCategory) {
    $sql .= " AND t.category_id = :cid";
    $params[':cid'] = $filterCategory;
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
$incomes = $stmt->fetchAll();

// Total pemasukan sesuai filter
$totalFilteredIncome = 0;
foreach ($incomes as $inc) {
    $totalFilteredIncome += (float)$inc['amount'];
}

// Kategori & Akun
$categories = get_user_categories($pdo, $userId, 'income');
$accounts = get_user_accounts($pdo, $userId);

$pageTitle = 'Catatan Pemasukan';
$pageHeading = 'Manajemen Pemasukan';
$currentPage = 'pemasukan';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas: Tombol Tambah & Ringkasan Cepat -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">Daftar Pemasukan</h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Total pemasukan periode ini: <strong style="color: var(--color-green); font-size: 15px;"><?= format_rupiah($totalFilteredIncome) ?></strong> (<?= count($incomes) ?> transaksi)
        </p>
    </div>
    <button type="button" class="btn btn-success" onclick="openAddModal()">
        <?= get_icon('plus', 'w-4 h-4') ?>
        <span>Tambah Pemasukan Baru</span>
    </button>
</div>

<!-- Kartu Filter & Pencarian -->
<div class="card" style="padding: 18px 20px; margin-bottom: 20px;">
    <form method="GET" action="pemasukan.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 120px; gap: 12px; align-items: end;">
        <div>
            <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Pencarian Sumber</label>
            <input type="text" name="search" class="form-control" placeholder="Cari nama pemasukan..." value="<?= htmlspecialchars($search) ?>">
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

<!-- Tabel Riwayat Pemasukan -->
<div class="card">
    <?php if (empty($incomes)): ?>
        <div class="empty-state">
            <div class="empty-icon" style="background-color: var(--color-green-light); color: var(--color-green);">
                <?= get_icon('arrow-down-left', 'w-8 h-8') ?>
            </div>
            <h4 class="empty-title">Tidak Ada Pemasukan Ditemukan</h4>
            <p class="empty-description">Belum ada catatan pemasukan yang sesuai dengan filter atau kata kunci Anda.</p>
            <button class="btn btn-success btn-sm" onclick="openAddModal()">Catat Pemasukan Sekarang</button>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Sumber Pemasukan</th>
                        <th>Kategori</th>
                        <th>Rekening Penerima</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incomes as $row): ?>
                        <tr>
                            <td style="white-space: nowrap; font-weight: 500;">
                                <?= format_date_id($row['transaction_date']) ?>
                            </td>
                            <td>
                                <strong style="color: var(--color-navy);"><?= htmlspecialchars($row['title']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-income">
                                    <?= htmlspecialchars($row['category_name'] ?: 'Lainnya') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: var(--color-navy);">
                                    <?= htmlspecialchars($row['account_name'] ?: 'Kas Tunai') ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap; font-weight: 700; color: var(--color-green);">
                                +<?= format_rupiah($row['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px; max-width: 220px;">
                                <?= htmlspecialchars($row['notes'] ?: '-') ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditModal(<?= json_encode($row) ?>)'>
                                    <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                                </button>
                                <form method="POST" action="actions/transaksi_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus pemasukan ini? Saldo Anda akan disesuaikan otomatis.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="pemasukan.php">
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

<!-- MODAL FORM TAMBAH / EDIT PEMASUKAN -->
<div class="modal-overlay" id="modalIncomeForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="incomeModalTitle">Catat Pemasukan Baru</h3>
            <button class="modal-close" onclick="closeModal('modalIncomeForm')">&times;</button>
        </div>
        <form method="POST" action="actions/transaksi_action.php" id="formIncomeAction">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="incomeFormAction" value="create">
            <input type="hidden" name="id" id="incomeFormId" value="">
            <input type="hidden" name="type" value="income">
            <input type="hidden" name="redirect_to" value="pemasukan.php">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tanggal Pemasukan <span class="required">*</span></label>
                    <input type="date" name="transaction_date" id="incomeDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Sumber / Nama Pemasukan <span class="required">*</span></label>
                    <input type="text" name="title" id="incomeTitle" class="form-control" placeholder="Contoh: Gaji Bulanan, Bonus Proyek" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" id="incomeAmount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Pemasukan</label>
                    <select name="category_id" id="incomeCategoryId" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Rekening Penerima</label>
                    <select name="account_id" id="incomeAccountId" class="form-control">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" id="incomeNotes" class="form-control" rows="2" placeholder="Catatan atau keterangan..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalIncomeForm')">Batal</button>
                <button type="submit" class="btn btn-success" id="incomeSubmitBtn">Simpan Pemasukan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('incomeModalTitle').innerText = 'Catat Pemasukan Baru';
    document.getElementById('incomeFormAction').value = 'create';
    document.getElementById('incomeFormId').value = '';
    document.getElementById('incomeDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('incomeTitle').value = '';
    document.getElementById('incomeAmount').value = '';
    document.getElementById('incomeNotes').value = '';
    document.getElementById('incomeSubmitBtn').innerText = 'Simpan Pemasukan';
    openModal('modalIncomeForm');
}

function openEditModal(data) {
    document.getElementById('incomeModalTitle').innerText = 'Edit Transaksi Pemasukan';
    document.getElementById('incomeFormAction').value = 'update';
    document.getElementById('incomeFormId').value = data.id;
    document.getElementById('incomeDate').value = data.transaction_date;
    document.getElementById('incomeTitle').value = data.title;
    document.getElementById('incomeAmount').value = formatThousands(Math.round(data.amount));
    if (data.category_id) {
        document.getElementById('incomeCategoryId').value = data.category_id;
    }
    if (data.account_id) {
        document.getElementById('incomeAccountId').value = data.account_id;
    }
    document.getElementById('incomeNotes').value = data.notes || '';
    document.getElementById('incomeSubmitBtn').innerText = 'Perbarui Pemasukan';
    openModal('modalIncomeForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

