<?php
session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

$configFile = 'config.json';
$message = '';
$error = '';

// 設定ファイルの読み込み
$defaults = [];
if (file_exists($configFile)) {
    $defaults = json_decode(file_get_contents($configFile), true);
}

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = $_POST['health_check_year'] ?? null;
    $season = $_POST['health_check_season'] ?? null;
    $enable_defaults = isset($_POST['enable_defaults']);

    $newDefaults = [
        'year' => $year,
        'season' => $season,
        'enabled' => $enable_defaults
    ];

    if (file_put_contents($configFile, json_encode($newDefaults, JSON_PRETTY_PRINT))) {
        $message = '設定を保存しました。';
        $defaults = $newDefaults; // ページに新しい設定を反映
    } else {
        $error = '設定ファイルの保存に失敗しました。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者設定</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
    <div class="container">
        <header class="header">
            <h1>管理者設定</h1>
            <p class="subtitle">問診票のデフォルト設定</p>
        </header>

        <div style="padding: 20px;">
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="section">
                    <h2>健康診断のデフォルト設定</h2>
                    <p style="font-size: 11px; color: #666; margin-bottom: 15px;">
                        ここで設定した年度と時期は、問診票入力画面(index.php)で自動的に選択され、変更できなくなります。
                    </p>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="enable_defaults" value="1" <?php echo ($defaults['enabled'] ?? false) ? 'checked' : ''; ?>>
                            デフォルト設定を有効にする
                        </label>
                    </div>

                    <div class="form-group">
                        <label>年度</label>
                        <div class="radio-group">
                            <?php for ($y = 2025; $y <= 2030; $y++): ?>
                                <label>
                                    <input type="radio" name="health_check_year" value="<?php echo $y; ?>" <?php echo (($defaults['year'] ?? '') == $y) ? 'checked' : ''; ?>> <?php echo $y; ?>年
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>時期</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="health_check_season" value="春" <?php echo (($defaults['season'] ?? '') === '春') ? 'checked' : ''; ?>> 春
                            </label>
                            <label>
                                <input type="radio" name="health_check_season" value="冬" <?php echo (($defaults['season'] ?? '') === '冬') ? 'checked' : ''; ?>> 冬
                            </label>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">設定を保存</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary" style="text-decoration: none;">ダッシュボードに戻る</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>