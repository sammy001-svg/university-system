<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Setting;

final class SettingController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('settings.manage');

        $rows     = Database::select('SELECT * FROM settings ORDER BY setting_group, id');
        $grouped  = [];
        foreach ($rows as $row) {
            $grouped[$row['setting_group']][] = $row;
        }

        return $this->view('admin.settings', [
            'pageTitle' => 'System Settings',
            'grouped'   => $grouped,
            'active'    => (string) $request->query('group', array_key_first($grouped) ?? 'general'),
        ]);
    }

    public function save(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verifyCsrf($request);

        $submitted = $request->array('settings');
        $known     = Database::select('SELECT setting_key, data_type, setting_value FROM settings');
        $changed   = 0;

        foreach ($known as $setting) {
            $key = $setting['setting_key'];
            if (!array_key_exists($key, $submitted)) {
                // Unchecked checkboxes are absent from the POST body.
                if ($setting['data_type'] === 'boolean' && $request->has('settings_present')) {
                    if ($setting['setting_value'] !== '0') {
                        Setting::set($key, '0', 'boolean', '');
                        $changed++;
                    }
                }
                continue;
            }

            $value = is_array($submitted[$key]) ? json_encode($submitted[$key]) : trim((string) $submitted[$key]);
            if ($value !== (string) $setting['setting_value']) {
                Database::statement('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$value, $key]);
                $changed++;
            }
        }

        Setting::flush();
        Audit::log('settings_update', 'settings', 'settings', null, $changed . ' setting(s) changed');

        $this->success($changed === 0 ? 'No changes to save.' : $changed . ' setting(s) updated.', '/admin/settings');
    }
}
