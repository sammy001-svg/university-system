<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Finance\PayrollService;
use RuntimeException;

/**
 * The monthly payroll: periods, the register the Finance Office prints,
 * individual payslips, staff salaries and staff loans.
 */
final class PayrollController extends Controller
{
    /* =================================================================
     |  Periods
     * ===============================================================*/

    public function index(Request $request): string
    {
        $this->authorize('payroll.view');
        $periods = Database::select(
            'SELECT pp.*, COUNT(ps.id) AS payslips, COALESCE(SUM(ps.gross_pay), 0) AS gross,
                    COALESCE(SUM(ps.net_pay), 0) AS net
               FROM payroll_periods pp
          LEFT JOIN payslips ps ON ps.payroll_period_id = pp.id
              GROUP BY pp.id
              ORDER BY pp.year DESC, pp.month DESC, pp.id DESC'
        );

        return $this->view('finance.payroll.index', [
            'pageTitle' => 'Payroll',
            'periods'   => $periods,
            'onPayroll' => (int) Database::scalar(
                'SELECT COUNT(*) FROM staff st JOIN users u ON u.id = st.user_id WHERE st.status = "active" AND u.deleted_at IS NULL'
            ),
            'noSalary'  => (int) Database::scalar(
                'SELECT COUNT(*) FROM staff st JOIN users u ON u.id = st.user_id
                  WHERE st.status = "active" AND u.deleted_at IS NULL AND st.basic_salary <= 0'
            ),
        ]);
    }

    public function createPeriod(Request $request): never
    {
        $this->authorize('payroll.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'month'    => 'required|integer|min:1|max:12',
            'year'     => 'required|integer|min:2020|max:2099',
            'pay_date' => 'nullable|date',
            'notes'    => 'nullable|max:255',
        ]);

        try {
            $id = PayrollService::openPeriod(
                (int) $data['month'],
                (int) $data['year'],
                ($data['pay_date'] ?? '') ?: null,
                ($data['notes'] ?? '') ?: null
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/payroll');
        }
        Audit::created('PayrollPeriod', $id, $data, 'payroll');
        $this->success('Payroll period opened. Run it when the salaries and allowances are ready.', '/finance/payroll/' . $id);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('payroll.view');
        $period = PayrollService::period((int) $id) ?? throw new HttpException(404, 'Payroll period not found.');
        $rows   = PayrollService::register((int) $id);

        return $this->view('finance.payroll.show', [
            'pageTitle' => 'Payroll ' . $period['period_name'],
            'period'    => $period,
            'rows'      => $rows,
            'totals'    => PayrollService::totals($rows),
        ]);
    }

    public function process(Request $request, string $id): never
    {
        $this->authorize('payroll.create');
        $this->verifyCsrf($request);
        try {
            $result = PayrollService::process((int) $id, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/payroll/' . (int) $id);
        }
        Audit::log('process', 'payroll', 'PayrollPeriod', (string) (int) $id, 'Payroll run', null, $result);
        $this->success(
            sprintf('Payroll run for %d staff. Gross %s, net %s.', $result['staff'], money($result['gross']), money($result['net'])),
            '/finance/payroll/' . (int) $id
        );
    }

    public function approve(Request $request, string $id): never
    {
        $this->authorize('payroll.approve');
        $this->verifyCsrf($request);
        try {
            $count = PayrollService::approve((int) $id, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/payroll/' . (int) $id);
        }
        Audit::log('approve', 'payroll', 'PayrollPeriod', (string) (int) $id, 'Payroll approved', null, ['payslips' => $count]);
        $this->success($count . ' payslip(s) approved. The run can now be paid.', '/finance/payroll/' . (int) $id);
    }

    public function pay(Request $request, string $id): never
    {
        $this->authorize('payroll.pay');
        $this->verifyCsrf($request);
        try {
            $count = PayrollService::markPaid((int) $id, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/payroll/' . (int) $id);
        }
        Audit::log('pay', 'payroll', 'PayrollPeriod', (string) (int) $id, 'Payroll paid', null, ['payslips' => $count]);
        $this->success($count . ' payslip(s) marked paid.', '/finance/payroll/' . (int) $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('payroll.delete');
        $this->verifyCsrf($request);
        $period = PayrollService::period((int) $id) ?? throw new HttpException(404);
        if (in_array($period['status'], ['paid', 'closed'], true)) {
            $this->error('A paid payroll period cannot be deleted.', '/finance/payroll');
        }
        // Deleting the payslips gives back any loan repayments they took.
        Database::statement(
            'UPDATE staff_loans l
               JOIN staff_loan_repayments r ON r.loan_id = l.id
               JOIN payslips ps ON ps.id = r.payslip_id
                SET l.balance = l.balance + r.amount, l.status = IF(l.status = "settled", "active", l.status)
              WHERE ps.payroll_period_id = ?',
            [(int) $id]
        );
        Database::statement('DELETE FROM payroll_periods WHERE id = ?', [(int) $id]);
        Audit::deleted('PayrollPeriod', (int) $id, $period, 'payroll');
        $this->success('Payroll period deleted.', '/finance/payroll');
    }

    /** The register as a spreadsheet, in the columns of the university's own sheet. */
    public function export(Request $request, string $id): never
    {
        $this->authorize('payroll.export');
        $period = PayrollService::period((int) $id) ?? throw new HttpException(404);
        $rows   = PayrollService::register((int) $id);
        $totals = PayrollService::totals($rows);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Sr No', 'FirstName', 'Middle Name', 'LastName', 'Employee ID', 'Gender', 'Phone Number', 'DoB',
                       'Position', 'Department', 'Basic Salary', 'Allowances', 'Gross Income', 'Loan/Penalty',
                       'Taxable amount', 'Tax Deduction', 'Others', 'Total Deductions', 'Outstanding Balance',
                       'Net Salary', 'Payroll Date']);
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i + 1, $r['first_name'], $r['other_name'], $r['last_name'], $r['staff_number'],
                $r['gender'] ? strtoupper(substr((string) $r['gender'], 0, 1)) : '',
                $r['phone'], $r['date_of_birth'], $r['designation'], $r['department_name'],
                number_format((float) $r['basic_salary'], 2, '.', ''),
                number_format((float) $r['allowances'], 2, '.', ''),
                number_format((float) $r['gross_pay'], 2, '.', ''),
                number_format((float) $r['loan_deduction'] + (float) $r['penalty'], 2, '.', ''),
                number_format((float) $r['taxable_amount'], 2, '.', ''),
                number_format((float) $r['tax_deduction'], 2, '.', ''),
                number_format((float) $r['other_deductions'], 2, '.', ''),
                number_format((float) $r['total_deductions'], 2, '.', ''),
                number_format((float) $r['outstanding_balance'], 2, '.', ''),
                number_format((float) $r['net_pay'], 2, '.', ''),
                $period['pay_date'],
            ]);
        }
        fputcsv($out, ['', '', '', '', '', '', '', '', '', 'TOTAL',
            number_format($totals['basic_salary'], 2, '.', ''), number_format($totals['allowances'], 2, '.', ''),
            number_format($totals['gross_pay'], 2, '.', ''),
            number_format($totals['loan_deduction'] + $totals['penalty'], 2, '.', ''),
            number_format($totals['taxable_amount'], 2, '.', ''), number_format($totals['tax_deduction'], 2, '.', ''),
            number_format($totals['other_deductions'], 2, '.', ''), number_format($totals['total_deductions'], 2, '.', ''),
            number_format($totals['outstanding_balance'], 2, '.', ''), number_format($totals['net_pay'], 2, '.', ''), '']);
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        $name = 'payroll-' . strtolower(str_replace(' ', '-', (string) $period['period_name'])) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    /* =================================================================
     |  One payslip
     * ===============================================================*/

    public function payslip(Request $request, string $id): string
    {
        $this->authorize('payroll.view');
        $payslip = $this->payslipOrFail((int) $id);

        return $this->view('finance.payroll.payslip', [
            'pageTitle' => 'Payslip ' . $payslip['period_name'],
            'payslip'   => $payslip,
            'lines'     => Database::select('SELECT * FROM payslip_lines WHERE payslip_id = ? ORDER BY kind DESC, id', [(int) $id]),
            'loans'     => Database::select(
                'SELECT r.*, l.reference, l.kind FROM staff_loan_repayments r
                   JOIN staff_loans l ON l.id = r.loan_id WHERE r.payslip_id = ?',
                [(int) $id]
            ),
        ]);
    }

    /** A note or a one-off adjustment on a single payslip, before the run is approved. */
    public function updatePayslip(Request $request, string $id): never
    {
        $this->authorize('payroll.edit');
        $this->verifyCsrf($request);
        $payslip = $this->payslipOrFail((int) $id);
        if ($payslip['status'] === 'paid') {
            $this->error('A paid payslip cannot be changed.', '/finance/payroll/payslip/' . (int) $id);
        }
        $data = $this->validate($request, ['notes' => 'nullable|max:255']);
        Database::statement('UPDATE payslips SET notes = ? WHERE id = ?', [($data['notes'] ?? '') ?: null, (int) $id]);

        try {
            PayrollService::recompute((int) $id);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/payroll/payslip/' . (int) $id);
        }
        Audit::log('recalculate', 'payroll', 'Payslip', (string) (int) $id, 'Payslip recalculated');
        $this->success('Payslip recalculated.', '/finance/payroll/payslip/' . (int) $id);
    }

    private function payslipOrFail(int $id): array
    {
        $payslip = Database::selectOne(
            'SELECT ps.*, st.staff_number, st.designation, st.bank_name, st.bank_account,
                    u.title, u.first_name, u.other_name, u.last_name, u.email, u.phone, u.gender, u.date_of_birth,
                    COALESCE(d.name, f.name) AS department_name,
                    pp.period_name, pp.month, pp.year, pp.pay_date, pp.status AS period_status
               FROM payslips ps
               JOIN staff st           ON st.id = ps.staff_id
               JOIN users u            ON u.id = st.user_id
          LEFT JOIN departments d      ON d.id = st.department_id
          LEFT JOIN faculties f        ON f.id = st.faculty_id
               JOIN payroll_periods pp ON pp.id = ps.payroll_period_id
              WHERE ps.id = ?',
            [$id]
        );
        if ($payslip === null) {
            throw new HttpException(404, 'Payslip not found.');
        }
        return $payslip;
    }

    /* =================================================================
     |  Salaries: what each staff member earns
     * ===============================================================*/

    public function salaries(Request $request): string
    {
        $this->authorize('payroll.view');
        $search = (string) $request->query('q', '');
        $args   = [];
        $where  = 'st.status = "active" AND u.deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR st.staff_number LIKE ? OR st.designation LIKE ?)';
            $args  = array_fill(0, 4, '%' . $search . '%');
        }

        return $this->view('finance.payroll.salaries', [
            'pageTitle'  => 'Staff salaries',
            'q'          => $search,
            'staff'      => Database::select(
                'SELECT st.id, st.staff_number, st.designation, st.basic_salary, st.salary_grade,
                        u.first_name, u.other_name, u.last_name,
                        COALESCE(d.name, f.name) AS department_name,
                        (SELECT COALESCE(SUM(COALESCE(ssc.amount, sc.amount)), 0) FROM staff_salary_components ssc
                           JOIN salary_components sc ON sc.id = ssc.component_id
                          WHERE ssc.staff_id = st.id AND sc.kind = "allowance" AND sc.basis = "fixed") AS fixed_allowances,
                        (SELECT COUNT(*) FROM staff_salary_components WHERE staff_id = st.id) AS components,
                        (SELECT COALESCE(SUM(balance), 0) FROM staff_loans WHERE staff_id = st.id AND status = "active") AS loan_balance
                   FROM staff st
                   JOIN users u       ON u.id = st.user_id
              LEFT JOIN departments d ON d.id = st.department_id
              LEFT JOIN faculties f   ON f.id = st.faculty_id
                  WHERE ' . $where . '
                  ORDER BY u.last_name, u.first_name',
                $args
            ),
        ]);
    }

    public function editSalary(Request $request, string $id): string
    {
        $this->authorize('payroll.view');
        $staff = $this->staffOrFail((int) $id);

        return $this->view('finance.payroll.salary-form', [
            'pageTitle'  => 'Salary - ' . $staff['first_name'] . ' ' . $staff['last_name'],
            'staff'      => $staff,
            'components' => Database::select('SELECT * FROM salary_components WHERE status = "active" ORDER BY kind DESC, sort_order, id'),
            'assigned'   => array_column(
                Database::select('SELECT component_id, amount FROM staff_salary_components WHERE staff_id = ?', [(int) $id]),
                'amount',
                'component_id'
            ),
            'loans'      => Database::select('SELECT * FROM staff_loans WHERE staff_id = ? ORDER BY status, id DESC', [(int) $id]),
        ]);
    }

    public function updateSalary(Request $request, string $id): never
    {
        $this->authorize('payroll.edit');
        $this->verifyCsrf($request);
        $staff = $this->staffOrFail((int) $id);
        $data  = $this->validate($request, [
            'basic_salary' => 'required|numeric|min:0',
            'salary_grade' => 'nullable|max:20',
            'bank_name'    => 'nullable|max:120',
            'bank_account' => 'nullable|max:60',
            'tax_pin'      => 'nullable|max:40',
        ]);

        Database::beginTransaction();
        try {
            Database::statement(
                'UPDATE staff SET basic_salary = ?, salary_grade = ?, bank_name = ?, bank_account = ?, tax_pin = ? WHERE id = ?',
                [(float) $data['basic_salary'], ($data['salary_grade'] ?? '') ?: null, ($data['bank_name'] ?? '') ?: null,
                 ($data['bank_account'] ?? '') ?: null, ($data['tax_pin'] ?? '') ?: null, (int) $id]
            );

            // Components: an empty box means "not on this person's payslip".
            $amounts = (array) $request->input('component', []);
            Database::statement('DELETE FROM staff_salary_components WHERE staff_id = ?', [(int) $id]);
            foreach ($amounts as $componentId => $amount) {
                if (trim((string) $amount) === '') {
                    continue;
                }
                Database::statement(
                    'INSERT INTO staff_salary_components (staff_id, component_id, amount) VALUES (?, ?, ?)',
                    [(int) $id, (int) $componentId, (float) $amount]
                );
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        Audit::updated('Staff', (int) $id, $staff, $data, 'payroll');
        $this->success('Salary saved. Re-run any draft payroll to pick it up.', '/finance/payroll/salaries/' . (int) $id);
    }

    private function staffOrFail(int $id): array
    {
        $staff = Database::selectOne(
            'SELECT st.*, u.title, u.first_name, u.other_name, u.last_name, u.gender, u.phone, u.date_of_birth,
                    COALESCE(d.name, f.name) AS department_name
               FROM staff st
               JOIN users u       ON u.id = st.user_id
          LEFT JOIN departments d ON d.id = st.department_id
          LEFT JOIN faculties f   ON f.id = st.faculty_id
              WHERE st.id = ?',
            [$id]
        );
        if ($staff === null) {
            throw new HttpException(404, 'Staff member not found.');
        }
        return $staff;
    }

    /* =================================================================
     |  Loans, salary advances and penalties
     * ===============================================================*/

    public function loans(Request $request): string
    {
        $this->authorize('payroll.view');
        $status = in_array($request->query('status', 'active'), ['active', 'settled', 'cancelled', 'all'], true)
            ? (string) $request->query('status', 'active') : 'active';

        $where = $status === 'all' ? '1' : 'l.status = ?';
        $args  = $status === 'all' ? [] : [$status];

        return $this->view('finance.payroll.loans', [
            'pageTitle' => 'Staff loans and advances',
            'status'    => $status,
            'loans'     => Database::select(
                'SELECT l.*, st.staff_number, u.first_name, u.last_name,
                        (SELECT COALESCE(SUM(amount), 0) FROM staff_loan_repayments WHERE loan_id = l.id) AS repaid
                   FROM staff_loans l
                   JOIN staff st ON st.id = l.staff_id
                   JOIN users u  ON u.id = st.user_id
                  WHERE ' . $where . '
                  ORDER BY l.status, l.id DESC',
                $args
            ),
            'staff'     => Database::select(
                'SELECT st.id, CONCAT(u.last_name, ", ", u.first_name, " (", st.staff_number, ")") AS label
                   FROM staff st JOIN users u ON u.id = st.user_id
                  WHERE st.status = "active" AND u.deleted_at IS NULL
                  ORDER BY u.last_name, u.first_name'
            ),
        ]);
    }

    public function storeLoan(Request $request): never
    {
        $this->authorize('payroll.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'staff_id'           => 'required|integer|exists:staff,id',
            'kind'               => 'required|in:loan,salary_advance,penalty',
            'principal'          => 'required|numeric|min:1',
            'monthly_deduction'  => 'required|numeric|min:1',
            'issued_on'          => 'nullable|date',
            'first_deduction_on' => 'nullable|date',
            'reason'             => 'nullable|max:255',
        ]);
        if ((float) $data['monthly_deduction'] > (float) $data['principal']) {
            $this->error('The monthly deduction cannot be more than the amount owed.', '/finance/payroll/loans');
        }

        $reference = strtoupper(substr($data['kind'], 0, 3)) . '-' . date('Ym') . '-'
            . str_pad((string) ((int) Database::scalar('SELECT COUNT(*) FROM staff_loans') + 1), 4, '0', STR_PAD_LEFT);

        Database::statement(
            'INSERT INTO staff_loans (staff_id, reference, kind, principal, monthly_deduction, balance,
                                      issued_on, first_deduction_on, reason, status, approved_by)
             VALUES (?,?,?,?,?,?,?,?,?,"active",?)',
            [(int) $data['staff_id'], $reference, $data['kind'], (float) $data['principal'], (float) $data['monthly_deduction'],
             (float) $data['principal'], ($data['issued_on'] ?? '') ?: date('Y-m-d'), ($data['first_deduction_on'] ?? '') ?: null,
             ($data['reason'] ?? '') ?: null, (int) Auth::id()]
        );
        Audit::created('StaffLoan', (int) Database::lastInsertId(), $data, 'payroll');
        $this->success('Recorded as ' . $reference . '. It will be deducted from the next payroll run.', '/finance/payroll/loans');
    }

    public function cancelLoan(Request $request, string $id): never
    {
        $this->authorize('payroll.edit');
        $this->verifyCsrf($request);
        $loan = Database::selectOne('SELECT * FROM staff_loans WHERE id = ?', [(int) $id]) ?? throw new HttpException(404);
        if ($loan['status'] !== 'active') {
            $this->error('Only a live loan can be cancelled.', '/finance/payroll/loans');
        }
        Database::statement('UPDATE staff_loans SET status = "cancelled" WHERE id = ?', [(int) $id]);
        Audit::log('cancel', 'payroll', 'StaffLoan', (string) (int) $id, 'Loan cancelled', $loan, null);
        $this->success('Loan ' . $loan['reference'] . ' cancelled. Nothing further will be deducted.', '/finance/payroll/loans');
    }
}
