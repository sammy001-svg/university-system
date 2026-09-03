<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

final class PayrollController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('payroll.view');
        $periods = Database::select('SELECT * FROM payroll_periods ORDER BY year DESC, month DESC, id DESC');
        return $this->view('finance.payroll.index', ['pageTitle' => 'Payroll', 'periods' => $periods]);
    }

    public function createPeriod(Request $request): never
    {
        $this->authorize('payroll.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'month'    => 'required|integer|min:1|max:12',
            'year'     => 'required|integer|min:2020|max:2099',
            'pay_date' => 'nullable|date',
        ]);
        $dateObj    = DateTime::createFromFormat('!m', (string)$data['month']);
        $monthName  = $dateObj ? $dateObj->format('F') : 'Month ' . $data['month'];
        $periodName = $monthName . ' ' . $data['year'];
        $payDate    = !empty($data['pay_date']) ? $data['pay_date'] : date('Y-m-t', strtotime("{$data['year']}-{$data['month']}-01"));

        $exists = Database::scalar('SELECT id FROM payroll_periods WHERE month=? AND year=?', [(int)$data['month'], (int)$data['year']]);
        if ($exists) {
            $this->error("Payroll period for {$periodName} already exists.", '/finance/payroll');
        }

        Database::statement(
            'INSERT INTO payroll_periods (period_name, month, year, pay_date, status) VALUES (?,?,?,?,"draft")',
            [$periodName, (int)$data['month'], (int)$data['year'], $payDate]
        );
        $this->success('Payroll period created.', '/finance/payroll');
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('payroll.view');
        $period = Database::selectOne('SELECT * FROM payroll_periods WHERE id=?', [(int)$id]);
        if (!$period) { throw new HttpException(404); }
        $payslips = Database::select(
            'SELECT ps.*, st.staff_number, u.first_name, u.last_name
               FROM payslips ps
               JOIN staff st ON st.id = ps.staff_id
               JOIN users u  ON u.id = st.user_id
              WHERE ps.payroll_period_id = ?
              ORDER BY u.last_name, u.first_name',
            [(int)$id]
        );
        return $this->view('finance.payroll.show', ['pageTitle' => 'Payroll Period', 'period' => $period, 'payslips' => $payslips]);
    }

    public function process(Request $request, string $id): never
    {
        $this->authorize('payroll.create');
        $this->verifyCsrf($request);
        $period = Database::selectOne('SELECT * FROM payroll_periods WHERE id=?', [(int)$id]);
        if (!$period) { throw new HttpException(404); }

        // Generate payslips for all active staff
        $staff = Database::select("SELECT * FROM staff WHERE status='active'");
        foreach ($staff as $s) {
            $exists = Database::scalar('SELECT id FROM payslips WHERE payroll_period_id=? AND staff_id=?', [(int)$id, $s['id']]);
            if (!$exists) {
                $basic = (float)($s['basic_salary'] ?? 0.00);
                Database::statement(
                    'INSERT INTO payslips (payroll_period_id, staff_id, basic_salary, gross_pay, net_pay, status) VALUES (?,?,?,?,?,"draft")',
                    [(int)$id, $s['id'], $basic, $basic, $basic]
                );
            }
        }
        Database::statement("UPDATE payroll_periods SET status='approved', processed_by=? WHERE id=?", [\App\Core\Auth::id(), (int)$id]);
        $this->success('Payroll processed. ' . count($staff) . ' payslip(s) generated.', '/finance/payroll/' . $id);
    }

    public function payslip(Request $request, string $id): string
    {
        $this->authorize('payroll.view');
        $payslip = Database::selectOne(
            'SELECT ps.*, st.staff_number, u.first_name, u.last_name, u.email, st.designation,
                    pp.period_name, pp.month, pp.year, pp.pay_date
               FROM payslips ps
               JOIN staff st           ON st.id = ps.staff_id
               JOIN users u            ON u.id = st.user_id
               JOIN payroll_periods pp ON pp.id = ps.payroll_period_id
              WHERE ps.id=?',
            [(int)$id]
        );
        if (!$payslip) { throw new HttpException(404); }
        return $this->view('finance.payroll.payslip', ['pageTitle' => 'Payslip', 'payslip' => $payslip]);
    }
}
