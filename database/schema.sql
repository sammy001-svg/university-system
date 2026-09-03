-- =====================================================================
--  UNIVERSITY MANAGEMENT SYSTEM  --  DATABASE SCHEMA
--  Engine: MySQL 8.0 / MariaDB 10.4+   Charset: utf8mb4
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- CREATE DATABASE IF NOT EXISTS `university_db`
--   DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `university_db`;

-- =====================================================================
--  SECTION 1 : IDENTITY, ACCESS CONTROL & SYSTEM
-- =====================================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                 CHAR(36)        NOT NULL,
  `username`             VARCHAR(60)     NOT NULL,
  `email`                VARCHAR(150)    NOT NULL,
  `password_hash`        VARCHAR(255)    NOT NULL,
  `title`                VARCHAR(20)              DEFAULT NULL,
  `first_name`           VARCHAR(80)     NOT NULL,
  `last_name`            VARCHAR(80)     NOT NULL,
  `other_name`           VARCHAR(80)              DEFAULT NULL,
  `gender`               ENUM('male','female','other') DEFAULT NULL,
  `date_of_birth`        DATE                     DEFAULT NULL,
  `phone`                VARCHAR(30)              DEFAULT NULL,
  `alt_phone`            VARCHAR(30)              DEFAULT NULL,
  `avatar`               VARCHAR(255)             DEFAULT NULL,
  `user_type`            ENUM('admin','staff','lecturer','student','applicant','parent') NOT NULL DEFAULT 'staff',
  `status`               ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
  `must_change_password` TINYINT(1)      NOT NULL DEFAULT 0,
  `email_verified_at`    DATETIME                 DEFAULT NULL,
  `last_login_at`        DATETIME                 DEFAULT NULL,
  `last_login_ip`        VARCHAR(45)              DEFAULT NULL,
  `failed_attempts`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`         DATETIME                 DEFAULT NULL,
  `remember_token`       VARCHAR(100)             DEFAULT NULL,
  `created_by`           BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_uuid` (`uuid`),
  KEY `ix_users_type_status` (`user_type`,`status`),
  KEY `ix_users_name` (`last_name`,`first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255)          DEFAULT NULL,
  `level`       TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT '1 = highest authority',
  `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL COMMENT 'module.action e.g. students.create',
  `module`      VARCHAR(60)  NOT NULL,
  `description` VARCHAR(255)          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `ix_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `ix_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `role_id`     INT UNSIGNED    NOT NULL,
  `assigned_by` BIGINT UNSIGNED          DEFAULT NULL,
  `assigned_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `ix_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED    NOT NULL,
  `effect`        ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  PRIMARY KEY (`user_id`,`permission_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `used_at`    DATETIME              DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pr_email` (`email`),
  KEY `ix_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(150) NOT NULL,
  `ip_address` VARCHAR(45)           DEFAULT NULL,
  `user_agent` VARCHAR(255)          DEFAULT NULL,
  `successful` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_la_identifier` (`identifier`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED          DEFAULT NULL,
  `action`      VARCHAR(60)     NOT NULL,
  `module`      VARCHAR(60)              DEFAULT NULL,
  `entity`      VARCHAR(80)              DEFAULT NULL,
  `entity_id`   VARCHAR(60)              DEFAULT NULL,
  `description` VARCHAR(255)             DEFAULT NULL,
  `old_values`  TEXT                     DEFAULT NULL,
  `new_values`  TEXT                     DEFAULT NULL,
  `ip_address`  VARCHAR(45)              DEFAULT NULL,
  `user_agent`  VARCHAR(255)             DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_user` (`user_id`),
  KEY `ix_audit_entity` (`entity`,`entity_id`),
  KEY `ix_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT                  DEFAULT NULL,
  `setting_group` VARCHAR(60)  NOT NULL DEFAULT 'general',
  `data_type`     ENUM('string','integer','decimal','boolean','json','text','file') NOT NULL DEFAULT 'string',
  `label`         VARCHAR(150)          DEFAULT NULL,
  `description`   VARCHAR(255)          DEFAULT NULL,
  `is_public`     TINYINT(1)   NOT NULL DEFAULT 0,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `type`       VARCHAR(60)     NOT NULL DEFAULT 'info',
  `icon`       VARCHAR(60)              DEFAULT NULL,
  `title`      VARCHAR(150)    NOT NULL,
  `message`    TEXT                     DEFAULT NULL,
  `link`       VARCHAR(255)             DEFAULT NULL,
  `read_at`    DATETIME                 DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notif_user` (`user_id`,`read_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 2 : INSTITUTIONAL & ACADEMIC STRUCTURE
-- =====================================================================

DROP TABLE IF EXISTS `campuses`;
CREATE TABLE `campuses` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(20)  NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `address`    VARCHAR(255)          DEFAULT NULL,
  `city`       VARCHAR(80)           DEFAULT NULL,
  `country`    VARCHAR(80)           DEFAULT 'Kenya',
  `phone`      VARCHAR(30)           DEFAULT NULL,
  `email`      VARCHAR(120)          DEFAULT NULL,
  `is_main`    TINYINT(1)   NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campus_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `faculties`;
CREATE TABLE `faculties` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campus_id`        INT UNSIGNED          DEFAULT NULL,
  `code`             VARCHAR(20)  NOT NULL,
  `name`             VARCHAR(150) NOT NULL,
  `dean_id`          BIGINT UNSIGNED       DEFAULT NULL,
  `email`            VARCHAR(120)          DEFAULT NULL,
  `phone`            VARCHAR(30)           DEFAULT NULL,
  `description`      TEXT                  DEFAULT NULL,
  `established_year` SMALLINT UNSIGNED     DEFAULT NULL,
  `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_faculty_code` (`code`),
  KEY `ix_faculty_campus` (`campus_id`),
  CONSTRAINT `fk_faculty_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `faculty_id`  INT UNSIGNED NOT NULL,
  `code`        VARCHAR(20)  NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `hod_id`      BIGINT UNSIGNED       DEFAULT NULL,
  `email`       VARCHAR(120)          DEFAULT NULL,
  `phone`       VARCHAR(30)           DEFAULT NULL,
  `description` TEXT                  DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dept_code` (`code`),
  KEY `ix_dept_faculty` (`faculty_id`),
  CONSTRAINT `fk_dept_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id`      INT UNSIGNED NOT NULL,
  `code`               VARCHAR(20)  NOT NULL,
  `name`               VARCHAR(180) NOT NULL,
  `award`              VARCHAR(120)          DEFAULT NULL,
  `level`              ENUM('certificate','diploma','bachelor','postgraduate_diploma','masters','phd') NOT NULL DEFAULT 'bachelor',
  `duration_years`     DECIMAL(3,1) NOT NULL DEFAULT 4.0,
  `semesters_per_year` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `total_credit_hours` SMALLINT UNSIGNED     DEFAULT NULL,
  `study_mode`         ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
  `min_entry_grade`    VARCHAR(20)           DEFAULT NULL,
  `entry_requirements` TEXT                  DEFAULT NULL,
  `description`        TEXT                  DEFAULT NULL,
  `application_fee`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`             ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_code` (`code`),
  KEY `ix_program_dept` (`department_id`),
  CONSTRAINT `fk_program_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id`  INT UNSIGNED NOT NULL,
  `code`           VARCHAR(25)  NOT NULL,
  `title`          VARCHAR(200) NOT NULL,
  `credit_hours`   TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `lecture_hours`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tutorial_hours` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `practical_hours` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `level`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `description`    TEXT                  DEFAULT NULL,
  `objectives`     TEXT                  DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_code` (`code`),
  KEY `ix_course_dept` (`department_id`),
  CONSTRAINT `fk_course_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `program_courses`;
CREATE TABLE `program_courses` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id`      INT UNSIGNED NOT NULL,
  `course_id`       INT UNSIGNED NOT NULL,
  `year_of_study`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `semester_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `course_type`     ENUM('core','elective','common','audit') NOT NULL DEFAULT 'core',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_course` (`program_id`,`course_id`),
  KEY `ix_pc_course` (`course_id`),
  KEY `ix_pc_plan` (`program_id`,`year_of_study`,`semester_number`),
  CONSTRAINT `fk_pc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses` (`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_prerequisites`;
CREATE TABLE `course_prerequisites` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`              INT UNSIGNED NOT NULL,
  `prerequisite_course_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prereq` (`course_id`,`prerequisite_course_id`),
  CONSTRAINT `fk_prereq_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prereq_req`    FOREIGN KEY (`prerequisite_course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE `academic_years` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(40)  NOT NULL COMMENT 'e.g. 2025/2026',
  `start_date` DATE         NOT NULL,
  `end_date`   DATE         NOT NULL,
  `is_current` TINYINT(1)   NOT NULL DEFAULT 0,
  `status`     ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ay_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id`   INT UNSIGNED NOT NULL,
  `name`               VARCHAR(60)  NOT NULL,
  `semester_number`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `start_date`         DATE         NOT NULL,
  `end_date`           DATE         NOT NULL,
  `registration_start` DATE                  DEFAULT NULL,
  `registration_end`   DATE                  DEFAULT NULL,
  `exam_start`         DATE                  DEFAULT NULL,
  `exam_end`           DATE                  DEFAULT NULL,
  `is_current`         TINYINT(1)   NOT NULL DEFAULT 0,
  `results_published`  TINYINT(1)   NOT NULL DEFAULT 0,
  `status`             ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_semester` (`academic_year_id`,`semester_number`),
  CONSTRAINT `fk_sem_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campus_id` INT UNSIGNED          DEFAULT NULL,
  `code`      VARCHAR(20)  NOT NULL,
  `name`      VARCHAR(120) NOT NULL,
  `floors`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status`    ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_building_code` (`code`),
  CONSTRAINT `fk_building_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `building_id`   INT UNSIGNED          DEFAULT NULL,
  `code`          VARCHAR(30)  NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `floor`         TINYINT      NOT NULL DEFAULT 0,
  `capacity`      SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  `room_type`     ENUM('lecture_hall','laboratory','tutorial','exam_hall','office','library','other') NOT NULL DEFAULT 'lecture_hall',
  `has_projector` TINYINT(1)   NOT NULL DEFAULT 0,
  `status`        ENUM('available','maintenance','unavailable') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_code` (`code`),
  KEY `ix_room_building` (`building_id`),
  CONSTRAINT `fk_room_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 3 : PEOPLE  (students, staff, guardians)
-- =====================================================================

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT UNSIGNED NOT NULL,
  `staff_number`    VARCHAR(40)     NOT NULL,
  `department_id`   INT UNSIGNED             DEFAULT NULL,
  `designation`     VARCHAR(120)             DEFAULT NULL,
  `staff_category`  ENUM('academic','administrative','support','technical') NOT NULL DEFAULT 'academic',
  `employment_type` ENUM('permanent','contract','part_time','visiting','intern') NOT NULL DEFAULT 'permanent',
  `qualification`   VARCHAR(180)             DEFAULT NULL,
  `specialization`  VARCHAR(180)             DEFAULT NULL,
  `date_joined`     DATE                     DEFAULT NULL,
  `contract_end`    DATE                     DEFAULT NULL,
  `salary_grade`    VARCHAR(20)              DEFAULT NULL,
  `basic_salary`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `bank_name`       VARCHAR(120)             DEFAULT NULL,
  `bank_branch`     VARCHAR(120)             DEFAULT NULL,
  `bank_account`    VARCHAR(60)              DEFAULT NULL,
  `tax_pin`         VARCHAR(40)              DEFAULT NULL,
  `nssf_number`     VARCHAR(40)              DEFAULT NULL,
  `nhif_number`     VARCHAR(40)              DEFAULT NULL,
  `national_id`     VARCHAR(40)              DEFAULT NULL,
  `address`         VARCHAR(255)             DEFAULT NULL,
  `status`          ENUM('active','on_leave','suspended','terminated','retired') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_number` (`staff_number`),
  UNIQUE KEY `uq_staff_user` (`user_id`),
  KEY `ix_staff_dept` (`department_id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `intakes`;
CREATE TABLE `intakes` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id`    INT UNSIGNED NOT NULL,
  `name`                VARCHAR(80)  NOT NULL,
  `code`                VARCHAR(30)  NOT NULL,
  `start_date`          DATE                  DEFAULT NULL,
  `application_open`    DATE                  DEFAULT NULL,
  `application_close`   DATE                  DEFAULT NULL,
  `status`              ENUM('planned','open','closed') NOT NULL DEFAULT 'planned',
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intake_code` (`code`),
  CONSTRAINT `fk_intake_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                BIGINT UNSIGNED NOT NULL,
  `admission_number`       VARCHAR(40)     NOT NULL,
  `registration_number`    VARCHAR(40)              DEFAULT NULL,
  `program_id`             INT UNSIGNED    NOT NULL,
  `intake_id`              INT UNSIGNED             DEFAULT NULL,
  `campus_id`              INT UNSIGNED             DEFAULT NULL,
  `year_of_study`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `current_semester`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `study_mode`             ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
  `admission_date`         DATE                     DEFAULT NULL,
  `expected_completion`    DATE                     DEFAULT NULL,
  `completion_date`        DATE                     DEFAULT NULL,
  `sponsor_type`           ENUM('self','government','scholarship','employer','parent','other') NOT NULL DEFAULT 'self',
  `sponsor_name`           VARCHAR(150)             DEFAULT NULL,
  `nationality`            VARCHAR(80)              DEFAULT 'Kenyan',
  `national_id`            VARCHAR(40)              DEFAULT NULL,
  `passport_number`        VARCHAR(40)              DEFAULT NULL,
  `religion`               VARCHAR(60)              DEFAULT NULL,
  `marital_status`         ENUM('single','married','divorced','widowed') DEFAULT 'single',
  `blood_group`            VARCHAR(10)              DEFAULT NULL,
  `disability`             VARCHAR(150)             DEFAULT NULL,
  `postal_address`         VARCHAR(150)             DEFAULT NULL,
  `physical_address`       VARCHAR(255)             DEFAULT NULL,
  `city`                   VARCHAR(80)              DEFAULT NULL,
  `county`                 VARCHAR(80)              DEFAULT NULL,
  `emergency_contact_name` VARCHAR(150)             DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(30)             DEFAULT NULL,
  `emergency_contact_relation` VARCHAR(60)          DEFAULT NULL,
  `previous_school`        VARCHAR(180)             DEFAULT NULL,
  `previous_qualification` VARCHAR(180)             DEFAULT NULL,
  `previous_grade`         VARCHAR(30)              DEFAULT NULL,
  `cgpa`                   DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
  `credits_earned`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`                 ENUM('active','deferred','suspended','graduated','withdrawn','expelled','alumni') NOT NULL DEFAULT 'active',
  `created_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_admission` (`admission_number`),
  UNIQUE KEY `uq_student_user` (`user_id`),
  KEY `ix_student_reg` (`registration_number`),
  KEY `ix_student_program` (`program_id`,`year_of_study`),
  KEY `ix_student_status` (`status`),
  CONSTRAINT `fk_student_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `fk_student_intake`  FOREIGN KEY (`intake_id`)  REFERENCES `intakes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_student_campus`  FOREIGN KEY (`campus_id`)  REFERENCES `campuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `guardians`;
CREATE TABLE `guardians` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`      BIGINT UNSIGNED          DEFAULT NULL,
  `full_name`    VARCHAR(150)    NOT NULL,
  `relationship` VARCHAR(60)     NOT NULL DEFAULT 'parent',
  `phone`        VARCHAR(30)              DEFAULT NULL,
  `email`        VARCHAR(150)             DEFAULT NULL,
  `occupation`   VARCHAR(120)             DEFAULT NULL,
  `address`      VARCHAR(255)             DEFAULT NULL,
  `is_primary`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_guardian_student` (`student_id`),
  CONSTRAINT `fk_guardian_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guardian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 4 : ADMISSIONS
-- =====================================================================

DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_number` VARCHAR(40)     NOT NULL,
  `user_id`            BIGINT UNSIGNED          DEFAULT NULL,
  `intake_id`          INT UNSIGNED    NOT NULL,
  `program_id`         INT UNSIGNED    NOT NULL,
  `alt_program_id`     INT UNSIGNED             DEFAULT NULL,
  `first_name`         VARCHAR(80)     NOT NULL,
  `last_name`          VARCHAR(80)     NOT NULL,
  `other_name`         VARCHAR(80)              DEFAULT NULL,
  `email`              VARCHAR(150)    NOT NULL,
  `phone`              VARCHAR(30)              DEFAULT NULL,
  `gender`             ENUM('male','female','other') DEFAULT NULL,
  `date_of_birth`      DATE                     DEFAULT NULL,
  `nationality`        VARCHAR(80)              DEFAULT 'Kenyan',
  `national_id`        VARCHAR(40)              DEFAULT NULL,
  `address`            VARCHAR(255)             DEFAULT NULL,
  `county`             VARCHAR(80)              DEFAULT NULL,
  `previous_school`    VARCHAR(180)             DEFAULT NULL,
  `qualification`      VARCHAR(180)             DEFAULT NULL,
  `grade_obtained`     VARCHAR(30)              DEFAULT NULL,
  `year_completed`     SMALLINT UNSIGNED        DEFAULT NULL,
  `study_mode`         ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
  `sponsor_type`       ENUM('self','government','scholarship','employer','parent','other') NOT NULL DEFAULT 'self',
  `personal_statement` TEXT                     DEFAULT NULL,
  `application_fee_paid` TINYINT(1)    NOT NULL DEFAULT 0,
  `score`              DECIMAL(5,2)             DEFAULT NULL,
  `status`             ENUM('draft','submitted','under_review','shortlisted','accepted','rejected','enrolled','withdrawn') NOT NULL DEFAULT 'draft',
  `reviewed_by`        BIGINT UNSIGNED          DEFAULT NULL,
  `reviewed_at`        DATETIME                 DEFAULT NULL,
  `remarks`            TEXT                     DEFAULT NULL,
  `submitted_at`       DATETIME                 DEFAULT NULL,
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_number` (`application_number`),
  KEY `ix_app_status` (`status`),
  KEY `ix_app_intake` (`intake_id`,`program_id`),
  CONSTRAINT `fk_app_intake`  FOREIGN KEY (`intake_id`)  REFERENCES `intakes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  CONSTRAINT `fk_app_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_documents`;
CREATE TABLE `application_documents` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` BIGINT UNSIGNED NOT NULL,
  `document_type`  VARCHAR(80)     NOT NULL,
  `file_name`      VARCHAR(255)    NOT NULL,
  `file_path`      VARCHAR(255)    NOT NULL,
  `file_size`      INT UNSIGNED             DEFAULT NULL,
  `uploaded_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_appdoc_app` (`application_id`),
  CONSTRAINT `fk_appdoc_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 5 : TEACHING, REGISTRATION & TIMETABLE
-- =====================================================================

DROP TABLE IF EXISTS `course_offerings`;
CREATE TABLE `course_offerings` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`      INT UNSIGNED    NOT NULL,
  `semester_id`    INT UNSIGNED    NOT NULL,
  `program_id`     INT UNSIGNED             DEFAULT NULL,
  `section`        VARCHAR(10)     NOT NULL DEFAULT 'A',
  `lecturer_id`    BIGINT UNSIGNED          DEFAULT NULL,
  `room_id`        INT UNSIGNED             DEFAULT NULL,
  `capacity`       SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `enrolled_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_mode`  ENUM('physical','online','hybrid') NOT NULL DEFAULT 'physical',
  `coursework_weight` TINYINT UNSIGNED NOT NULL DEFAULT 30,
  `exam_weight`       TINYINT UNSIGNED NOT NULL DEFAULT 70,
  `status`         ENUM('open','closed','cancelled','completed') NOT NULL DEFAULT 'open',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_offering` (`course_id`,`semester_id`,`section`),
  KEY `ix_offering_sem` (`semester_id`),
  KEY `ix_offering_lecturer` (`lecturer_id`),
  CONSTRAINT `fk_off_course`   FOREIGN KEY (`course_id`)   REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_off_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_off_program`  FOREIGN KEY (`program_id`)  REFERENCES `programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_off_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_off_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `offering_lecturers`;
CREATE TABLE `offering_lecturers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id` BIGINT UNSIGNED NOT NULL,
  `staff_id`    BIGINT UNSIGNED NOT NULL,
  `role`        ENUM('main','assistant','tutor','lab_technician') NOT NULL DEFAULT 'main',
  `assigned_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_off_lect` (`offering_id`,`staff_id`),
  CONSTRAINT `fk_ol_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ol_staff`    FOREIGN KEY (`staff_id`)    REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_registrations`;
CREATE TABLE `course_registrations` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      BIGINT UNSIGNED NOT NULL,
  `offering_id`     BIGINT UNSIGNED NOT NULL,
  `semester_id`     INT UNSIGNED    NOT NULL,
  `registration_type` ENUM('normal','retake','audit','supplementary') NOT NULL DEFAULT 'normal',
  `approval_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`     BIGINT UNSIGNED          DEFAULT NULL,
  `approved_at`     DATETIME                 DEFAULT NULL,
  `status`          ENUM('registered','dropped','completed') NOT NULL DEFAULT 'registered',
  `remarks`         VARCHAR(255)             DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registration` (`student_id`,`offering_id`),
  KEY `ix_reg_semester` (`semester_id`),
  KEY `ix_reg_offering` (`offering_id`),
  CONSTRAINT `fk_reg_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reg_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reg_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `timetable_slots`;
CREATE TABLE `timetable_slots` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id` BIGINT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED    NOT NULL,
  `day_of_week` ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
  `start_time`  TIME            NOT NULL,
  `end_time`    TIME            NOT NULL,
  `room_id`     INT UNSIGNED             DEFAULT NULL,
  `session_type` ENUM('lecture','tutorial','practical','seminar') NOT NULL DEFAULT 'lecture',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_tt_offering` (`offering_id`),
  KEY `ix_tt_room_slot` (`room_id`,`day_of_week`,`start_time`),
  CONSTRAINT `fk_tt_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tt_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance_sessions`;
CREATE TABLE `attendance_sessions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`  BIGINT UNSIGNED NOT NULL,
  `session_date` DATE            NOT NULL,
  `start_time`   TIME                     DEFAULT NULL,
  `end_time`     TIME                     DEFAULT NULL,
  `topic`        VARCHAR(255)             DEFAULT NULL,
  `session_type` ENUM('lecture','tutorial','practical','seminar') NOT NULL DEFAULT 'lecture',
  `taken_by`     BIGINT UNSIGNED          DEFAULT NULL,
  `status`       ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_session` (`offering_id`,`session_date`,`start_time`),
  CONSTRAINT `fk_atts_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `status`     ENUM('present','absent','late','excused') NOT NULL DEFAULT 'absent',
  `remarks`    VARCHAR(255)             DEFAULT NULL,
  `marked_by`  BIGINT UNSIGNED          DEFAULT NULL,
  `marked_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_record` (`session_id`,`student_id`),
  KEY `ix_attr_student` (`student_id`),
  CONSTRAINT `fk_attr_session` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 6 : ASSESSMENT, EXAMINATIONS & RESULTS
-- =====================================================================

DROP TABLE IF EXISTS `grade_scales`;
CREATE TABLE `grade_scales` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade`       VARCHAR(5)   NOT NULL,
  `min_score`   DECIMAL(5,2) NOT NULL,
  `max_score`   DECIMAL(5,2) NOT NULL,
  `grade_point` DECIMAL(4,2) NOT NULL,
  `remarks`     VARCHAR(60)           DEFAULT NULL,
  `is_pass`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assessments`;
CREATE TABLE `assessments` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`  BIGINT UNSIGNED NOT NULL,
  `title`        VARCHAR(150)    NOT NULL,
  `type`         ENUM('cat','assignment','quiz','practical','project','presentation','final_exam') NOT NULL DEFAULT 'cat',
  `max_score`    DECIMAL(6,2)    NOT NULL DEFAULT 100.00,
  `weight`       DECIMAL(5,2)    NOT NULL DEFAULT 10.00 COMMENT 'percentage contribution',
  `due_date`     DATETIME                 DEFAULT NULL,
  `instructions` TEXT                     DEFAULT NULL,
  `attachment`   VARCHAR(255)             DEFAULT NULL,
  `is_published` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_assess_offering` (`offering_id`),
  CONSTRAINT `fk_assess_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assessment_scores`;
CREATE TABLE `assessment_scores` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` BIGINT UNSIGNED NOT NULL,
  `student_id`    BIGINT UNSIGNED NOT NULL,
  `score`         DECIMAL(6,2)             DEFAULT NULL,
  `submitted_at`  DATETIME                 DEFAULT NULL,
  `graded_by`     BIGINT UNSIGNED          DEFAULT NULL,
  `graded_at`     DATETIME                 DEFAULT NULL,
  `feedback`      TEXT                     DEFAULT NULL,
  `status`        ENUM('pending','submitted','graded','missed') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assess_score` (`assessment_id`,`student_id`),
  KEY `ix_as_student` (`student_id`),
  CONSTRAINT `fk_as_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_student`    FOREIGN KEY (`student_id`)    REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offering_id`   BIGINT UNSIGNED NOT NULL,
  `semester_id`   INT UNSIGNED    NOT NULL,
  `exam_type`     ENUM('main','supplementary','special','retake') NOT NULL DEFAULT 'main',
  `exam_date`     DATE            NOT NULL,
  `start_time`    TIME            NOT NULL,
  `duration_mins` SMALLINT UNSIGNED NOT NULL DEFAULT 120,
  `room_id`       INT UNSIGNED             DEFAULT NULL,
  `invigilator_id` BIGINT UNSIGNED         DEFAULT NULL,
  `max_score`     DECIMAL(6,2)    NOT NULL DEFAULT 100.00,
  `instructions`  TEXT                     DEFAULT NULL,
  `status`        ENUM('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_exam_offering` (`offering_id`),
  KEY `ix_exam_date` (`exam_date`),
  CONSTRAINT `fk_exam_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `course_results`;
CREATE TABLE `course_results` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `offering_id`      BIGINT UNSIGNED NOT NULL,
  `semester_id`      INT UNSIGNED    NOT NULL,
  `course_id`        INT UNSIGNED    NOT NULL,
  `coursework_score` DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
  `exam_score`       DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
  `total_score`      DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
  `grade`            VARCHAR(5)               DEFAULT NULL,
  `grade_point`      DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
  `credit_hours`     TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `quality_points`   DECIMAL(7,2)    NOT NULL DEFAULT 0.00,
  `attempt`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `outcome`          ENUM('pass','fail','incomplete','retake','deferred','audit') NOT NULL DEFAULT 'incomplete',
  `is_published`     TINYINT(1)      NOT NULL DEFAULT 0,
  `published_at`     DATETIME                 DEFAULT NULL,
  `entered_by`       BIGINT UNSIGNED          DEFAULT NULL,
  `approved_by`      BIGINT UNSIGNED          DEFAULT NULL,
  `remarks`          VARCHAR(255)             DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_result` (`student_id`,`offering_id`,`attempt`),
  KEY `ix_cr_semester` (`semester_id`),
  KEY `ix_cr_course` (`course_id`),
  CONSTRAINT `fk_cr_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_course`   FOREIGN KEY (`course_id`)   REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `semester_results`;
CREATE TABLE `semester_results` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`         BIGINT UNSIGNED NOT NULL,
  `semester_id`        INT UNSIGNED    NOT NULL,
  `year_of_study`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `credits_registered` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `credits_earned`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `quality_points`     DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  `gpa`                DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
  `cgpa`               DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
  `classification`     VARCHAR(60)              DEFAULT NULL,
  `decision`           ENUM('proceed','repeat','supplementary','discontinue','graduate','pending') NOT NULL DEFAULT 'pending',
  `remarks`            VARCHAR(255)             DEFAULT NULL,
  `generated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sem_result` (`student_id`,`semester_id`),
  CONSTRAINT `fk_sr_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `graduations`;
CREATE TABLE `graduations` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED    NOT NULL,
  `graduation_date`  DATE                     DEFAULT NULL,
  `final_cgpa`       DECIMAL(4,2)    NOT NULL DEFAULT 0.00,
  `classification`   VARCHAR(80)              DEFAULT NULL,
  `certificate_no`   VARCHAR(60)              DEFAULT NULL,
  `status`           ENUM('pending','cleared','approved','graduated','deferred') NOT NULL DEFAULT 'pending',
  `remarks`          VARCHAR(255)             DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grad_student` (`student_id`,`academic_year_id`),
  CONSTRAINT `fk_grad_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grad_ay` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 7 : FINANCE  (fees, invoices, payments, scholarships, payroll)
-- =====================================================================

DROP TABLE IF EXISTS `fee_types`;
CREATE TABLE `fee_types` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `category`    ENUM('tuition','accommodation','examination','library','activity','medical','registration','graduation','fine','other') NOT NULL DEFAULT 'other',
  `is_recurring` TINYINT(1)  NOT NULL DEFAULT 1,
  `is_mandatory` TINYINT(1)  NOT NULL DEFAULT 1,
  `description` VARCHAR(255)          DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_feetype_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `program_id`       INT UNSIGNED NOT NULL,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `year_of_study`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `semester_number`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `study_mode`       ENUM('full_time','part_time','evening','distance') NOT NULL DEFAULT 'full_time',
  `name`             VARCHAR(150) NOT NULL,
  `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`           ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_structure` (`program_id`,`academic_year_id`,`year_of_study`,`semester_number`,`study_mode`),
  CONSTRAINT `fk_fs_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fs_ay`      FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fee_structure_items`;
CREATE TABLE `fee_structure_items` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_structure_id` INT UNSIGNED NOT NULL,
  `fee_type_id`      INT UNSIGNED NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_mandatory`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fsi` (`fee_structure_id`,`fee_type_id`),
  CONSTRAINT `fk_fsi_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fsi_type`      FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number`   VARCHAR(40)     NOT NULL,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `semester_id`      INT UNSIGNED             DEFAULT NULL,
  `fee_structure_id` INT UNSIGNED             DEFAULT NULL,
  `title`            VARCHAR(180)             DEFAULT NULL,
  `total_amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `discount_amount`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `amount_paid`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `balance`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `issue_date`       DATE            NOT NULL,
  `due_date`         DATE                     DEFAULT NULL,
  `status`           ENUM('draft','unpaid','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  `issued_by`        BIGINT UNSIGNED          DEFAULT NULL,
  `notes`            VARCHAR(255)             DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  KEY `ix_inv_student` (`student_id`,`status`),
  KEY `ix_inv_semester` (`semester_id`),
  CONSTRAINT `fk_inv_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`  BIGINT UNSIGNED NOT NULL,
  `fee_type_id` INT UNSIGNED             DEFAULT NULL,
  `description` VARCHAR(200)    NOT NULL,
  `quantity`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_amount` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ix_ii_invoice` (`invoice_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ii_feetype` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_number` VARCHAR(40)     NOT NULL,
  `student_id`     BIGINT UNSIGNED NOT NULL,
  `invoice_id`     BIGINT UNSIGNED          DEFAULT NULL,
  `amount`         DECIMAL(12,2)   NOT NULL,
  `method`         ENUM('cash','bank_transfer','mpesa','card','cheque','bursary','scholarship','waiver') NOT NULL DEFAULT 'cash',
  `reference`      VARCHAR(80)              DEFAULT NULL,
  `bank_name`      VARCHAR(120)             DEFAULT NULL,
  `paid_at`        DATETIME        NOT NULL,
  `received_by`    BIGINT UNSIGNED          DEFAULT NULL,
  `status`         ENUM('pending','confirmed','reversed','failed') NOT NULL DEFAULT 'confirmed',
  `notes`          VARCHAR(255)             DEFAULT NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt` (`receipt_number`),
  KEY `ix_pay_student` (`student_id`),
  KEY `ix_pay_invoice` (`invoice_id`),
  KEY `ix_pay_date` (`paid_at`),
  CONSTRAINT `fk_pay_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scholarships`;
CREATE TABLE `scholarships` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `sponsor`     VARCHAR(150)          DEFAULT NULL,
  `award_type`  ENUM('full','partial','fixed_amount') NOT NULL DEFAULT 'partial',
  `percentage`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `slots`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `criteria`    TEXT                  DEFAULT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `student_scholarships`;
CREATE TABLE `student_scholarships` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       BIGINT UNSIGNED NOT NULL,
  `scholarship_id`   INT UNSIGNED    NOT NULL,
  `academic_year_id` INT UNSIGNED    NOT NULL,
  `amount`           DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `awarded_at`       DATE                     DEFAULT NULL,
  `status`           ENUM('applied','approved','active','revoked','completed') NOT NULL DEFAULT 'applied',
  `remarks`          VARCHAR(255)             DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_scholarship` (`student_id`,`scholarship_id`,`academic_year_id`),
  CONSTRAINT `fk_ss_student`     FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_scholarship` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_ay`          FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_number` VARCHAR(40)    NOT NULL,
  `category`      VARCHAR(80)     NOT NULL,
  `department_id` INT UNSIGNED             DEFAULT NULL,
  `description`   VARCHAR(255)    NOT NULL,
  `amount`        DECIMAL(12,2)   NOT NULL,
  `payee`         VARCHAR(150)             DEFAULT NULL,
  `expense_date`  DATE            NOT NULL,
  `payment_method` ENUM('cash','bank_transfer','cheque','mpesa','card') NOT NULL DEFAULT 'bank_transfer',
  `reference`     VARCHAR(80)              DEFAULT NULL,
  `approved_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `status`        ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  `created_by`    BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voucher` (`voucher_number`),
  KEY `ix_exp_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payroll_periods`;
CREATE TABLE `payroll_periods` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_name` VARCHAR(40)  NOT NULL,
  `month`       TINYINT UNSIGNED NOT NULL,
  `year`        SMALLINT UNSIGNED NOT NULL,
  `pay_date`    DATE                  DEFAULT NULL,
  `status`      ENUM('draft','processing','approved','paid','closed') NOT NULL DEFAULT 'draft',
  `processed_by` BIGINT UNSIGNED      DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period` (`month`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payslips`;
CREATE TABLE `payslips` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_period_id` INT UNSIGNED   NOT NULL,
  `staff_id`         BIGINT UNSIGNED NOT NULL,
  `basic_salary`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `house_allowance`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `transport_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `other_allowances` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `gross_pay`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `paye`             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `nssf`             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `nhif`             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `other_deductions` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `net_pay`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `status`           ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payslip` (`payroll_period_id`,`staff_id`),
  CONSTRAINT `fk_ps_period` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_staff`  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 8 : LIBRARY
-- =====================================================================

DROP TABLE IF EXISTS `book_categories`;
CREATE TABLE `book_categories` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20)  NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookcat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `books`;
CREATE TABLE `books` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accession_number` VARCHAR(40)     NOT NULL,
  `isbn`             VARCHAR(30)              DEFAULT NULL,
  `title`            VARCHAR(255)    NOT NULL,
  `author`           VARCHAR(200)             DEFAULT NULL,
  `publisher`        VARCHAR(150)             DEFAULT NULL,
  `edition`          VARCHAR(40)              DEFAULT NULL,
  `publication_year` SMALLINT UNSIGNED        DEFAULT NULL,
  `category_id`      INT UNSIGNED             DEFAULT NULL,
  `shelf_location`   VARCHAR(60)              DEFAULT NULL,
  `total_copies`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `available_copies` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `price`            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `cover_image`      VARCHAR(255)             DEFAULT NULL,
  `description`      TEXT                     DEFAULT NULL,
  `status`           ENUM('available','archived','lost') NOT NULL DEFAULT 'available',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accession` (`accession_number`),
  KEY `ix_book_title` (`title`),
  KEY `ix_book_category` (`category_id`),
  CONSTRAINT `fk_book_category` FOREIGN KEY (`category_id`) REFERENCES `book_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `book_loans`;
CREATE TABLE `book_loans` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `issued_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `issued_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date`    DATE            NOT NULL,
  `returned_at` DATETIME                 DEFAULT NULL,
  `received_by` BIGINT UNSIGNED          DEFAULT NULL,
  `renewals`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `fine_amount` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `fine_paid`   TINYINT(1)      NOT NULL DEFAULT 0,
  `status`      ENUM('borrowed','returned','overdue','lost','damaged') NOT NULL DEFAULT 'borrowed',
  `remarks`     VARCHAR(255)             DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_loan_user` (`user_id`,`status`),
  KEY `ix_loan_book` (`book_id`),
  CONSTRAINT `fk_loan_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 9 : HOSTEL / ACCOMMODATION
-- =====================================================================

DROP TABLE IF EXISTS `hostels`;
CREATE TABLE `hostels` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campus_id`   INT UNSIGNED          DEFAULT NULL,
  `code`        VARCHAR(20)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `gender`      ENUM('male','female','mixed') NOT NULL DEFAULT 'mixed',
  `warden_id`   BIGINT UNSIGNED       DEFAULT NULL,
  `total_rooms` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `location`    VARCHAR(150)          DEFAULT NULL,
  `status`      ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hostel_code` (`code`),
  CONSTRAINT `fk_hostel_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hostel_rooms`;
CREATE TABLE `hostel_rooms` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hostel_id`   INT UNSIGNED NOT NULL,
  `room_number` VARCHAR(20)  NOT NULL,
  `floor`       TINYINT      NOT NULL DEFAULT 0,
  `room_type`   ENUM('single','double','triple','dormitory') NOT NULL DEFAULT 'double',
  `capacity`    TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `occupied`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `fee_per_semester` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`      ENUM('available','full','maintenance','reserved') NOT NULL DEFAULT 'available',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hostel_room` (`hostel_id`,`room_number`),
  CONSTRAINT `fk_hroom_hostel` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hostel_allocations`;
CREATE TABLE `hostel_allocations` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`     BIGINT UNSIGNED NOT NULL,
  `hostel_room_id` INT UNSIGNED    NOT NULL,
  `semester_id`    INT UNSIGNED    NOT NULL,
  `bed_number`     VARCHAR(10)              DEFAULT NULL,
  `allocated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vacated_at`     DATETIME                 DEFAULT NULL,
  `allocated_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `status`         ENUM('requested','allocated','checked_in','checked_out','cancelled') NOT NULL DEFAULT 'allocated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hostel_alloc` (`student_id`,`semester_id`),
  KEY `ix_ha_room` (`hostel_room_id`),
  CONSTRAINT `fk_ha_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ha_room`    FOREIGN KEY (`hostel_room_id`) REFERENCES `hostel_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ha_sem`     FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 10 : HUMAN RESOURCES (leave, appraisal)
-- =====================================================================

DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE `leave_types` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(80)  NOT NULL,
  `days_allowed` SMALLINT UNSIGNED NOT NULL DEFAULT 21,
  `is_paid`      TINYINT(1)   NOT NULL DEFAULT 1,
  `description`  VARCHAR(255)          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leavetype_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id`      BIGINT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED    NOT NULL,
  `start_date`    DATE            NOT NULL,
  `end_date`      DATE            NOT NULL,
  `days`          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `reason`        TEXT                     DEFAULT NULL,
  `handover_to`   BIGINT UNSIGNED          DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `approved_at`   DATETIME                 DEFAULT NULL,
  `remarks`       VARCHAR(255)             DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_leave_staff` (`staff_id`,`status`),
  CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_type`  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SECTION 11 : COMMUNICATION, DOCUMENTS & SUPPORT
-- =====================================================================

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(200)    NOT NULL,
  `body`         TEXT            NOT NULL,
  `audience`     ENUM('all','students','staff','lecturers','faculty','department','program','year') NOT NULL DEFAULT 'all',
  `target_id`    INT UNSIGNED             DEFAULT NULL,
  `priority`     ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `attachment`   VARCHAR(255)             DEFAULT NULL,
  `published_at` DATETIME                 DEFAULT NULL,
  `expires_at`   DATETIME                 DEFAULT NULL,
  `created_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `status`       ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `views`        INT UNSIGNED    NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ann_audience` (`audience`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id`    BIGINT UNSIGNED NOT NULL,
  `recipient_id` BIGINT UNSIGNED NOT NULL,
  `parent_id`    BIGINT UNSIGNED          DEFAULT NULL,
  `subject`      VARCHAR(200)             DEFAULT NULL,
  `body`         TEXT            NOT NULL,
  `attachment`   VARCHAR(255)             DEFAULT NULL,
  `read_at`      DATETIME                 DEFAULT NULL,
  `deleted_by_sender`    TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_by_recipient` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_msg_recipient` (`recipient_id`,`read_at`),
  KEY `ix_msg_sender` (`sender_id`),
  CONSTRAINT `fk_msg_sender`    FOREIGN KEY (`sender_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200)    NOT NULL,
  `description` TEXT                     DEFAULT NULL,
  `event_type`  ENUM('academic','sports','cultural','graduation','orientation','meeting','holiday','other') NOT NULL DEFAULT 'academic',
  `start_datetime` DATETIME     NOT NULL,
  `end_datetime`   DATETIME              DEFAULT NULL,
  `venue`       VARCHAR(180)             DEFAULT NULL,
  `organizer`   VARCHAR(180)             DEFAULT NULL,
  `is_public`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_by`  BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_event_start` (`start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200)    NOT NULL,
  `category`    VARCHAR(80)     NOT NULL DEFAULT 'general',
  `file_name`   VARCHAR(255)    NOT NULL,
  `file_path`   VARCHAR(255)    NOT NULL,
  `file_size`   INT UNSIGNED             DEFAULT NULL,
  `mime_type`   VARCHAR(120)             DEFAULT NULL,
  `owner_type`  VARCHAR(60)              DEFAULT NULL,
  `owner_id`    BIGINT UNSIGNED          DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED          DEFAULT NULL,
  `is_public`   TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_doc_owner` (`owner_type`,`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clearances`;
CREATE TABLE `clearances` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  BIGINT UNSIGNED NOT NULL,
  `clearance_type` ENUM('semester','graduation','withdrawal','transfer') NOT NULL DEFAULT 'graduation',
  `unit`        ENUM('finance','library','hostel','department','sports','laboratory','registrar') NOT NULL,
  `status`      ENUM('pending','cleared','blocked') NOT NULL DEFAULT 'pending',
  `cleared_by`  BIGINT UNSIGNED          DEFAULT NULL,
  `cleared_at`  DATETIME                 DEFAULT NULL,
  `remarks`     VARCHAR(255)             DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clearance` (`student_id`,`clearance_type`,`unit`),
  CONSTRAINT `fk_clr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(30)     NOT NULL,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `category`      ENUM('academic','finance','ict','hostel','library','general','complaint') NOT NULL DEFAULT 'general',
  `subject`       VARCHAR(200)    NOT NULL,
  `body`          TEXT            NOT NULL,
  `priority`      ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status`        ENUM('open','in_progress','resolved','closed','reopened') NOT NULL DEFAULT 'open',
  `assigned_to`   BIGINT UNSIGNED          DEFAULT NULL,
  `resolved_at`   DATETIME                 DEFAULT NULL,
  `resolution`    TEXT                     DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_number` (`ticket_number`),
  KEY `ix_ticket_user` (`user_id`,`status`),
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_replies`;
CREATE TABLE `ticket_replies` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`  BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `body`       TEXT            NOT NULL,
  `is_internal` TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_treply_ticket` (`ticket_id`),
  CONSTRAINT `fk_treply_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `disciplinary_cases`;
CREATE TABLE `disciplinary_cases` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_number` VARCHAR(30)     NOT NULL,
  `student_id`  BIGINT UNSIGNED NOT NULL,
  `offence`     VARCHAR(200)    NOT NULL,
  `description` TEXT                     DEFAULT NULL,
  `incident_date` DATE                   DEFAULT NULL,
  `reported_by` BIGINT UNSIGNED          DEFAULT NULL,
  `hearing_date` DATE                    DEFAULT NULL,
  `verdict`     TEXT                     DEFAULT NULL,
  `penalty`     ENUM('none','warning','fine','suspension','expulsion','community_service','probation') NOT NULL DEFAULT 'none',
  `fine_amount` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `status`      ENUM('reported','under_investigation','hearing','closed','appealed') NOT NULL DEFAULT 'reported',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_case_number` (`case_number`),
  KEY `ix_disc_student` (`student_id`),
  CONSTRAINT `fk_disc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  BIGINT UNSIGNED NOT NULL,
  `visit_date`  DATETIME        NOT NULL,
  `complaint`   VARCHAR(255)             DEFAULT NULL,
  `diagnosis`   VARCHAR(255)             DEFAULT NULL,
  `treatment`   TEXT                     DEFAULT NULL,
  `attended_by` VARCHAR(150)             DEFAULT NULL,
  `is_confidential` TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_med_student` (`student_id`),
  CONSTRAINT `fk_med_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
