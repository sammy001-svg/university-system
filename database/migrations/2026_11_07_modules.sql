-- Graduation & alumni, student requests, advising, course evaluations, research,
-- assets & stock, staff appraisals, recruitment, clubs & societies and budgets.
-- Applied automatically by App\Services\AcademicCatalogSync::upgradeSchema() on the first
-- request after deployment; every statement is safe to run again. (New columns on
-- existing tables - students.advisor_id and the graduation application fields - are
-- added there too, as MySQL has no ADD COLUMN IF NOT EXISTS.)

-- ---------------------------------------------------------------- alumni
CREATE TABLE IF NOT EXISTS `alumni_profiles` (
  `student_id`        BIGINT UNSIGNED NOT NULL,
  `employer`          VARCHAR(150)             DEFAULT NULL,
  `job_title`         VARCHAR(150)             DEFAULT NULL,
  `industry`          VARCHAR(100)             DEFAULT NULL,
  `city`              VARCHAR(100)             DEFAULT NULL,
  `country`           VARCHAR(100)             DEFAULT NULL,
  `email`             VARCHAR(150)             DEFAULT NULL,
  `phone`             VARCHAR(30)              DEFAULT NULL,
  `linkedin_url`      VARCHAR(255)             DEFAULT NULL,
  `further_study`     VARCHAR(200)             DEFAULT NULL,
  `in_directory`      TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'agrees to appear in the alumni directory',
  `updated_at`        DATETIME        NOT NULL,
  PRIMARY KEY (`student_id`),
  CONSTRAINT `fk_alumni_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- student requests
CREATE TABLE IF NOT EXISTS `student_requests` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`         VARCHAR(20)     NOT NULL,
  `student_id`        BIGINT UNSIGNED NOT NULL,
  `request_type`      ENUM('leave_of_absence','deferment','withdrawal','programme_change','late_registration','grade_appeal','credit_transfer','name_change','readmission','other') NOT NULL,
  `details`           TEXT            NOT NULL,
  `effective_date`    DATE                     DEFAULT NULL,
  `offering_id`       BIGINT UNSIGNED          DEFAULT NULL COMMENT 'the course, for a grade appeal or late registration',
  `target_program_id` INT UNSIGNED             DEFAULT NULL COMMENT 'for a change of programme',
  `attachment_path`   VARCHAR(255)             DEFAULT NULL,
  `attachment_name`   VARCHAR(255)             DEFAULT NULL,
  `status`            ENUM('submitted','under_review','approved','rejected','cancelled') NOT NULL DEFAULT 'submitted',
  `reviewed_by`       BIGINT UNSIGNED          DEFAULT NULL,
  `decision_note`     TEXT                     DEFAULT NULL,
  `decided_at`        DATETIME                 DEFAULT NULL,
  `created_at`        DATETIME        NOT NULL,
  `updated_at`        DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sr_reference` (`reference`),
  KEY `ix_sr_student` (`student_id`),
  KEY `ix_sr_status` (`status`, `request_type`),
  CONSTRAINT `fk_req_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- academic advising
CREATE TABLE IF NOT EXISTS `advising_notes` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`         BIGINT UNSIGNED NOT NULL,
  `staff_id`           BIGINT UNSIGNED          DEFAULT NULL,
  `meeting_date`       DATE            NOT NULL,
  `note`               TEXT            NOT NULL,
  `follow_up_on`       DATE                     DEFAULT NULL,
  `visible_to_student` TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`         DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_an_student` (`student_id`, `meeting_date`),
  CONSTRAINT `fk_an_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- course & lecturer evaluation
CREATE TABLE IF NOT EXISTS `evaluation_periods` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semester_id`   INT UNSIGNED NOT NULL,
  `title`         VARCHAR(150) NOT NULL,
  `opens_at`      DATETIME     NOT NULL,
  `closes_at`     DATETIME     NOT NULL,
  `min_responses` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'results are hidden below this many responses, to protect anonymity',
  `status`        ENUM('draft','open','closed') NOT NULL DEFAULT 'open',
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ep_semester` (`semester_id`),
  CONSTRAINT `fk_ep_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluation_questions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section`    VARCHAR(60)  NOT NULL,
  `body`       VARCHAR(255) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eq_body` (`body`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who has evaluated which course (to allow one evaluation each). Kept apart from the
-- answers, which carry no student id, so responses stay anonymous.
CREATE TABLE IF NOT EXISTS `evaluation_submissions` (
  `period_id`    INT UNSIGNED    NOT NULL,
  `offering_id`  BIGINT UNSIGNED NOT NULL,
  `student_id`   BIGINT UNSIGNED NOT NULL,
  `submitted_on` DATE            NOT NULL,
  PRIMARY KEY (`period_id`, `offering_id`, `student_id`),
  CONSTRAINT `fk_es_period` FOREIGN KEY (`period_id`) REFERENCES `evaluation_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluation_responses` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_id`   INT UNSIGNED    NOT NULL,
  `offering_id` BIGINT UNSIGNED NOT NULL,
  `comments`    TEXT                     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_er_offering` (`period_id`, `offering_id`),
  CONSTRAINT `fk_er_period` FOREIGN KEY (`period_id`) REFERENCES `evaluation_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluation_answers` (
  `response_id` BIGINT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED    NOT NULL,
  `rating`      TINYINT UNSIGNED NOT NULL COMMENT '1 = strongly disagree ... 5 = strongly agree',
  PRIMARY KEY (`response_id`, `question_id`),
  CONSTRAINT `fk_ea_response` FOREIGN KEY (`response_id`) REFERENCES `evaluation_responses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `evaluation_questions` (`section`, `body`, `sort_order`) VALUES
  ('The course', 'The course objectives and requirements were clearly explained at the start.', 1),
  ('The course', 'The course content was well organised and followed the outline.', 2),
  ('The course', 'The course materials and readings helped my learning.', 3),
  ('The course', 'The workload was appropriate for the credit hours.', 4),
  ('Teaching', 'The lecturer was well prepared for classes.', 5),
  ('Teaching', 'The lecturer explained concepts clearly.', 6),
  ('Teaching', 'The lecturer encouraged questions and participation.', 7),
  ('Teaching', 'The lecturer was punctual and used class time well.', 8),
  ('Teaching', 'The lecturer was available for help outside class.', 9),
  ('Assessment', 'Assessments matched what was taught.', 10),
  ('Assessment', 'Marked work was returned promptly with useful feedback.', 11),
  ('Overall', 'Overall, I would rate this course highly.', 12);

-- ---------------------------------------------------------------- research
CREATE TABLE IF NOT EXISTS `research_outputs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id`      BIGINT UNSIGNED NOT NULL,
  `output_type`   ENUM('journal_article','conference_paper','book','book_chapter','report','thesis_supervision','patent','grant','project','other') NOT NULL,
  `title`         VARCHAR(300)    NOT NULL,
  `authors`       VARCHAR(500)             DEFAULT NULL,
  `venue`         VARCHAR(255)             DEFAULT NULL COMMENT 'journal, conference, publisher or funder',
  `year`          SMALLINT UNSIGNED NOT NULL,
  `url`           VARCHAR(500)             DEFAULT NULL COMMENT 'DOI or link',
  `amount`        DECIMAL(14,2)            DEFAULT NULL COMMENT 'grant value',
  `status`        ENUM('published','in_press','submitted','ongoing','completed','awarded') NOT NULL DEFAULT 'published',
  `peer_reviewed` TINYINT(1)      NOT NULL DEFAULT 0,
  `abstract`      TEXT                     DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ro_staff` (`staff_id`, `year`),
  CONSTRAINT `fk_ro_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- assets & stock
CREATE TABLE IF NOT EXISTS `assets` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag`             VARCHAR(40)     NOT NULL,
  `name`            VARCHAR(150)    NOT NULL,
  `category`        ENUM('computer','printer','projector','furniture','laboratory','vehicle','network','electrical','books','other') NOT NULL DEFAULT 'other',
  `serial_number`   VARCHAR(100)             DEFAULT NULL,
  `room_id`         INT UNSIGNED             DEFAULT NULL,
  `department_id`   INT UNSIGNED             DEFAULT NULL,
  `custodian_id`    BIGINT UNSIGNED          DEFAULT NULL COMMENT 'staff member responsible',
  `purchase_date`   DATE                     DEFAULT NULL,
  `purchase_cost`   DECIMAL(12,2)            DEFAULT NULL,
  `supplier`        VARCHAR(150)             DEFAULT NULL,
  `warranty_until`  DATE                     DEFAULT NULL,
  `asset_condition` ENUM('new','good','fair','poor','broken') NOT NULL DEFAULT 'good',
  `status`          ENUM('in_use','in_store','under_repair','disposed','lost') NOT NULL DEFAULT 'in_use',
  `notes`           TEXT                     DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_tag` (`tag`),
  KEY `ix_asset_status` (`status`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asset_maintenance` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id`     BIGINT UNSIGNED NOT NULL,
  `performed_on` DATE            NOT NULL,
  `description`  VARCHAR(255)    NOT NULL,
  `cost`         DECIMAL(12,2)            DEFAULT NULL,
  `vendor`       VARCHAR(150)             DEFAULT NULL,
  `created_by`   BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_am_asset` (`asset_id`),
  CONSTRAINT `fk_am_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(40)  NOT NULL,
  `name`          VARCHAR(150) NOT NULL,
  `category`      VARCHAR(80)  NOT NULL DEFAULT 'Stationery',
  `unit`          VARCHAR(30)  NOT NULL DEFAULT 'piece',
  `quantity`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `reorder_level` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `unit_cost`     DECIMAL(12,2)          DEFAULT NULL,
  `location`      VARCHAR(120)           DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id`       INT UNSIGNED    NOT NULL,
  `direction`     ENUM('in','out','adjust') NOT NULL,
  `quantity`      DECIMAL(12,2)   NOT NULL,
  `balance_after` DECIMAL(12,2)   NOT NULL,
  `department_id` INT UNSIGNED             DEFAULT NULL COMMENT 'issued to',
  `reference`     VARCHAR(80)              DEFAULT NULL,
  `note`          VARCHAR(255)             DEFAULT NULL,
  `created_by`    BIGINT UNSIGNED          DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sm_item` (`item_id`, `created_at`),
  CONSTRAINT `fk_sm_item` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- staff appraisal
CREATE TABLE IF NOT EXISTS `appraisal_cycles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(150) NOT NULL,
  `period_start` DATE         NOT NULL,
  `period_end`   DATE         NOT NULL,
  `due_date`     DATE                  DEFAULT NULL,
  `status`       ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisals` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cycle_id`           INT UNSIGNED    NOT NULL,
  `staff_id`           BIGINT UNSIGNED NOT NULL,
  `reviewer_id`        BIGINT UNSIGNED          DEFAULT NULL COMMENT 'staff id of the appraiser',
  `achievements`       TEXT                     DEFAULT NULL,
  `challenges`         TEXT                     DEFAULT NULL,
  `goals_next`         TEXT                     DEFAULT NULL,
  `training_needs`     TEXT                     DEFAULT NULL,
  `self_rating`        TINYINT UNSIGNED         DEFAULT NULL,
  `rating_quality`     TINYINT UNSIGNED         DEFAULT NULL,
  `rating_teaching`    TINYINT UNSIGNED         DEFAULT NULL COMMENT 'teaching or core duties',
  `rating_research`    TINYINT UNSIGNED         DEFAULT NULL,
  `rating_service`     TINYINT UNSIGNED         DEFAULT NULL,
  `rating_conduct`     TINYINT UNSIGNED         DEFAULT NULL,
  `overall_rating`     DECIMAL(3,2)             DEFAULT NULL,
  `reviewer_comments`  TEXT                     DEFAULT NULL,
  `staff_comments`     TEXT                     DEFAULT NULL COMMENT 'the appraisee''s response to the review',
  `status`             ENUM('pending','self_submitted','reviewed','acknowledged') NOT NULL DEFAULT 'pending',
  `self_submitted_at`  DATETIME                 DEFAULT NULL,
  `reviewed_at`        DATETIME                 DEFAULT NULL,
  `acknowledged_at`    DATETIME                 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appraisal` (`cycle_id`, `staff_id`),
  KEY `ix_appr_reviewer` (`reviewer_id`),
  CONSTRAINT `fk_appr_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appr_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- recruitment
CREATE TABLE IF NOT EXISTS `job_vacancies` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(30)  NOT NULL,
  `title`           VARCHAR(150) NOT NULL,
  `department_id`   INT UNSIGNED          DEFAULT NULL,
  `staff_category`  ENUM('academic','administrative','support','technical') NOT NULL DEFAULT 'academic',
  `employment_type` ENUM('permanent','contract','part_time','temporary') NOT NULL DEFAULT 'permanent',
  `positions`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `description`     TEXT         NOT NULL,
  `requirements`    TEXT                  DEFAULT NULL,
  `closing_date`    DATE         NOT NULL,
  `status`          ENUM('draft','open','closed','filled') NOT NULL DEFAULT 'open',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vacancy_ref` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_applications` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vacancy_id`    INT UNSIGNED    NOT NULL,
  `full_name`     VARCHAR(150)    NOT NULL,
  `email`         VARCHAR(150)    NOT NULL,
  `phone`         VARCHAR(30)              DEFAULT NULL,
  `qualification` VARCHAR(150)             DEFAULT NULL,
  `cover_letter`  TEXT                     DEFAULT NULL,
  `cv_path`       VARCHAR(255)             DEFAULT NULL,
  `cv_name`       VARCHAR(255)             DEFAULT NULL,
  `status`        ENUM('received','shortlisted','interviewed','offered','hired','rejected') NOT NULL DEFAULT 'received',
  `notes`         TEXT                     DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ja_vacancy` (`vacancy_id`, `status`),
  CONSTRAINT `fk_ja_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- clubs & societies
CREATE TABLE IF NOT EXISTS `clubs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(150) NOT NULL,
  `category`         ENUM('academic','cultural','sports','religious','professional','community_service','media','other') NOT NULL DEFAULT 'academic',
  `description`      TEXT                  DEFAULT NULL,
  `patron_id`        BIGINT UNSIGNED       DEFAULT NULL COMMENT 'staff patron',
  `meeting_schedule` VARCHAR(150)          DEFAULT NULL,
  `open_to_join`     TINYINT(1)   NOT NULL DEFAULT 1,
  `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `club_members` (
  `club_id`    INT UNSIGNED    NOT NULL,
  `student_id` BIGINT UNSIGNED NOT NULL,
  `role`       ENUM('member','president','vice_president','secretary','treasurer','officer') NOT NULL DEFAULT 'member',
  `joined_on`  DATE            NOT NULL,
  PRIMARY KEY (`club_id`, `student_id`),
  KEY `ix_cm_student` (`student_id`),
  CONSTRAINT `fk_cm_club`    FOREIGN KEY (`club_id`)    REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- budgets
CREATE TABLE IF NOT EXISTS `budget_lines` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `department_id`    INT UNSIGNED          DEFAULT NULL COMMENT 'NULL = institution-wide',
  `category`         VARCHAR(80)  NOT NULL COMMENT 'matches the expense category',
  `amount`           DECIMAL(14,2) NOT NULL,
  `notes`            VARCHAR(255)          DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_budget_line` (`academic_year_id`, `department_id`, `category`),
  CONSTRAINT `fk_bl_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- permissions
INSERT IGNORE INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
  ('View graduation', 'graduation.view', 'graduation', 'View graduation applications, lists and alumni'),
  ('Manage graduation', 'graduation.manage', 'graduation', 'Clear and approve graduands, issue certificate numbers'),
  ('View requests', 'requests.view', 'requests', 'View student requests and petitions'),
  ('Review requests', 'requests.review', 'requests', 'Approve or reject student requests'),
  ('View advising', 'advising.view', 'advising', 'View advisees, advising notes and academic standing'),
  ('Manage advising', 'advising.manage', 'advising', 'Assign academic advisors'),
  ('View evaluations', 'evaluations.view', 'evaluations', 'View course and lecturer evaluation results'),
  ('Manage evaluations', 'evaluations.manage', 'evaluations', 'Open and close evaluation periods'),
  ('View research', 'research.view', 'research', 'View the institution''s research output'),
  ('Manage research', 'research.manage', 'research', 'Record research output for any staff member'),
  ('View assets', 'assets.view', 'assets', 'View the asset register and stock'),
  ('Manage assets', 'assets.manage', 'assets', 'Add and edit assets, record maintenance and stock movements'),
  ('View appraisals', 'appraisals.view', 'appraisals', 'View staff appraisal results'),
  ('Manage appraisals', 'appraisals.manage', 'appraisals', 'Run appraisal cycles and assign appraisers'),
  ('View recruitment', 'recruitment.view', 'recruitment', 'View vacancies and applicants'),
  ('Manage recruitment', 'recruitment.manage', 'recruitment', 'Post vacancies and move applicants through selection'),
  ('View clubs', 'clubs.view', 'clubs', 'View clubs and societies'),
  ('Manage clubs', 'clubs.manage', 'clubs', 'Register clubs, patrons and officers'),
  ('View budgets', 'budgets.view', 'budgets', 'View budgets against spending'),
  ('Manage budgets', 'budgets.manage', 'budgets', 'Set budget lines');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r JOIN permissions p ON
     (r.slug = 'vice-chancellor' AND p.slug IN ('graduation.view','requests.view','advising.view','evaluations.view','research.view','assets.view','appraisals.view','recruitment.view','clubs.view','budgets.view'))
  OR (r.slug = 'registrar' AND p.slug IN ('graduation.view','graduation.manage','requests.view','requests.review','advising.view','advising.manage','evaluations.view','evaluations.manage','clubs.view','clubs.manage'))
  OR (r.slug = 'dean' AND p.slug IN ('graduation.view','requests.view','requests.review','advising.view','evaluations.view','research.view','research.manage','appraisals.view','recruitment.view','clubs.view','budgets.view'))
  OR (r.slug = 'hod' AND p.slug IN ('requests.view','requests.review','advising.view','evaluations.view','research.view','recruitment.view','budgets.view','assets.view'))
  OR (r.slug = 'finance-officer' AND p.slug IN ('assets.view','assets.manage','budgets.view','budgets.manage','graduation.view'))
  OR (r.slug = 'hr-officer' AND p.slug IN ('appraisals.view','appraisals.manage','recruitment.view','recruitment.manage','research.view'))
  OR (r.slug = 'librarian' AND p.slug IN ('assets.view'));
