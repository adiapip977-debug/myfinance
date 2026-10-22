<?php
/**
 * MYFINANCE — Manajemen Rekening, Dompet & Transfer Internal
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Ambil seluruh rekening dan hitung saldo dinamis masing-masing
$accounts = get_user_accounts($pdo, $userId);

$totalAllAccountsBalance = 0;
foreach ($accounts as $acc) {
    $totalAllAccountsBalance += (float)$acc['current_balance'];
}

// Ambil riwayat transfer antar-rekening
$stmtTransfer = $pdo->prepare("SELECT t.*, 
    f.name as from_account_name, 
    to_acc.name as to_account_name 
    FROM transfers t
    JOIN accounts f ON t.from_account_id = f.id
    JOIN accounts to_acc ON t.to_account_id = to_acc.id
    WHERE t.user_id = ?
    ORDER BY t.transfer_date DESC, t.id DESC
    LIMIT 20");
$stmtTransfer->execute([$userId]);
$transfers = $stmtTransfer->fetchAll();

$pageTitle = 'Rekening & Dompet';
$pageHeading = 'Manajemen Rekening & Dompet';
$currentPage = 'rekening';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">Rekening & Sumber Dana</h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Total akumulasi saldo seluruh rekening: <strong style="color: var(--color-navy); font-size: 15px;"><?= format_rupiah($totalAllAccountsBalance) ?></strong>
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn btn-secondary" onclick="openTransferModal()">
            <?= get_icon('repeat', 'w-4 h-4 text-purple-600') ?>
            <span>Transfer Antar-Rekening</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="openAddAccountModal()">
            <?= get_icon('plus', 'w-4 h-4') ?>
            <span>Tambah Rekening Baru</span>
        </button>
    </div>
</div>

<!-- Grid Kartu Rekening & Dompet -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <?php foreach ($accounts as $acc): ?>
        <?php
            $typeLabels = [
                'cash'    => 'Uang Tunai (Cash)',
                'bank'    => 'Rekening Bank',
                'ewallet' => 'Dompet Digital (E-Wallet)',
                'savings' => 'Tabungan Khusus',
                'other'   => 'Lainnya'
            ];
            $typeLabel = $typeLabels[$acc['type']] ?? 'Rekening';
        ?>
        <div class="stat-card" style="border-top: 4px solid <?= htmlspecialchars($acc['color']) ?>;">
            <div class="stat-top">
                <div>
                    <span class="badge" style="background-color: var(--color-purple-light); color: var(--color-purple); font-size: 11px; margin-bottom: 4px;">
                        <?= $typeLabel ?>
                    </span>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--color-navy);"><?= htmlspecialchars($acc['name']) ?></h4>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditAccountModal(<?= json_encode($acc) ?>)'>
                        <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                    </button>
                    <form method="POST" action="actions/rekening_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus rekening ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_account">
                        <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-icon" title="Hapus" style="color: var(--color-red);">
                            <?= get_icon('trash', 'w-4 h-4') ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="stat-amount" style="margin-top: 8px;">
                <?= format_rupiah($acc['current_balance']) ?>
            </div>

            <div class="stat-meta" style="margin-top: 8px;">
                Saldo awal: <?= format_rupiah($acc['initial_balance']) ?>
                <?php if (!empty($acc['account_number'])): ?>
                    &bull; No: <?= htmlspecialchars($acc['account_number']) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Riwayat Transfer Antar Rekening -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= get_icon('repeat', 'w-5 h-5 text-purple-600') ?> Riwayat Transfer Antar-Rekening</h3>
        <button type="button" class="btn btn-primary btn-sm" onclick="openTransferModal()">Transfer Baru</button>
    </div>

    <?php if (empty($transfers)): ?>
        <p style="color: var(--color-text-muted); font-size: 14px; text-align: center; padding: 24px;">
            Belum ada riwayat transfer internal antar rekening.
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Dari Rekening (Asal)</th>
                        <th>Ke Rekening (Tujuan)</th>
                        <th>Nominal Transfer</th>
                        <th>Catatan</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $tr): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= format_date_id($tr['transfer_date']) ?></td>
                            <td>
                                <span class="badge" style="background-color: #FEE2E2; color: #991B1B;">
                                    <?= htmlspecialchars($tr['from_account_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background-color: #D1FAE5; color: #065F46;">
                                    <?= htmlspecialchars($tr['to_account_name']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--color-navy); white-space: nowrap;">
                                <?= format_rupiah($tr['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px;"><?= htmlspecialchars($tr['notes'] ?: '-') ?></td>
                            <td style="text-align: right;">
                                <form method="POST" action="actions/transfer_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus catatan transfer ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_transfer">
                                    <input type="hidden" name="id" value="<?= $tr['id'] ?>">
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

<!-- MODAL TAMBAH / EDIT REKENING -->
<div class="modal-overlay" id="modalAccountForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="accountModalTitle">Tambah Rekening Baru</h3>
            <button class="modal-close" onclick="closeModal('modalAccountForm')">&times;</button>
        </div>
        <form method="POST" action="actions/rekening_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="accountFormAction" value="create_account">
            <input type="hidden" name="id" id="accountFormId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Rekening / Dompet <span class="required">*</span></label>
                    <input type="text" name="name" id="accountName" class="form-control" placeholder="Contoh: BCA Tabungan, Dompet Saku, GoPay" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Jenis Rekening <span class="required">*</span></label>
                    <select name="type" id="accountType" class="form-control">
                        <option value="cash">Uang Tunai (Cash)</option>
                        <option value="bank">Rekening Bank</option>
                        <option value="ewallet">E-Wallet (OVO/GoPay/Dana)</option>
                        <option value="savings">Rekening Tabungan Khusus</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nomor Rekening / No HP (Opsional)</label>
                    <input type="text" name="account_number" id="accountNumber" class="form-control" placeholder="Contoh: 1234567890">
                </div>

                <div class="form-group">
                    <label class="form-label">Saldo Awal</label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="initial_balance" id="accountInitialBalance" class="form-control rupiah-input" placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan (Opsional)</label>
                    <textarea name="notes" id="accountNotes" class="form-control" rows="2" placeholder="Keterangan singkat..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAccountForm')">Batal</button>
                <button type="submit" class="btn btn-primary" id="accountSubmitBtn">Simpan Rekening</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TRANSFER ANTAR REKENING -->
<div class="modal-overlay" id="modalTransferForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Transfer Antar-Rekening</h3>
            <button class="modal-close" onclick="closeModal('modalTransferForm')">&times;</button>
        </div>
        <form method="POST" action="actions/transfer_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_transfer">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tanggal Transfer <span class="required">*</span></label>
                    <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Rekening Sumber (Asal) <span class="required">*</span></label>
                    <select name="from_account_id" class="form-control" required>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Rekening Tujuan <span class="required">*</span></label>
                    <select name="to_account_id" class="form-control" required>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Transfer Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Transfer (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Tarik tunai ATM, Top up e-wallet"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTransferForm')">Batal</button>
                <button type="submit" class="btn btn-primary">Catat Transfer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddAccountModal() {
    document.getElementById('accountModalTitle').innerText = 'Tambah Rekening Baru';
    document.getElementById('accountFormAction').value = 'create_account';
    document.getElementById('accountFormId').value = '';
    document.getElementById('accountName').value = '';
    document.getElementById('accountType').value = 'bank';
    document.getElementById('accountNumber').value = '';
    document.getElementById('accountInitialBalance').value = '';
    document.getElementById('accountNotes').value = '';
    document.getElementById('accountSubmitBtn').innerText = 'Simpan Rekening';
    openModal('modalAccountForm');
}

function openEditAccountModal(data) {
    document.getElementById('accountModalTitle').innerText = 'Edit Rekening';
    document.getElementById('accountFormAction').value = 'update_account';
    document.getElementById('accountFormId').value = data.id;
    document.getElementById('accountName').value = data.name;
    document.getElementById('accountType').value = data.type;
    document.getElementById('accountNumber').value = data.account_number || '';
    document.getElementById('accountInitialBalance').value = formatThousands(Math.round(data.initial_balance));
    document.getElementById('accountNotes').value = data.notes || '';
    document.getElementById('accountSubmitBtn').innerText = 'Perbarui Rekening';
    openModal('modalAccountForm');
}

function openTransferModal() {
    openModal('modalTransferForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

