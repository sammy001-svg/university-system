<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\FeeStructure;

final class FeeStructureController extends Controller
{
    private FeeStructure $model;
    public function __construct() { $this->model = new FeeStructure(); }

    public function index(Request $request): string
    {
        $this->authorize('finance.view');
        $structures = Database::select(
            'SELECT fs.*, p.name AS program_name, ay.name AS academic_year
               FROM fee_structures fs
          LEFT JOIN programs p      ON p.id = fs.program_id
          LEFT JOIN academic_years ay ON ay.id = fs.academic_year_id
              ORDER BY ay.start_date DESC, p.name'
        );
        return $this->view('finance.fee-structures.index', ['pageTitle' => 'Fee Structures', 'structures' => $structures]);
    }

    public function create(Request $request): string
    {
        $this->authorize('finance.manage');
        return $this->view('finance.fee-structures.form', [
            'pageTitle'    => 'New Fee Structure',
            'record'       => [],
            'isNew'        => true,
            'programs'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM programs WHERE status='active' ORDER BY name"),
            'academicYears'=> Database::select('SELECT id, name FROM academic_years ORDER BY start_date DESC'),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'program_id'       => 'nullable|integer|exists:programs,id',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'study_mode'       => 'required|in:full_time,part_time,evening,distance',
            'year_of_study'    => 'required|integer|between:1,6',
            'semester_number'  => 'required|integer|between:1,4',
            'name'             => 'required|max:120',
        ]);
        $id = $this->model->create($data);
        $this->success('Fee structure created.', '/finance/fee-structures/' . $id);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('finance.view');
        $structure = $this->findOrFail($this->model, (int) $id, 'Fee Structure');
        $items = Database::select(
            'SELECT fsi.*, ft.name AS fee_type_name FROM fee_structure_items fsi JOIN fee_types ft ON ft.id = fsi.fee_type_id WHERE fsi.fee_structure_id = ?',
            [(int) $id]
        );
        return $this->view('finance.fee-structures.show', [
            'pageTitle' => 'Fee Structure',
            'structure' => $structure,
            'items'     => $items,
            'feeTypes'  => Database::select('SELECT id, name FROM fee_types ORDER BY name'),
        ]);
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('finance.manage');
        $record = $this->findOrFail($this->model, (int) $id, 'Fee Structure');
        return $this->view('finance.fee-structures.form', [
            'pageTitle'    => 'Edit Fee Structure',
            'record'       => $record,
            'isNew'        => false,
            'programs'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM programs WHERE status='active' ORDER BY name"),
            'academicYears'=> Database::select('SELECT id, name FROM academic_years ORDER BY start_date DESC'),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['name' => 'required|max:120']);
        $this->model->update((int) $id, $data);
        $this->success('Fee structure updated.', '/finance/fee-structures');
    }

    public function addItem(Request $request, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'fee_type_id' => 'required|integer|exists:fee_types,id',
            'amount'      => 'required|numeric',
        ]);
        $data['fee_structure_id'] = (int) $id;
        Database::statement(
            'INSERT INTO fee_structure_items (fee_structure_id, fee_type_id, amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)',
            [$data['fee_structure_id'], $data['fee_type_id'], $data['amount']]
        );
        $this->success('Item added.', '/finance/fee-structures/' . $id);
    }

    public function removeItem(Request $request, string $structure, string $id): never
    {
        $this->authorize('finance.manage');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM fee_structure_items WHERE id=? AND fee_structure_id=?', [(int)$id, (int)$structure]);
        $this->success('Item removed.', '/finance/fee-structures/' . $structure);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('finance.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int) $id);
        $this->success('Fee structure deleted.', '/finance/fee-structures');
    }
}
