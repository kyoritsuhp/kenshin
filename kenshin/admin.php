<?php
session_start();

// データベース接続
$db_host = 'localhost';
$db_name = 'monshin';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $id = $_POST['id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND password = :password");
    $stmt->execute([':id' => $id, ':password' => $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $user['username'];
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'IDまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン - 健診問診票</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>健診問診票 管理者ログイン</h2>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="id"><img src="/img/common_user.svg" alt="ID" class="login-icon"> 管理者ID</label>
                    <input type="text" id="id" name="id" class="form-control" value="<?= htmlspecialchars($_POST['id'] ?? '') ?>" placeholder="管理者IDを入力" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password"><img src="/img/common_passward.svg" alt="PASSWORD" class="login-icon"> パスワード</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="パスワードを入力" required>
                </div>
                    <button type="submit" name="login" class="btn btn-primary"><span class="btn-icon-login"></span> ログイン</button>
            </form>
                    <a href="index.php" class="back-link"><span class="btn-icon-back"></span> 問診票入力画面に戻る</a>
        </div>
    </div>
</body>
</html>