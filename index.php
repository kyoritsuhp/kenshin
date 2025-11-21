<?php
session_start();

// データベース接続 (monshin)
$db_host = 'localhost';
$db_name = 'monshin';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

$message = '';
$error = '';

// 設定ファイルの読み込み
$defaults = [];
$configFile = 'config.json';
if (file_exists($configFile)) {
    $defaults = json_decode(file_get_contents($configFile), true);
}
$isFixed = ($defaults['enabled'] ?? false);

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // フォームからの値を取得
    $staff_id = trim($_POST['staff_id'] ?? ''); // (例: "100" や "１００")

    // ★ 修正: 全角の英数字が入力された場合、半角に変換する
    $staff_id = mb_convert_kana($staff_id, "a"); // (例: "１００" -> "100")

    // ▼▼▼ 【①氏名】全角・半角スペースを除去 ▼▼▼
    $staff_name_raw = trim($_POST['staff_name'] ?? '');
    // 全角スペース(　)と半角スペース( )を両方除去したものを $staff_name とする
    // これにより "松井　友弘" は "松井友弘" になります
    $staff_name = preg_replace('/[\s　]/u', '', $staff_name_raw);
    // ▲▲▲

    // ▼▼▼ 【追加】生年月日を取得 ▼▼▼
    $birth_date = trim($_POST['birth_date'] ?? '');
    // ▲▲▲

    $department = trim($_POST['department'] ?? '');
    $facility_name = trim($_POST['facility_name'] ?? '');

    // ▼▼▼ 【③施設名連動】協立病院でない場合、所属部署をクリア ▼▼▼
    if ($facility_name !== '協立病院') {
        $department = ''; // 非表示の場合は値を空にする
    }
    // ▲▲▲

    // デフォルト設定が有効な場合は、POSTされた値ではなく設定値を使用
    if ($isFixed) {
        $health_check_year = $defaults['year'] ?? null;
        $health_check_season = $defaults['season'] ?? null;
    } else {
        $health_check_year = trim($_POST['health_check_year'] ?? '');
        $health_check_season = trim($_POST['health_check_season'] ?? '');
    }

    // ▼▼▼ 【ご要望のロジック】人事DBとの照合 ▼▼▼
    $karte_id_to_write = null;
    $jinji_pdo = null;

    // ★ 修正: 5桁パディング済みのIDを保存する変数を定義
    // staff_idが空（''）の場合は '' のまま。
    // staff_idが入力されている（'100'）場合は '00100' になる。
    $staff_id_padded = !empty($staff_id) ? str_pad($staff_id, 5, "0", STR_PAD_LEFT) : $staff_id;

    if (!empty($staff_id)) {

        // $staff_id_padded は上で定義済み

        try {
            $jinji_db_host = 'localhost';
            $jinji_db_name = 'jinji';
            $jinji_db_user = 'root';
            $jinji_db_pass = '';

            $jinji_pdo = new PDO("mysql:host=$jinji_db_host;dbname=$jinji_db_name;charset=utf8mb4", $jinji_db_user, $jinji_db_pass);
            $jinji_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // ▼▼▼ 【追加】人事DB (jinji) への登録・更新処理 ▼▼▼
            if ($facility_name === '協立病院') {
                // 施設名が「協立病院」の場合：ID, 氏名, 生年月日を登録
                // (重複時は氏名と生年月日を更新)
                $sql_upsert = "INSERT INTO staff (staff_id, name, birth_date) VALUES (:staff_id, :name, :birth_date)
                               ON DUPLICATE KEY UPDATE name = :name_upd, birth_date = :birth_date_upd";
                $stmt_upsert = $jinji_pdo->prepare($sql_upsert);
                $stmt_upsert->execute([
                    ':staff_id' => $staff_id_padded,
                    ':name' => $staff_name, // ★ ここでスペース除去済みの氏名を登録しています
                    ':birth_date' => $birth_date,
                    ':name_upd' => $staff_name, // ★ ここもスペース除去済み
                    ':birth_date_upd' => $birth_date
                ]);
            } else {
                // 「協立病院」以外の場合：生年月日を更新 (IDが存在する場合のみ)
                if (!empty($birth_date)) {
                    $sql_update = "UPDATE staff SET birth_date = :birth_date WHERE staff_id = :staff_id";
                    $stmt_update = $jinji_pdo->prepare($sql_update);
                    $stmt_update->execute([
                        ':birth_date' => $birth_date,
                        ':staff_id' => $staff_id_padded
                    ]);
                }
            }
            // ▲▲▲ 人事DB処理終了 ▲▲▲


            $sql_jinji = "SELECT karte_id FROM staff WHERE staff_id = :staff_id_check";
            $stmt_jinji = $jinji_pdo->prepare($sql_jinji);
            $stmt_jinji->execute([':staff_id_check' => $staff_id_padded]); // ★ パディング済みのIDで照合

            $matched_staff = $stmt_jinji->fetch(PDO::FETCH_ASSOC);

            if ($matched_staff) {
                $karte_id_to_write = $matched_staff['karte_id'];
            }

        } catch (PDOException $e) {
            $error = '人事データベースとの照合中にエラーが発生しました: ' . $e->getMessage();
        } finally {
            if ($jinji_pdo !== null) {
                $jinji_pdo = null;
            }
        }
    }
    // ▲▲▲ 照合ロジック終了 ▲▲▲

    // (上記で人事DB照合エラーが発生していない場合のみ)問診DBへの書き込みを実行
    if (empty($error)) {
        try {
            $sql = "INSERT INTO questionnaire_responses (
                staff_id, staff_name, department, facility_name, health_check_year, health_check_season,
                karte_id,
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
                :staff_id, :staff_name, :department, :facility_name, :health_check_year, :health_check_season,
                :karte_id,
                :q1, :q1_medicine_name,
                :q2, :q2_medicine_name,
                :q3, :q3_medicine_name,
                :q4, :q5, :q6, :q7, :q8, :q9, :q10, :q11,
                :q12, :q13, :q14, :q15, :q16, :q17, :q18, :q19, :q20, :q21, :q22
            )";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':staff_id' => $staff_id_padded, // ★ 修正: $staff_id_padded (パディング済みID) を登録
                ':staff_name' => $staff_name, // ★ 修正: 【①氏名】スペース除去済みの $staff_name を登録
                ':department' => $department, // ★ 修正: 【②所属部署】
                ':facility_name' => $facility_name,
                ':health_check_year' => $health_check_year,
                ':health_check_season' => $health_check_season,
                ':karte_id' => $karte_id_to_write,
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

            $message = '問診票の送信が完了しました。ご協力ありがとうございました。';
        } catch (PDOException $e) {
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

    <link rel="stylesheet" href="index_style.css">

    <style>
        /* select (プルダウン) のフォントを他のフォーム要素と合わせる */
        .form-group select {
            font-family: inherit;
            /* 親要素のフォント（ページ全体）を継承 */
            font-size: inherit;
            /* 親要素のフォントサイズを継承 */
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <a href="../hospital_portal/index.php" class="back-to-portal-btn" data-i18n="back_to_portal">← トップページに戻る</a>
            <img src="images/monshin_13458.png" alt="問診票アイコン" class="header-icon">
            <div class="header-text">
                <h1 data-i18n="header_title">職員健康診断 問診票</h1>
                <p class="subtitle" data-i18n="header_subtitle">標準的な質問票</p>
            </div>
        </header>

        <div class="progress-container">
            <div class="progress-bar" id="progressBar"></div>
            <div class="progress-steps">
                <span class="progress-step active" data-step="1" data-i18n="step_info">職員情報</span>
                <span class="progress-step" data-step="2" data-i18n="step_med">服薬</span>
                <span class="progress-step" data-step="3" data-i18n="step_history">既往歴</span>
                <span class="progress-step" data-step="4" data-i18n="step_lifestyle">生活習慣</span>
                <span class="progress-step" data-step="5" data-i18n="step_diet">食生活</span>
                <span class="progress-step" data-step="6" data-i18n="step_alcohol">飲酒・睡眠</span>
                <span class="progress-step" data-step="7" data-i18n="step_intention">改善意欲</span>
                <span class="progress-step" data-step="8" data-i18n="step_confirm">確認</span>
            </div>
        </div>

        <?php if ($message) : ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error) : ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$message) : // 送信完了時はフォームを非表示 
        ?>
            <form method="POST" action="" class="questionnaire-form" id="questionnaireForm" novalidate>

                <div class="form-page active" data-page="1">
                    <div class="section">
                        <h2 data-i18n="lang_select_title">言語選択 / Language Selection</h2>
                        <div class="radio-group">
                            <label><input type="radio" name="display_language" value="ja" checked> 日本語</label>
                            <label><input type="radio" name="display_language" value="id"> Indonesia</label>
                            <label><input type="radio" name="display_language" value="mn"> Монгол</label>
                            <label><input type="radio" name="display_language" value="my"> မြန်မာ</label>
                        </div>
                    </div>
                    <div class="section">
                        <h2><span class="section-icon icon-info"></span> <span data-i18n="section_health_info">健康診断情報</span></h2>
                        <?php if ($isFixed) : ?>
                            <div class="form-group">
                                <label data-i18n="lbl_year_season">年度・時期</label>
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="health_check_year" value="<?php echo htmlspecialchars($defaults['year'] ?? ''); ?>" checked disabled>
                                        <?php echo htmlspecialchars($defaults['year'] ?? ''); ?><span data-i18n="suffix_year">年</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="health_check_season" value="<?php echo htmlspecialchars($defaults['season'] ?? ''); ?>" checked disabled>
                                        <?php echo htmlspecialchars($defaults['season'] ?? ''); ?>
                                    </label>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="form-group">
                                <label data-i18n="lbl_year">年度</label>
                                <div class="radio-group">
                                    <?php for ($y = 2025; $y <= 2030; $y++) : ?>
                                        <label>
                                            <input type="radio" name="health_check_year" value="<?php echo $y; ?>" required>
                                            <?php echo $y; ?><span data-i18n="suffix_year">年</span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label data-i18n="lbl_season">時期</label>
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="health_check_season" value="春" required> <span data-i18n="season_spring">春</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="health_check_season" value="冬" required> <span data-i18n="season_winter">冬</span>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="section">
                        <h2><span class="section-icon icon-user"></span> <span data-i18n="section_staff_info">職員情報</span></h2>
                        <div class="form-group">
                            <label data-i18n="lbl_facility">施設名</label>
                            <div class="radio-group">
                                <label><input type="radio" name="facility_name" value="協立病院" required> <span data-i18n="facility_kyouritsu">協立病院</span></label>
                                <label><input type="radio" name="facility_name" value="清寿園" required> <span data-i18n="facility_seijuen">清寿園</span></label>
                                <label><input type="radio" name="facility_name" value="かがやき" required> <span data-i18n="facility_kagayaki">かがやき</span></label>
                                <label><input type="radio" name="facility_name" value="かがやき2号館" required> <span data-i18n="facility_kagayaki2">かがやき2号館</span></label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="staff_id" data-i18n="lbl_staff_id">職員ID</label>
                            <input type="text" id="staff_id" name="staff_id" value="<?php echo htmlspecialchars($staff_id ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="staff_name" data-i18n="lbl_staff_name">氏名</label>
                            <input type="text" id="staff_name" name="staff_name" value="<?php echo htmlspecialchars($staff_name_raw ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="birth_date" data-i18n="lbl_birth_date">生年月日</label>
                            <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date ?? ''); ?>" required>
                        </div>
                        <div class="form-group" id="department_group">
                            <label for="department" data-i18n="lbl_department">所属部署</label>
                            <select id="department" name="department">
                                <option value="" <?php echo empty($department) ? 'selected' : ''; ?> data-i18n="dept_select">--選択して下さい--</option>
                                <option value="不明・該当なし" <?php echo ($department ?? '') === '不明・該当なし' ? 'selected' : ''; ?> data-i18n="dept_unknown">不明・該当なし</option>
                                <option value="2階回復期" <?php echo ($department ?? '') === '2階回復期' ? 'selected' : ''; ?> data-i18n="dept_2f_rec">2階回復期</option>
                                <option value="3階一般" <?php echo ($department ?? '') === '3階一般' ? 'selected' : ''; ?> data-i18n="dept_3f_gen">3階一般</option>
                                <option value="4階西病棟" <?php echo ($department ?? '') === '4階西病棟' ? 'selected' : ''; ?> data-i18n="dept_4f_west">4階西病棟</option>
                                <option value="4階東病棟" <?php echo ($department ?? '') === '4階東病棟' ? 'selected' : ''; ?> data-i18n="dept_4f_east">4階東病棟</option>
                                <option value="2階介護医療院" <?php echo ($department ?? '') === '2階介護医療院' ? 'selected' : ''; ?> data-i18n="dept_2f_care">2階介護医療院</option>
                                <option value="3階介護医療院" <?php echo ($department ?? '') === '3階介護医療院' ? 'selected' : ''; ?> data-i18n="dept_3f_care">3階介護医療院</option>
                                <option value="事務" <?php echo ($department ?? '') === '事務' ? 'selected' : ''; ?> data-i18n="dept_office">事務</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="2">
                    <div class="section">
                        <h2><span class="section-icon icon-step01"></span> <span data-i18n="section_medication">服薬状況</span></h2>
                        <div class="question">
                            <label data-i18n="q1_text">1. a. 血圧を下げる薬を服用している</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q1" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q1" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                            <div class="form-group" id="q1_medicine_name_group" style="display: none; margin-top: 10px;">
                                <label for="q1_medicine_name" style="font-weight: normal;" data-i18n="lbl_med_name_req">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                                <input type="text" id="q1_medicine_name" name="q1_medicine_name" placeholder="例：アムロジピン" data-i18n-placeholder="ph_med_ex1">
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q2_text">2. b. インスリン注射又は血糖を下げる薬を使用している</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q2" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q2" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                            <div class="form-group" id="q2_medicine_name_group" style="display: none; margin-top: 10px;">
                                <label for="q2_medicine_name" style="font-weight: normal;" data-i18n="lbl_med_name_req">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                                <input type="text" id="q2_medicine_name" name="q2_medicine_name" placeholder="例：メトホルミン" data-i18n-placeholder="ph_med_ex2">
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q3_text">3. c. コレステロールや中性脂肪を下げる薬を服用している</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q3" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q3" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                            <div class="form-group" id="q3_medicine_name_group" style="display: none; margin-top: 10px;">
                                <label for="q3_medicine_name" style="font-weight: normal;" data-i18n="lbl_med_name_req">もし「はい」の場合、お薬名を具体的に入力してください。</label>
                                <input type="text" id="q3_medicine_name" name="q3_medicine_name" placeholder="例：ロスバスタチン" data-i18n-placeholder="ph_med_ex3">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="3">
                    <div class="section">
                        <h2><span class="section-icon icon-history"></span> <span data-i18n="section_history">既往歴</span></h2>
                        <div class="question">
                            <label data-i18n="q4_text">4. 医師から、脳卒中（脳出血、脳梗塞等）にかかっているといわれたり、治療を受けたことがありますか。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q4" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q4" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q5_text">5. 医師から、心臓病（狭心症、心筋梗塞等）にかかっているといわれたり、治療を受けたことがありますか。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q5" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q5" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q6_text">6. 医師から、慢性の腎不全にかかっているといわれたり、治療（人工透析）を受けたことがありますか。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q6" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q6" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q7_text">7. 医師から、貧血といわれたことがある。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q7" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q7" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="4">
                    <div class="section">
                        <h2><span class="section-icon icon-lifestyle"></span> <span data-i18n="section_lifestyle">生活習慣</span></h2>
                        <div class="question">
                            <label>
                                <span data-i18n="q8_text">8. 現在、たばこを習慣的に吸っている。</span><br>
                                <span class="note" data-i18n="q8_note">（※「現在、習慣的に喫煙している者」とは、「合計100本以上、又は6ヶ月以上吸っている者」であり、最近1ヶ月間も吸っている者）</span>
                            </label>
                            <div class="radio-group">
                                <label><input type="radio" name="q8" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q8" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q9_text">9. 20歳の時の体重から10kg以上増加している。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q9" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q9" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q10_text">10. 1回30分以上の軽く汗をかく運動を週2日以上、1年以上実施</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q10" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q10" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q11_text">11. 日常生活において歩行又は同等の身体活動を1日1時間以上実施</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q11" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q11" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q12_text">12. ほぼ同じ年齢の同性と比較して歩く速度が速い。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q12" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q12" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q13_text">13. この1年間で体重の増減が±3kg以上あった。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q13" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q13" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="5">
                    <div class="section">
                        <h2><span class="section-icon icon-diet"></span> <span data-i18n="section_diet">食生活</span></h2>
                        <div class="question">
                            <label data-i18n="q14_text">14. 人と比較して食べる速度が速い。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q14" value="1" required> <span data-i18n="opt_fast">速い</span></label>
                                <label><input type="radio" name="q14" value="2" required> <span data-i18n="opt_normal">ふつう</span></label>
                                <label><input type="radio" name="q14" value="3" required> <span data-i18n="opt_slow">遅い</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q15_text">15. 就寝前の2時間以内に夕食をとることが週に3回以上ある。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q15" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q15" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q16_text">16. 夕食後に間食（3食以外の夜食）をとることが週に3回以上ある。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q16" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q16" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q17_text">17. 朝食を抜くことが週に3回以上ある。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q17" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q17" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="6">
                    <div class="section">
                        <h2><span class="section-icon icon-alcohol"></span> <span data-i18n="section_alcohol">飲酒</span></h2>
                        <div class="question">
                            <label data-i18n="q18_text">18. お酒（清酒、焼酎、ビール、洋酒など）を飲む頻度</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q18" value="1" required> <span data-i18n="opt_daily">毎日</span></label>
                                <label><input type="radio" name="q18" value="2" required> <span data-i18n="opt_sometimes">時々</span></label>
                                <label><input type="radio" name="q18" value="3" required> <span data-i18n="opt_rarely">ほとんど飲まない（飲めない）</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label>
                                <span data-i18n="q19_text">19. 飲酒日の1日当たりの飲酒量</span><br>
                                <span class="note" data-i18n="q19_note">清酒1合(180ml)の目安: ビール中瓶1本(約500ml)、焼酎35度(80ml)、ウイスキーダブル一杯(60ml)、ワイン2杯(240ml)</span>
                            </label>
                            <div class="radio-group">
                                <label><input type="radio" name="q19" value="1" required> <span data-i18n="opt_alc_1">1合未満</span></label>
                                <label><input type="radio" name="q19" value="2" required> <span data-i18n="opt_alc_2">1~2合未満</span></label>
                                <label><input type="radio" name="q19" value="3" required> <span data-i18n="opt_alc_3">2~3合未満</span></label>
                                <label><input type="radio" name="q19" value="4" required> <span data-i18n="opt_alc_4">3合以上</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="section">
                        <h2><span class="section-icon icon-sleep"></span> <span data-i18n="section_sleep">睡眠</span></h2>
                        <div class="question">
                            <label data-i18n="q20_text">20. 睡眠で休養が十分とれている。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q20" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q20" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="7">
                    <div class="section">
                        <h2><span class="section-icon icon-intention"></span> <span data-i18n="section_improvement">生活習慣改善について</span></h2>
                        <div class="question">
                            <label data-i18n="q21_text">21. 運動や食生活等の生活習慣を改善してみようと思いますか。</label>
                            <div class="radio-group vertical">
                                <label><input type="radio" name="q21" value="1" required> <span data-i18n="opt_q21_1">① 改善するつもりはない</span></label>
                                <label><input type="radio" name="q21" value="2" required> <span data-i18n="opt_q21_2">② 改善するつもりである（概ね6か月以内）</span></label>
                                <label><input type="radio" name="q21" value="3" required> <span data-i18n="opt_q21_3">③ 近いうちに（概ね1か月以内）改善するつもりであり、少しずつ始めている</span></label>
                                <label><input type="radio" name="q21" value="4" required> <span data-i18n="opt_q21_4">④ 既に改善に取り組んでいる（6か月未満）</span></label>
                                <label><input type="radio" name="q21" value="5" required> <span data-i18n="opt_q21_5">⑤ 既に改善に取り組んでいる（6か月以上）</span></label>
                            </div>
                        </div>
                        <div class="question">
                            <label data-i18n="q22_text">22. 生活習慣の改善について保健指導を受ける機会があれば、利用しますか。</label>
                            <div class="radio-group">
                                <label><input type="radio" name="q22" value="1" required> <span data-i18n="opt_yes">はい</span></label>
                                <label><input type="radio" name="q22" value="2" required> <span data-i18n="opt_no">いいえ</span></label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-page" data-page="8">
                    <div class="section">
                        <h2><span class="section-icon icon-confirm"></span> <span data-i18n="section_confirm">入力内容の確認</span></h2>
                        <p data-i18n="confirm_msg">以下の内容で送信します。よろしければ「送信」ボタンを押してください。</p>
                        <div id="confirmationReview">
                        </div>
                    </div>
                </div>

                <div class="navigation-buttons">
                    <button type="button" id="backBtn" class="btn btn-secondary" data-i18n="btn_back">戻る</button>

                    <div class="navigation-buttons-right">
                        <button type="button" id="nextBtn" class="btn btn-primary" data-i18n="btn_next">次へ</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary"><span class="btn-icon-plane"></span> <span data-i18n="btn_submit">送信する</span></button>
                    </div>
                </div>
            </form>
        <?php endif; // $message がない場合のみフォーム表示 
        ?>

        <div class="admin-link">
            <a href="admin.php" data-i18n="admin_login">管理者ログイン</a>
        </div>
    </div>

    <script>
        // ▼▼▼ 多言語対応辞書 (別ファイルから読み込み) ▼▼▼
        <?php include 'translations.php'; ?>

        /**
         * ラジオボタンの選択に応じて特定の要素の表示/非表示を切り替える関数
         */
        function setupDynamicForm(radioName, targetGroupId) {
            const radios = document.querySelectorAll(`input[name="${radioName}"]`);
            const targetGroup = document.getElementById(targetGroupId);
            const targetInput = targetGroup ? targetGroup.querySelector('input[type="text"]') : null;

            if (!targetGroup) return; // 要素がなければ何もしない

            function toggleVisibility() {
                const checkedRadio = document.querySelector(`input[name="${radioName}"]:checked`);
                if (checkedRadio && checkedRadio.value === '1') { // 「はい」が選択された場合
                    targetGroup.style.display = 'block';
                } else { // 「いいえ」が選択された場合
                    targetGroup.style.display = 'none';
                    if (targetInput) targetInput.value = ''; // テキスト入力をクリア
                }
            }

            radios.forEach(radio => radio.addEventListener('change', toggleVisibility));
            toggleVisibility(); // 初期表示
        }

        /**
         * ▼▼▼ 【③施設名連動】所属部署の表示/非表示を切り替える関数 ▼▼▼
         */
        function toggleDepartmentVisibility() {
            const departmentGroup = document.getElementById('department_group');
            const departmentSelect = document.getElementById('department');
            if (!departmentGroup || !departmentSelect) return;

            const selectedFacility = document.querySelector('input[name="facility_name"]:checked');

            if (selectedFacility && selectedFacility.value === '協立病院') {
                // "協立病院" が選択されている場合
                departmentGroup.style.display = 'block'; // 表示
                // ▼▼▼ 【④任意】 'required' の操作を削除 (必須ではなくなったため) ▼▼▼
                // departmentSelect.required = true; 
            } else {
                // "協立病院" 以外が選択されている (または未選択) の場合
                departmentGroup.style.display = 'none'; // 非表示
                // ▼▼▼ 【④任意】 'required' の操作を削除 (必須ではなくなったため) ▼▼▼
                // departmentSelect.required = false; 
                departmentSelect.value = ''; // 選択をクリア

                // 非表示にする際、もし赤文字（is-invalid）になっていたら解除する
                const parentContainer = departmentSelect.closest('.form-group');
                if (parentContainer) {
                    parentContainer.classList.remove('is-invalid');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 送信完了メッセージがある場合は、フォーム操作JSを実行しない
            if (document.querySelector('.message.success')) {
                return;
            }

            const form = document.getElementById('questionnaireForm');
            const pages = document.querySelectorAll('.form-page');
            const nextBtn = document.getElementById('nextBtn');
            const backBtn = document.getElementById('backBtn');
            const submitBtn = document.getElementById('submitBtn');
            const progressBar = document.getElementById('progressBar');
            const progressSteps = document.querySelectorAll('.progress-step');

            let currentPage = 1;
            const totalPages = pages.length;

            // 各質問に動的フォーム機能を設定
            setupDynamicForm('q1', 'q1_medicine_name_group');
            setupDynamicForm('q2', 'q2_medicine_name_group');
            setupDynamicForm('q3', 'q3_medicine_name_group');

            // ▼▼▼ 【③施設名連動】初期表示を実行 ▼▼▼
            toggleDepartmentVisibility();

            // ▼▼▼ 多言語切り替えロジック ▼▼▼
            const langRadios = document.querySelectorAll('input[name="display_language"]');
            let currentLang = 'ja'; // デフォルト

            function updateLanguage(lang) {
                currentLang = lang;
                const dict = translations[lang];
                if (!dict) return;

                // 1. data-i18n属性を持つ要素のテキストを更新
                document.querySelectorAll('[data-i18n]').forEach(el => {
                    const key = el.getAttribute('data-i18n');
                    if (dict[key]) {
                        el.innerText = dict[key];
                    }
                });

                // 2. data-i18n-placeholder属性を持つ要素のプレースホルダーを更新
                document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                    const key = el.getAttribute('data-i18n-placeholder');
                    if (dict[key]) {
                        el.placeholder = dict[key];
                    }
                });
            }

            // ラジオボタンイベントリスナー
            langRadios.forEach(radio => {
                radio.addEventListener('change', (e) => {
                    updateLanguage(e.target.value);
                });
            });
            // ▲▲▲

            // ▼▼▼ 修正: ページ表示時に必須項目のラベル色を（赤に）設定する関数 ▼▼▼
            /**
             * ページ内の必須ラジオ項目をチェックし、未選択なら.is-invalidを付与
             * @param {HTMLElement} pageElement - 対象のページ要素
             */
            function setInitialValidationState(pageElement) {
                // --- ラジオの必須チェック ---
                const radioGroupNames = new Set();
                pageElement.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    radioGroupNames.add(radio.name);
                });

                radioGroupNames.forEach(name => {
                    const firstRadio = pageElement.querySelector(`input[name="${name}"]`);
                    if (firstRadio && firstRadio.disabled) {
                        return;
                    }
                    const anyChecked = pageElement.querySelector(`input[name="${name}"]:checked`);
                    const parentContainer = firstRadio.closest('.question') || firstRadio.closest('.form-group');
                    if (!parentContainer) return;

                    if (!anyChecked) {
                        parentContainer.classList.add('is-invalid');
                    } else {
                        parentContainer.classList.remove('is-invalid');
                    }
                });

                // --- Select（プルダウン）の必須チェック ---
                pageElement.querySelectorAll('select[required]').forEach(select => {
                    // ▼▼▼ 【④任意】 'required' がない 'department' はここでチェックされなくなる (赤枠の修正) ▼▼▼
                    if (select.disabled || !select.required) return;

                    const parentContainer = select.closest('.form-group');
                    if (!parentContainer) return;

                    if (select.value === "") { // 未選択（valueが空）
                        parentContainer.classList.add('is-invalid');
                    } else {
                        parentContainer.classList.remove('is-invalid');
                    }
                });

                // --- ▼▼▼ 【⑦必須】 テキスト入力の必須チェックを追加 ▼▼▼ ---
                pageElement.querySelectorAll('input[required], textarea[required]').forEach(input => {
                    if (input.type === 'radio' || input.disabled) return; // ラジオとdisabledは除外

                    const parentContainer = input.closest('.form-group');
                    if (!parentContainer) return;

                    if (input.value.trim() === "") { // 値が空
                        parentContainer.classList.add('is-invalid');
                    } else {
                        parentContainer.classList.remove('is-invalid');
                    }
                });
                // --- ▲▲▲ ---
            }
            // ▲▲▲

            // ▼▼▼ 修正: ラジオボタンの選択（change）イベント ▼▼▼
            /**
             * ラジオボタン選択時のスタイル（背景色）を更新
             */
            function updateRadioStyles(groupName) {
                const radiosInGroup = document.querySelectorAll(`input[type="radio"][name="${groupName}"]`);
                radiosInGroup.forEach(radio => {
                    const label = radio.closest('label');
                    if (label) {
                        label.classList.toggle('is-selected', radio.checked);
                    }
                });
            }

            const allRadios = document.querySelectorAll('.radio-group input[type="radio"]');
            allRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    // 1. 選択した項目の背景色を変更
                    updateRadioStyles(this.name);

                    // 2. 親コンテナの.is-invalid（赤文字）を解除
                    const parentContainer = this.closest('.question') || this.closest('.form-group');
                    if (parentContainer) {
                        parentContainer.classList.remove('is-invalid');
                    }

                    // ▼▼▼ 【③施設名連動】施設名が変更されたら所属部署の表示を切り替え ▼▼▼
                    if (this.name === 'facility_name') {
                        toggleDepartmentVisibility();
                    }
                });

                // ページロード時に、ブラウザが保持している選択状態を反映
                if (radio.checked) {
                    updateRadioStyles(radio.name);
                }
            });
            // ▲▲▲

            // ▼▼▼ 【②所属部署】Select（プルダウン）の change イベント ▼▼▼
            const allSelects = document.querySelectorAll('select');
            allSelects.forEach(select => {
                select.addEventListener('change', function() {
                    // 選択したら親コンテナの.is-invalid（赤文字）を解除
                    const parentContainer = this.closest('.form-group');
                    if (parentContainer) {
                        // ▼▼▼ 【④任意】 'required' がないため、is-invalid は解除するだけでよい ▼▼▼
                        parentContainer.classList.remove('is-invalid');
                    }
                });
            });
            // ▲▲▲

            // ▼▼▼ 【⑦必須】 必須テキスト入力の input イベントを追加 ▼▼▼
            const allRequiredInputs = document.querySelectorAll('input[required], textarea[required]');
            allRequiredInputs.forEach(input => {
                if (input.type === 'radio' || input.type === 'checkbox') return; // テキスト系のみ

                input.addEventListener('input', function() {
                    const parentContainer = this.closest('.form-group');
                    if (parentContainer) {
                        // checkValidity() で現在の入力値が有効か（空でないか）をチェック
                        if (this.checkValidity()) {
                            parentContainer.classList.remove('is-invalid');
                        } else {
                            parentContainer.classList.add('is-invalid');
                        }
                    }
                });
            });
            // ▲▲▲


            /**
             * ナビゲーションボタンとプログレスバーの状態を更新する
             * ▼▼▼ 修正: setInitialValidationState を呼び出すよう変更 ▼▼▼
             */
            function updateNavigation() {
                // ページ表示
                pages.forEach((page, index) => {
                    const isActive = (index + 1) === currentPage;
                    page.classList.toggle('active', isActive);

                    // ▼▼▼ ページが表示された瞬間に、そのページの赤文字（未選択）状態をセットする ▼▼▼
                    if (isActive) {
                        setInitialValidationState(page);
                    }
                });

                // ボタン表示 (CSS側も visibility を使うように変更したため、.visibleクラスの付け外しはそのまま)
                backBtn.classList.toggle('visible', currentPage > 1);
                nextBtn.classList.toggle('visible', currentPage < totalPages);
                submitBtn.classList.toggle('visible', currentPage === totalPages);

                // プログレスバー
                const percent = ((currentPage - 1) / (totalPages - 1)) * 100;
                progressBar.style.width = percent + '%';

                // ステップのテキスト
                progressSteps.forEach((step, index) => {
                    const stepNum = index + 1;
                    if (stepNum < currentPage) {
                        step.classList.add('completed');
                        step.classList.remove('active');
                    } else if (stepNum === currentPage) {
                        step.classList.add('active');
                        step.classList.remove('completed');
                    } else {
                        step.classList.remove('active');
                        step.classList.remove('completed');
                    }
                });
            }

            /**
             * 現在のページのバリデーションを実行する
             * （「次へ」ボタンクリック時に使用）
             */
            function validateCurrentPage() {
                const activePage = document.querySelector(`.form-page[data-page="${currentPage}"]`);
                if (!activePage) return false;

                let firstInvalid = null;

                // 念のため、現在ページの赤文字状態をリセット
                activePage.querySelectorAll('.question.is-invalid').forEach(q => q.classList.remove('is-invalid'));
                activePage.querySelectorAll('.form-group.is-invalid').forEach(fg => fg.classList.remove('is-invalid'));

                // --- ラジオボタンのチェック ---
                const radioGroupNames = new Set();
                activePage.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                    radioGroupNames.add(radio.name);
                });

                radioGroupNames.forEach(name => {
                    const firstRadio = activePage.querySelector(`input[name="${name}"]`);
                    if (firstRadio && firstRadio.disabled) return;

                    const anyChecked = activePage.querySelector(`input[name="${name}"]:checked`);
                    if (!anyChecked) {
                        const radioElement = firstRadio;
                        if (radioElement) {
                            try {
                                radioElement.reportValidity(); // ブラウザ標準のポップアップ
                            } catch (e) {
                                console.warn(`Validation failed for: ${name}`);
                            }
                            if (!firstInvalid) firstInvalid = radioElement;

                            // 親コンテナに .is-invalid を追加（ラベルを赤にする）
                            const parentContainer = radioElement.closest('.question') || radioElement.closest('.form-group');
                            if (parentContainer) {
                                parentContainer.classList.add('is-invalid');
                            }
                        }
                    }
                });
                // --- ラジオボタンのチェックここまで ---

                // --- ▼▼▼ 【②所属部署】Select（プルダウン）のチェックを追加 ▼▼▼ ---
                const selects = activePage.querySelectorAll('select[required]');
                selects.forEach(select => {
                    // ▼▼▼ 【④任意】 'department' は 'required' でないため、このチェックから除外される ▼▼▼
                    if (select.disabled || !select.required) return;

                    if (!select.checkValidity()) { // checkValidity()は value="" の場合に false を返す
                        try {
                            select.reportValidity();
                        } catch (e) {
                            console.warn(`Validation failed for: ${select.name}`);
                        }
                        if (!firstInvalid) firstInvalid = select;

                        // 親コンテナに .is-invalid を追加（ラベルを赤にする）
                        const parentContainer = select.closest('.form-group');
                        if (parentContainer) {
                            parentContainer.classList.add('is-invalid');
                        }
                    }
                });
                // --- Selectのチェックここまで ---

                // --- その他の必須入力（テキストボックスなど）のチェック ---
                const inputs = activePage.querySelectorAll('input[required], textarea[required]');
                inputs.forEach(input => {
                    if (input.type === 'radio' || input.disabled) return; // ラジオとdisabledは除外

                    if (!input.checkValidity()) {
                        input.reportValidity();
                        if (!firstInvalid) firstInvalid = input;

                        // ▼▼▼ 【⑦必須】 テキスト入力でも .is-invalid を追加 ▼▼▼
                        const parentContainer = input.closest('.form-group');
                        if (parentContainer) {
                            parentContainer.classList.add('is-invalid');
                        }
                        // --- ▲▲▲ ---
                    }
                });
                // --- その他の必須入力ここまで ---

                if (firstInvalid) {
                    firstInvalid.focus();
                    return false;
                }

                return true;
            }

            // ラジオボタンの選択値を取得してテキストに変換する関数
            // ★ 言語対応版に修正
            function getRadioValueText(name) {
                const dict = translations[currentLang] || translations['ja'];

                const checkedRadio = form.querySelector(`input[name="${name}"]:checked`);
                let value = '';

                if (!checkedRadio) {
                    // 固定されている場合、disabledのラジオから値を取得
                    const disabledRadio = form.querySelector(`input[name="${name}"]:disabled`);
                    if (disabledRadio) {
                        value = disabledRadio.value;
                    } else {
                        return `<span style="color: red;">${dict.val_unselected}</span>`;
                    }
                } else {
                    value = checkedRadio.value;
                }

                // マッピング処理 (辞書を使って表示用テキストを取得)
                if (name === 'q1' || name === 'q2' || name === 'q3' || name === 'q4' || name === 'q5' || name === 'q6' || name === 'q7' || name === 'q8' || name === 'q9' || name === 'q10' || name === 'q11' || name === 'q12' || name === 'q13' || name === 'q15' || name === 'q16' || name === 'q17' || name === 'q20' || name === 'q22') {
                    return (value === '1') ? dict.opt_yes : dict.opt_no;
                }
                if (name === 'q14') {
                    if (value === '1') return dict.opt_fast;
                    if (value === '2') return dict.opt_normal;
                    if (value === '3') return dict.opt_slow;
                }
                if (name === 'q18') {
                    if (value === '1') return dict.opt_daily;
                    if (value === '2') return dict.opt_sometimes;
                    if (value === '3') return dict.opt_rarely;
                }
                if (name === 'q19') {
                    if (value === '1') return dict.opt_alc_1;
                    if (value === '2') return dict.opt_alc_2;
                    if (value === '3') return dict.opt_alc_3;
                    if (value === '4') return dict.opt_alc_4;
                }
                if (name === 'q21') {
                    if (value === '1') return dict.opt_q21_1;
                    if (value === '2') return dict.opt_q21_2;
                    if (value === '3') return dict.opt_q21_3;
                    if (value === '4') return dict.opt_q21_4;
                    if (value === '5') return dict.opt_q21_5;
                }
                if (name === 'facility_name') {
                    if (value === '協立病院') return dict.facility_kyouritsu;
                    if (value === '清寿園') return dict.facility_seijuen;
                    if (value === 'かがやき') return dict.facility_kagayaki;
                    if (value === 'かがやき2号館') return dict.facility_kagayaki2;
                    return value;
                }
                if (name === 'health_check_year') {
                    return value + (currentLang === 'ja' ? '年' : ''); // 数値はそのまま
                }
                if (name === 'health_check_season') {
                    if (value === '春') return dict.season_spring;
                    if (value === '冬') return dict.season_winter;
                    return value;
                }

                return value;
            }

            // ▼▼▼ 【②所属部署】Select（プルダウン）の選択値を取得してテキストに変換する関数 ▼▼▼
            function getSelectValueText(name) {
                const dict = translations[currentLang] || translations['ja'];
                const select = form.querySelector(`select[name="${name}"]`);
                if (!select) return '（要素なし）';

                // ▼▼▼ 【③施設名連動】非表示（＝協立病院以外）の場合は「対象外」 ▼▼▼
                const departmentGroup = document.getElementById('department_group'); // JSグローバルスコープから参照
                if (departmentGroup && departmentGroup.style.display === 'none') {
                    return `（${dict.val_target_out}）`;
                }

                const value = select.value;
                if (value === "") {
                    return dict.dept_select; 
                }

                if (value === '不明・該当なし') return dict.dept_unknown;
                if (value === '2階回復期') return dict.dept_2f_rec;
                if (value === '3階一般') return dict.dept_3f_gen;
                if (value === '4階西病棟') return dict.dept_4f_west;
                if (value === '4階東病棟') return dict.dept_4f_east;
                if (value === '2階介護医療院') return dict.dept_2f_care;
                if (value === '3階介護医療院') return dict.dept_3f_care;
                if (value === '事務') return dict.dept_office;

                return value;
            }

            // テキスト入力の値を取得する関数
            function getInputValue(id) {
                const dict = translations[currentLang] || translations['ja'];
                const element = document.getElementById(id);
                const value = element ? element.value.trim() : '';

                // ▼▼▼ 【⑦必須】 氏名が必須になったため、未入力時の表示を修正 ▼▼▼
                if (element && element.required && value === '') {
                    return `<span style="color: red;">${dict.val_unentered}</span>`;
                }

                // ▼▼▼ 職員IDが任意の入力になったため、未入力の表示を変更 ▼▼▼
                if (id === 'staff_id' && value === '') {
                    return dict.val_unentered;
                }
                return value ? htmlspecialchars(value) : dict.val_unentered;
            }

            // HTMLエスケープ
            function htmlspecialchars(str) {
                return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            /**
             * 確認ページ（ページ8）の内容を生成する
             * ★ 辞書データを使用してラベルを動的に生成
             */
            function buildConfirmationReview() {
                const dict = translations[currentLang] || translations['ja'];
                const confirmationReview = document.getElementById('confirmationReview');
                let detailsHtml = '<ul>';

                // ページ1
                detailsHtml += `<li><strong>${dict.lbl_conf_year}:</strong> ${getRadioValueText('health_check_year')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_season}:</strong> ${getRadioValueText('health_check_season')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_id}:</strong> ${getInputValue('staff_id')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_name}:</strong> ${getInputValue('staff_name')}</li>`;
                // ▼▼▼ 【追加】生年月日の確認表示 (辞書にキーがない場合は'生年月日') ▼▼▼
                detailsHtml += `<li><strong>${dict.lbl_conf_birth_date || '生年月日'}:</strong> ${getInputValue('birth_date')}</li>`;
                // ▲▲▲
                detailsHtml += `<li><strong>${dict.lbl_conf_facility}:</strong> ${getRadioValueText('facility_name')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_dept}:</strong> ${getSelectValueText('department')}</li>`;

                // ページ2
                let q1_text = getRadioValueText('q1');
                if (getRadioValueText('q1') === dict.opt_yes && getInputValue('q1_medicine_name') !== dict.val_unentered) q1_text += ` (${getInputValue('q1_medicine_name')})`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q1}:</strong> ${q1_text}</li>`;

                let q2_text = getRadioValueText('q2');
                if (getRadioValueText('q2') === dict.opt_yes && getInputValue('q2_medicine_name') !== dict.val_unentered) q2_text += ` (${getInputValue('q2_medicine_name')})`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q2}:</strong> ${q2_text}</li>`;

                let q3_text = getRadioValueText('q3');
                if (getRadioValueText('q3') === dict.opt_yes && getInputValue('q3_medicine_name') !== dict.val_unentered) q3_text += ` (${getInputValue('q3_medicine_name')})`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q3}:</strong> ${q3_text}</li>`;

                // ページ3
                detailsHtml += `<li><strong>${dict.lbl_conf_q4}:</strong> ${getRadioValueText('q4')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q5}:</strong> ${getRadioValueText('q5')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q6}:</strong> ${getRadioValueText('q6')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q7}:</strong> ${getRadioValueText('q7')}</li>`;

                // ページ4
                detailsHtml += `<li><strong>${dict.lbl_conf_q8}:</strong> ${getRadioValueText('q8')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q9}:</strong> ${getRadioValueText('q9')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q10}:</strong> ${getRadioValueText('q10')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q11}:</strong> ${getRadioValueText('q11')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q12}:</strong> ${getRadioValueText('q12')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q13}:</strong> ${getRadioValueText('q13')}</li>`;

                // ページ5
                detailsHtml += `<li><strong>${dict.lbl_conf_q14}:</strong> ${getRadioValueText('q14')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q15}:</strong> ${getRadioValueText('q15')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q16}:</strong> ${getRadioValueText('q16')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q17}:</strong> ${getRadioValueText('q17')}</li>`;

                // ページ6
                detailsHtml += `<li><strong>${dict.lbl_conf_q18}:</strong> ${getRadioValueText('q18')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q19}:</strong> ${getRadioValueText('q19')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q20}:</strong> ${getRadioValueText('q20')}</li>`;

                // ページ7
                detailsHtml += `<li><strong>${dict.lbl_conf_q21}:</strong> ${getRadioValueText('q21')}</li>`;
                detailsHtml += `<li><strong>${dict.lbl_conf_q22}:</strong> ${getRadioValueText('q22')}</li>`;

                detailsHtml += '</ul>';
                confirmationReview.innerHTML = detailsHtml;
            }

            // 「次へ」ボタンの処理
            nextBtn.addEventListener('click', function() {
                if (validateCurrentPage()) {
                    if (currentPage < totalPages) {
                        currentPage++;
                        if (currentPage === totalPages) {
                            buildConfirmationReview();
                        }
                        updateNavigation();
                        window.scrollTo(0, 0); // ページトップにスクロール
                    }
                }
            });

            // 「戻る」ボタンの処理
            backBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    updateNavigation();
                    window.scrollTo(0, 0); // ページトップにスクロール
                }
            });

            // フォーム送信時の最終確認
            form.addEventListener('submit', function(e) {
                // 最後のページで、最終バリデーションを実行
                if (currentPage === totalPages) {
                    // 全ページのバリデーション（簡易版）
                    const allRequiredRadios = new Set();
                    form.querySelectorAll('input[type="radio"][required]').forEach(radio => {
                        if (radio.disabled) return;
                        allRequiredRadios.add(radio.name);
                    });

                    let allValid = true;
                    allRequiredRadios.forEach(name => {
                        if (!form.querySelector(`input[name="${name}"]:checked`)) {
                            allValid = false;
                        }
                    });

                    // ▼▼▼ 【②所属部署】Select（プルダウン）の最終チェックを追加 ▼▼▼
                    form.querySelectorAll('select[required]').forEach(select => {
                        // ▼▼▼ 【④任意】 'department' は 'required' でないため、このチェックから除外される ▼▼▼
                        if (select.disabled || !select.required) return;
                        if (!select.checkValidity()) {
                            allValid = false;
                        }
                    });

                    // ▼▼▼ 【⑦必須】 必須テキスト入力のチェックを追加 ▼▼▼
                    form.querySelectorAll('input[required], textarea[required]').forEach(input => {
                        // ▼▼▼ 職員ID (staff_id) はチェック対象外にする ▼▼▼
                        if (input.type === 'radio' || input.disabled || input.id === 'staff_id') return;

                        // ▼▼▼ 【⑦必須】 staff_id以外の必須テキストをチェック ▼▼▼
                        if (input.id === 'staff_id') return; // staff_idは必須ではない

                        if (!input.checkValidity()) {
                            allValid = false;
                        }
                    });

                    if (!allValid) {
                        e.preventDefault();
                        alert('未入力の必須項目があります。前のページに戻って確認してください。');
                        return;
                    }
                }

                if (!confirm('この内容で送信してもよろしいですか？')) {
                    e.preventDefault();
                }
            });

            // ▼▼▼ 修正: 初期表示時に updateNavigation() を呼び出す ▼▼▼
            // これにより、最初のページの赤文字状態がセットされます
            updateNavigation();
        });
    </script>
</body>
</html>