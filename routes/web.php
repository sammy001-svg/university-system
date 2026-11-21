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
    $r->get('/login/two-factor', 'Auth\LoginController@twoFactorForm');
    $r->post('/login/two-factor', 'Auth\LoginController@twoFactor');
});

// Public careers page: open vacancies and online applications.
$router->get('/careers', 'HR\CareersController@index');
$router->get('/careers/{id}', 'HR\CareersController@show');
$router->post('/careers/{id}', 'HR\CareersController@apply');

// Public check of an issued document (QR code on letters, transcripts and ID cards).
$router->get('/verify', 'Students\RecordController@verify');
$router->get('/verify/{code}', 'Students\RecordController@verify');

// Public online application portal
$router->get('/apply', 'Admissions\PublicApplicationController@show');
$router->post('/apply', 'Admissions\PublicApplicationController@submit');
$router->get('/apply/status', 'Admissions\PublicApplicationController@status');
$router->post('/apply/status', 'Admissions\PublicApplicationController@lookup');

$router->post('/logout', 'Auth\LoginController@logout', ['auth']);

/* ------------------------------------------------------------------ */
/*  Authenticated                                                      */
/* ------------------------------------------------------------------ */
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

$router->group(['middleware' => ['auth']], static function ($r) use ($resource): void {

    $r->get('/dashboard', 'DashboardController@index');
    $r->get('/search', 'DashboardController@search');
    $r->get('/profile', 'ProfileController@show');
    $r->post('/profile', 'ProfileController@update');

    // Account security: two-step sign-in and signed-in devices.
    $r->get('/account/security', 'SecurityController@index');
    $r->post('/account/security/two-factor/start', 'SecurityController@startTwoFactor');
    $r->post('/account/security/two-factor/enable', 'SecurityController@enableTwoFactor');
    $r->post('/account/security/two-factor/disable', 'SecurityController@disableTwoFactor');
    $r->post('/account/security/recovery-codes', 'SecurityController@newRecoveryCodes');
    $r->post('/account/security/sessions/{id}', 'SecurityController@revokeSession');

    // Course files, served only to the class (materials, assignment briefs, submitted work).
    $r->get('/files/materials/{id}', 'FileController@material');
    $r->get('/files/assignments/{id}', 'FileController@assignmentBrief');
    $r->get('/files/submissions/{id}', 'FileController@submission');

    // Paying fees online (students and linked parents/sponsors).
    $r->get('/pay/status/{reference}', 'PayController@status');
    $r->get('/pay/status/{reference}/check', 'PayController@check');
    $r->get('/pay/{student}/{invoice}', 'PayController@form');
    $r->post('/pay/{student}/{invoice}', 'PayController@start');

    // Family portal (parents and sponsors).
    $r->get('/family', 'FamilyController@index');
    $r->get('/family/{id}', 'FamilyController@show');
    $r->get('/family/{id}/fees', 'FamilyController@fees');

    // Official documents and degree audits.
    $r->get('/issued-documents/{id}', 'Students\RecordController@print');
    $r->post('/issued-documents/{id}/revoke', 'Students\RecordController@revoke');
    $r->get('/students/{id}/degree-audit', 'Students\RecordController@audit');
    $r->get('/students/{id}/documents', 'Students\RecordController@documents');
    $r->post('/students/{id}/documents', 'Students\RecordController@issue');
    $r->get('/students/{id}/guardians', 'Students\GuardianController@index');
    $r->post('/students/{id}/guardians', 'Students\GuardianController@store');
    $r->post('/guardians/{id}/access', 'Students\GuardianController@grantAccess');
    $r->delete('/guardians/{id}/access', 'Students\GuardianController@revokeAccess');
    $r->delete('/guardians/{id}', 'Students\GuardianController@destroy');

    // Graduation and alumni.
    $r->get('/graduation', 'Students\GraduationController@index');
    $r->post('/graduation/add', 'Students\GraduationController@add');
    $r->post('/graduation/{id}/advance', 'Students\GraduationController@advance');
    $r->get('/alumni', 'Students\GraduationController@alumni');

    // Student requests (petitions).
    $r->get('/requests', 'Students\RequestController@index');
    $r->get('/requests/{id}', 'Students\RequestController@show');
    $r->post('/requests/{id}/decide', 'Students\RequestController@decide');
    $r->get('/requests/{id}/attachment', 'Students\RequestController@attachment');

    // Academic advising and standing.
    $r->get('/advising', 'Students\AdvisingController@index');
    $r->post('/advising/assign', 'Students\AdvisingController@assign');
    $r->get('/advising/mine', 'Students\AdvisingController@mine');
    $r->get('/advising/standing', 'Students\AdvisingController@standing');
    $r->get('/advising/students/{id}', 'Students\AdvisingController@student');
    $r->post('/advising/students/{id}/notes', 'Students\AdvisingController@addNote');
    $r->post('/advising/students/{id}/advisor', 'Students\AdvisingController@assignOne');

    // Course and lecturer evaluation.
    $r->get('/evaluations', 'Quality\EvaluationController@index');
    $r->post('/evaluations/periods', 'Quality\EvaluationController@storePeriod');
    $r->post('/evaluations/periods/{id}/toggle', 'Quality\EvaluationController@togglePeriod');
    $r->get('/evaluations/{period}/{offering}', 'Quality\EvaluationController@report');
    $r->get('/teaching/evaluations', 'Quality\EvaluationController@mine');

    // Research output.
    $resource($r, '/research', 'Quality\ResearchController');

    // Assets and internal supplies (not for sale).
    $resource($r, '/assets/stock', 'Operations\StockController');
    $r->get('/assets/stock/{id}/movements', 'Operations\StockController@movements');
    $r->post('/assets/stock/{id}/movements', 'Operations\StockController@move');
    $resource($r, '/assets', 'Operations\AssetController');
    $r->get('/assets/{id}/maintenance', 'Operations\AssetController@maintenance');
    $r->post('/assets/{id}/maintenance', 'Operations\AssetController@addMaintenance');

    // Store: goods sold to students and staff. /store/sales/create comes before /store/sales/{id}.
    $r->get('/store/sales', 'Store\SaleController@index');
    $r->get('/store/sales/create', 'Store\SaleController@create');
    $r->post('/store/sales', 'Store\SaleController@store');
    $r->get('/store/sales/{id}', 'Store\SaleController@show');
    $r->post('/store/sales/{id}/void', 'Store\SaleController@void');
    $resource($r, '/store/products', 'Store\ProductController');
    $r->get('/store/products/{id}/stock', 'Store\ProductController@stock');
    $r->post('/store/products/{id}/stock', 'Store\ProductController@move');

    // Staff appraisal and recruitment.
    $r->get('/hr/appraisals', 'HR\AppraisalController@index');
    $r->post('/hr/appraisal-cycles', 'HR\AppraisalController@storeCycle');
    $r->post('/hr/appraisal-cycles/{id}/toggle', 'HR\AppraisalController@closeCycle');
    $r->get('/appraisals/mine', 'HR\AppraisalController@mine');
    $r->get('/appraisals/{id}', 'HR\AppraisalController@show');
    $r->post('/appraisals/{id}/self', 'HR\AppraisalController@selfAssess');
    $r->post('/appraisals/{id}/review', 'HR\AppraisalController@review');
    $r->post('/appraisals/{id}/reviewer', 'HR\AppraisalController@assignReviewer');
    $resource($r, '/hr/vacancies', 'HR\VacancyController');
    $r->get('/hr/vacancies/{id}/applicants', 'HR\VacancyController@applicants');
    $r->post('/hr/applications/{id}', 'HR\VacancyController@moveApplicant');
    $r->get('/hr/applications/{id}/cv', 'HR\VacancyController@cv');

    // Clubs and societies.
    $resource($r, '/clubs', 'StudentLife\ClubController');
    $r->get('/clubs/{id}/members', 'StudentLife\ClubController@members');
    $r->post('/clubs/{id}/members', 'StudentLife\ClubController@saveMember');

    // Budgets.
    $r->get('/finance/budgets', 'Finance\BudgetController@index');
    $r->post('/finance/budgets', 'Finance\BudgetController@save');

    // Online payments queue (Finance).
    $r->get('/finance/online-payments', 'Finance\OnlinePaymentController@index');
    $r->post('/finance/online-payments/{reference}/approve', 'Finance\OnlinePaymentController@approve');
    $r->post('/finance/online-payments/{reference}/reject', 'Finance\OnlinePaymentController@reject');
    $r->get('/finance/online-payments/{reference}/proof', 'Finance\OnlinePaymentController@proof');

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
    $r->delete('/registrations/{id}', 'Academics\RegistrationController@destroy');

    // Online learning and online examinations oversight.
    $r->get('/online-learning', 'Academics\OnlineLearningController@index');
    $r->get('/online-learning/{id}', 'Academics\OnlineLearningController@show');
    $r->get('/online-exams', 'Academics\OnlineExamController@index');
    $r->get('/online-exams/{id}', 'Academics\OnlineExamController@show');
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
    $r->get('/students/{id}/records', 'Students\AcademicRecordController@record');
    $r->get('/students/{id}/transcript', 'Students\AcademicRecordController@transcript');
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

    // Online classroom (live classes and course materials) and online examinations.
    // Registered before /teaching/{id} so their literal paths are not taken for a class id.
    $r->get('/teaching/online-exams', 'Lecturer\OnlineExamController@mine');
    $r->get('/teaching/online-exams/{id}', 'Lecturer\OnlineExamController@show');
    $r->get('/teaching/online-exams/{id}/edit', 'Lecturer\OnlineExamController@edit');
    $r->put('/teaching/online-exams/{id}', 'Lecturer\OnlineExamController@update');
    $r->delete('/teaching/online-exams/{id}', 'Lecturer\OnlineExamController@destroy');
    $r->post('/teaching/online-exams/{id}/questions', 'Lecturer\OnlineExamController@saveQuestion');
    $r->post('/teaching/online-exams/{id}/status', 'Lecturer\OnlineExamController@status');
    $r->get('/teaching/online-exams/{id}/results', 'Lecturer\OnlineExamController@results');
    $r->post('/teaching/online-exams/{id}/transfer', 'Lecturer\OnlineExamController@transfer');
    $r->delete('/teaching/exam-questions/{id}', 'Lecturer\OnlineExamController@destroyQuestion');
    $r->post('/teaching/exam-questions/{id}/move', 'Lecturer\OnlineExamController@moveQuestion');
    $r->get('/teaching/online-exams/attempts/{id}', 'Lecturer\OnlineExamController@attempt');
    $r->post('/teaching/online-exams/attempts/{id}', 'Lecturer\OnlineExamController@mark');
    $r->put('/teaching/class-sessions/{id}', 'Lecturer\ClassroomController@updateSession');
    $r->delete('/teaching/class-sessions/{id}', 'Lecturer\ClassroomController@destroySession');
    $r->get('/teaching/class-sessions/{id}/attendance', 'Lecturer\ClassroomController@attendance');
    $r->delete('/teaching/materials/{id}', 'Lecturer\ClassroomController@destroyMaterial');

    $r->get('/teaching/assignments/{id}', 'Lecturer\AssignmentController@show');
    $r->get('/teaching/assignments/{id}/edit', 'Lecturer\AssignmentController@edit');
    $r->put('/teaching/assignments/{id}', 'Lecturer\AssignmentController@update');
    $r->delete('/teaching/assignments/{id}', 'Lecturer\AssignmentController@destroy');
    $r->post('/teaching/assignments/{id}/grade', 'Lecturer\AssignmentController@grade');
    $r->post('/teaching/assignments/{id}/transfer', 'Lecturer\AssignmentController@transfer');

    $r->get('/teaching/{id}', 'Lecturer\TeachingController@show');
    $r->get('/teaching/{id}/assignments/create', 'Lecturer\AssignmentController@create');
    $r->post('/teaching/{id}/assignments', 'Lecturer\AssignmentController@store');
    $r->get('/teaching/{id}/classroom', 'Lecturer\ClassroomController@show');
    $r->post('/teaching/{id}/class-sessions', 'Lecturer\ClassroomController@storeSession');
    $r->post('/teaching/{id}/materials', 'Lecturer\ClassroomController@storeMaterial');
    $r->get('/teaching/{id}/online-exams/create', 'Lecturer\OnlineExamController@create');
    $r->post('/teaching/{id}/online-exams', 'Lecturer\OnlineExamController@store');
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
    $r->get('/timetable/conflicts', 'Timetable\TimetableController@conflicts');
    $r->get('/timetable/create', 'Timetable\TimetableController@create');
    $r->post('/timetable/check', 'Timetable\TimetableController@check');
    $r->post('/timetable', 'Timetable\TimetableController@store');
    $r->get('/timetable/{id}/edit', 'Timetable\TimetableController@edit');
    $r->put('/timetable/{id}', 'Timetable\TimetableController@update');
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
    $resource($r, '/tuition-rates', 'Finance\TuitionRateController');
    $resource($r, '/scholarships', 'Finance\ScholarshipController');
    $resource($r, '/expenses', 'Finance\ExpenseController');
    $resource($r, '/salary-components', 'Finance\SalaryComponentController');
    $resource($r, '/tax-bands', 'Finance\TaxBandController');

    $r->get('/fee-structures', 'Finance\FeeStructureController@index');
    $r->get('/fee-structures/create', 'Finance\FeeStructureController@create');
    $r->post('/fee-structures', 'Finance\FeeStructureController@store');
    $r->get('/fee-structures/{id}', 'Finance\FeeStructureController@show');
    $r->get('/fee-structures/{id}/edit', 'Finance\FeeStructureController@edit');
    $r->put('/fee-structures/{id}', 'Finance\FeeStructureController@update');
    $r->post('/fee-structures/{id}/items', 'Finance\FeeStructureController@addItem');
    $r->delete('/fee-structures/{structure}/items/{id}', 'Finance\FeeStructureController@removeItem');
    $r->post('/fee-structures/{id}/installments', 'Finance\FeeStructureController@addInstallment');
    $r->delete('/fee-structures/{structure}/installments/{id}', 'Finance\FeeStructureController@removeInstallment');
    $r->delete('/fee-structures/{id}', 'Finance\FeeStructureController@destroy');

    $r->get('/invoices', 'Finance\InvoiceController@index');
    $r->get('/invoices/generate', 'Finance\InvoiceController@generateForm');
    $r->post('/invoices/generate', 'Finance\InvoiceController@generate');
    $r->get('/invoices/{id}', 'Finance\InvoiceController@show');
    $r->get('/invoices/{id}/billing-form', 'Finance\InvoiceController@billingForm');
    $r->post('/invoices/{id}/cancel', 'Finance\InvoiceController@cancel');

    $r->get('/payments', 'Finance\PaymentController@index');
    $r->get('/payments/create', 'Finance\PaymentController@create');
    $r->post('/payments', 'Finance\PaymentController@store');
    $r->get('/payments/{id}/receipt', 'Finance\PaymentController@receipt');
    $r->post('/payments/{id}/reverse', 'Finance\PaymentController@reverse');

    // Payroll. The fixed paths come before /payroll/{id} so they are not read as an id.
    $r->get('/payroll', 'Finance\PayrollController@index');
    $r->post('/payroll', 'Finance\PayrollController@createPeriod');
    $r->get('/payroll/salaries', 'Finance\PayrollController@salaries');
    $r->get('/payroll/salaries/{id}', 'Finance\PayrollController@editSalary');
    $r->post('/payroll/salaries/{id}', 'Finance\PayrollController@updateSalary');
    $r->get('/payroll/loans', 'Finance\PayrollController@loans');
    $r->post('/payroll/loans', 'Finance\PayrollController@storeLoan');
    $r->post('/payroll/loans/{id}/cancel', 'Finance\PayrollController@cancelLoan');
    $r->get('/payroll/payslip/{id}', 'Finance\PayrollController@payslip');
    $r->post('/payroll/payslip/{id}', 'Finance\PayrollController@updatePayslip');
    $r->get('/payroll/{id}', 'Finance\PayrollController@show');
    $r->get('/payroll/{id}/export', 'Finance\PayrollController@export');
    $r->post('/payroll/{id}/process', 'Finance\PayrollController@process');
    $r->post('/payroll/{id}/approve', 'Finance\PayrollController@approve');
    $r->post('/payroll/{id}/pay', 'Finance\PayrollController@pay');
    $r->delete('/payroll/{id}', 'Finance\PayrollController@destroy');
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
    $r->get('/records', 'Students\AcademicRecordController@myRecord');
    $r->get('/transcript', 'Students\AcademicRecordController@myTranscript');
    $r->get('/finance', 'Portal\FinanceController@index');
    $r->get('/finance/invoice/{id}', 'Portal\FinanceController@invoice');
    $r->get('/finance/invoice/{id}/billing-form', 'Portal\FinanceController@billingForm');
    $r->get('/timetable', 'Portal\PortalController@timetable');
    $r->get('/attendance', 'Portal\PortalController@attendance');
    $r->get('/library', 'Portal\PortalController@library');
    $r->get('/hostel', 'Portal\PortalController@hostel');
    $r->get('/clearance', 'Portal\PortalController@clearance');

    $r->get('/assignments', 'Portal\AssignmentController@index');
    $r->get('/assignments/{id}', 'Portal\AssignmentController@show');
    $r->post('/assignments/{id}', 'Portal\AssignmentController@submit');
    $r->get('/course-guide', 'Portal\CourseGuideController@show');
    $r->get('/degree-audit', 'Students\RecordController@myAudit');
    $r->get('/graduation', 'Portal\GraduationController@show');
    $r->post('/graduation', 'Portal\GraduationController@apply');
    $r->post('/alumni-profile', 'Portal\GraduationController@profile');
    $r->get('/requests', 'Students\RequestController@mine');
    $r->post('/requests', 'Students\RequestController@store');
    $r->post('/requests/{id}/cancel', 'Students\RequestController@cancel');
    $r->get('/advising', 'Students\AdvisingController@myAdvising');
    $r->get('/clubs', 'StudentLife\ClubController@portal');
    $r->post('/clubs/{id}', 'StudentLife\ClubController@join');
    $r->get('/evaluations', 'Quality\EvaluationController@portal');
    $r->get('/evaluations/{period}/{offering}', 'Quality\EvaluationController@form');
    $r->post('/evaluations/{period}/{offering}', 'Quality\EvaluationController@submit');
    $r->get('/documents', 'Students\RecordController@myDocuments');
    $r->post('/documents', 'Students\RecordController@issueMine');
    $r->get('/courses', 'Portal\CourseController@index');
    $r->get('/courses/{id}', 'Portal\CourseController@show');
    $r->get('/classes', 'Portal\OnlineClassController@index');
    $r->get('/classes/{id}/join', 'Portal\OnlineClassController@join');
    $r->get('/exams', 'Portal\OnlineExamController@index');
    $r->get('/exams/{id}', 'Portal\OnlineExamController@show');
    $r->post('/exams/{id}/start', 'Portal\OnlineExamController@start');
    $r->get('/exams/{id}/take', 'Portal\OnlineExamController@take');
    $r->post('/exams/{id}/answer', 'Portal\OnlineExamController@answer');
    $r->post('/exams/{id}/focus', 'Portal\OnlineExamController@focus');
    $r->post('/exams/{id}/submit', 'Portal\OnlineExamController@submit');
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
    $r->get('/sessions', 'Admin\SecurityAdminController@sessions');
    $r->post('/sessions/{id}/revoke', 'Admin\SecurityAdminController@revokeSession');
    $r->get('/backups', 'Admin\SecurityAdminController@backups');
    $r->post('/backups', 'Admin\SecurityAdminController@createBackup');
    $r->get('/backups/{name}', 'Admin\SecurityAdminController@downloadBackup');
    $r->delete('/backups/{name}', 'Admin\SecurityAdminController@deleteBackup');
    $r->get('/alerts', 'Admin\SecurityAdminController@alerts');
    $r->post('/alerts/send', 'Admin\SecurityAdminController@sendAlerts');

    $r->get('/data-imports', 'Admin\DataImportController@index');
    $r->post('/data-imports/run', 'Admin\DataImportController@run');
    $r->get('/data-imports/slips', 'Admin\DataImportController@slips');
    $r->post('/data-imports/slips', 'Admin\DataImportController@issue');
    $r->get('/data-imports/{id}', 'Admin\DataImportController@show');

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
