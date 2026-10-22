<?php
/**
 * MYFINANCE — Target Tabungan & Alokasi (Savings Goals)
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Ambil semua target tabungan pengguna
$sql = "SELECT g.*, 
        (SELECT COALESCE(SUM(c.amount), 0) FROM savings_contributions c WHERE c.goal_id = g.id) as collected,
        (SELECT COUNT(*) FROM savings_contributions c WHERE c.goal_id = g.id) as contribution_count
        FROM savings_goals g
        WHERE g.user_id = ?
        ORDER BY g.status ASC, g.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$goals = $stmt->fetchAll();

// Rekening untuk modal setoran
$accounts = get_user_accounts($pdo, $userId);

// Ambil riwayat setoran terbaru
$stmtContrib = $pdo->prepare("SELECT c.*, g.title as goal_title, a.name as account_name 
                              FROM savings_contributions c
                              JOIN savings_goals g ON c.goal_id = g.id
                              LEFT JOIN accounts a ON c.account_id = a.id
                              WHERE c.user_id = ?
                              ORDER BY c.contribution_date DESC, c.id DESC
                              LIMIT 10");
$stmtContrib->execute([$userId]);
$recentContributions = $stmtContrib->fetchAll();

$pageTitle = 'Target Tabungan';
$pageHeading = 'Target Tabungan & Impian';
$currentPage = 'tabungan';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Bar Atas -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
    <div>
        <h3 style="font-size: 18px; font-weight: 700; color: var(--color-navy); margin-bottom: 4px;">Rencana & Target Tabungan</h3>
        <p style="font-size: 13px; color: var(--color-text-muted);">
            Wujudkan impian masa depan seperti dana darurat, liburan, investasi, atau pembelian penting.
        </p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" class="btn btn-success" onclick="openDepositModal()">
            <?= get_icon('plus', 'w-4 h-4') ?>
            <span>Setor Dana Tabungan</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="openAddGoalModal()">
            <?= get_icon('target', 'w-4 h-4') ?>
            <span>Buat Target Baru</span>
        </button>
    </div>
</div>

<!-- Daftar Kartu Target Tabungan -->
<?php if (empty($goals)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon"><?= get_icon('target', 'w-8 h-8') ?></div>
            <h4 class="empty-title">Belum Ada Target Tabungan</h4>
            <p class="empty-description">Buat target pertama Anda untuk mulai menabung secara teratur dan memantau perkembangannya.</p>
            <button class="btn btn-primary btn-sm" onclick="openAddGoalModal()">Mulai Buat Target</button>
        </div>
    </div>
<?php else: ?>
    <div class="grid-2" style="margin-bottom: 28px;">
        <?php foreach ($goals as $g): ?>
            <?php
                $target = (float)$g['target_amount'];
                $collected = (float)$g['collected'];
                $remaining = max(0, $target - $collected);
                $percent = $target > 0 ? min(100, round(($collected / $target) * 100, 1)) : 0;
                $isCompleted = ($g['status'] === 'completed') || ($collected >= $target);
            ?>
            <div class="card" style="margin-bottom: 0; border-top: 4px solid <?= $isCompleted ? 'var(--color-green)' : 'var(--color-purple)' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <h4 style="font-size: 17px; font-weight: 700; color: var(--color-navy);"><?= htmlspecialchars($g['title']) ?></h4>
                            <?php if ($isCompleted): ?>
                                <span class="badge badge-income"><?= get_icon('check', 'w-3 h-3') ?> Tercapai!</span>
                            <?php else: ?>
                                <span class="badge badge-transfer"><?= $percent ?>%</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($g['description'])): ?>
                            <p style="font-size: 13px; color: var(--color-text-muted); margin-top: 4px;"><?= htmlspecialchars($g['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <button type="button" class="btn btn-secondary btn-icon" title="Setor Dana" onclick="openDepositForGoal(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['title'])) ?>')">
                            <?= get_icon('plus', 'w-4 h-4 text-emerald-600') ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-icon" title="Edit" onclick='openEditGoalModal(<?= json_encode($g) ?>)'>
                            <?= get_icon('edit', 'w-4 h-4 text-purple-600') ?>
                        </button>
                        <form method="POST" action="actions/tabungan_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus target tabungan ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_goal">
                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-icon" title="Hapus" style="color: var(--color-red);">
                                <?= get_icon('trash', 'w-4 h-4') ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container" style="height: 12px; margin: 16px 0;">
                    <div class="progress-bar <?= $isCompleted ? 'normal' : 'purple' ?>" style="width: <?= $percent ?>%;"></div>
                </div>

                <!-- Info Nominal -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px; background-color: #F8FAFC; padding: 12px; border-radius: var(--radius-md);">
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 11px; text-transform: uppercase;">Dana Terkumpul</span>
                        <strong style="color: var(--color-green); font-size: 15px;"><?= format_rupiah($collected) ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 11px; text-transform: uppercase;">Target Nominal</span>
                        <strong style="color: var(--color-navy); font-size: 15px;"><?= format_rupiah($target) ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 11px; text-transform: uppercase;">Kekurangan Dana</span>
                        <strong style="color: <?= $remaining > 0 ? 'var(--color-red)' : 'var(--color-green)' ?>;"><?= format_rupiah($remaining) ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block; font-size: 11px; text-transform: uppercase;">Target Tanggal</span>
                        <strong style="color: var(--color-navy);"><?= $g['target_date'] ? format_date_id($g['target_date']) : 'Fleksibel' ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Riwayat Alokasi / Setoran Tabungan -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= get_icon('credit-card', 'w-5 h-5 text-purple-600') ?> Riwayat Alokasi Dana Tabungan Terbaru</h3>
    </div>

    <?php if (empty($recentContributions)): ?>
        <p style="color: var(--color-text-muted); font-size: 14px; text-align: center; padding: 20px;">Belum ada riwayat setoran tabungan.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Target Tabungan</th>
                        <th>Sumber Rekening</th>
                        <th>Nominal Disetor</th>
                        <th>Catatan</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentContributions as $c): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= format_date_id($c['contribution_date']) ?></td>
                            <td><strong><?= htmlspecialchars($c['goal_title']) ?></strong></td>
                            <td><?= htmlspecialchars($c['account_name'] ?: 'Umum') ?></td>
                            <td style="font-weight: 700; color: var(--color-green); white-space: nowrap;">
                                +<?= format_rupiah($c['amount']) ?>
                            </td>
                            <td style="color: var(--color-text-muted); font-size: 13px;"><?= htmlspecialchars($c['notes'] ?: '-') ?></td>
                            <td style="text-align: right;">
                                <form method="POST" action="actions/tabungan_action.php" style="display: inline-block;" onsubmit="return confirmDelete('Hapus catatan setoran tabungan ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_contribution">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<!-- MODAL BUAT / EDIT TARGET TABUNGAN -->
<div class="modal-overlay" id="modalGoalForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="goalModalTitle">Buat Target Tabungan Baru</h3>
            <button class="modal-close" onclick="closeModal('modalGoalForm')">&times;</button>
        </div>
        <form method="POST" action="actions/tabungan_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="goalFormAction" value="create_goal">
            <input type="hidden" name="id" id="goalFormId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Target Impian <span class="required">*</span></label>
                    <input type="text" name="title" id="goalTitle" class="form-control" placeholder="Contoh: Beli Laptop Baru, Dana Darurat" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Nominal Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="target_amount" id="goalTargetAmount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Target Tanggal Selesai (Opsional)</label>
                    <input type="date" name="target_date" id="goalTargetDate" class="form-control">
                </div>

                <div class="form-group" id="goalStatusContainer" style="display: none;">
                    <label class="form-label">Status Target</label>
                    <select name="status" id="goalStatus" class="form-control">
                        <option value="in_progress">Sedang Berjalan</option>
                        <option value="completed">Telah Tercapai</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi / Keterangan (Opsional)</label>
                    <textarea name="description" id="goalDescription" class="form-control" rows="2" placeholder="Tujuan atau motivasi tabungan ini..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalGoalForm')">Batal</button>
                <button type="submit" class="btn btn-primary" id="goalSubmitBtn">Simpan Target</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SETOR DANA TABUNGAN -->
<div class="modal-overlay" id="modalDepositForm">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Setor Dana ke Tabungan</h3>
            <button class="modal-close" onclick="closeModal('modalDepositForm')">&times;</button>
        </div>
        <form method="POST" action="actions/tabungan_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_contribution">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pilih Target Tabungan <span class="required">*</span></label>
                    <select name="goal_id" id="depositGoalId" class="form-control" required>
                        <?php foreach ($goals as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tanggal Setor <span class="required">*</span></label>
                    <input type="date" name="contribution_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal Setoran Rupiah <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-addon">Rp</span>
                        <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Diambil dari Rekening / Dompet (Opsional)</label>
                    <select name="account_id" class="form-control">
                        <option value="">-- Alokasi Tabungan Bebas (Tanpa Potong Rekening) --</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748B; font-size: 12px; margin-top: 4px; display: block;">Jika dipilih, saldo rekening tersebut akan dipindahkan ke tabungan.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan setoran..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalDepositForm')">Batal</button>
                <button type="submit" class="btn btn-success">Simpan Setoran Tabungan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddGoalModal() {
    document.getElementById('goalModalTitle').innerText = 'Buat Target Tabungan Baru';
    document.getElementById('goalFormAction').value = 'create_goal';
    document.getElementById('goalFormId').value = '';
    document.getElementById('goalTitle').value = '';
    document.getElementById('goalTargetAmount').value = '';
    document.getElementById('goalTargetDate').value = '';
    document.getElementById('goalDescription').value = '';
    document.getElementById('goalStatusContainer').style.display = 'none';
    document.getElementById('goalSubmitBtn').innerText = 'Simpan Target';
    openModal('modalGoalForm');
}

function openEditGoalModal(data) {
    document.getElementById('goalModalTitle').innerText = 'Edit Target Tabungan';
    document.getElementById('goalFormAction').value = 'update_goal';
    document.getElementById('goalFormId').value = data.id;
    document.getElementById('goalTitle').value = data.title;
    document.getElementById('goalTargetAmount').value = formatThousands(Math.round(data.target_amount));
    document.getElementById('goalTargetDate').value = data.target_date || '';
    document.getElementById('goalDescription').value = data.description || '';
    document.getElementById('goalStatus').value = data.status || 'in_progress';
    document.getElementById('goalStatusContainer').style.display = 'block';
    document.getElementById('goalSubmitBtn').innerText = 'Perbarui Target';
    openModal('modalGoalForm');
}

function openDepositModal() {
    openModal('modalDepositForm');
}

function openDepositForGoal(goalId, goalTitle) {
    document.getElementById('depositGoalId').value = goalId;
    openModal('modalDepositForm');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

