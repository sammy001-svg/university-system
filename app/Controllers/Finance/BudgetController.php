<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Support\Lookup;

/**
 * Budgets by academic year, department and expense category, against actual spending:
 * approved and paid expenses dated within the academic year.
 */
final class BudgetController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('budgets.view');
        $years  = Lookup::academicYears();
        $yearId = (int) $request->query('year', (int) (Database::scalar('SELECT academic_year_id FROM semesters WHERE is_current = 1 LIMIT 1') ?: array_key_first($years)));
        $year   = Database::selectOne('SELECT * FROM academic_years WHERE id = ?', [$yearId]);

        $lines = [];
        foreach (Database::select(
            'SELECT b.*, COALESCE(d.name, "Institution-wide") AS department_name FROM budget_lines b LEFT JOIN departments d ON d.id = b.department_id
              WHERE b.academic_year_id = ? ORDER BY department_name, b.category', [$yearId]) as $b) {
            $lines[($b['department_id'] ?? 0) . '|' . $b['category']] = $b + ['actual' => 0.0];
        }
        // Spending in the year, matched to budget lines by department and category.
        if ($year !== null) {
            foreach (Database::select(
                'SELECT e.department_id, e.category, SUM(e.amount) AS actual, COALESCE(d.name, "Institution-wide") AS department_name
                   FROM expenses e LEFT JOIN departments d ON d.id = e.department_id
                  WHERE e.status IN ("approved","paid") AND e.expense_date BETWEEN ? AND ? GROUP BY e.department_id, e.category, d.name',
                [$year['start_date'], $year['end_date']]) as $x) {
                $key = ($x['department_id'] ?? 0) . '|' . $x['category'];
                if (!isset($lines[$key])) {
                    $lines[$key] = ['id' => null, 'department_id' => $x['department_id'], 'department_name' => $x['department_name'],
                                    'category' => $x['category'], 'amount' => 0, 'notes' => null, 'actual' => 0.0];
                }
                $lines[$key]['actual'] = (float) $x['actual'];
            }
        }
        uasort($lines, static fn (array $a, array $b): int => [$a['department_name'], $a['category']] <=> [$b['department_name'], $b['category']]);

        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $l): array => [$l['department_name'], $l['category'], $l['amount'], $l['actual'], (float) $l['amount'] - $l['actual']],
                array_values($lines)), 'budget-' . ($year['name'] ?? '') . '.csv', ['Department', 'Category', 'Budget', 'Spent', 'Remaining']);
        }
        return $this->view('finance.budgets', [
            'pageTitle'   => 'Budgets',
            'years'       => $years,
            'yearId'      => $yearId,
            'year'        => $year,
            'lines'       => array_values($lines),
            'departments' => Lookup::departments(),
            'categories'  => array_column(Database::select('SELECT DISTINCT category FROM expenses UNION SELECT DISTINCT category FROM budget_lines ORDER BY 1'), 'category'),
            'canManage'   => Auth::can('budgets.manage'),
        ]);
    }

    public function save(Request $request): never
    {
        $this->authorize('budgets.manage');
        $this->verifyCsrf($request);
        $yearId = (int) $request->input('academic_year_id');
        $category = trim((string) $request->input('category', ''));
        $amount = round((float) $request->input('amount'), 2);
        $back = '/finance/budgets?year=' . $yearId;
        if ($category === '' || $amount < 0) {
            $this->error('Enter the category and a budget amount.', $back);
        }
        $dept = $request->input('department_id') ? (int) $request->input('department_id') : null;
        if ($request->input('remove') === '1') {
            Database::statement('DELETE FROM budget_lines WHERE academic_year_id = ? AND category = ? AND department_id <=> ?', [$yearId, $category, $dept]);
            $this->success('Budget line removed.', $back);
        }
        $existing = Database::scalar('SELECT id FROM budget_lines WHERE academic_year_id = ? AND category = ? AND department_id <=> ?', [$yearId, $category, $dept]);
        if ($existing) {
            Database::statement('UPDATE budget_lines SET amount = ?, notes = ? WHERE id = ?', [$amount, trim((string) $request->input('notes', '')) ?: null, $existing]);
        } else {
            Database::statement('INSERT INTO budget_lines (academic_year_id, department_id, category, amount, notes) VALUES (?, ?, ?, ?, ?)',
                [$yearId, $dept, mb_substr($category, 0, 80), $amount, trim((string) $request->input('notes', '')) ?: null]);
        }
        Audit::log('budget', 'finance', 'budget_lines', null, "Budget {$category}: " . money($amount));
        $this->success('Budget saved.', $back);
    }
}
