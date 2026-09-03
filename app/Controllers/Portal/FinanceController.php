<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Invoice;
use App\Models\Student;

final class FinanceController extends Controller
{
    public function index(Request $request): string
    {
        $student  = $this->student();
        $invoices = Database::select(
            'SELECT * FROM invoices WHERE student_id = ? ORDER BY created_at DESC',
            [$student['id']]
        );
        $payments = Database::select(
            'SELECT * FROM payments WHERE student_id = ? AND status = "confirmed" ORDER BY paid_at DESC',
            [$student['id']]
        );

        return $this->view('portal.finance', [
            'pageTitle' => 'My Fees & Payments',
            'student'   => $student,
            'invoices'  => $invoices,
            'payments'  => $payments,
            'balance'   => (new Student())->balance($student['id']),
            'totalBilled' => (new Student())->totalBilled($student['id']),
            'totalPaid'   => (new Student())->totalPaid($student['id']),
        ]);
    }

    public function invoice(Request $request, string $id): string
    {
        $student = $this->student();
        $invoice = Database::selectOne(
            'SELECT i.*, s.admission_number, u.first_name, u.last_name
               FROM invoices i
               JOIN students s ON s.id = i.student_id
               JOIN users u    ON u.id = s.user_id
              WHERE i.id = ? AND i.student_id = ?',
            [(int) $id, $student['id']]
        );

        if (!$invoice) { throw new HttpException(404); }

        $items = Database::select(
            'SELECT ii.*, ft.name AS fee_type_name FROM invoice_items ii
              JOIN fee_types ft ON ft.id = ii.fee_type_id
             WHERE ii.invoice_id = ?',
            [$invoice['id']]
        );

        return $this->view('portal.invoice', [
            'pageTitle' => 'Invoice ' . $invoice['invoice_number'],
            'student'   => $student,
            'invoice'   => $invoice,
            'items'     => $items,
        ]);
    }

    private function student(): array
    {
        $s = (new Student())->byUserId((int) Auth::id());
        if (!$s) { throw new HttpException(403, 'No student profile linked to your account.'); }
        return $s;
    }
}
