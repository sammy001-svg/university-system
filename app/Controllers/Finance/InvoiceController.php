<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Invoice;
use App\Services\BillingService;

final class InvoiceController extends Controller
{
    private Invoice $model;
    public function __construct() { $this->model = new Invoice(); }

    public function index(Request $request): string
    {
        $this->authorize('finance.view');
        $query = Database::select(
            'SELECT i.*, s.admission_number, u.first_name, u.last_name
               FROM invoices i
               JOIN students s ON s.id = i.student_id
               JOIN users u    ON u.id = s.user_id
              ORDER BY i.created_at DESC
              LIMIT 200'
        );
        return $this->view('finance.invoices.index', ['pageTitle' => 'Invoices', 'invoices' => $query]);
    }

    public function generateForm(Request $request): string
    {
        $this->authorize('finance.create');
        return $this->view('finance.invoices.generate', [
            'pageTitle'    => 'Generate Invoices',
            'programs'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM programs WHERE status='active' ORDER BY name"),
            'academicYears'=> Database::select('SELECT id, name FROM academic_years ORDER BY start_date DESC'),
            'feeStructures'=> Database::select('SELECT fs.id, fs.name, ay.name AS year FROM fee_structures fs JOIN academic_years ay ON ay.id=fs.academic_year_id ORDER BY ay.start_date DESC'),
        ]);
    }

    public function generate(Request $request): never
    {
        $this->authorize('finance.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'fee_structure_id' => 'required|integer|exists:fee_structures,id',
            'due_date'         => 'required|date',
        ]);
        $count = (new BillingService())->generateInvoices((int)$data['fee_structure_id'], $data['due_date'], (int)Auth::id());
        $this->success("{$count} invoice(s) generated.", '/finance/invoices');
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('finance.view');
        $invoice = Database::selectOne(
            'SELECT i.*, s.admission_number, u.first_name, u.last_name
               FROM invoices i JOIN students s ON s.id=i.student_id JOIN users u ON u.id=s.user_id
              WHERE i.id=?',
            [(int)$id]
        );
        if (!$invoice) { throw new HttpException(404); }
        $items = Database::select('SELECT ii.*, ft.name AS fee_type_name FROM invoice_items ii LEFT JOIN fee_types ft ON ft.id=ii.fee_type_id WHERE ii.invoice_id=?', [(int)$id]);
        return $this->view('finance.invoices.show', ['pageTitle' => 'Invoice', 'invoice' => $invoice, 'items' => $items]);
    }

    public function cancel(Request $request, string $id): never
    {
        $this->authorize('finance.edit');
        $this->verifyCsrf($request);
        Database::statement("UPDATE invoices SET status='cancelled', updated_at=NOW() WHERE id=?", [(int)$id]);
        $this->success('Invoice cancelled.', '/finance/invoices/' . $id);
    }
}
