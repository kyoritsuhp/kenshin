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

// ▼▼▼ ソート処理を追加 ▼▼▼
// ホワイトリスト方式で許可するカラムを定義
$allowed_sort_columns = [
    'response_id', 
    'staff_id', 
    'staff_name', 
    'department', 
    'facility_name', 
    'health_check_year', 
    'health_check_season', 
    'submitted_at'
];
$sort_by = $_GET['sort_by'] ?? 'submitted_at';
// GETパラメータが 'asc' の場合のみ 'ASC' とし、それ以外 (desc, null, 不正な値) は 'DESC' とする
$sort_order = strtolower($_GET['sort_order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

// $sort_by が許可リストに含まれていない場合は、デフォルト値（submitted_at）に戻す
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'submitted_at';
}
// ▲▲▲ ソート処理を追加 ▲▲▲


$sql_base = "SELECT * FROM questionnaire_responses";
$where_clauses = [];
$params = [];
$query_string_params = []; // For export links & sort links

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

// ▼▼▼ ソート順を動的に変更 ▼▼▼
$sql .= " ORDER BY $sort_by $sort_order";
// ▲▲▲ ソート順を動的に変更 ▲▲▲

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// エクスポート用のクエリ文字列 (フィルターのみ)
$export_query_string = http_build_query($query_string_params);
if (!empty($export_query_string)) {
    $export_query_string = '?' . $export_query_string;
}

// --- ▼▼▼ 汎用ソートリンク生成関数 (修正済み) ▼▼▼ ---
// ソート用のリンクとインジケーター（▲▼）を生成する関数
function get_sort_link($column_name, $display_name, $current_sort_by, $current_sort_order, $base_params) {
    // $current_sort_order は DB接続時に 'ASC' または 'DESC' に正規化されている
    
    $next_sort_order = 'asc'; // デフォルトのリンク先 (ソートされていない列をクリックした時)
    $indicator = '';

    // 現在ソート中の列が、この関数の対象列と同じ場合
    if ($current_sort_by === $column_name) {
        // ★修正点: $current_sort_order が 'asc' ではなく 'ASC' (大文字) かをチェック
        if ($current_sort_order === 'ASC') {
            // 現在 '昇順' なので、次のリンクは '降順 (desc)' にする
            $next_sort_order = 'desc'; 
            $indicator = ' <span class="sort-asc">▲</span>';
        } else {
            // 現在 '降順 (DESC)' なので、次のリンクは '昇順 (asc)' にする
            $next_sort_order = 'asc'; // ★修正点: 'asc' を明示的に指定
            $indicator = ' <span class="sort-desc">▼</span>';
        }
    }

    // 既存のフィルターパラメータにソートパラメータを追加
    $link_params = $base_params;
    $link_params['sort_by'] = $column_name;
    $link_params['sort_order'] = $next_sort_order;
    
    $url = 'admin_dashboard.php?' . http_build_query($link_params);
    
    // HTMLリンクを返す
    return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($display_name) . $indicator . '</a>';
}
// --- ▲▲▲ 汎用ソートリンク生成関数 (修正済み) ▲▲▲ ---


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
        .filter-row {
            display: flex;
            gap: 20px;
        }
        .filter-row .filter-group {
            flex: 1;
            min-width: 0;
        }
        
        /* ソート用スタイル */
        th a {
            color: white;
            text-decoration: none;
            display: block;
        }
        th a:hover {
            text-decoration: underline;
        }
        .sort-asc, .sort-desc {
            font-size: 9px;
            vertical-align: middle;
        }
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
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary btn-small">絞り込み</button>
                        <a href="admin_dashboard.php" class="btn btn-secondary btn-small" style="text-decoration: none;">リセット</a>
                    </div>
                </form>
            </div>
            <div class="action-buttons">
                <a href="export_csv.php<?php echo htmlspecialchars($export_query_string); ?>" class="btn btn-primary btn-small">CSV出力</a>
                <a href="export_excel.php<?php echo htmlspecialchars($export_query_string); ?>" class="btn btn-primary btn-small">Excel出力</a>
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
                            <th><?php echo get_sort_link('response_id', 'ID', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('staff_id', '職員ID', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('facility_name', '施設名', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('health_check_year', '年度', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('health_check_season', '時期', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('department', '部署', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('staff_name', '氏名', $sort_by, $sort_order, $query_string_params); ?></th>
                            <th><?php echo get_sort_link('submitted_at', '送信日時', $sort_by, $sort_order, $query_string_params); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($responses)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                該当するデータがありません。
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($responses as $row): ?>
                        <tr>
                            <td><a href="admin_index.php?id=<?php echo htmlspecialchars($row['response_id']); ?>"><?php echo htmlspecialchars($row['response_id']); ?></a></td>
                            <td><?php echo htmlspecialchars($row['staff_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                             <td><?php echo htmlspecialchars($row['health_check_year']); ?></td>
                            <td><?php echo htmlspecialchars($row['health_check_season']); ?></td>
                             <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
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