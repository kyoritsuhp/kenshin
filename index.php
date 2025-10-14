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

$message = '';
$error = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $staff_name = trim($_POST['staff_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    if (empty($staff_id)) {
        $error = '職員IDを入力してください。';
    } else {
        try {
            // ▼▼▼ 修正箇所 ▼▼▼
            $sql = "INSERT INTO questionnaire_responses (
                staff_id, staff_name, department,
                q1_blood_pressure_med, q1_medicine_name,
                q2_insulin_med, q2_medicine_name,
                q3_cholesterol_med, q3_medicine_name,
                q4_stroke, q5_heart_disease, q6_kidney_failure, q7_anemia,
                q8_smoking, q9_weight_gain, q10_exercise, q11_walking,
                q12_walking_speed, q13_weight_change, q14_eating_speed,
                q15_dinner_before_bed, q16_snack_after_dinner, q17_skip_breakfast,
                q18_alcohol_frequency, q19_alcohol_amount, q20_sleep,
                q21_improvement_intention, q22_guidance_use
            ) VALUES (
                :staff_id, :staff_name, :department,
                :q1, :q1_medicine_name,
                :q2, :q2_medicine_name,
                :q3, :q3_medicine_name,
                :q4, :q5, :q6, :q7, :q8, :q9, :q10, :q11,
                :q12, :q13, :q14, :q15, :q16, :q17, :q18, :q19, :q20, :q21, :q22
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':staff_id' => $staff_id,
                ':staff_name' => $staff_name,
                ':department' => $department,
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
            ]);
            // ▲▲▲ 修正箇所 ▲▲▲
            
            $message = '問診票の送信が完了しました。ご協力ありがとうございました。';
        } catch(PDOException $e) {
            $error = '送信中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>職員健康診断 問診票</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>職員健康診断 問診票</h1>
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
                <h2>職員情報</h2>
                <div class="form-group">
                    <label for="staff_id">職員ID <span class="required">*必須</span></label>
                    <input type="text" id="staff_id" name="staff_id" required>
                </div>
                <div class="form-group">
                    <label for="staff_name">氏名</label>
                    <input type="text" id="staff_name" name="staff_name">
                </div>
                <div class="form-group">
                    <label for="department">所属部署</label>
                    <input type="text" id="department" name="department">
                </div>
            </div>

            <div class="section">
                <h2>服薬状況</h2>
                <div class="question">
                    <label>1. a. 血圧を下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q1" value="1" required> はい</label>
                        <label><input type="radio" name="q1" value="2"> いいえ</label>
                    </div>
                    <div class="form-group" id="q1_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q1_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q1_medicine_name" name="q1_medicine_name" placeholder="例：アムロジピン">
                    </div>
                </div>
                <div class="question">
                    <label>2. b. インスリン注射又は血糖を下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q2" value="1" required> はい</label>
                        <label><input type="radio" name="q2" value="2"> いいえ</label>
                    </div>
                    <div class="form-group" id="q2_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q2_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q2_medicine_name" name="q2_medicine_name" placeholder="例：メトホルミン">
                    </div>
                </div>
                <div class="question">
                    <label>3. c. コレステロールを下げる薬</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q3" value="1" required> はい</label>
                        <label><input type="radio" name="q3" value="2"> いいえ</label>
                    </div>
                    <div class="form-group" id="q3_medicine_name_group" style="display: none; margin-top: 10px;">
                        <label for="q3_medicine_name" style="font-weight: normal;">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                        <input type="text" id="q3_medicine_name" name="q3_medicine_name" placeholder="例：ロスバスタチン">
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>既往歴</h2>
                <div class="question">
                    <label>4. 医師から、脳卒中（脳出血、脳梗塞等）にかかっているといわれたり、治療を受けたことがありますか。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q4" value="1" required> はい</label>
                        <label><input type="radio" name="q4" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>5. 医師から、心臓病（狭心症、心筋梗塞等）にかかっているといわれたり、治療を受けたことがありますか。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q5" value="1" required> はい</label>
                        <label><input type="radio" name="q5" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>6. 医師から、慢性の腎不全にかかっているといわれたり、治療（人工透析）を受けたことがありますか。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q6" value="1" required> はい</label>
                        <label><input type="radio" name="q6" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>7. 医師から、貧血といわれたことがある。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q7" value="1" required> はい</label>
                        <label><input type="radio" name="q7" value="2"> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>生活習慣</h2>
                <div class="question">
                    <label>8. 現在、たばこを習慣的に吸っている。<br><span class="note">（※「現在、習慣的に喫煙している者」とは、「合計100本以上、又は6ヶ月以上吸っている者」であり、最近1ヶ月間も吸っている者）</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="q8" value="1" required> はい</label>
                        <label><input type="radio" name="q8" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>9. 20歳の時の体重から10kg以上増加している。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q9" value="1" required> はい</label>
                        <label><input type="radio" name="q9" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>10. 1回30分以上の軽く汗をかく運動を週2日以上、1年以上実施</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q10" value="1" required> はい</label>
                        <label><input type="radio" name="q10" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>11. 日常生活において歩行又は同等の身体活動を1日1時間以上実施</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q11" value="1" required> はい</label>
                        <label><input type="radio" name="q11" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>12. ほぼ同じ年齢の同性と比較して歩く速度が速い。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q12" value="1" required> はい</label>
                        <label><input type="radio" name="q12" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>13. この1年間で体重の増減が±3kg以上あった。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q13" value="1" required> はい</label>
                        <label><input type="radio" name="q13" value="2"> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>食生活</h2>
                <div class="question">
                    <label>14. 人と比較して食べる速度が速い。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q14" value="1" required> 速い</label>
                        <label><input type="radio" name="q14" value="2"> ふつう</label>
                        <label><input type="radio" name="q14" value="3"> 遅い</label>
                    </div>
                </div>
                <div class="question">
                    <label>15. 就寝前の2時間以内に夕食をとることが週に3回以上ある。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q15" value="1" required> はい</label>
                        <label><input type="radio" name="q15" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>16. 夕食後に間食（3食以外の夜食）をとることが週に3回以上ある。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q16" value="1" required> はい</label>
                        <label><input type="radio" name="q16" value="2"> いいえ</label>
                    </div>
                </div>
                <div class="question">
                    <label>17. 朝食を抜くことが週に3回以上ある。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q17" value="1" required> はい</label>
                        <label><input type="radio" name="q17" value="2"> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>飲酒</h2>
                <div class="question">
                    <label>18. お酒（清酒、焼酎、ビール、洋酒など）を飲む頻度</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q18" value="1" required> 毎日</label>
                        <label><input type="radio" name="q18" value="2"> 時々</label>
                        <label><input type="radio" name="q18" value="3"> ほとんど飲まない（飲めない）</label>
                    </div>
                </div>
                <div class="question">
                    <label>19. 飲酒日の1日当たりの飲酒量<br><span class="note">清酒1合(180ml)の目安: ビール中瓶1本(約500ml)、焼酎35度(80ml)、ウイスキーダブル一杯(60ml)、ワイン2杯(240ml)</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="q19" value="1" required> 1合未満</label>
                        <label><input type="radio" name="q19" value="2"> 1~2合未満</label>
                        <label><input type="radio" name="q19" value="3"> 2~3合未満</label>
                        <label><input type="radio" name="q19" value="4"> 3合以上</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>睡眠</h2>
                <div class="question">
                    <label>20. 睡眠で休養が十分とれている。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q20" value="1" required> はい</label>
                        <label><input type="radio" name="q20" value="2"> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>生活習慣改善について</h2>
                <div class="question">
                    <label>21. 運動や食生活等の生活習慣を改善してみようと思いますか。</label>
                    <div class="radio-group vertical">
                        <label><input type="radio" name="q21" value="1" required> ① 改善するつもりはない</label>
                        <label><input type="radio" name="q21" value="2"> ② 改善するつもりである（概ね6か月以内）</label>
                        <label><input type="radio" name="q21" value="3"> ③ 近いうちに（概ね1か月以内）改善するつもりであり、少しずつ始めている</label>
                        <label><input type="radio" name="q21" value="4"> ④ 既に改善に取り組んでいる（6か月未満）</label>
                        <label><input type="radio" name="q21" value="5"> ⑤ 既に改善に取り組んでいる（6か月以上）</label>
                    </div>
                </div>
                <div class="question">
                    <label>22. 生活習慣の改善について保健指導を受ける機会があれば、利用しますか。</label>
                    <div class="radio-group">
                        <label><input type="radio" name="q22" value="1" required> はい</label>
                        <label><input type="radio" name="q22" value="2"> いいえ</label>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">送信する</button>
                <button type="reset" class="btn btn-secondary">リセット</button>
            </div>
        </form>

        <div class="admin-link">
            <a href="admin.php">管理者ログイン</a>
        </div>
    </div>

    <script>
        // フォーム送信前の確認
        document.getElementById('questionnaireForm').addEventListener('submit', function(e) {
            if (!confirm('問診票を送信してもよろしいですか？')) {
                e.preventDefault();
            }
        });

        /**
         * ラジオボタンの選択に応じて特定の要素の表示/非表示を切り替える関数
         * @param {string} radioName - ラジオボタンのname属性
         * @param {string} targetGroupId - 表示/非表示を切り替える要素のID
         */
        function setupDynamicForm(radioName, targetGroupId) {
            const radios = document.querySelectorAll(`input[name="${radioName}"]`);
            const targetGroup = document.getElementById(targetGroupId);
            const targetInput = targetGroup.querySelector('input[type="text"]');

            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === '1') { // 「はい」が選択された場合
                        targetGroup.style.display = 'block';
                    } else { // 「いいえ」が選択された場合
                        targetGroup.style.display = 'none';
                        if(targetInput) targetInput.value = ''; // テキスト入力をクリア
                    }
                });
            });
        }

        // 各質問に動的フォーム機能を設定
        setupDynamicForm('q1', 'q1_medicine_name_group');
        setupDynamicForm('q2', 'q2_medicine_name_group');
        setupDynamicForm('q3', 'q3_medicine_name_group');

    </script>
</body>
</html>