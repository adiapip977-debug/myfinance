<?php
/**
 * MYFINANCE — Profil & Pengaturan Akun
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$userId = get_logged_in_user_id();
$pdo = get_db_connection();

// Ambil data user terkini
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Total saldo
$summary = get_financial_summary($pdo, $userId);

$pageTitle = 'Profil Pengguna';
$pageHeading = 'Pengaturan Profil & Akun';
$currentPage = 'profil';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="grid-2">
    <!-- Kartu Informasi & Ubah Profil -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= get_icon('user', 'w-5 h-5 text-purple-600') ?> Informasi Pribadi & Preferensi</h3>
        </div>

        <form method="POST" action="actions/profil_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Saldo Awal Dasar</label>
                <div class="input-group">
                    <span class="input-addon">Rp</span>
                    <input type="text" name="initial_balance" class="form-control rupiah-input" value="<?= format_rupiah($user['initial_balance'], false) ?>">
                </div>
                <small style="color: #64748B; font-size: 12px; margin-top: 4px; display: block;">
                    Saldo awal yang menjadi dasar perhitungan total kekayaan Anda.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Mata Uang Aplikasi</label>
                <input type="text" class="form-control" value="Rupiah Indonesia (IDR - Rp)" disabled style="background-color: #F1F5F9; color: #64748B;">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn btn-primary">Simpan Perubahan Profil</button>
            </div>
        </form>
    </div>

    <!-- Kolom Kanan: Ganti Password & Ringkasan Akun -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Kartu Ubah Password -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title"><?= get_icon('credit-card', 'w-5 h-5 text-purple-600') ?> Ganti Kata Sandi</h3>
            </div>

            <form method="POST" action="actions/profil_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label">Kata Sandi Saat Ini <span class="required">*</span></label>
                    <input type="password" name="old_password" class="form-control" placeholder="Masukkan password lama" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kata Sandi Baru <span class="required">*</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Konfirmasi Kata Sandi Baru <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi kata sandi baru" required>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Perbarui Kata Sandi</button>
                </div>
            </form>
        </div>

        <!-- Kartu Ringkasan Status Akun -->
        <div class="card" style="margin-bottom: 0; background-color: #F8FAFC;">
            <h4 style="font-size: 15px; font-weight: 700; color: var(--color-navy); margin-bottom: 12px;">Informasi Keanggotaan</h4>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-muted);">Status Akun:</span>
                    <span class="badge badge-income">Aktif & Terverifikasi</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-muted);">Terdaftar Sejak:</span>
                    <strong><?= format_date_id($user['created_at'], true) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--color-text-muted);">Total Saldo Terhitung:</span>
                    <strong style="color: var(--color-navy);"><?= format_rupiah($summary['total_balance']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
