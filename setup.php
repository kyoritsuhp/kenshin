<?php
// 設定ファイルを読み込む
require_once 'config.php';

$admin_id = 'admin';
$admin_pass = 'admin'; // 初期パスワード

try {
    // データベースに接続
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // テーブルを作成
    $sql = file_get_contents('database_setup.sql');
    $pdo->exec($sql);
    echo "テーブルが正常に作成されました。\n";

    // 管理者アカウントを安全に作成
    $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (id, password, username) VALUES (:id, :password, :username)");
    $stmt->execute([
        ':id' => $admin_id,
        ':password' => $hashed_password,
        ':username' => '管理者'
    ]);
    echo "管理者アカウントが正常に作成されました。\n";
    echo "ID: " . htmlspecialchars($admin_id) . "\n";
    echo "初期パスワード: " . htmlspecialchars($admin_pass) . "\n";

} catch (PDOException $e) {
    die("セットアップエラー: " . $e->getMessage());
}
?>