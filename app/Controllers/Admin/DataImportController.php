<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Request;
use App\Services\DataImport\DataImportSync;
use App\Services\DataImport\Importer;

/**
 * Admin > Data Imports: what the automatic import of database/data/ did, and
 * login slips - first-time passwords for the accounts it created.
 */
final class DataImportController extends Controller
{
    /** Accounts a slip can be printed for, with the ID and programme / college shown on it. */
    private const ACCOUNTS_SQL = '
        SELECT u.id, u.username, u.title, u.first_name, u.last_name, u.other_name, u.user_type,
               u.password_hash = "' . Importer::NO_PASSWORD . '" AS awaiting,
               COALESCE(st.admission_number, sf.staff_number) AS id_number,
               COALESCE(p.name, sf.designation) AS detail,
               COALESCE(f.name, sfc.name) AS college
          FROM users u
          LEFT JOIN students st ON st.user_id = u.id
          LEFT JOIN programs p ON p.id = st.program_id
          LEFT JOIN departments d ON d.id = p.department_id
          LEFT JOIN faculties f ON f.id = d.faculty_id
          LEFT JOIN staff sf ON sf.user_id = u.id
          LEFT JOIN faculties sfc ON sfc.id = sf.faculty_id
         WHERE u.status = "active" AND u.deleted_at IS NULL AND u.last_login_at IS NULL';

    private const TYPES = ['student' => 'Students', 'lecturer' => 'Lecturers', 'staff' => 'Staff'];

    public function index(Request $request): string
    {
        $this->authorize('settings.manage');
        $sync = new DataImportSync(dirname(__DIR__, 3));
        DataImportSync::ensureTable();

        $imports = array_column(Database::select('SELECT * FROM data_imports ORDER BY file_name'), null, 'file_name');
        $files   = [];
        foreach ($sync->files() as $file) {
            $name         = basename($file);
            $row          = $imports[$name] ?? null;
            $files[$name] = ['row' => $row, 'changed' => $row !== null && $row['checksum'] !== sha1_file($file)];
            unset($imports[$name]);
        }

        return $this->view('admin.data-imports.index', [
            'pageTitle' => 'Data Imports',
            'files'     => $files,
            'removed'   => $imports,   // imported earlier, file since removed from database/data/
            'awaiting'  => Database::select(
                'SELECT user_type, COUNT(*) AS n FROM users WHERE password_hash = ? AND status = "active" AND deleted_at IS NULL GROUP BY user_type',
                [Importer::NO_PASSWORD]
            ),
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('settings.manage');
        $row = Database::selectOne('SELECT * FROM data_imports WHERE id = ?', [(int) $id]);
        if ($row === null) {
            $this->abort(404, 'Import not found.');
        }
        return $this->view('admin.data-imports.show', ['pageTitle' => $row['file_name'], 'import' => $row]);
    }

    /** Run any pending import now instead of waiting for the next page load (or re-run them all). */
    public function run(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verifyCsrf($request);
        @set_time_limit(0);

        $reports = (new DataImportSync(dirname(__DIR__, 3)))->importPending($request->input('all') === '1');
        Audit::log('import', 'admin', 'data_imports', null, 'Ran data imports: ' . ($reports === [] ? 'nothing pending' : implode(', ', array_keys($reports))));
        if ($reports === []) {
            $this->success('Every data file is already imported.', '/admin/data-imports');
        }
        $summary = implode('; ', array_map(static fn (string $f, $r): string => "{$f}: {$r->totals()}", array_keys($reports), $reports));
        $failed  = array_sum(array_map(static fn ($r): int => $r->failed, $reports));
        $failed > 0
            ? $this->warning('Imported with problems - ' . $summary, '/admin/data-imports')
            : $this->success('Imported - ' . $summary, '/admin/data-imports');
    }

    /** Accounts that have never signed in; by default only those still waiting for a first password. */
    public function slips(Request $request): string
    {
        $this->authorize('users.reset');
        [$sql, $params] = $this->accountFilter($request);
        return $this->view('admin.data-imports.slips', [
            'pageTitle' => 'Login Slips',
            'accounts'  => Database::select($sql . ' ORDER BY u.user_type, college, detail, u.last_name, u.first_name', $params),
            'types'     => self::TYPES,
        ]);
    }

    /**
     * Give the chosen accounts a fresh temporary password and print the slips.
     * The passwords are shown on this page only; they are not stored anywhere in plain text.
     */
    public function issue(Request $request): string
    {
        $this->authorize('users.reset');
        $this->verifyCsrf($request);
        @set_time_limit(0);

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        if ($ids === []) {
            $this->error('Tick at least one account to print a slip for.', '/admin/data-imports/slips');
        }
        $accounts = Database::select(
            self::ACCOUNTS_SQL . ' AND u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY u.user_type, college, detail, u.last_name, u.first_name',
            $ids
        );

        $slips = [];
        foreach ($accounts as $account) {
            $password = Hash::tempPassword();
            // Cost 10 keeps a batch of hundreds quick; the hash is upgraded at the person's first sign-in.
            Database::statement(
                'UPDATE users SET password_hash = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?',
                [password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]), $account['id']]
            );
            $slips[] = $account + ['password' => $password];
        }
        Audit::log('password_reset', 'users', 'users', null, 'Issued login slips (temporary passwords) for ' . count($slips) . ' account(s)');

        return $this->view('admin.data-imports.print-slips', [
            'pageTitle' => 'Login Slips',
            'slips'     => $slips,
            'loginUrl'  => url('/login'),
        ]);
    }

    /** @return array{0:string,1:array} */
    private function accountFilter(Request $request): array
    {
        $sql    = self::ACCOUNTS_SQL;
        $params = [];
        if ($request->query('scope') !== 'never') {
            $sql     .= ' AND u.password_hash = ?';
            $params[] = Importer::NO_PASSWORD;
        }
        if (isset(self::TYPES[(string) $request->query('type')])) {
            $sql     .= ' AND u.user_type = ?';
            $params[] = $request->query('type');
        }
        if ($request->filled('q')) {
            $like     = '%' . $request->query('q') . '%';
            $sql     .= ' AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR st.admission_number LIKE ? OR sf.staff_number LIKE ?)';
            $params   = array_merge($params, array_fill(0, 5, $like));
        }
        return [$sql, $params];
    }
}
