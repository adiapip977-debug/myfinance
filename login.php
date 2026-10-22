<?php
/**
 * MYFINANCE — Login Page
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
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $usernameVal = clean_input($usernameOrEmail);

    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Silakan isi username/email dan kata sandi Anda.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute([
                ':username' => $usernameOrEmail,
                ':email'    => $usernameOrEmail
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                login_user($user);
                set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($user['name']) . '!');
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Username, email, atau kata sandi yang Anda masukkan salah.';
            }
        } catch (PDOException $e) {
            error_log('[MYFINANCE Login Error] ' . $e->getMessage());
            $error = 'Terjadi kesalahan sistem saat mencoba login. Silakan coba sesaat lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — MYFINANCE</title>
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
            padding: 24px;
        }
        .auth-card {
            background-color: #FFFFFF;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 440px;
            width: 100%;
            padding: 36px 32px;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .auth-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #7C3AED, #A78BFA);
            color: #FFFFFF;
            border-radius: 14px;
            font-size: 24px;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(124, 58, 237, 0.35);
            margin-bottom: 14px;
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
            <h1 class="auth-title">MYFINANCE</h1>
            <p class="auth-subtitle">Kelola dan pantau keuangan pribadi Anda dengan cerdas</p>
        </div>

        <?= render_flash() ?>

        <?php if (!empty($error)): ?>
            <div class="auth-alert-error">
                <?= get_icon('alert-circle', 'w-5 h-5 flex-shrink-0 text-red-600') ?>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username" class="form-label">Username atau Email</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: user atau email@anda.com" value="<?= $usernameVal ?>" required autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label for="password" class="form-label" style="margin-bottom: 0;">Kata Sandi</label>
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan kata sandi akun" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px; font-size: 15px;">
                Masuk ke Aplikasi
            </button>
        </form>

        <div class="auth-footer">
            Belum memiliki akun? <a href="register.php">Daftar Akun Baru</a>
        </div>
    </div>
</div>
</body>
</html>

