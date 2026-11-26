<?php
declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use RuntimeException;

/**
 * The monthly payroll, as the Finance Office works it:
 *
 *   Gross income     = basic salary + allowances
 *   Taxable amount   = basic + taxable allowances - the deductions that are relieved of tax
 *   Tax deduction    = the income-tax bands applied to that amount
 *   Total deductions = loan/penalty + tax + everything else withheld
 *   Net salary       = gross income - total deductions
 *
 * A run is rebuilt as often as needed while it is a draft. Loan repayments are
 * tied to the payslip that made them, so rebuilding a run never double-counts a
 * repayment or loses a balance.
 */
final class PayrollService
{
    /** Open a period. Returns its id; the month and year are unique. */
    public static function openPeriod(int $month, int $year, ?string $payDate, ?string $notes = null): int
    {
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Choose a month between January and December.');
        }
        if (Database::scalar('SELECT id FROM payroll_periods WHERE month = ? AND year = ?', [$month, $year])) {
            throw new RuntimeException('That payroll period already exists.');
        }
        $name    = date('F Y', (int) mktime(0, 0, 0, $month, 1, $year));
        $payDate = $payDate !== null && $payDate !== '' ? $payDate : date('Y-m-t', (int) mktime(0, 0, 0, $month, 1, $year));

        Database::statement(
            'INSERT INTO payroll_periods (period_name, month, year, pay_date, notes, status) VALUES (?, ?, ?, ?, ?, "draft")',
            [$name, $month, $year, $payDate, $notes ?: null]
        );
        return (int) Database::lastInsertId();
    }

    public static function period(int $periodId): ?array
    {
        return Database::selectOne('SELECT * FROM payroll_periods WHERE id = ?', [$periodId]);
    }

    /**
     * Build (or rebuild) every payslip in a draft period.
     *
     * @return array{staff:int, gross:float, net:float}
     */
    public static function process(int $periodId, ?int $actorId): array
    {
        $period = self::period($periodId) ?? throw new RuntimeException('Payroll period not found.');
        if (in_array($period['status'], ['paid', 'closed'], true)) {
            throw new RuntimeException('This payroll has been paid and can no longer be rebuilt.');
        }

        $staff = Database::select(
            'SELECT st.id, st.basic_salary FROM staff st
               JOIN users u ON u.id = st.user_id
              WHERE st.status = "active" AND u.deleted_at IS NULL
              ORDER BY st.id'
        );
        if ($staff === []) {
            throw new RuntimeException('There is no active staff member to pay.');
        }

        $components = self::componentCatalogue();
        $bands      = self::bands();
        $on         = (string) ($period['pay_date'] ?? date('Y-m-d'));

        Database::beginTransaction();
        try {
            // Rebuilding: give back every loan repayment this period had taken.
            self::reverseRepayments($periodId);

            foreach ($staff as $member) {
                $figures   = self::compute((int) $member['id'], (float) $member['basic_salary'], $on, $components, $bands);
                $payslipId = self::savePayslip($periodId, (int) $member['id'], $figures);
                self::applyLoans($payslipId, (int) $member['id'], $figures, $on);
            }

            // Anyone no longer on the active payroll drops out of a draft run.
            Database::statement(
                'DELETE ps FROM payslips ps
                   JOIN staff st ON st.id = ps.staff_id
                  WHERE ps.payroll_period_id = ? AND st.status <> "active"',
                [$periodId]
            );

            Database::statement(
                'UPDATE payroll_periods SET status = "processing", processed_by = ? WHERE id = ?',
                [$actorId, $periodId]
            );
            // Read the totals back, so they are the figures actually saved.
            $sums = Database::selectOne(
                'SELECT COUNT(*) AS staff, COALESCE(SUM(gross_pay), 0) AS gross, COALESCE(SUM(net_pay), 0) AS net
                   FROM payslips WHERE payroll_period_id = ?',
                [$periodId]
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        return ['staff' => (int) $sums['staff'], 'gross' => (float) $sums['gross'], 'net' => (float) $sums['net']];
    }

    /** Recalculate one payslip in place, after an edit to a salary, allowance or loan. */
    public static function recompute(int $payslipId): void
    {
        $payslip = Database::selectOne(
            'SELECT ps.*, pp.pay_date, pp.status AS period_status, st.basic_salary
               FROM payslips ps
               JOIN payroll_periods pp ON pp.id = ps.payroll_period_id
               JOIN staff st           ON st.id = ps.staff_id
              WHERE ps.id = ?',
            [$payslipId]
        ) ?? throw new RuntimeException('Payslip not found.');
        if ($payslip['status'] === 'paid') {
            throw new RuntimeException('A paid payslip cannot be recalculated.');
        }

        $on = (string) ($payslip['pay_date'] ?? date('Y-m-d'));
        Database::beginTransaction();
        try {
            self::reverseRepayments((int) $payslip['payroll_period_id'], $payslipId);
            $figures = self::compute((int) $payslip['staff_id'], (float) $payslip['basic_salary'], $on, self::componentCatalogue(), self::bands());
            self::savePayslip((int) $payslip['payroll_period_id'], (int) $payslip['staff_id'], $figures);
            self::applyLoans($payslipId, (int) $payslip['staff_id'], $figures, $on);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function approve(int $periodId, int $actorId): int
    {
        $period = self::period($periodId) ?? throw new RuntimeException('Payroll period not found.');
        if (in_array($period['status'], ['paid', 'closed'], true)) {
            throw new RuntimeException('This payroll has already been paid.');
        }
        $count = (int) Database::scalar('SELECT COUNT(*) FROM payslips WHERE payroll_period_id = ?', [$periodId]);
        if ($count === 0) {
            throw new RuntimeException('Run the payroll before approving it.');
        }
        Database::statement('UPDATE payslips SET status = "approved" WHERE payroll_period_id = ? AND status = "draft"', [$periodId]);
        Database::statement(
            'UPDATE payroll_periods SET status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?',
            [$actorId, $periodId]
        );
        return $count;
    }

    public static function markPaid(int $periodId, int $actorId): int
    {
        $period = self::period($periodId) ?? throw new RuntimeException('Payroll period not found.');
        if ($period['status'] !== 'approved') {
            throw new RuntimeException('Approve the payroll before marking it paid.');
        }
        $count = (int) Database::scalar('SELECT COUNT(*) FROM payslips WHERE payroll_period_id = ?', [$periodId]);
        Database::statement('UPDATE payslips SET status = "paid", paid_at = NOW() WHERE payroll_period_id = ?', [$periodId]);
        Database::statement('UPDATE payroll_periods SET status = "paid", paid_at = NOW() WHERE id = ?', [$periodId]);
        Database::statement('UPDATE staff_loans SET status = "settled" WHERE balance <= 0 AND status = "active"');
        return $count;
    }

    /**
     * The payroll register: one row per staff member, in the order and with the
     * columns of the university's own sheet.
     */
    public static function register(int $periodId): array
    {
        return Database::select(
            'SELECT ps.*, st.staff_number, st.designation,
                    u.first_name, u.other_name, u.last_name, u.gender, u.phone, u.date_of_birth,
                    COALESCE(d.name, f.name, IF(st.staff_category = "academic", NULL, "Administration")) AS department_name
               FROM payslips ps
               JOIN staff st          ON st.id = ps.staff_id
               JOIN users u           ON u.id = st.user_id
          LEFT JOIN departments d     ON d.id = st.department_id
          LEFT JOIN faculties f       ON f.id = st.faculty_id
              WHERE ps.payroll_period_id = ?
              ORDER BY u.last_name, u.first_name',
            [$periodId]
        );
    }

    /** Column totals for the register's footer. */
    public static function totals(array $rows): array
    {
        $keys   = ['basic_salary', 'allowances', 'gross_pay', 'loan_deduction', 'penalty', 'taxable_amount',
                   'tax_deduction', 'social_security', 'other_deductions', 'total_deductions', 'net_pay', 'outstanding_balance'];
        $totals = array_fill_keys($keys, 0.0);
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $totals[$key] += (float) ($row[$key] ?? 0);
            }
        }
        return $totals;
    }

    /* =================================================================
     |  The calculation
     * ===============================================================*/

    /**
     * One staff member's figures.
     *
     * @return array{basic_salary:float, allowances:float, gross_pay:float, taxable_amount:float,
     *               tax_deduction:float, social_security:float, other_deductions:float,
     *               total_deductions:float, net_pay:float, lines:list<array>}
     */
    private static function compute(int $staffId, float $basic, string $on, array $components, array $bands): array
    {
        $assigned = Database::select(
            'SELECT component_id, amount FROM staff_salary_components
              WHERE staff_id = ? AND (starts_on IS NULL OR starts_on <= ?) AND (ends_on IS NULL OR ends_on >= ?)',
            [$staffId, $on, $on]
        );
        $overrides = array_column($assigned, 'amount', 'component_id');

        $allowances    = 0.0;
        $taxReliefable = 0.0;   // deductions taken off before tax (social security, pension)
        $otherWithheld = 0.0;
        $social        = 0.0;
        $taxableEarned = 0.0;
        $lines         = [];

        foreach ($components as $component) {
            $id      = (int) $component['id'];
            $applies = isset($overrides[$id]) || (int) $component['applies_to_all'] === 1;
            if (!$applies) {
                continue;
            }
            $amount = self::amountOf($component, $overrides[$id] ?? null, $basic, $basic + $allowances);
            if ($amount <= 0.0) {
                continue;
            }
            $lines[] = [
                'component_id' => $id,
                'label'        => $component['name'],
                'kind'         => $component['kind'],
                'amount'       => $amount,
                'taxable'      => (int) $component['taxable'],
            ];
            if ($component['kind'] === 'allowance') {
                $allowances += $amount;
                if ((int) $component['taxable'] === 1) {
                    $taxableEarned += $amount;
                }
            } else {
                $otherWithheld += $amount;
                // A deduction that is not itself taxed is relieved before tax is worked out.
                if ((int) $component['taxable'] === 0) {
                    $taxReliefable += $amount;
                }
                if ($component['code'] === 'SSECURITY') {
                    $social += $amount;
                }
            }
        }

        $gross   = round($basic + $allowances, 2);
        $taxable = round(max(0.0, $basic + $taxableEarned - $taxReliefable), 2);
        $tax     = self::tax($taxable, $bands);

        return [
            'basic_salary'     => round($basic, 2),
            'allowances'       => round($allowances, 2),
            'gross_pay'        => $gross,
            'taxable_amount'   => $taxable,
            'tax_deduction'    => $tax,
            'social_security'  => round($social, 2),
            'other_deductions' => round($otherWithheld, 2),
            'total_deductions' => 0.0,   // completed once the loan deduction is known
            'net_pay'          => 0.0,
            'lines'            => $lines,
        ];
    }

    /** Income tax on a month's taxable pay, from the bands the Finance Office keeps. */
    public static function tax(float $taxable, ?array $bands = null): float
    {
        $bands ??= self::bands();
        if ($bands === [] || $taxable <= 0.0) {
            return 0.0;
        }
        $annual = $bands[0]['period'] === 'annual';
        $base   = $annual ? $taxable * 12 : $taxable;

        $chosen = $bands[0];
        foreach ($bands as $band) {
            if ($base > (float) $band['band_from']) {
                $chosen = $band;
            }
        }
        $tax = (float) $chosen['fixed_amount'] + ($base - (float) $chosen['band_from']) * ((float) $chosen['rate'] / 100);
        $tax = max(0.0, $annual ? $tax / 12 : $tax);
        return round($tax, 2);
    }

    /** @return list<array> active bands, lowest first */
    public static function bands(): array
    {
        return Database::select('SELECT * FROM tax_bands WHERE status = "active" ORDER BY sort_order, band_from');
    }

    /** @return list<array> active components, allowances first */
    private static function componentCatalogue(): array
    {
        return Database::select('SELECT * FROM salary_components WHERE status = "active" ORDER BY kind DESC, sort_order, id');
    }

    private static function amountOf(array $component, mixed $override, float $basic, float $gross): float
    {
        $value = $override !== null ? (float) $override : (float) $component['amount'];
        return round(match ($component['basis']) {
            'percent_basic' => $basic * $value / 100,
            'percent_gross' => $gross * $value / 100,
            default         => $value,
        }, 2);
    }

    /* =================================================================
     |  Saving
     * ===============================================================*/

    private static function savePayslip(int $periodId, int $staffId, array $f): int
    {
        Database::statement(
            'INSERT INTO payslips (payroll_period_id, staff_id, basic_salary, allowances, gross_pay, taxable_amount,
                                   tax_deduction, social_security, other_deductions, total_deductions, net_pay, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,"draft")
             ON DUPLICATE KEY UPDATE basic_salary = VALUES(basic_salary), allowances = VALUES(allowances),
                 gross_pay = VALUES(gross_pay), taxable_amount = VALUES(taxable_amount),
                 tax_deduction = VALUES(tax_deduction), social_security = VALUES(social_security),
                 other_deductions = VALUES(other_deductions), status = "draft"',
            [$periodId, $staffId, $f['basic_salary'], $f['allowances'], $f['gross_pay'], $f['taxable_amount'],
             $f['tax_deduction'], $f['social_security'], $f['other_deductions'], 0, 0]
        );
        $payslipId = (int) Database::scalar(
            'SELECT id FROM payslips WHERE payroll_period_id = ? AND staff_id = ?',
            [$periodId, $staffId]
        );

        Database::statement('DELETE FROM payslip_lines WHERE payslip_id = ?', [$payslipId]);
        foreach ($f['lines'] as $line) {
            Database::statement(
                'INSERT INTO payslip_lines (payslip_id, component_id, label, kind, amount, taxable) VALUES (?,?,?,?,?,?)',
                [$payslipId, $line['component_id'], $line['label'], $line['kind'], $line['amount'], $line['taxable']]
            );
        }
        return $payslipId;
    }

    /**
     * Take this month's instalment off each live loan or penalty, record it
     * against the payslip, and close the payslip's totals.
     */
    private static function applyLoans(int $payslipId, int $staffId, array $f, string $on): void
    {
        $loans = Database::select(
            'SELECT * FROM staff_loans
              WHERE staff_id = ? AND status = "active" AND balance > 0
                AND (first_deduction_on IS NULL OR first_deduction_on <= ?)
              ORDER BY id',
            [$staffId, $on]
        );

        // What the pay can bear once tax and the standing deductions are off.
        $available = round($f['gross_pay'] - $f['tax_deduction'] - $f['other_deductions'], 2);

        $loanTaken = 0.0;
        $penalty   = 0.0;
        foreach ($loans as $loan) {
            if ($available <= 0.0) {
                break;   // nothing left this month; the balance carries forward
            }
            $balance    = (float) $loan['balance'];
            $instalment = (float) $loan['monthly_deduction'];
            $amount     = round(min($instalment > 0 ? $instalment : $balance, $balance, $available), 2);
            if ($amount <= 0.0) {
                continue;
            }
            $available -= $amount;
            $after = round($balance - $amount, 2);
            Database::statement(
                'INSERT INTO staff_loan_repayments (loan_id, payslip_id, amount, balance_after) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE amount = VALUES(amount), balance_after = VALUES(balance_after)',
                [$loan['id'], $payslipId, $amount, $after]
            );
            Database::statement(
                'UPDATE staff_loans SET balance = ?, status = IF(? <= 0, "settled", "active") WHERE id = ?',
                [$after, $after, $loan['id']]
            );
            $loan['kind'] === 'penalty' ? $penalty += $amount : $loanTaken += $amount;
        }

        $outstanding = (float) Database::scalar(
            'SELECT COALESCE(SUM(balance), 0) FROM staff_loans WHERE staff_id = ? AND status = "active"',
            [$staffId]
        );

        $total = round($f['tax_deduction'] + $f['other_deductions'] + $loanTaken + $penalty, 2);
        Database::statement(
            'UPDATE payslips SET loan_deduction = ?, penalty = ?, total_deductions = ?, net_pay = ?, outstanding_balance = ?
              WHERE id = ?',
            [$loanTaken, $penalty, $total, round($f['gross_pay'] - $total, 2), $outstanding, $payslipId]
        );
    }

    /** Undo the loan repayments a draft run has already taken, so it can be rebuilt. */
    private static function reverseRepayments(int $periodId, ?int $onlyPayslipId = null): void
    {
        $sql = 'SELECT r.id, r.loan_id, r.amount FROM staff_loan_repayments r
                  JOIN payslips ps ON ps.id = r.payslip_id
                 WHERE ps.payroll_period_id = ?';
        $args = [$periodId];
        if ($onlyPayslipId !== null) {
            $sql .= ' AND r.payslip_id = ?';
            $args[] = $onlyPayslipId;
        }
        foreach (Database::select($sql, $args) as $repayment) {
            Database::statement(
                'UPDATE staff_loans SET balance = balance + ?, status = IF(status = "settled", "active", status) WHERE id = ?',
                [$repayment['amount'], $repayment['loan_id']]
            );
            Database::statement('DELETE FROM staff_loan_repayments WHERE id = ?', [$repayment['id']]);
        }
    }
}
