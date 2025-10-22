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

// --- フィルター処理 ---
$facility_filter = $_GET['facility'] ?? 'all';
$year_filter = $_GET['year'] ?? 'all';
$season_filter = $_GET['season'] ?? 'all';

$sql_base = "SELECT * FROM questionnaire_responses";
$where_clauses = [];
$params = [];
$query_string_params = []; // For export links

if ($facility_filter !== 'all') {
    $where_clauses[] = "facility_name = :facility_name";
    $params[':facility_name'] = $facility_filter;
    $query_string_params['facility'] = $facility_filter;
}
if ($year_filter !== 'all') {
    $where_clauses[] = "health_check_year = :health_check_year";
    $params[':health_check_year'] = $year_filter;
    $query_string_params['year'] = $year_filter;
}
if ($season_filter !== 'all') {
    $where_clauses[] = "health_check_season = :health_check_season";
    $params[':health_check_season'] = $season_filter;
    $query_string_params['season'] = $season_filter;
}

$sql = $sql_base;
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// エクスポート用のクエリ文字列
$export_query_string = http_build_query($query_string_params);
if (!empty($export_query_string)) {
    $export_query_string = '?' . $export_query_string;
}
// --- フィルター処理ここまで ---


// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// フィルター用のユニークな値を取得
$years = $pdo->query("SELECT DISTINCT health_check_year FROM questionnaire_responses ORDER BY health_check_year DESC")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ダッシュボード</title>
    <link rel="stylesheet" href="style.css">
    <!-- フィルタースタイルを追加 -->
    <style>
        .filter-section {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .filter-group strong {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: #555;
        }
        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-options label {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 11px;
            font-weight: normal;
        }
        .filter-options label:hover {
            background: #e9ecef;
            border-color: #667eea;
        }
        .filter-options label input[type="radio"] {
            margin-right: 5px;
        }
        .filter-options label:has(input[type="radio"]:checked) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        /* ▼▼▼ 横並び用スタイルを追加 ▼▼▼ */
        .filter-row {
            display: flex;
            gap: 20px; /* グループ間の隙間 */
        }
        .filter-row .filter-group {
            flex: 1; /* 幅を均等に分割 */
            min-width: 0; /* flexアイテムが縮小できるように */
        }
        /* ▲▲▲ 横並び用スタイルを追加 ▲▲▲ */
    </style>
</head>

<body class="dashboard-page">
    <div class="container">
        <header class="header" style="position: relative;">
            <a href="index.php" class="back-to-form-btn">← 問診票入力画面に戻る</a>
            <h1>管理者ダッシュボード</h1>
            <p class="subtitle">問診票回答管理</p>
            <a href="?logout=1" class="logout-btn">ログアウト</a>
        </header>

        <div style="padding: 20px;">
            
            <!-- ▼▼▼ フィルターフォーム ▼▼▼ -->
            <div class="filter-section">
                <form method="GET" action="admin_dashboard.php">
                    <div class="filter-group">
                        <strong>施設名</strong>
                        <div class="filter-options">
                            <label><input type="radio" name="facility" value="all" <?php echo ($facility_filter == 'all') ? 'checked' : ''; ?>> すべて</label>
                            <label><input type="radio" name="facility" value="協立病院" <?php echo ($facility_filter == '協立病院') ? 'checked' : ''; ?>> 協立病院</label>
                            <label><input type="radio" name="facility" value="清寿園" <?php echo ($facility_filter == '清寿園') ? 'checked' : ''; ?>> 清寿園</label>
                            <label><input type="radio" name="facility" value="かがやき" <?php echo ($facility_filter == 'かがやき') ? 'checked' : ''; ?>> かがやき</label>
                            <label><input type="radio" name="facility" value="かがやき2号館" <?php echo ($facility_filter == 'かがやき2号館') ? 'checked' : ''; ?>> かがやき2号館</label>
                        </div>
                    </div>
                    
                    <!-- ▼▼▼ 横並びにするためのラッパー ▼▼▼ -->
                    <div class="filter-row">
                        <div class="filter-group">
                            <strong>年度</strong>
                            <div class="filter-options">
                                <label><input type="radio" name="year" value="all" <?php echo ($year_filter == 'all') ? 'checked' : ''; ?>> すべて</label>
                                <?php foreach ($years as $year): ?>
                                <label><input type="radio" name="year" value="<?php echo htmlspecialchars($year); ?>" <?php echo ($year_filter == $year) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($year); ?>年</label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="filter-group">
                            <strong>時期</strong>
                            <div class="filter-options">
                                <label><input type="radio" name="season" value="all" <?php echo ($season_filter == 'all') ? 'checked' : ''; ?>> すべて</label>
                                <label><input type="radio" name="season" value="春" <?php echo ($season_filter == '春') ? 'checked' : ''; ?>> 春</label>
                                <label><input type="radio" name="season" value="冬" <?php echo ($season_filter == '冬') ? 'checked' : ''; ?>> 冬</label>
                            </div>
                        </div>
                    </div>
                    <!-- ▲▲▲ 横並びラッパーここまで ▲▲▲ -->

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary btn-small">絞り込み</button>
                        <a href="admin_dashboard.php" class="btn btn-secondary btn-small" style="text-decoration: none;">リセット</a>
                    </div>
                </form>
            </div>
            <!-- ▲▲▲ フィルターフォーム ▲▲▲ -->

            <div class="action-buttons">
                <!-- ▼▼▼ エクスポートリンクにフィルター情報を追加 ▼▼▼ -->
                <a href="export_csv.php<?php echo htmlspecialchars($export_query_string); ?>" class="btn btn-primary btn-small">CSV出力</a>
                <a href="export_excel.php<?php echo htmlspecialchars($export_query_string); ?>" class="btn btn-primary btn-small">Excel出力</a>
                <!-- ▲▲▲ エクスポートリンクにフィルター情報を追加 ▲▲▲ -->
                <button onclick="window.print()" class="btn btn-secondary btn-small">印刷</button>
                <a href="admin_manage.php" class="btn btn-primary btn-small">管理画面</a>
            </div>

            <p style="margin-bottom: 10px; font-size: 12px; font-weight: 600;">
                表示件数: <?php echo count($responses); ?>件
            </p>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>職員ID</th>
                            <th>氏名</th>
                            <th>部署</th>
                            <th>施設名</th>
                            <th>年度</th>
                            <th>時期</th>
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
                        <?php if (empty($responses)): ?>
                        <tr>
                            <td colspan="33" style="text-align: center; padding: 20px;">
                                該当するデータがありません。
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($responses as $row): ?>
                        <tr>
                            <td><a href="admin_index.php?id=<?php echo htmlspecialchars($row['response_id']); ?>"><?php echo htmlspecialchars($row['response_id']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['health_check_year']); ?></td>
                            <td><?php echo htmlspecialchars($row['health_check_season']); ?></td>
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


