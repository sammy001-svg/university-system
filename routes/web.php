<?php
/**
 * Application routes.
 *
 * $router is provided by public/index.php.
 * Middleware: auth, guest, role:<slug>, permission:<slug[,slug]>
 */
declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;

/* ------------------------------------------------------------------ */
/*  Public                                                             */
/* ------------------------------------------------------------------ */
$router->get('/', static fn () => Response::redirect('/dashboard'));

$router->group(['middleware' => ['guest']], static function ($r): void {
    $r->get('/login', 'Auth\LoginController@showLogin');
    $r->post('/login', 'Auth\LoginController@login');
    $r->get('/password/forgot', 'Auth\PasswordController@showForgot');
    $r->post('/password/forgot', 'Auth\PasswordController@sendResetLink');
    $r->get('/password/reset', 'Auth\PasswordController@showReset');
    $r->post('/password/reset', 'Auth\PasswordController@reset');
});

// Public online application portal
$router->get('/apply', 'Admissions\PublicApplicationController@show');
$router->post('/apply', 'Admissions\PublicApplicationController@submit');
$router->get('/apply/status', 'Admissions\PublicApplicationController@status');
$router->post('/apply/status', 'Admissions\PublicApplicationController@lookup');

$router->post('/logout', 'Auth\LoginController@logout', ['auth']);

/* ------------------------------------------------------------------ */
/*  Authenticated                                                      */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['auth']], static function ($r): void {

    $r->get('/dashboard', 'DashboardController@index');
    $r->get('/search', 'DashboardController@search');
    $r->get('/profile', 'ProfileController@show');
    $r->post('/profile', 'ProfileController@update');

    $r->get('/password/change', 'Auth\PasswordController@showChange');
    $r->post('/password/change', 'Auth\PasswordController@change');

    $r->get('/notifications', 'Comms\NotificationController@index');
    $r->get('/notifications/{id}/read', 'Comms\NotificationController@read');
    $r->post('/notifications/read-all', 'Comms\NotificationController@readAll');

    $r->get('/announcements', 'Comms\AnnouncementController@index');
    $r->get('/announcements/create', 'Comms\AnnouncementController@create');
    $r->post('/announcements', 'Comms\AnnouncementController@store');
    $r->get('/announcements/{id}', 'Comms\AnnouncementController@show');
    $r->get('/announcements/{id}/edit', 'Comms\AnnouncementController@edit');
    $r->put('/announcements/{id}', 'Comms\AnnouncementController@update');
    $r->delete('/announcements/{id}', 'Comms\AnnouncementController@destroy');

    $r->get('/messages', 'Comms\MessageController@inbox');
    $r->get('/messages/sent', 'Comms\MessageController@sent');
    $r->get('/messages/compose', 'Comms\MessageController@compose');
    $r->post('/messages', 'Comms\MessageController@send');
    $r->get('/messages/{id}', 'Comms\MessageController@show');

    $r->get('/events', 'Comms\EventController@index');
    $r->get('/events/create', 'Comms\EventController@create');
    $r->post('/events', 'Comms\EventController@store');
    $r->get('/events/{id}/edit', 'Comms\EventController@edit');
    $r->put('/events/{id}', 'Comms\EventController@update');
    $r->delete('/events/{id}', 'Comms\EventController@destroy');
});

/**
 * Register the seven conventional CRUD routes for a resource controller.
 */
$resource = static function ($r, string $path, string $controller): void {
    $r->get($path, $controller . '@index');
    $r->get($path . '/create', $controller . '@create');
    $r->post($path, $controller . '@store');
    $r->get($path . '/{id}/edit', $controller . '@edit');
    $r->put($path . '/{id}', $controller . '@update');
    $r->patch($path . '/{id}', $controller . '@update');
    $r->delete($path . '/{id}', $controller . '@destroy');
};

/* ------------------------------------------------------------------ */
/*  Academics                                                          */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/academics', 'middleware' => ['auth']], static function ($r) use ($resource): void {
    $resource($r, '/faculties',   'Academics\FacultyController');
    $resource($r, '/departments', 'Academics\DepartmentController');
    $resource($r, '/programs',    'Academics\ProgramController');
    $resource($r, '/courses',     'Academics\CourseController');
    $resource($r, '/years',       'Academics\AcademicYearController');
    $resource($r, '/semesters',   'Academics\SemesterController');
    $resource($r, '/offerings',   'Academics\OfferingController');

    $r->get('/semesters/{id}/activate', 'Academics\SemesterController@activate');

    $r->get('/programs/{id}/curriculum', 'Academics\CurriculumController@show');
    $r->post('/programs/{id}/curriculum', 'Academics\CurriculumController@add');
    $r->delete('/programs/{program}/curriculum/{id}', 'Academics\CurriculumController@remove');

    $r->get('/courses/{id}/prerequisites', 'Academics\CurriculumController@prerequisites');
    $r->post('/courses/{id}/prerequisites', 'Academics\CurriculumController@addPrerequisite');
    $r->delete('/courses/{course}/prerequisites/{id}', 'Academics\CurriculumController@removePrerequisite');

    $r->get('/offerings/{id}/roster', 'Academics\OfferingController@roster');
    $r->post('/offerings/generate', 'Academics\OfferingController@generate');

    $r->get('/registrations', 'Academics\RegistrationController@index');
    $r->get('/registrations/create', 'Academics\RegistrationController@create');
    $r->post('/registrations', 'Academics\RegistrationController@store');
    $r->post('/registrations/{id}/approve', 'Academics\RegistrationController@approve');
    $r->post('/registrations/{id}/reject', 'Academics\RegistrationController@reject');
    $r->delete('/registrations/{id}', 'Academics\RegistrationController@drop');
});

/* ------------------------------------------------------------------ */
/*  Admissions                                                         */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/admissions', 'middleware' => ['auth']], static function ($r) use ($resource): void {
    $resource($r, '/intakes', 'Admissions\IntakeController');

    $r->get('/applications', 'Admissions\ApplicationController@index');
    $r->get('/applications/create', 'Admissions\ApplicationController@create');
    $r->post('/applications', 'Admissions\ApplicationController@store');
    $r->get('/applications/{id}', 'Admissions\ApplicationController@show');
    $r->post('/applications/{id}/review', 'Admissions\ApplicationController@review');
    $r->post('/applications/{id}/enrol', 'Admissions\ApplicationController@enrol');
    $r->delete('/applications/{id}', 'Admissions\ApplicationController@destroy');
});

/* ------------------------------------------------------------------ */
/*  Students & staff                                                   */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['auth']], static function ($r): void {
    $r->get('/students', 'Students\StudentController@index');
    $r->get('/students/create', 'Students\StudentController@create');
    $r->post('/students', 'Students\StudentController@store');
    $r->get('/students/export', 'Students\StudentController@export');
    $r->get('/students/{id}', 'Students\StudentController@show');
    $r->get('/students/{id}/edit', 'Students\StudentController@edit');
    $r->put('/students/{id}', 'Students\StudentController@update');
    $r->delete('/students/{id}', 'Students\StudentController@destroy');
    $r->post('/students/{id}/status', 'Students\StudentController@changeStatus');
    $r->get('/students/{id}/transcript', 'Exams\TranscriptController@show');
    $r->get('/students/{id}/statement', 'Finance\StatementController@show');

    $r->get('/staff', 'Staff\StaffController@index');
    $r->get('/staff/create', 'Staff\StaffController@create');
    $r->post('/staff', 'Staff\StaffController@store');
    $r->get('/staff/{id}', 'Staff\StaffController@show');
    $r->get('/staff/{id}/edit', 'Staff\StaffController@edit');
    $r->put('/staff/{id}', 'Staff\StaffController@update');
    $r->delete('/staff/{id}', 'Staff\StaffController@destroy');
});

/* ------------------------------------------------------------------ */
/*  Teaching, attendance, exams and results                            */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['auth']], static function ($r) use ($resource): void {

    $r->get('/teaching', 'Lecturer\TeachingController@index');
    $r->get('/teaching/{id}', 'Lecturer\TeachingController@show');
    $r->get('/teaching/{id}/marks', 'Lecturer\MarkEntryController@edit');
    $r->post('/teaching/{id}/marks', 'Lecturer\MarkEntryController@save');
    $r->post('/teaching/{id}/publish', 'Lecturer\MarkEntryController@publish');
    $r->get('/teaching/{id}/assessments', 'Lecturer\AssessmentController@index');
    $r->post('/teaching/{id}/assessments', 'Lecturer\AssessmentController@store');
    $r->get('/teaching/assessments/{id}/scores', 'Lecturer\AssessmentController@scores');
    $r->post('/teaching/assessments/{id}/scores', 'Lecturer\AssessmentController@saveScores');
    $r->delete('/teaching/assessments/{id}', 'Lecturer\AssessmentController@destroy');

    $r->get('/attendance', 'Attendance\AttendanceController@index');
    $r->get('/attendance/sessions/create', 'Attendance\AttendanceController@create');
    $r->post('/attendance/sessions', 'Attendance\AttendanceController@store');
    $r->get('/attendance/sessions/{id}', 'Attendance\AttendanceController@mark');
    $r->post('/attendance/sessions/{id}', 'Attendance\AttendanceController@save');
    $r->delete('/attendance/sessions/{id}', 'Attendance\AttendanceController@destroy');
    $r->get('/attendance/report', 'Attendance\AttendanceController@report');

    $r->get('/timetable', 'Timetable\TimetableController@index');
    $r->get('/timetable/create', 'Timetable\TimetableController@create');
    $r->post('/timetable', 'Timetable\TimetableController@store');
    $r->delete('/timetable/{id}', 'Timetable\TimetableController@destroy');

    $r->get('/exams', 'Exams\ExamController@index');
    $r->get('/exams/create', 'Exams\ExamController@create');
    $r->post('/exams', 'Exams\ExamController@store');
    $r->get('/exams/{id}/edit', 'Exams\ExamController@edit');
    $r->put('/exams/{id}', 'Exams\ExamController@update');
    $r->delete('/exams/{id}', 'Exams\ExamController@destroy');

    $r->get('/exams/results', 'Exams\ResultController@index');
    $r->get('/exams/results/entry', 'Exams\ResultController@entry');
    $r->post('/exams/results/entry', 'Exams\ResultController@save');
    $r->post('/exams/results/publish', 'Exams\ResultController@publish');
    $r->post('/exams/results/compute', 'Exams\ResultController@compute');
    $r->get('/exams/transcripts', 'Exams\TranscriptController@index');
    $r->get('/exams/senate-list', 'Exams\ResultController@senateList');

    $resource($r, '/exams/grade-scale', 'Exams\GradeScaleController');
});

/* ------------------------------------------------------------------ */
/*  Finance                                                            */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/finance', 'middleware' => ['auth']], static function ($r) use ($resource): void {
    $resource($r, '/fee-types', 'Finance\FeeTypeController');
    $resource($r, '/scholarships', 'Finance\ScholarshipController');
    $resource($r, '/expenses', 'Finance\ExpenseController');

    $r->get('/fee-structures', 'Finance\FeeStructureController@index');
    $r->get('/fee-structures/create', 'Finance\FeeStructureController@create');
    $r->post('/fee-structures', 'Finance\FeeStructureController@store');
    $r->get('/fee-structures/{id}', 'Finance\FeeStructureController@show');
    $r->get('/fee-structures/{id}/edit', 'Finance\FeeStructureController@edit');
    $r->put('/fee-structures/{id}', 'Finance\FeeStructureController@update');
    $r->post('/fee-structures/{id}/items', 'Finance\FeeStructureController@addItem');
    $r->delete('/fee-structures/{structure}/items/{id}', 'Finance\FeeStructureController@removeItem');
    $r->delete('/fee-structures/{id}', 'Finance\FeeStructureController@destroy');

    $r->get('/invoices', 'Finance\InvoiceController@index');
    $r->get('/invoices/generate', 'Finance\InvoiceController@generateForm');
    $r->post('/invoices/generate', 'Finance\InvoiceController@generate');
    $r->get('/invoices/{id}', 'Finance\InvoiceController@show');
    $r->post('/invoices/{id}/cancel', 'Finance\InvoiceController@cancel');

    $r->get('/payments', 'Finance\PaymentController@index');
    $r->get('/payments/create', 'Finance\PaymentController@create');
    $r->post('/payments', 'Finance\PaymentController@store');
    $r->get('/payments/{id}/receipt', 'Finance\PaymentController@receipt');
    $r->post('/payments/{id}/reverse', 'Finance\PaymentController@reverse');

    $r->get('/payroll', 'Finance\PayrollController@index');
    $r->post('/payroll', 'Finance\PayrollController@createPeriod');
    $r->get('/payroll/{id}', 'Finance\PayrollController@show');
    $r->post('/payroll/{id}/process', 'Finance\PayrollController@process');
    $r->get('/payroll/payslip/{id}', 'Finance\PayrollController@payslip');
});

/* ------------------------------------------------------------------ */
/*  Library, hostel, student services and HR                           */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['auth']], static function ($r) use ($resource): void {
    $resource($r, '/library/categories', 'Library\CategoryController');
    $resource($r, '/library/books', 'Library\BookController');
    $r->get('/library/loans', 'Library\LoanController@index');
    $r->get('/library/loans/issue', 'Library\LoanController@issueForm');
    $r->post('/library/loans/issue', 'Library\LoanController@issue');
    $r->post('/library/loans/{id}/return', 'Library\LoanController@receive');
    $r->post('/library/loans/{id}/renew', 'Library\LoanController@renew');

    $resource($r, '/hostel/hostels', 'Hostel\HostelController');
    $resource($r, '/hostel/rooms', 'Hostel\RoomController');
    $r->get('/hostel/allocations', 'Hostel\AllocationController@index');
    $r->get('/hostel/allocations/create', 'Hostel\AllocationController@create');
    $r->post('/hostel/allocations', 'Hostel\AllocationController@store');
    $r->post('/hostel/allocations/{id}/checkout', 'Hostel\AllocationController@checkout');
    $r->delete('/hostel/allocations/{id}', 'Hostel\AllocationController@destroy');

    $r->get('/services/clearance', 'Students\ClearanceController@index');
    $r->get('/services/clearance/{student}', 'Students\ClearanceController@show');
    $r->post('/services/clearance/{student}', 'Students\ClearanceController@update');

    $resource($r, '/services/medical', 'Students\MedicalController');
    $resource($r, '/services/discipline', 'Students\DisciplineController');

    $r->get('/services/tickets', 'Comms\TicketController@index');
    $r->get('/services/tickets/create', 'Comms\TicketController@create');
    $r->post('/services/tickets', 'Comms\TicketController@store');
    $r->get('/services/tickets/{id}', 'Comms\TicketController@show');
    $r->post('/services/tickets/{id}/reply', 'Comms\TicketController@reply');
    $r->post('/services/tickets/{id}/status', 'Comms\TicketController@changeStatus');

    $resource($r, '/hr/leave-types', 'Staff\LeaveTypeController');
    $r->get('/hr/leave', 'Staff\LeaveController@index');
    $r->get('/hr/leave/create', 'Staff\LeaveController@create');
    $r->post('/hr/leave', 'Staff\LeaveController@store');
    $r->post('/hr/leave/{id}/decide', 'Staff\LeaveController@decide');

    $resource($r, '/documents', 'Comms\DocumentController');
});

/* ------------------------------------------------------------------ */
/*  Student portal                                                     */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/portal', 'middleware' => ['auth', 'permission:portal.access']], static function ($r): void {
    $r->get('/', 'Portal\PortalController@dashboard');
    $r->get('/profile', 'Portal\PortalController@profile');
    $r->post('/profile', 'Portal\PortalController@updateProfile');
    $r->get('/registration', 'Portal\RegistrationController@index');
    $r->post('/registration', 'Portal\RegistrationController@register');
    $r->delete('/registration/{id}', 'Portal\RegistrationController@drop');
    $r->get('/results', 'Portal\ResultController@index');
    $r->get('/transcript', 'Portal\ResultController@transcript');
    $r->get('/finance', 'Portal\FinanceController@index');
    $r->get('/finance/invoice/{id}', 'Portal\FinanceController@invoice');
    $r->get('/timetable', 'Portal\PortalController@timetable');
    $r->get('/attendance', 'Portal\PortalController@attendance');
    $r->get('/library', 'Portal\PortalController@library');
    $r->get('/hostel', 'Portal\PortalController@hostel');
    $r->get('/clearance', 'Portal\PortalController@clearance');
});

/* ------------------------------------------------------------------ */
/*  Administration & reports                                           */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/admin', 'middleware' => ['auth']], static function ($r) use ($resource): void {
    $r->get('/users', 'Admin\UserController@index');
    $r->get('/users/create', 'Admin\UserController@create');
    $r->post('/users', 'Admin\UserController@store');
    $r->get('/users/{id}/edit', 'Admin\UserController@edit');
    $r->put('/users/{id}', 'Admin\UserController@update');
    $r->delete('/users/{id}', 'Admin\UserController@destroy');
    $r->post('/users/{id}/reset-password', 'Admin\UserController@resetPassword');
    $r->post('/users/{id}/toggle', 'Admin\UserController@toggleStatus');

    $r->get('/roles', 'Admin\RoleController@index');
    $r->get('/roles/create', 'Admin\RoleController@create');
    $r->post('/roles', 'Admin\RoleController@store');
    $r->get('/roles/{id}/edit', 'Admin\RoleController@edit');
    $r->put('/roles/{id}', 'Admin\RoleController@update');
    $r->delete('/roles/{id}', 'Admin\RoleController@destroy');
    $r->get('/roles/{id}/permissions', 'Admin\RoleController@permissions');
    $r->post('/roles/{id}/permissions', 'Admin\RoleController@savePermissions');

    $r->get('/settings', 'Admin\SettingController@index');
    $r->post('/settings', 'Admin\SettingController@save');
    $r->get('/audit', 'Admin\AuditController@index');

    $resource($r, '/campuses', 'Admin\CampusController');
    $resource($r, '/rooms', 'Admin\RoomController');
    $resource($r, '/buildings', 'Admin\BuildingController');
});

$router->group(['prefix' => '/reports', 'middleware' => ['auth', 'permission:reports.view']], static function ($r): void {
    $r->get('/', 'Reports\ReportController@index');
    $r->get('/enrolment', 'Reports\ReportController@enrolment');
    $r->get('/academic', 'Reports\ReportController@academic');
    $r->get('/finance', 'Reports\ReportController@finance');
    $r->get('/attendance', 'Reports\ReportController@attendance');
    $r->get('/library', 'Reports\ReportController@library');
});
