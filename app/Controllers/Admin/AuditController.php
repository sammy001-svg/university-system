<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;

final class AuditController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('audit.view');

        $query = QueryBuilder::table('audit_logs', 'a')
            ->select(
                'a.*',
                'CONCAT(u.first_name, " ", u.last_name) AS actor_name',
                'u.username AS actor_username'
            )
            ->leftJoin('users u', 'u.id = a.user_id');

        if ($request->filled('q')) {
            $query->search((string) $request->query('q'), ['a.description', 'a.entity', 'u.username', 'a.action']);
        }
        if ($request->filled('module')) {
            $query->where('a.module', $request->query('module'));
        }
        if ($request->filled('action')) {
            $query->where('a.action', $request->query('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('a.user_id', $request->query('user_id'));
        }
        if ($request->filled('from')) {
            $query->where('a.created_at', '>=', $request->query('from') . ' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('a.created_at', '<=', $request->query('to') . ' 23:59:59');
        }

        if ($request->query('export') === 'csv') {
            $rows = (clone $query)->orderBy('a.created_at', 'DESC')->limit(5000)->get();
            $out  = array_map(static fn (array $r): array => [
                $r['created_at'], $r['actor_username'] ?? 'system', $r['action'],
                $r['module'] ?? '', $r['entity'] ?? '', $r['entity_id'] ?? '',
                $r['description'] ?? '', $r['ip_address'] ?? '',
            ], $rows);
            Response::csv($out, 'audit-trail-' . date('Ymd-His') . '.csv',
                ['Timestamp', 'User', 'Action', 'Module', 'Entity', 'Entity ID', 'Description', 'IP address']);
        }

        $result = $query->orderBy('a.created_at', 'DESC')->paginate($this->page($request), $this->perPage($request, 40));

        return $this->view('admin.audit', [
            'pageTitle' => 'Audit Trail',
            'result'    => $result,
            'modules'   => array_column(Database::select('SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module'), 'module'),
            'actions'   => array_column(Database::select('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action'),
        ]);
    }
}
