-- Online classes, course materials and online examinations.
-- Applied automatically by App\Services\AcademicCatalogSync::upgradeSchema() on the first
-- request after deployment; every statement is safe to run again.

-- Live online class sessions (Jitsi, Zoom, Google Meet, Teams ...) for a class offering.
CREATE TABLE IF NOT EXISTS `class_sessions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`      BIGINT UNSIGNED NOT NULL,
  `title`            VARCHAR(150)    NOT NULL,
  `description`      TEXT                     DEFAULT NULL,
  `platform`         ENUM('jitsi','zoom','google_meet','teams','other') NOT NULL DEFAULT 'jitsi',
  `meeting_url`      VARCHAR(500)    NOT NULL,
  `meeting_id`       VARCHAR(100)             DEFAULT NULL,
  `passcode`         VARCHAR(100)             DEFAULT NULL,
  `starts_at`        DATETIME        NOT NULL,
  `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `recording_url`    VARCHAR(500)             DEFAULT NULL,
  `status`           ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by`       BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cs_offering` (`offering_id`, `starts_at`),
  KEY `ix_cs_starts` (`starts_at`),
  CONSTRAINT `fk_cs_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who joined each online class (recorded when a student presses Join).
CREATE TABLE IF NOT EXISTS `class_session_attendance` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`      BIGINT UNSIGNED NOT NULL,
  `student_id`      BIGINT UNSIGNED NOT NULL,
  `first_joined_at` DATETIME        NOT NULL,
  `last_joined_at`  DATETIME        NOT NULL,
  `join_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_csa` (`session_id`, `student_id`),
  KEY `ix_csa_student` (`student_id`),
  CONSTRAINT `fk_csa_session` FOREIGN KEY (`session_id`) REFERENCES `class_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_csa_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lecture notes, slides, readings and links shared with a class.
CREATE TABLE IF NOT EXISTS `course_materials` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`  BIGINT UNSIGNED NOT NULL,
  `title`        VARCHAR(150)    NOT NULL,
  `description`  TEXT                     DEFAULT NULL,
  `week`         TINYINT UNSIGNED         DEFAULT NULL,
  `kind`         ENUM('file','link') NOT NULL DEFAULT 'file',
  `file_path`    VARCHAR(255)             DEFAULT NULL,
  `file_name`    VARCHAR(255)             DEFAULT NULL,
  `file_size`    INT UNSIGNED             DEFAULT NULL,
  `url`          VARCHAR(500)             DEFAULT NULL,
  `is_published` TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cm_offering` (`offering_id`, `week`),
  CONSTRAINT `fk_cm_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timed online examinations (quizzes, tests, mid-terms, finals) for a class offering.
CREATE TABLE IF NOT EXISTS `online_exams` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`         BIGINT UNSIGNED NOT NULL,
  `title`               VARCHAR(150)    NOT NULL,
  `exam_type`           ENUM('quiz','test','midterm','final','assignment') NOT NULL DEFAULT 'quiz',
  `instructions`        TEXT                     DEFAULT NULL,
  `opens_at`            DATETIME        NOT NULL,
  `closes_at`           DATETIME        NOT NULL,
  `duration_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `pass_percent`        TINYINT UNSIGNED NOT NULL DEFAULT 50,
  `shuffle_questions`   TINYINT(1)      NOT NULL DEFAULT 1,
  `shuffle_options`     TINYINT(1)      NOT NULL DEFAULT 1,
  `requires_enrollment` TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'only students whose registration is complete (1st installment paid)',
  `show_results`        ENUM('immediately','after_close','manual') NOT NULL DEFAULT 'after_close',
  `results_released`    TINYINT(1)      NOT NULL DEFAULT 0,
  `status`              ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  `assessment_id`       BIGINT UNSIGNED          DEFAULT NULL COMMENT 'gradebook assessment the scores were transferred to',
  `created_by`          BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_oe_offering` (`offering_id`),
  KEY `ix_oe_window` (`opens_at`, `closes_at`),
  CONSTRAINT `fk_oe_offering`   FOREIGN KEY (`offering_id`)   REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oe_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `online_exam_questions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id`       BIGINT UNSIGNED NOT NULL,
  `question_type` ENUM('single','multiple','true_false','short_answer','essay') NOT NULL DEFAULT 'single',
  `body`          TEXT            NOT NULL,
  `marks`         DECIMAL(6,2)    NOT NULL DEFAULT 1.00,
  `explanation`   TEXT                     DEFAULT NULL COMMENT 'shown to students when results are released',
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_oeq_exam` (`exam_id`, `sort_order`),
  CONSTRAINT `fk_oeq_exam` FOREIGN KEY (`exam_id`) REFERENCES `online_exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Choices for single/multiple/true-false questions; accepted answers for short-answer questions.
CREATE TABLE IF NOT EXISTS `online_exam_options` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `body`        VARCHAR(1000)   NOT NULL,
  `is_correct`  TINYINT(1)      NOT NULL DEFAULT 0,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_oeo_question` (`question_id`, `sort_order`),
  CONSTRAINT `fk_oeo_question` FOREIGN KEY (`question_id`) REFERENCES `online_exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `online_exam_attempts` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id`        BIGINT UNSIGNED NOT NULL,
  `student_id`     BIGINT UNSIGNED NOT NULL,
  `started_at`     DATETIME        NOT NULL,
  `deadline_at`    DATETIME        NOT NULL,
  `submitted_at`   DATETIME                 DEFAULT NULL,
  `status`         ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
  `auto_submitted` TINYINT(1)      NOT NULL DEFAULT 0,
  `question_order` TEXT                     DEFAULT NULL COMMENT 'JSON: question ids and option ids in the order this student sees them',
  `score`          DECIMAL(7,2)             DEFAULT NULL,
  `max_score`      DECIMAL(7,2)             DEFAULT NULL,
  `focus_lost`     SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'times the student left the exam window',
  `ip_address`     VARCHAR(45)              DEFAULT NULL,
  `user_agent`     VARCHAR(255)             DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oea` (`exam_id`, `student_id`),
  KEY `ix_oea_student` (`student_id`),
  CONSTRAINT `fk_oea_exam`    FOREIGN KEY (`exam_id`)    REFERENCES `online_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oea_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `online_exam_answers` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`    BIGINT UNSIGNED NOT NULL,
  `question_id`   BIGINT UNSIGNED NOT NULL,
  `option_ids`    VARCHAR(255)             DEFAULT NULL COMMENT 'comma-separated chosen option ids',
  `answer_text`   TEXT                     DEFAULT NULL,
  `is_correct`    TINYINT(1)               DEFAULT NULL,
  `marks_awarded` DECIMAL(6,2)             DEFAULT NULL COMMENT 'NULL = awaiting manual marking',
  `feedback`      TEXT                     DEFAULT NULL,
  `graded_by`     BIGINT UNSIGNED          DEFAULT NULL,
  `answered_at`   DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oean` (`attempt_id`, `question_id`),
  CONSTRAINT `fk_oean_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `online_exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oean_question` FOREIGN KEY (`question_id`) REFERENCES `online_exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
