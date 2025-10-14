<!--
ファイル名称: export_csv.php
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

// CSV出力設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="monshin_' . date('Ymd_His') . '.csv"');

// BOM付加（Excel対応）
echo "\xEF\xBB\xBF";

// 出力バッファ
$output = fopen('php://output', 'w');

// ヘッダー行
$headers = [
    '回答ID',
    '職員ID',
    '氏名',
    '所属部署',
    'Q1_血圧を下げる薬',
    'Q2_インスリン又は血糖を下げる薬',
    'Q3_コレステロールを下げる薬',
    'Q4_脳卒中',
    'Q5_心臓病',
    'Q6_慢性腎不全',
    'Q7_貧血',
    'Q8_喫煙',
    'Q9_体重増加10kg以上',
    'Q10_運動週2日以上',
    'Q11_歩行1日1時間以上',
    'Q12_歩く速度が速い',
    'Q13_体重増減±3kg以上',
    'Q14_食べる速度',
    'Q15_就寝前2時間以内の夕食',
    'Q16_夕食後の間食',
    'Q17_朝食を抜く',
    'Q18_飲酒頻度',
    'Q19_飲酒量',
    'Q20_睡眠で休養',
    'Q21_生活習慣改善意欲',
    'Q22_保健指導利用意向',
    '送信日時',
    '更新日時'
];
fputcsv($output, $headers);

// データ行
foreach ($responses as $row) {
    $data = [
        $row['response_id'],
        $row['staff_id'],
        $row['staff_name'],
        $row['department'],
        $row['q1_blood_pressure_med'],
        $row['q2_insulin_med'],
        $row['q3_cholesterol_med'],
        $row['q4_stroke'],
        $row['q5_heart_disease'],
        $row['q6_kidney_failure'],
        $row['q7_anemia'],
        $row['q8_smoking'],
        $row['q9_weight_gain'],
        $row['q10_exercise'],
        $row['q11_walking'],
        $row['q12_walking_speed'],
        $row['q13_weight_change'],
        $row['q14_eating_speed'],
        $row['q15_dinner_before_bed'],
        $row['q16_snack_after_dinner'],
        $row['q17_skip_breakfast'],
        $row['q18_alcohol_frequency'],
        $row['q19_alcohol_amount'],
        $row['q20_sleep'],
        $row['q21_improvement_intention'],
        $row['q22_guidance_use'],
        $row['submitted_at'],
        $row['updated_at']
    ];
    fputcsv($output, $data);
}

fclose($output);
exit;
