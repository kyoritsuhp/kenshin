<?php
session_start();

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

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

// 全回答データ取得
$stmt = $pdo->query("SELECT * FROM questionnaire_responses ORDER BY submitted_at DESC");
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ダッシュボード</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="dashboard-page">
    <div class="container">
        <header class="header" style="position: relative;">
            <h1>管理者ダッシュボード</h1>
            <p class="subtitle">問診票回答管理</p>
            <a href="?logout=1" class="logout-btn">ログアウト</a>
        </header>

        <div style="padding: 20px;">
            <div class="action-buttons">
                <a href="export_csv.php" class="btn btn-primary btn-small">CSV出力</a>
                <a href="export_excel.php" class="btn btn-primary btn-small">Excel出力</a>
                <button onclick="window.print()" class="btn btn-secondary btn-small">印刷</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>職員ID</th>
                            <th>氏名</th>
                            <th>部署</th>
                            <th>Q1</th>
                            <th>Q1薬名</th>
                            <th>Q2</th>
                            <th>Q2薬名</th>
                            <th>Q3</th>
                            <th>Q3薬名</th>
                            <th>Q4</th>
                            <th>Q5</th>
                            <th>Q6</th>
                            <th>Q7</th>
                            <th>Q8</th>
                            <th>Q9</th>
                            <th>Q10</th>
                            <th>Q11</th>
                            <th>Q12</th>
                            <th>Q13</th>
                            <th>Q14</th>
                            <th>Q15</th>
                            <th>Q16</th>
                            <th>Q17</th>
                            <th>Q18</th>
                            <th>Q19</th>
                            <th>Q20</th>
                            <th>Q21</th>
                            <th>Q22</th>
                            <th>送信日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responses as $row): ?>
                        <tr>
                            <td><a href="admin_index.php?id=<?php echo htmlspecialchars($row['response_id']); ?>"><?php echo htmlspecialchars($row['response_id']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['q1_blood_pressure_med']); ?></td>
                            <td><?php echo htmlspecialchars($row['q1_medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['q2_insulin_med']); ?></td>
                            <td><?php echo htmlspecialchars($row['q2_medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['q3_cholesterol_med']); ?></td>
                            <td><?php echo htmlspecialchars($row['q3_medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['q4_stroke']); ?></td>
                            <td><?php echo htmlspecialchars($row['q5_heart_disease']); ?></td>
                            <td><?php echo htmlspecialchars($row['q6_kidney_failure']); ?></td>
                            <td><?php echo htmlspecialchars($row['q7_anemia']); ?></td>
                            <td><?php echo htmlspecialchars($row['q8_smoking']); ?></td>
                            <td><?php echo htmlspecialchars($row['q9_weight_gain']); ?></td>
                            <td><?php echo htmlspecialchars($row['q10_exercise']); ?></td>
                            <td><?php echo htmlspecialchars($row['q11_walking']); ?></td>
                            <td><?php echo htmlspecialchars($row['q12_walking_speed']); ?></td>
                            <td><?php echo htmlspecialchars($row['q13_weight_change']); ?></td>
                            <td><?php echo htmlspecialchars($row['q14_eating_speed']); ?></td>
                            <td><?php echo htmlspecialchars($row['q15_dinner_before_bed']); ?></td>
                            <td><?php echo htmlspecialchars($row['q16_snack_after_dinner']); ?></td>
                            <td><?php echo htmlspecialchars($row['q17_skip_breakfast']); ?></td>
                            <td><?php echo htmlspecialchars($row['q18_alcohol_frequency']); ?></td>
                            <td><?php echo htmlspecialchars($row['q19_alcohol_amount']); ?></td>
                            <td><?php echo htmlspecialchars($row['q20_sleep']); ?></td>
                            <td><?php echo htmlspecialchars($row['q21_improvement_intention']); ?></td>
                            <td><?php echo htmlspecialchars($row['q22_guidance_use']); ?></td>
                            <td><?php echo htmlspecialchars($row['submitted_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>