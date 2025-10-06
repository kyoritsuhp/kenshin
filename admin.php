<!--
ファイル名称: admin.php
生成日時: 2025-10-02
-->
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
    <title>管理者ログイン</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>管理者ログイン</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="id">管理者ID</label>
                <input type="text" id="id" name="id" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="button-group">
                <button type="submit" name="login" class="btn btn-primary">ログイン</button>
            </div>
        </form>
        
        <div class="admin-link">
            <a href="index.php">問診票入力画面に戻る</a>
        </div>
    </div>
</body>
</html>
