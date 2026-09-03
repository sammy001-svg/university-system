<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Setting;
use App\Core\Upload;
use Throwable;

final class SettingController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('settings.manage');

        $this->ensureDefaultSettings();

        $rows = Database::select('SELECT * FROM settings ORDER BY setting_group, id');
        $grouped = [];
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

        $this->ensureDefaultSettings();

        $submitted = $request->array('settings');
        $known     = Database::select('SELECT setting_key, data_type, setting_value FROM settings');
        $changed   = 0;

        foreach ($known as $setting) {
            $key  = $setting['setting_key'];
            $type = $setting['data_type'];

            // Handle file uploads (e.g. institution_logo)
            if ($type === 'file' || $key === 'institution_logo') {
                if (isset($_FILES[$key]) && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    try {
                        $upload = Upload::store($_FILES[$key], 'logo', Upload::IMAGES, 4);
                        if (!empty($setting['setting_value'])) {
                            Upload::delete($setting['setting_value']);
                        }
                        Database::statement('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [$upload['path'], $key]);
                        $changed++;
                    } catch (Throwable $e) {
                        $this->error('Logo upload error: ' . $e->getMessage(), '/admin/settings');
                    }
                }
                continue;
            }

            if (!array_key_exists($key, $submitted)) {
                // Unchecked checkboxes are absent from POST body.
                if ($type === 'boolean' && $request->has('settings_present')) {
                    if ($setting['setting_value'] !== '0') {
                        Database::statement('UPDATE settings SET setting_value = "0" WHERE setting_key = ?', [$key]);
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

        $this->success($changed === 0 ? 'No changes to save.' : $changed . ' setting(s) updated successfully.', '/admin/settings');
    }

    private function ensureDefaultSettings(): void
    {
        $defaults = [
            ['institution_name', 'Best Brain University', 'general', 'string', 'Institution Name'],
            ['institution_short_name', 'BBU', 'general', 'string', 'Short Name / Abbreviation'],
            ['institution_motto', 'Excellence in Education & Innovation', 'general', 'string', 'Institution Motto'],
            ['institution_email', 'info@bestbrain.edu.lr', 'general', 'string', 'Official Email'],
            ['institution_phone', '+231 77 000 000', 'general', 'string', 'Telephone / Contact'],
            ['institution_address', 'Monrovia, Liberia', 'general', 'text', 'Postal & Physical Address'],
            ['institution_country', 'Liberia', 'general', 'string', 'Country'],
            ['institution_website', 'https://www.bestbrain.edu.lr', 'general', 'string', 'Website URL'],
            ['institution_logo', '', 'general', 'file', 'Institution Logo'],
            ['support_email', 'ict@bestbrain.edu.lr', 'general', 'string', 'ICT Helpdesk Email'],
            ['academic_year_label', '2025/2026', 'academic', 'string', 'Current Academic Year Label'],
            ['min_credits_per_semester', '12', 'academic', 'integer', 'Minimum Credits / Semester'],
            ['max_credits_per_semester', '21', 'academic', 'integer', 'Maximum Credits / Semester'],
            ['pass_mark', '40', 'academic', 'integer', 'Pass Mark (%)'],
            ['coursework_weight', '30', 'academic', 'integer', 'Coursework Weight (%)'],
            ['exam_weight', '70', 'academic', 'integer', 'Exam Weight (%)'],
            ['attendance_threshold', '75', 'academic', 'integer', 'Attendance Threshold (%)'],
            ['require_registration_approval', '1', 'academic', 'boolean', 'Require Registration Approval'],
            ['fee_threshold_percent', '60', 'finance', 'integer', 'Minimum Fee Paid (%) Before Registration'],
            ['currency_code', 'USD', 'finance', 'string', 'Currency Code'],
            ['currency_symbol', '$', 'finance', 'string', 'Currency Symbol'],
            ['invoice_due_days', '30', 'finance', 'integer', 'Invoice Due Days'],
            ['late_payment_penalty', '0', 'finance', 'decimal', 'Late Payment Penalty (%)'],
            ['library_loan_days', '14', 'library', 'integer', 'Standard Loan Period (Days)'],
            ['library_borrow_limit', '3', 'library', 'integer', 'Borrow Limit (Titles)'],
            ['library_fine_per_day', '1.00', 'library', 'decimal', 'Overdue Fine Per Day ($)'],
            ['library_max_renewals', '2', 'library', 'integer', 'Maximum Renewals'],
            ['hostel_application_open', '1', 'hostel', 'boolean', 'Hostel Applications Open'],
        ];

        foreach ($defaults as [$key, $value, $group, $type, $label]) {
            $exists = Database::scalar('SELECT COUNT(*) FROM settings WHERE setting_key = ?', [$key]);
            if (!$exists) {
                Database::statement(
                    'INSERT INTO settings (setting_key, setting_value, setting_group, data_type, label) VALUES (?, ?, ?, ?, ?)',
                    [$key, $value, $group, $type, $label]
                );
            }
        }
    }
}
