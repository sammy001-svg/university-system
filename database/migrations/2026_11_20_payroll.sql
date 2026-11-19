-- ---------------------------------------------------------------------------
--  Payroll: the register the Finance Office prints each month.
--
--  Columns follow the university's own payroll sheet:
--    Basic Salary | Allowances | Gross Income | Loan/Penalty | Taxable amount |
--    Tax Deduction | Others | Total Deductions | Outstanding Balance | Net Salary
--
--  Every statement here is safe to repeat: the schema sync runs the file again
--  whenever it changes.
-- ---------------------------------------------------------------------------

-- The catalogue of allowances and deductions the university pays or withholds.
CREATE TABLE IF NOT EXISTS salary_components (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  kind        ENUM('allowance','deduction') NOT NULL DEFAULT 'allowance',
  basis       ENUM('fixed','percent_basic','percent_gross') NOT NULL DEFAULT 'fixed',
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  taxable     TINYINT(1)   NOT NULL DEFAULT 1,
  applies_to_all TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255)          DEFAULT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_salary_component (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What an individual earns or has withheld, over and above the catalogue default.
CREATE TABLE IF NOT EXISTS staff_salary_components (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id     BIGINT UNSIGNED NOT NULL,
  component_id INT UNSIGNED    NOT NULL,
  amount       DECIMAL(12,2)            DEFAULT NULL,
  starts_on    DATE                     DEFAULT NULL,
  ends_on      DATE                     DEFAULT NULL,
  note         VARCHAR(255)             DEFAULT NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_component (staff_id, component_id),
  KEY ix_ssc_component (component_id),
  CONSTRAINT fk_ssc_staff     FOREIGN KEY (staff_id)     REFERENCES staff (id) ON DELETE CASCADE,
  CONSTRAINT fk_ssc_component FOREIGN KEY (component_id) REFERENCES salary_components (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff loans, salary advances and penalties: the source of "Loan/Penalty"
-- and "Outstanding Balance" on the register.
CREATE TABLE IF NOT EXISTS staff_loans (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id           BIGINT UNSIGNED NOT NULL,
  reference          VARCHAR(40)     NOT NULL,
  kind               ENUM('loan','salary_advance','penalty') NOT NULL DEFAULT 'loan',
  principal          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  monthly_deduction  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  balance            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  issued_on          DATE                     DEFAULT NULL,
  first_deduction_on DATE                     DEFAULT NULL,
  reason             VARCHAR(255)             DEFAULT NULL,
  status             ENUM('active','settled','cancelled') NOT NULL DEFAULT 'active',
  approved_by        BIGINT UNSIGNED          DEFAULT NULL,
  created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_staff_loan_ref (reference),
  KEY ix_loan_staff (staff_id, status),
  CONSTRAINT fk_loan_staff FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each month's repayment, tied to the payslip that made it, so a run can be
-- recalculated without the balance drifting.
CREATE TABLE IF NOT EXISTS staff_loan_repayments (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loan_id       BIGINT UNSIGNED NOT NULL,
  payslip_id    BIGINT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  balance_after DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_loan_payslip (loan_id, payslip_id),
  KEY ix_lr_payslip (payslip_id),
  CONSTRAINT fk_lr_loan    FOREIGN KEY (loan_id)    REFERENCES staff_loans (id) ON DELETE CASCADE,
  CONSTRAINT fk_lr_payslip FOREIGN KEY (payslip_id) REFERENCES payslips (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The itemised allowances and deductions behind a payslip's totals.
CREATE TABLE IF NOT EXISTS payslip_lines (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payslip_id   BIGINT UNSIGNED NOT NULL,
  component_id INT UNSIGNED             DEFAULT NULL,
  label        VARCHAR(120)    NOT NULL,
  kind         ENUM('allowance','deduction') NOT NULL,
  amount       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  taxable      TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_psl_payslip (payslip_id),
  CONSTRAINT fk_psl_payslip FOREIGN KEY (payslip_id) REFERENCES payslips (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Income-tax bands. Seeded with Liberia's statutory personal income tax
-- schedule; the Finance Office edits these when the rates change.
CREATE TABLE IF NOT EXISTS tax_bands (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(80)  NOT NULL,
  band_from      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  band_to        DECIMAL(14,2)          DEFAULT NULL,
  rate           DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  fixed_amount   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  period         ENUM('monthly','annual') NOT NULL DEFAULT 'annual',
  effective_from DATE                   DEFAULT NULL,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_band_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tax_bands (id, name, band_from, band_to, rate, fixed_amount, period, status, sort_order) VALUES
  (1, 'Up to 70,000',         0.00,      70000.00,  0.00,     0.00, 'annual', 'active', 1),
  (2, '70,001 to 200,000',    70000.00,  200000.00, 5.00,     0.00, 'annual', 'active', 2),
  (3, '200,001 to 800,000',   200000.00, 800000.00, 15.00, 6500.00, 'annual', 'active', 3),
  (4, 'Above 800,000',        800000.00, NULL,      25.00, 96500.00, 'annual', 'active', 4);

INSERT IGNORE INTO salary_components (id, code, name, kind, basis, amount, taxable, applies_to_all, description, status, sort_order) VALUES
  (1, 'HOUSING',   'Housing allowance',        'allowance', 'fixed', 0.00, 1, 0, 'Monthly housing allowance.', 'active', 1),
  (2, 'TRANSPORT', 'Transport allowance',      'allowance', 'fixed', 0.00, 1, 0, 'Monthly transport allowance.', 'active', 2),
  (3, 'RESPONS',   'Responsibility allowance', 'allowance', 'fixed', 0.00, 1, 0, 'Paid for a post of responsibility.', 'active', 3),
  (4, 'MEDICAL',   'Medical allowance',        'allowance', 'fixed', 0.00, 0, 0, 'Not subject to income tax.', 'active', 4),
  (5, 'OVERTIME',  'Overtime',                 'allowance', 'fixed', 0.00, 1, 0, 'Entered per payroll run.', 'active', 5),
  (6, 'SSECURITY', 'Social security',          'deduction', 'percent_basic', 0.00, 0, 1, 'Employee social security contribution. Set the rate before the first run.', 'active', 10),
  (7, 'PENSION',   'Pension contribution',     'deduction', 'percent_basic', 0.00, 0, 1, 'Employee pension contribution.', 'active', 11),
  (8, 'UNION',     'Union dues',               'deduction', 'fixed', 0.00, 0, 0, 'Staff association dues.', 'active', 12);
