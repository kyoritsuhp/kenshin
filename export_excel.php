<!--
ファイル名称: export_excel.php
生成日時: 2025-10-02
-->
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

// データ取得
$stmt = $pdo->query("SELECT * FROM questionnaire_responses ORDER BY submitted_at DESC");
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Excel形式（HTML table）で出力
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="monshin_' . date('Ymd_His') . '.xls"');

// BOM付加
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; font-size: 11px; }
        th { background-color: #4472C4; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>回答ID</th>
                <th>職員ID</th>
                <th>氏名</th>
                <th>所属部署</th>
                <th>Q1_血圧を下げる薬</th>
                <th>Q2_インスリン又は血糖を下げる薬</th>
                <th>Q3_コレステロールを下げる薬</th>
                <th>Q4_脳卒中</th>
                <th>Q5_心臓病</th>
                <th>Q6_慢性腎不全</th>
                <th>Q7_貧血</th>
                <th>Q8_喫煙</th>
                <th>Q9_体重増加10kg以上</th>
                <th>Q10_運動週2日以上</th>
                <th>Q11_歩行1日1時間以上</th>
                <th>Q12_歩く速度が速い</th>
                <th>Q13_体重増減±3kg以上</th>
                <th>Q14_食べる速度</th>
                <th>Q15_就寝前2時間以内の夕食</th>
                <th>Q16_夕食後の間食</th>
                <th>Q17_朝食を抜く</th>
                <th>Q18_飲酒頻度</th>
                <th>Q19_飲酒量</th>
                <th>Q20_睡眠で休養</th>
                <th>Q21_生活習慣改善意欲</th>
                <th>Q22_保健指導利用意向</th>
                <th>送信日時</th>
                <th>更新日時</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($responses as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['response_id']); ?></td>
                <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                <td><?php echo htmlspecialchars($row['department']); ?></td>
                <td><?php echo htmlspecialchars($row['q1_blood_pressure_med']); ?></td>
                <td><?php echo htmlspecialchars($row['q2_insulin_med']); ?></td>
                <td><?php echo htmlspecialchars($row['q3_cholesterol_med']); ?></td>
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
                <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
