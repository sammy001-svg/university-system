<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Student;

/** Alias used by route: /students/{id}/statement */
final class StatementController extends Controller
{
    public function show(Request $request, string $id): string
    {
        $this->authorize('finance.view');
        $student = (new Student())->profile((int)$id);
        if (!$student) { throw new HttpException(404); }

        $invoices = Database::select('SELECT * FROM invoices WHERE student_id=? ORDER BY created_at DESC', [(int)$id]);
        $payments = Database::select("SELECT * FROM payments WHERE student_id=? AND status='confirmed' ORDER BY paid_at DESC", [(int)$id]);

        return $this->view('finance.statement', [
            'pageTitle' => 'Fee Statement – ' . $student['first_name'] . ' ' . $student['last_name'],
            'student'   => $student,
            'invoices'  => $invoices,
            'payments'  => $payments,
            'balance'   => (new Student())->balance((int)$id),
        ]);
    }
}
