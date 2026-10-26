-- =====================================================================
--  Upgrade: fee structures by college / department / student status,
--  installment plans, and semester enrolment cleared by the 1st installment.
--  Apply once to databases installed before 2026-10-26:
--    mysql -u <user> -p <database> < database/migrations/2026_10_26_billing_installments.sql
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `fee_structures`
  DROP FOREIGN KEY `fk_fs_program`;
ALTER TABLE `fee_structures`
  DROP INDEX `uq_fee_structure`;
ALTER TABLE `fee_structures`
  ADD COLUMN `faculty_id`    INT UNSIGNED DEFAULT NULL AFTER `id`,
  ADD COLUMN `department_id` INT UNSIGNED DEFAULT NULL AFTER `faculty_id`,
  MODIFY `program_id`        INT UNSIGNED DEFAULT NULL,
  ADD COLUMN `student_status` ENUM('all','new','continuing') NOT NULL DEFAULT 'all' AFTER `academic_year_id`,
  MODIFY `year_of_study`     TINYINT UNSIGNED DEFAULT NULL,
  MODIFY `semester_number`   TINYINT UNSIGNED DEFAULT NULL,
  MODIFY `study_mode`        ENUM('full_time','part_time','evening','distance') DEFAULT NULL,
  ADD KEY `ix_fs_lookup` (`academic_year_id`,`status`),
  ADD CONSTRAINT `fk_fs_faculty`    FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fs_program`    FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `fee_structure_installments` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_structure_id`   INT UNSIGNED NOT NULL,
  `installment_number` TINYINT UNSIGNED NOT NULL,
  `label`              VARCHAR(60)  NOT NULL,
  `tuition_percent`    DECIMAL(5,2) NOT NULL,
  `due_days`           SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fs_installment` (`fee_structure_id`,`installment_number`),
  CONSTRAINT `fk_fsinst_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_installments` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`         BIGINT UNSIGNED NOT NULL,
  `installment_number` TINYINT UNSIGNED NOT NULL,
  `label`              VARCHAR(60)     NOT NULL,
  `amount`             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `amount_paid`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `due_date`           DATE                     DEFAULT NULL,
  `status`             ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_installment` (`invoice_id`,`installment_number`),
  CONSTRAINT `fk_invinst_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `semester_enrollments` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  BIGINT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED    NOT NULL,
  `invoice_id`  BIGINT UNSIGNED          DEFAULT NULL,
  `status`      ENUM('pending_payment','enrolled') NOT NULL DEFAULT 'pending_payment',
  `enrolled_at` DATETIME                 DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment` (`student_id`,`semester_id`),
  KEY `ix_enrollment_semester` (`semester_id`,`status`),
  CONSTRAINT `fk_enr_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enr_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enr_invoice`  FOREIGN KEY (`invoice_id`)  REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fee_types` (`code`, `name`, `category`, `is_recurring`, `is_mandatory`) VALUES
  ('ADM', 'Admission Fee', 'registration', 0, 1),
  ('ORI', 'Orientation Fee', 'activity', 0, 1),
  ('IDC', 'Student ID Card', 'other', 0, 1),
  ('LAB', 'Laboratory Fee', 'other', 1, 1),
  ('CLN', 'Clinical Practicum Fee', 'other', 1, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `category` = VALUES(`category`);

-- ---------------------------------------------------------------------
--  Backfill invoices raised before this upgrade with the default plan
--  (same rule as FeeStructure::installments() without a saved plan):
--  1st installment = every non-tuition fee + fee_threshold_percent of tuition,
--  2nd installment = the rest of the tuition.
-- ---------------------------------------------------------------------
SET @first_pct = COALESCE((SELECT CAST(setting_value AS DECIMAL(5,2)) FROM settings WHERE setting_key = 'fee_threshold_percent'), 60);

DROP TEMPORARY TABLE IF EXISTS `tmp_invoice_split`;
CREATE TEMPORARY TABLE `tmp_invoice_split` AS
SELECT i.id AS invoice_id,
       i.amount_paid,
       COALESCE(sem.start_date, i.issue_date) AS first_due,
       COALESCE(i.due_date, sem.end_date, i.issue_date) AS second_due,
       ROUND(i.total_amount - i.discount_amount, 2) AS net,
       ROUND(LEAST(i.total_amount - i.discount_amount,
             (i.total_amount - i.discount_amount - GREATEST(COALESCE(t.tuition, 0) - i.discount_amount, 0))
             + GREATEST(COALESCE(t.tuition, 0) - i.discount_amount, 0) * @first_pct / 100), 2) AS first_amount
  FROM invoices i
  LEFT JOIN semesters sem ON sem.id = i.semester_id
  LEFT JOIN (SELECT ii.invoice_id, SUM(ii.amount) AS tuition
               FROM invoice_items ii JOIN fee_types ft ON ft.id = ii.fee_type_id
              WHERE ft.category = 'tuition' GROUP BY ii.invoice_id) t ON t.invoice_id = i.id
 WHERE NOT EXISTS (SELECT 1 FROM invoice_installments x WHERE x.invoice_id = i.id);

INSERT INTO `invoice_installments` (invoice_id, installment_number, label, amount, amount_paid, due_date, status)
SELECT invoice_id, 1, '1st Installment', first_amount, LEAST(first_amount, amount_paid), first_due,
       CASE WHEN amount_paid >= first_amount - 0.005 THEN 'paid'
            WHEN amount_paid > 0 THEN 'partial'
            WHEN first_due < CURDATE() THEN 'overdue'
            ELSE 'unpaid' END
  FROM tmp_invoice_split;

INSERT INTO `invoice_installments` (invoice_id, installment_number, label, amount, amount_paid, due_date, status)
SELECT invoice_id, 2, '2nd Installment', ROUND(net - first_amount, 2),
       GREATEST(0, LEAST(net - first_amount, amount_paid - first_amount)), second_due,
       CASE WHEN amount_paid >= net - 0.005 THEN 'paid'
            WHEN amount_paid > first_amount THEN 'partial'
            WHEN second_due < CURDATE() THEN 'overdue'
            ELSE 'unpaid' END
  FROM tmp_invoice_split
 WHERE net - first_amount > 0.005;

-- Students whose 1st installment is already cleared are enrolled (registration complete).
INSERT IGNORE INTO `semester_enrollments` (student_id, semester_id, invoice_id, status, enrolled_at)
SELECT i.student_id, i.semester_id, i.id,
       IF(fi.status = 'paid', 'enrolled', 'pending_payment'),
       IF(fi.status = 'paid', NOW(), NULL)
  FROM invoices i
  JOIN invoice_installments fi ON fi.invoice_id = i.id AND fi.installment_number = 1
 WHERE i.semester_id IS NOT NULL AND i.status != 'cancelled'
 ORDER BY i.id DESC;

DROP TEMPORARY TABLE IF EXISTS `tmp_invoice_split`;
