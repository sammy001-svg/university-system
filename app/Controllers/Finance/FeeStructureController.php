<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Models\FeeStructure;

final class FeeStructureController extends Controller
{
    private FeeStructure $model;
    public function __construct() { $this->model = new FeeStructure(); }

    public function index(Request $request): string
    {
        $this->authorize('finance.view');
        $structures = $this->model->listing()
            ->orderByRaw('ay.start_date DESC, fs.faculty_id IS NOT NULL, fs.department_id IS NOT NULL, fs.program_id IS NOT NULL, fs.name')
            ->get();
        return $this->view('finance.fee-structures.index', ['pageTitle' => 'Fee Structures', 'structures' => $structures]);
    }

    public function create(Request $request): string
    {
        $this->authorize('finance.manage');
        return $this->view('finance.fee-structures.form', ['pageTitle' => 'New Fee Structure', 'record' => [], 'isNew' => true] + $this->formOptions());
    }

    public function store(Request $request): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $id = $this->model->create($this->validated($request));
        $this->success('Fee structure created. Add its fee items and installment plan.', '/finance/fee-structures/' . $id);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('finance.view');
        $structure = $this->model->listing()->where('fs.id', (int) $id)->first();
        if ($structure === null) {
            throw new HttpException(404, 'Fee Structure not found.');
        }
        $items = Database::select(
            'SELECT fsi.*, ft.name AS fee_type_name, ft.category FROM fee_structure_items fsi JOIN fee_types ft ON ft.id = fsi.fee_type_id WHERE fsi.fee_structure_id = ? ORDER BY ft.category = "tuition" DESC, ft.name',
            [(int) $id]
        );

        $tuition = 0.0;
        $other   = 0.0;
        foreach ($items as $item) {
            $item['category'] === 'tuition' ? $tuition += (float) $item['amount'] : $other += (float) $item['amount'];
        }
        $plan = $this->model->installments((int) $id);

        return $this->view('finance.fee-structures.show', [
            'pageTitle'    => 'Fee Structure',
            'structure'    => $structure,
            'items'        => $items,
            'feeTypes'     => Database::select('SELECT id, name FROM fee_types WHERE status = "active" ORDER BY name'),
            'plan'         => $plan,
            'hasSavedPlan' => isset($plan[0]['id']),
            'preview'      => FeeStructure::splitIntoInstallments($plan, $tuition, $other, 0.0),
        ]);
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('finance.manage');
        $record = $this->findOrFail($this->model, (int) $id, 'Fee Structure');
        return $this->view('finance.fee-structures.form', ['pageTitle' => 'Edit Fee Structure', 'record' => $record, 'isNew' => false] + $this->formOptions());
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $this->findOrFail($this->model, (int) $id, 'Fee Structure');
        $this->model->update((int) $id, $this->validated($request));
        $this->success('Fee structure updated.', '/finance/fee-structures/' . $id);
    }

    public function addItem(Request $request, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'fee_type_id' => 'required|integer|exists:fee_types,id',
            'amount'      => 'required|numeric',
        ]);
        Database::statement(
            'INSERT INTO fee_structure_items (fee_structure_id, fee_type_id, amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)',
            [(int) $id, $data['fee_type_id'], $data['amount']]
        );
        $this->model->recalculateTotal((int) $id);
        $this->success('Item added.', '/finance/fee-structures/' . $id);
    }

    public function removeItem(Request $request, string $structure, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM fee_structure_items WHERE id=? AND fee_structure_id=?', [(int)$id, (int)$structure]);
        $this->model->recalculateTotal((int) $structure);
        $this->success('Item removed.', '/finance/fee-structures/' . $structure);
    }

    public function addInstallment(Request $request, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'installment_number' => 'required|integer|between:1,6',
            'label'              => 'required|max:60',
            'tuition_percent'    => 'required|numeric|between:0,100',
            'due_days'           => 'required|integer',
        ]);

        $others = (float) Database::scalar(
            'SELECT COALESCE(SUM(tuition_percent), 0) FROM fee_structure_installments WHERE fee_structure_id = ? AND installment_number != ?',
            [(int) $id, $data['installment_number']]
        );
        if ($others + (float) $data['tuition_percent'] > 100.001) {
            $this->error('Installment shares of tuition cannot add up to more than 100%.', '/finance/fee-structures/' . $id);
        }

        Database::statement(
            'INSERT INTO fee_structure_installments (fee_structure_id, installment_number, label, tuition_percent, due_days)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE label = VALUES(label), tuition_percent = VALUES(tuition_percent), due_days = VALUES(due_days)',
            [(int) $id, $data['installment_number'], $data['label'], $data['tuition_percent'], $data['due_days']]
        );
        $this->success('Installment saved.', '/finance/fee-structures/' . $id);
    }

    public function removeInstallment(Request $request, string $structure, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM fee_structure_installments WHERE id = ? AND fee_structure_id = ?', [(int) $id, (int) $structure]);
        $this->success('Installment removed.', '/finance/fee-structures/' . $structure);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('finance.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int) $id);
        $this->success('Fee structure deleted.', '/finance/fee-structures');
    }

    /** Validate the structure form; only the column for the chosen scope is kept. */
    private function validated(Request $request): array
    {
        $data = $this->validate($request, [
            'name'             => 'required|max:150',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'scope'            => 'required|in:institution,faculty,department,program',
            'faculty_id'       => 'nullable|integer|exists:faculties,id',
            'department_id'    => 'nullable|integer|exists:departments,id',
            'program_id'       => 'nullable|integer|exists:programs,id',
            'student_status'   => 'required|in:all,new,continuing',
            'year_of_study'    => 'nullable|integer|between:1,6',
            'semester_number'  => 'nullable|integer|between:1,4',
            'study_mode'       => 'nullable|in:full_time,part_time,evening,distance',
            'status'           => 'required|in:draft,active,archived',
        ]);

        foreach (['faculty' => 'faculty_id', 'department' => 'department_id', 'program' => 'program_id'] as $scope => $column) {
            if ($data['scope'] !== $scope) {
                $data[$column] = null;
            } elseif (empty($data[$column])) {
                Session::keepOldInput($request->all());
                $this->error('Choose the ' . ($scope === 'faculty' ? 'college' : $scope) . ' this bill applies to.');
            }
        }
        unset($data['scope']);
        $data['study_mode'] = ($data['study_mode'] ?? '') === '' ? null : $data['study_mode'];
        return $data;
    }

    private function formOptions(): array
    {
        return [
            'faculties'     => Database::select("SELECT id, name AS label FROM faculties WHERE status='active' ORDER BY name"),
            'departments'   => Database::select("SELECT id, name AS label FROM departments WHERE status='active' ORDER BY name"),
            'programs'      => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM programs WHERE status='active' ORDER BY name"),
            'academicYears' => Database::select('SELECT id, name FROM academic_years ORDER BY start_date DESC'),
        ];
    }
}
