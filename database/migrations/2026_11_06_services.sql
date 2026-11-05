-- Assignments, online payments, verifiable documents, SMS/email alerts and sessions.
-- Applied automatically by App\Services\AcademicCatalogSync::upgradeSchema() on the first
-- request after deployment; every statement is safe to run again. (The two-step sign-in
-- columns on `users` are added there too, as MySQL has no ADD COLUMN IF NOT EXISTS.)

-- Coursework submitted online.
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`        BIGINT UNSIGNED NOT NULL,
  `title`              VARCHAR(150)    NOT NULL,
  `instructions`       TEXT                     DEFAULT NULL,
  `attachment_path`    VARCHAR(255)             DEFAULT NULL,
  `attachment_name`    VARCHAR(255)             DEFAULT NULL,
  `due_at`             DATETIME        NOT NULL,
  `max_score`          DECIMAL(6,2)    NOT NULL DEFAULT 100.00,
  `submission_type`    ENUM('file','text','both') NOT NULL DEFAULT 'file',
  `allow_late`         TINYINT(1)      NOT NULL DEFAULT 1,
  `late_penalty`       TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'percent of the score taken off a late submission',
  `status`             ENUM('draft','published') NOT NULL DEFAULT 'published',
  `assessment_id`      BIGINT UNSIGNED          DEFAULT NULL COMMENT 'gradebook assessment the scores were transferred to',
  `created_by`         BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_asg_offering` (`offering_id`, `due_at`),
  CONSTRAINT `fk_asg_offering`   FOREIGN KEY (`offering_id`)   REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asg_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED NOT NULL,
  `student_id`    BIGINT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(255)             DEFAULT NULL,
  `file_name`     VARCHAR(255)             DEFAULT NULL,
  `file_size`     INT UNSIGNED             DEFAULT NULL,
  `text_answer`   MEDIUMTEXT               DEFAULT NULL,
  `submitted_at`  DATETIME        NOT NULL,
  `is_late`       TINYINT(1)      NOT NULL DEFAULT 0,
  `versions`      SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'times submitted (a resubmission replaces the earlier work until it is marked)',
  `raw_score`     DECIMAL(6,2)             DEFAULT NULL,
  `score`         DECIMAL(6,2)             DEFAULT NULL COMMENT 'after any late penalty',
  `feedback`      TEXT                     DEFAULT NULL,
  `graded_by`     BIGINT UNSIGNED          DEFAULT NULL,
  `graded_at`     DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asub` (`assignment_id`, `student_id`),
  KEY `ix_asub_student` (`student_id`),
  CONSTRAINT `fk_asub_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asub_student`    FOREIGN KEY (`student_id`)    REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fee payments started online (MTN MoMo request-to-pay, or a mobile money / bank reference to verify).
CREATE TABLE IF NOT EXISTS `online_payments` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`          CHAR(36)        NOT NULL COMMENT 'our id for the transaction (sent to the provider)',
  `student_id`         BIGINT UNSIGNED NOT NULL,
  `invoice_id`         BIGINT UNSIGNED          DEFAULT NULL,
  `initiated_by`       BIGINT UNSIGNED          DEFAULT NULL COMMENT 'user who started it: the student or a parent/sponsor',
  `provider`           ENUM('mtn_momo','orange_money','mtn_manual','bank_transfer') NOT NULL,
  `payer_phone`        VARCHAR(30)              DEFAULT NULL,
  `amount`             DECIMAL(12,2)   NOT NULL,
  `currency`           CHAR(3)         NOT NULL DEFAULT 'USD',
  `status`             ENUM('pending','awaiting_verification','successful','failed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `provider_reference` VARCHAR(100)             DEFAULT NULL COMMENT 'transaction id from the provider or typed by the payer',
  `proof_path`         VARCHAR(255)             DEFAULT NULL,
  `message`            VARCHAR(255)             DEFAULT NULL,
  `payment_id`         BIGINT UNSIGNED          DEFAULT NULL COMMENT 'the posted payment, once successful',
  `verified_by`        BIGINT UNSIGNED          DEFAULT NULL,
  `verified_at`        DATETIME                 DEFAULT NULL,
  `created_at`         DATETIME        NOT NULL,
  `updated_at`         DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_op_reference` (`reference`),
  UNIQUE KEY `uq_op_provider_ref` (`provider`, `provider_reference`),
  KEY `ix_op_student` (`student_id`),
  KEY `ix_op_status` (`status`),
  CONSTRAINT `fk_op_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_op_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Official documents issued to students; anyone can check one at /verify/{code}.
CREATE TABLE IF NOT EXISTS `issued_documents` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial`            VARCHAR(40)     NOT NULL,
  `verification_code` VARCHAR(20)     NOT NULL,
  `doc_type`          ENUM('admission_letter','enrollment_letter','transcript','id_card','clearance_letter') NOT NULL,
  `student_id`        BIGINT UNSIGNED NOT NULL,
  `summary`           TEXT                     DEFAULT NULL COMMENT 'JSON: the facts certified, shown on the verification page',
  `issued_by`         BIGINT UNSIGNED          DEFAULT NULL,
  `issued_at`         DATETIME        NOT NULL,
  `valid_until`       DATE                     DEFAULT NULL,
  `status`            ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
  `revoked_reason`    VARCHAR(255)             DEFAULT NULL,
  `verify_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doc_serial` (`serial`),
  UNIQUE KEY `uq_doc_code` (`verification_code`),
  KEY `ix_doc_student` (`student_id`, `doc_type`),
  CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email and SMS alerts: queued here and sent after the page has been delivered.
CREATE TABLE IF NOT EXISTS `notification_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED          DEFAULT NULL,
  `channel`    ENUM('email','sms') NOT NULL,
  `event`      VARCHAR(40)     NOT NULL,
  `recipient`  VARCHAR(150)    NOT NULL,
  `subject`    VARCHAR(200)             DEFAULT NULL,
  `body`       TEXT            NOT NULL,
  `status`     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `error`      VARCHAR(255)             DEFAULT NULL,
  `created_at` DATETIME        NOT NULL,
  `sent_at`    DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_nl_status` (`status`, `id`),
  KEY `ix_nl_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signed-in devices; a revoked session is signed out on its next request.
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `session_hash` CHAR(64)        NOT NULL,
  `ip_address`   VARCHAR(45)              DEFAULT NULL,
  `user_agent`   VARCHAR(255)             DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL,
  `last_seen_at` DATETIME        NOT NULL,
  `revoked_at`   DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_us_hash` (`session_hash`),
  KEY `ix_us_user` (`user_id`, `revoked_at`),
  CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mobile money as a payment method.
ALTER TABLE `payments` MODIFY `method`
  ENUM('cash','bank_transfer','mpesa','mobile_money','card','cheque','bursary','scholarship','waiver') NOT NULL DEFAULT 'cash';

-- Settings for this phase (Admin > Settings); existing values are never overwritten.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `data_type`, `label`, `description`) VALUES
  ('graduation_min_cgpa', '2.00', 'academic', 'decimal', 'Minimum CGPA to Graduate', 'Used by the degree audit.'),
  ('online_payments_enabled', '1', 'payments', 'boolean', 'Students and Sponsors Can Pay Online', NULL),
  ('mtn_momo_merchant_number', '', 'payments', 'string', 'MTN MoMo Merchant Number (manual payments)', 'Shown to payers who send money and enter the transaction ID.'),
  ('orange_money_merchant_number', '', 'payments', 'string', 'Orange Money Merchant Number', 'Shown to payers who send money and enter the transaction ID.'),
  ('bank_payment_details', '', 'payments', 'text', 'Bank Account Details for Transfers', 'Bank, account name and number shown to payers.'),
  ('alerts_email_enabled', '1', 'notifications', 'boolean', 'Send Email Alerts', NULL),
  ('alerts_sms_enabled', '0', 'notifications', 'boolean', 'Send SMS Alerts', 'Needs an SMS gateway configured in the .env file (SMS_DRIVER).'),
  ('alerts_sms_events', 'payment_received,registration_complete,exam_published,results_released,assignment_graded,class_cancelled', 'notifications', 'string', 'Events Sent by SMS', 'Comma-separated: payment_received, registration_complete, class_scheduled, class_cancelled, exam_published, results_released, assignment_published, assignment_graded, document_issued'),
  ('require_2fa_admins', '0', 'security', 'boolean', 'Require Two-Step Sign-In for Administrators', NULL),
  ('backup_auto_daily', '1', 'security', 'boolean', 'Back Up the Database Daily', NULL),
  ('backup_keep', '14', 'security', 'integer', 'Number of Backups to Keep', NULL);
