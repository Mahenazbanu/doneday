-- ============================================================
-- DoneDay - database schema
-- Import via phpMyAdmin: Import -> choose this file -> Go.
-- ============================================================

CREATE DATABASE IF NOT EXISTS doneday
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE doneday;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)     NOT NULL,
    email         VARCHAR(190)     NOT NULL,
    password_hash VARCHAR(255)     NOT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- tasks
-- status: 'today' | 'later' | 'completed'
-- task_date: the calendar day a 'today' task belongs to
--            (used for the daily reset / limit counting)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    title       VARCHAR(200)  NOT NULL,
    description TEXT          NULL,
    priority    ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status      ENUM('today','later','completed') NOT NULL DEFAULT 'today',
    task_date   DATE          NULL,
    due_date    DATE          NULL,
    category    VARCHAR(50)   NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME     NULL,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tasks_user_status_date (user_id, status, task_date),
    KEY idx_tasks_user_completed   (user_id, completed_at),
    CONSTRAINT fk_tasks_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- settings (one row per user)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NOT NULL,
    daily_task_limit   TINYINT UNSIGNED NOT NULL DEFAULT 5,
    motivation_enabled TINYINT(1)   NOT NULL DEFAULT 1,
    theme              VARCHAR(10)  NOT NULL DEFAULT 'system',
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_user (user_id),
    CONSTRAINT fk_settings_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Development sample data (safe to delete in production)
-- Demo login: demo@doneday.test / demo1234
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash) VALUES
('Demo User', 'demo@doneday.test', '$2b$10$/8awssMZ/RfqX75tHLkInueT/CGcTK.AxMf9YdxAlO9k2m/kqQ38m');

SET @uid = LAST_INSERT_ID();

INSERT INTO settings (user_id) VALUES (@uid);

INSERT INTO tasks (user_id, title, description, priority, status, task_date, category) VALUES
(@uid, 'Finish DB assignment', 'Chapter 7 exercises + ER diagram', 'high',  'today', CURDATE(), 'Uni'),
(@uid, 'Study SQL joins', NULL, 'normal', 'today', CURDATE(), 'Uni'),
(@uid, 'Reply to Prof. Sharma', NULL, 'normal', 'today', CURDATE(), NULL),
(@uid, 'Exercise 20 minutes', NULL, 'low',    'today', CURDATE(), 'Health'),
(@uid, 'Clean desk', '5-minute reset', 'low',  'today', CURDATE(), 'Home'),
(@uid, 'Buy groceries', NULL, 'normal', 'later', NULL, 'Home'),
(@uid, 'Book dentist appointment', NULL, 'low', 'later', NULL, 'Health'),
(@uid, 'Plan weekend trip', NULL, 'low', 'later', NULL, NULL);
