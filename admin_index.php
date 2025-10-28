<?php
session_start();

// ログインチェック
// 1. 健診システムのセッション (admin_logged_in)
$is_kenshin_admin = (
    isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true
);

// 2. ポータルからの特権アクセス (admin=1 または kenshin=1)
$is_portal_privileged = (
    isset($_SESSION['user_id']) && // ポータルのログインID
    ((isset($_SESSION['admin']) && $_SESSION['admin'] == 1) || (isset($_SESSION['kenshin']) && $_SESSION['kenshin'] == 1))
);

// どちらの権限も持っていない場合、ログイン画面に戻す
if (!$is_kenshin_admin && !$is_portal_privileged) {
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

$message = '';
$error = '';
$response = null;

// GETまたはPOSTからIDを取得（削除処理のためにPOSTからも取得）
$response_id = $_GET['id'] ?? $_POST['response_id'] ?? null;
if (!$response_id) {
    die('IDが指定されていません。');
}

// ▼▼▼ 削除処理を追加 ▼▼▼
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $sql = "DELETE FROM questionnaire_responses WHERE response_id = :response_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':response_id' => $response_id]);

        // 削除が成功したらダッシュボードにリダイレクト
        header('Location: admin_dashboard.php');
        exit;
    } catch(PDOException $e) {
        $error = '削除中にエラーが発生しました: ' . $e->getMessage();
    }
}
// ▲▲▲ 削除処理を追加 ▲▲▲

// データ更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
    try {
        
        // カルテIDを8桁ゼロパディング
        $karte_id_raw = $_POST['karte_id'] ?? null;
        $karte_id_to_save = null;
        // 値が入力されている（nullでも空文字列でもない）場合のみパディング
        if (!is_null($karte_id_raw) && $karte_id_raw !== '') {
            $karte_id_to_save = str_pad($karte_id_raw, 8, '0', STR_PAD_LEFT);
        }

        $sql = "UPDATE questionnaire_responses SET
            staff_id = :staff_id, karte_id = :karte_id, staff_name = :staff_name, department = :department,
            q1_blood_pressure_med = :q1, q1_medicine_name = :q1_medicine_name,
            q2_insulin_med = :q2, q2_medicine_name = :q2_medicine_name,
            q3_cholesterol_med = :q3, q3_medicine_name = :q3_medicine_name,
            q4_stroke = :q4, q5_heart_disease = :q5, q6_kidney_failure = :q6, q7_anemia = :q7,
            q8_smoking = :q8, q9_weight_gain = :q9, q10_exercise = :q10, q11_walking = :q11,
            q12_walking_speed = :q12, q13_weight_change = :q13, q14_eating_speed = :q14,
            q15_dinner_before_bed = :q15, q16_snack_after_dinner = :q16, q17_skip_breakfast = :q17,
            q18_alcohol_frequency = :q18, q19_alcohol_amount = :q19, q20_sleep = :q20,
            q21_improvement_intention = :q21, q22_guidance_use = :q22
        WHERE response_id = :response_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':staff_id' => $_POST['staff_id'] ?? null,
            ':karte_id' => $karte_id_to_save, // ★ 変更点
            ':staff_name' => $_POST['staff_name'] ?? null,
            ':department' => $_POST['department'] ?? null,
            ':q1' => $_POST['q1'] ?? null,
            ':q1_medicine_name' => $_POST['q1_medicine_name'] ?? null,
            ':q2' => $_POST['q2'] ?? null,
            ':q2_medicine_name' => $_POST['q2_medicine_name'] ?? null,
            ':q3' => $_POST['q3'] ?? null,
            ':q3_medicine_name' => $_POST['q3_medicine_name'] ?? null,
            ':q4' => $_POST['q4'] ?? null,
            ':q5' => $_POST['q5'] ?? null,
            ':q6' => $_POST['q6'] ?? null,
            ':q7' => $_POST['q7'] ?? null,
            ':q8' => $_POST['q8'] ?? null,
            ':q9' => $_POST['q9'] ?? null,
            ':q10' => $_POST['q10'] ?? null,
            ':q11' => $_POST['q11'] ?? null,
            ':q12' => $_POST['q12'] ?? null,
            ':q13' => $_POST['q13'] ?? null,
            ':q14' => $_POST['q14'] ?? null,
            ':q15' => $_POST['q15'] ?? null,
            ':q16' => $_POST['q16'] ?? null,
            ':q17' => $_POST['q17'] ?? null,
            ':q18' => $_POST['q18'] ?? null,
            ':q19' => $_POST['q19'] ?? null,
            ':q20' => $_POST['q20'] ?? null,
            ':q21' => $_POST['q21'] ?? null,
            ':q22' => $_POST['q22'] ?? null,
            ':response_id' => $response_id
        ]);
        
        // 更新成功メッセージをセッションに保存してリダイレクト
        $_SESSION['flash_message'] = "回答ID: $response_id のデータは正常に更新されました。";
        header('Location: admin_dashboard.php');
        exit;

    } catch(PDOException $e) {
        $error = '更新中にエラーが発生しました: ' . $e->getMessage();
    }
}

// 編集対象のデータを取得
try {
    $stmt = $pdo->prepare("SELECT * FROM questionnaire_responses WHERE response_id = :id");
    $stmt->execute([':id' => $response_id]);
    $response = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$response) {
        die('該当するデータが見つかりません。');
    }
} catch(PDOException $e) {
    die("データ取得エラー: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>問診票データ編集</title>
    <link rel="stylesheet" href="admin_index_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="dashboard-page">
    <div class="container">
        <header class="header">
            <h1>問診票データ編集 (回答ID: <?php echo htmlspecialchars($response['response_id']); ?>)</h1>
            <p class="subtitle">標準的な質問票</p>
        </header>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="questionnaire-form" id="questionnaireForm">
            <div class="section">
                <h2><i class="fas fa-user-circle"></i> 職員情報</h2>
                <div class="form-group">
                    <label for="staff_id">職員ID</label>
                    <input type="text" id="staff_id" name="staff_id" value="<?php echo htmlspecialchars($response['staff_id']); ?>">
                </div>
                <div class="form-group">
                    <label for="karte_id">カルテID</label>
                    <input type="text" id="karte_id" name="karte_id" value="<?php echo htmlspecialchars($response['karte_id'] ?? ''); ?>" placeholder="例: 100 (保存時に00100になります)">
                </div>
                <div class="form-group">
                    <label for="staff_name">氏名</label>
                    <input type="text" id="staff_name" name="staff_name" value="<?php echo htmlspecialchars($response['staff_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="department">所属部署</label>
                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($response['department']); ?>">
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-pills"></i> 服薬状況</h2>
                <div class="question">
                    <label>1. a. 血圧を下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q1" value="1" <?php if($response['q1_blood_pressure_med'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q1" value="2" <?php if($response['q1_blood_pressure_med'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                    <div class="form-group" id="q1_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q1_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q1_medicine_name" name="q1_medicine_name" value="<?php echo htmlspecialchars($response['q1_medicine_name']); ?>" placeholder="例：アムロジピン">
                    </div>
                </div>
                <div class="question">
                    <label>2. b. インスリン注射又は血糖を下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q2" value="1" <?php if($response['q2_insulin_med'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q2" value="2" <?php if($response['q2_insulin_med'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                    <div class="form-group" id="q2_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q2_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q2_medicine_name" name="q2_medicine_name" value="<?php echo htmlspecialchars($response['q2_medicine_name']); ?>" placeholder="例：メトホルミン">
                    </div>
                </div>
                <div class="question">
                    <label>3. c. コレステロールを下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q3" value="1" <?php if($response['q3_cholesterol_med'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q3" value="2" <?php if($response['q3_cholesterol_med'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                    <div class="form-group" id="q3_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q3_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q3_medicine_name" name="q3_medicine_name" value="<?php echo htmlspecialchars($response['q3_medicine_name']); ?>" placeholder="例：ロスバスタチン">
                    </div>
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-heartbeat"></i> 既往歴</h2>
                <div class="question">
                    <label>4. 脳卒中（脳出血、脳梗塞等）</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q4" value="1" <?php if($response['q4_stroke'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q4" value="2" <?php if($response['q4_stroke'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>5. 心臓病（狭心症、心筋梗塞等）</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q5" value="1" <?php if($response['q5_heart_disease'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q5" value="2" <?php if($response['q5_heart_disease'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>6. 慢性の腎不全（人工透析）</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q6" value="1" <?php if($response['q6_kidney_failure'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q6" value="2" <?php if($response['q6_kidney_failure'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>7. 貧血</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q7" value="1" <?php if($response['q7_anemia'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q7" value="2" <?php if($response['q7_anemia'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-walking"></i> 生活習慣</h2>
                <div class="question">
                    <label>8. 喫煙</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q8" value="1" <?php if($response['q8_smoking'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q8" value="2" <?php if($response['q8_smoking'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>9. 20歳の時から10kg以上体重増加</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q9" value="1" <?php if($response['q9_weight_gain'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q9" value="2" <?php if($response['q9_weight_gain'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>10. 30分以上の運動を週2日以上</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q10" value="1" <?php if($response['q10_exercise'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q10" value="2" <?php if($response['q10_exercise'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>11. 1日1時間以上歩行</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q11" value="1" <?php if($response['q11_walking'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q11" value="2" <?php if($response['q11_walking'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>12. 歩く速度が速い</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q12" value="1" <?php if($response['q12_walking_speed'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q12" value="2" <?php if($response['q12_walking_speed'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>13. 1年で体重±3kg以上変動</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q13" value="1" <?php if($response['q13_weight_change'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q13" value="2" <?php if($response['q13_weight_change'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-utensils"></i> 食生活</h2>
                <div class="question">
                    <label>14. 食べる速度</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q14" value="1" <?php if($response['q14_eating_speed'] == 1) echo 'checked'; ?>> 速い</label>
                        <label><input type="radio" name="q14" value="2" <?php if($response['q14_eating_speed'] == 2) echo 'checked'; ?>> ふつう</label>
                        <label><input type="radio" name="q14" value="3" <?php if($response['q14_eating_speed'] == 3) echo 'checked'; ?>> 遅い</label>
                    </div>
                </div>
                <div class="question">
                    <label>15. 就寝前2時間以内の夕食</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q15" value="1" <?php if($response['q15_dinner_before_bed'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q15" value="2" <?php if($response['q15_dinner_before_bed'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>16. 夕食後の間食</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q16" value="1" <?php if($response['q16_snack_after_dinner'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q16" value="2" <?php if($response['q16_snack_after_dinner'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>17. 朝食抜き</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q17" value="1" <?php if($response['q17_skip_breakfast'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q17" value="2" <?php if($response['q17_skip_breakfast'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-ellipsis-h"></i> その他</h2>
                <div class="question">
                    <label>18. 飲酒頻度</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q18" value="1" <?php if($response['q18_alcohol_frequency'] == 1) echo 'checked'; ?>> 毎日</label>
                        <label><input type="radio" name="q18" value="2" <?php if($response['q18_alcohol_frequency'] == 2) echo 'checked'; ?>> 時々</label>
                        <label><input type="radio" name="q18" value="3" <?php if($response['q18_alcohol_frequency'] == 3) echo 'checked'; ?>> 飲まない</label>
                    </div>
                </div>
                <div class="question">
                    <label>19. 飲酒量</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q19" value="1" <?php if($response['q19_alcohol_amount'] == 1) echo 'checked'; ?>> 1合未満</label>
                        <label><input type="radio" name="q19" value="2" <?php if($response['q19_alcohol_amount'] == 2) echo 'checked'; ?>> 1-2合</label>
                        <label><input type="radio" name="q19" value="3" <?php if($response['q19_alcohol_amount'] == 3) echo 'checked'; ?>> 2-3合</label>
                        <label><input type="radio" name="q19" value="4" <?php if($response['q19_alcohol_amount'] == 4) echo 'checked'; ?>> 3合以上</label>
                    </div>
                </div>
                <div class="question">
                    <label>20. 睡眠で休養</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q20" value="1" <?php if($response['q20_sleep'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q20" value="2" <?php if($response['q20_sleep'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>21. 生活習慣改善の意欲</label>
                    <div class="radio-group vertical">
                        <label><input type="radio" name="q21" value="1" <?php if($response['q21_improvement_intention'] == 1) echo 'checked'; ?>> 改善するつもりはない</label>
                        <label><input type="radio" name="q21" value="2" <?php if($response['q21_improvement_intention'] == 2) echo 'checked'; ?>> 改善するつもり(6ヶ月以内)</label>
                        <label><input type="radio" name="q21" value="3" <?php if($response['q21_improvement_intention'] == 3) echo 'checked'; ?>> 近いうちに改善(1ヶ月以内)</label>
                        <label><input type="radio" name="q21" value="4" <?php if($response['q21_improvement_intention'] == 4) echo 'checked'; ?>> 既に取り組んでいる(6ヶ月未満)</label>
                        <label><input type="radio" name="q21" value="5" <?php if($response['q21_improvement_intention'] == 5) echo 'checked'; ?>> 既に取り組んでいる(6ヶ月以上)</label>
                    </div>
                </div>
                <div class="question">
                    <label>22. 保健指導の利用希望</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q22" value="1" <?php if($response['q22_guidance_use'] == 1) echo 'checked'; ?>> はい</label>
                        <label><input type="radio" name="q22" value="2" <?php if($response['q22_guidance_use'] == 2) echo 'checked'; ?>> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 更新</button>
                <button type="button" id="showDeleteModalBtn" class="btn btn-danger"><i class="fas fa-trash-alt"></i> 削除</button>
                <a href="admin_dashboard.php" class="btn btn-secondary" style="text-decoration: none;"><i class="fas fa-list"></i> 一覧に戻る</a>
            </div>
            
            <input type="hidden" name="response_id" value="<?php echo htmlspecialchars($response['response_id']); ?>">
        </form>

    </div>

    <div id="deleteConfirmationModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> 削除の確認</h2>
                <span class="modal-close-btn">&times;</span>
            </div>
            <div class="modal-body">
                <p>この操作は取り消せません。</p>
                <p>回答ID: <strong><?php echo htmlspecialchars($response['response_id']); ?></strong> のデータを本当に削除しますか？</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-cancel-btn">キャンセル</button>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="response_id" value="<?php echo htmlspecialchars($response['response_id']); ?>">
                    <button type="submit" name="delete" class="btn btn-danger"><i class="fas fa-trash-alt"></i> 削除実行</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // フォーム送信前の確認
            document.getElementById('questionnaireForm').addEventListener('submit', function(e) {
                // 押されたボタンが 'delete' ボタンでないことを確認
                var isDeleteSubmit = document.activeElement && document.activeElement.name === 'delete';
                
                // 'delete' ボタン（モーダル内の削除実行）のサブミットでは確認ダイアログを出さない
                if (!isDeleteSubmit && !confirm('この内容で更新してもよろしいですか？')) {
                    e.preventDefault(); // 更新をキャンセル
                }
            });

            /**
             * ラジオボタンの選択に応じて特定の要素の表示/非表示を切り替える関数
             */
            function setupDynamicForm(radioName, targetGroupId) {
                const radios = document.querySelectorAll(`input[name="${radioName}"]`);
                const targetGroup = document.getElementById(targetGroupId);
                
                if (!targetGroup) return; // 対象グループがない場合は何もしない

                function toggleVisibility() {
                    const checkedRadio = document.querySelector(`input[name="${radioName}"]:checked`);
                    if (checkedRadio && checkedRadio.value === '1') {
                        targetGroup.style.display = 'block';
                    } else {
                        targetGroup.style.display = 'none';
                    }
                }

                radios.forEach(radio => radio.addEventListener('change', toggleVisibility));
                toggleVisibility(); // 初期表示
            }

            setupDynamicForm('q1', 'q1_medicine_name_group');
            setupDynamicForm('q2', 'q2_medicine_name_group');
            setupDynamicForm('q3', 'q3_medicine_name_group');

            // ▼▼▼ 削除モーダル用のスクリプトを追加 ▼▼▼
            const deleteModal = document.getElementById('deleteConfirmationModal');
            if (deleteModal) {
                const showDeleteBtn = document.getElementById('showDeleteModalBtn');
                const closeDeleteBtns = deleteModal.querySelectorAll('.modal-close-btn, .modal-cancel-btn');

                if (showDeleteBtn) {
                    showDeleteBtn.addEventListener('click', function() {
                        deleteModal.style.display = 'flex';
                    });
                }

                closeDeleteBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        deleteModal.style.display = 'none';
                    });
                });

                // モーダルの外側をクリックしたら閉じる
                deleteModal.addEventListener('click', function(e) {
                    if (e.target === deleteModal) {
                        deleteModal.style.display = 'none';
                    }
                });
            }
            // ▲▲▲ 削除モーダル用のスクリプトを追加 ▲▲▲

            /**
             * ラジオボタンのスタイルを更新する
             * @param {string} groupName - ラジオボタンのname属性
             */
            function updateRadioStyles(groupName) {
                // name属性が一致するラジオボタンをすべて取得
                const radiosInGroup = document.querySelectorAll(`.radio-group input[name="${groupName}"]`);
                radiosInGroup.forEach(radio => {
                    const label = radio.closest('label');
                    if (label) {
                        // radio.checked が true なら .is-selected を追加, false なら削除
                        label.classList.toggle('is-selected', radio.checked);
                    }
                });
            }

            // このページのラジオボタン（.radio-group 内）をすべて取得
            const allFormRadios = document.querySelectorAll('.radio-group input[type="radio"]');
            const radioGroups = new Set(); // name属性を管理

            allFormRadios.forEach(radio => {
                radioGroups.add(radio.name); // name属性をセットに保存

                // 1. ラジオボタンが変更されたら、そのグループのスタイルを更新
                radio.addEventListener('change', function() {
                    updateRadioStyles(this.name);
                });
            });

            // 2. ページロード時に、チェックされている項目のスタイルを初期設定
            radioGroups.forEach(name => {
                updateRadioStyles(name);
            });
        });
    </script>
</body>
</html>