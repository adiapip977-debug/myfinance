<?php
/**
 * MYFINANCE — Register Page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, langsung ke dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$nameVal = '';
$usernameVal = '';
$emailVal = '';
$initialBalanceVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $initialBalanceRaw = $_POST['initial_balance'] ?? '0';

    $nameVal = clean_input($name);
    $usernameVal = clean_input($username);
    $emailVal = clean_input($email);
    $initialBalanceVal = clean_input($initialBalanceRaw);

    $initialBalance = parse_rupiah($initialBalanceRaw);

    // Validasi
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        $error = 'Semua kolom bertanda bintang (*) wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username minimal 3 karakter dan hanya boleh berisi huruf, angka, dan underscore (_).';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal harus terdiri dari 6 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Konfirmasi kata sandi tidak cocok dengan kata sandi.';
    } elseif ($initialBalance < 0) {
        $error = 'Saldo awal tidak boleh bernilai negatif.';
    } else {
        try {
            $pdo = get_db_connection();

            // Cek apakah username atau email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username atau alamat email sudah terdaftar. Silakan gunakan yang lain.';
            } else {
                // Simpan user baru
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, initial_balance) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $username, $email, $hashedPassword, $initialBalance]);
                $newUserId = (int)$pdo->lastInsertId();

                // Buat akun/rekening bawaan (Dompet Tunai, Rekening Bank, E-Wallet)
                seed_default_user_data($pdo, $newUserId);

                // Update saldo awal ke rekening utama jika saldo awal > 0
                if ($initialBalance > 0) {
                    $stmtAcc = $pdo->prepare("UPDATE accounts SET initial_balance = ? WHERE user_id = ? AND type = 'cash' LIMIT 1");
                    $stmtAcc->execute([$initialBalance, $newUserId]);
                }

                // Ambil data user untuk langsung login otomatis
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$newUserId]);
                $newUser = $stmt->fetch();

                login_user($newUser);
                set_flash('success', 'Akun MYFINANCE berhasil didaftarkan! Selamat datang, ' . htmlspecialchars($name) . '.');
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('[MYFINANCE Register Error] ' . $e->getMessage());
            $error = 'Terjadi kegagalan saat mendaftarkan akun. Silakan coba sesaat lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Akun — MYFINANCE</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#111827">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-192.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #111827 0%, #1F2937 100%);
            padding: 32px 20px;
        }
        .auth-card {
            background-color: #FFFFFF;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
            padding: 36px 32px;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #7C3AED, #A78BFA);
            color: #FFFFFF;
            border-radius: 12px;
            font-size: 22px;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(124, 58, 237, 0.35);
            margin-bottom: 12px;
        }
        .auth-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-navy);
            letter-spacing: -0.02em;
        }
        .auth-subtitle {
            font-size: 13px;
            color: var(--color-text-muted);
            margin-top: 4px;
        }
        .auth-alert-error {
            background-color: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--color-text-muted);
        }
        .auth-footer a {
            font-weight: 600;
            color: var(--color-purple);
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">M</div>
            <h1 class="auth-title">Daftar Akun MYFINANCE</h1>
            <p class="auth-subtitle">Mulai kelola keuangan pribadi Anda secara terstruktur</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="auth-alert-error">
                <?= get_icon('alert-circle', 'w-5 h-5 flex-shrink-0 text-red-600') ?>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Contoh: Budi Santoso" value="<?= $nameVal ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: budi_santoso" value="<?= $usernameVal ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Alamat Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" placeholder="budi@example.com" value="<?= $emailVal ?>" required>
            </div>

            <div class="form-group">
                <label for="initial_balance" class="form-label">Saldo Awal (Opsional)</label>
                <div class="input-group">
                    <span class="input-addon">Rp</span>
                    <input type="text" id="initial_balance" name="initial_balance" class="form-control rupiah-input" placeholder="0" value="<?= $initialBalanceVal ?: '0' ?>">
                </div>
                <small style="color: #64748B; font-size: 12px; margin-top: 4px; display: block;">Saldo awal uang tunai atau rekening Anda saat ini.</small>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Kata Sandi <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label for="password_confirm" class="form-label">Konfirmasi Kata Sandi <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Ulangi kata sandi" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px; font-size: 15px;">
                Daftar & Masuk
            </button>
        </form>

        <div class="auth-footer">
            Sudah memiliki akun? <a href="login.php">Masuk Sekarang</a>
        </div>
    </div>
</div>
<script src="assets/js/script.js"></script>
</body>
</html>

