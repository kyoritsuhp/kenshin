-- ファイル名称: database_create.sql
-- 生成日時: 2025-10-02

-- データベース作成
CREATE DATABASE IF NOT EXISTS monshin DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE monshin;

-- usersテーブル作成（管理者用）
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    password VARCHAR(50) NOT NULL,
    username VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理者アカウント挿入
INSERT INTO users (id, password, username) VALUES ('admin', 'admin', '管理者');

-- 問診票回答テーブル作成
CREATE TABLE IF NOT EXISTS questionnaire_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) NOT NULL,
    staff_name VARCHAR(100),
    department VARCHAR(100),
    
    -- 質問1-3: 薬の使用
    q1_blood_pressure_med TINYINT COMMENT '1:はい, 2:いいえ',
    q1_medicine_name VARCHAR(255) COMMENT 'Q1の具体的な薬名',
    q2_insulin_med TINYINT COMMENT '1:はい, 2:いいえ',
    q2_medicine_name VARCHAR(255) COMMENT 'Q2の具体的な薬名',
    q3_cholesterol_med TINYINT COMMENT '1:はい, 2:いいえ',
    q3_medicine_name VARCHAR(255) COMMENT 'Q3の具体的な薬名',
    
    -- 質問4-7: 既往歴
    q4_stroke TINYINT COMMENT '1:はい, 2:いいえ',
    q5_heart_disease TINYINT COMMENT '1:はい, 2:いいえ',
    q6_kidney_failure TINYINT COMMENT '1:はい, 2:いいえ',
    q7_anemia TINYINT COMMENT '1:はい, 2:いいえ',
    
    -- 質問8-13: 生活習慣
    q8_smoking TINYINT COMMENT '1:はい, 2:いいえ',
    q9_weight_gain TINYINT COMMENT '1:はい, 2:いいえ',
    q10_exercise TINYINT COMMENT '1:はい, 2:いいえ',
    q11_walking TINYINT COMMENT '1:はい, 2:いいえ',
    q12_walking_speed TINYINT COMMENT '1:はい, 2:いいえ',
    q13_weight_change TINYINT COMMENT '1:はい, 2:いいえ',
    
    -- 質問14-17: 食生活
    q14_eating_speed TINYINT COMMENT '1:速い, 2:ふつう, 3:遅い',
    q15_dinner_before_bed TINYINT COMMENT '1:はい, 2:いいえ',
    q16_snack_after_dinner TINYINT COMMENT '1:はい, 2:いいえ',
    q17_skip_breakfast TINYINT COMMENT '1:はい, 2:いいえ',
    
    -- 質問18-19: 飲酒
    q18_alcohol_frequency TINYINT COMMENT '1:毎日, 2:時々, 3:ほとんど飲まない',
    q19_alcohol_amount TINYINT COMMENT '1:1合未満, 2:1-2合未満, 3:2-3合未満, 4:3合以上',
    
    -- 質問20: 睡眠
    q20_sleep TINYINT COMMENT '1:はい, 2:いいえ',
    
    -- 質問21-22: 改善意欲
    q21_improvement_intention TINYINT COMMENT '1-5',
    q22_guidance_use TINYINT COMMENT '1:はい, 2:いいえ',
    
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_staff_id (staff_id),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2025-10-22 追記: 施設名カラムを追加
ALTER TABLE questionnaire_responses
  ADD COLUMN facility_name VARCHAR(100) AFTER department;
USE monshin;
ALTER TABLE questionnaire_responses
  ADD COLUMN health_check_year VARCHAR(4) AFTER department,
  ADD COLUMN health_check_season VARCHAR(10) AFTER health_check_year;

  ALTER TABLE questionnaire_responses MODIFY staff_id VARCHAR(50) NULL;