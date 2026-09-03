<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Hash;
use App\Core\Mailer;
use App\Core\Notify;
use App\Models\Application;
use App\Models\Student;
use App\Models\User;

/**
 * Turns an accepted application into a student record with a portal account.
 */
final class AdmissionService
{
    public function __construct(
        private Application $applications = new Application(),
        private Student $students = new Student(),
        private User $users = new User(),
    ) {
    }

    /**
     * @return array{ok:bool,message:string,student_id?:int,credentials?:array}
     */
    public function enrol(int $applicationId, ?int $actorId = null): array
    {
        $application = $this->applications->detail($applicationId);
        if ($application === null) {
            return ['ok' => false, 'message' => 'Application not found.'];
        }
        if ($application['status'] === 'enrolled') {
            return ['ok' => false, 'message' => 'This applicant has already been enrolled.'];
        }
        if ($application['status'] !== 'accepted') {
            return ['ok' => false, 'message' => 'Only accepted applications can be enrolled.'];
        }
        if ($this->users->exists('email', $application['email'])) {
            return ['ok' => false, 'message' => 'A user account already exists with that email address.'];
        }

        $password = Hash::tempPassword();

        $result = Database::transaction(function () use ($application, $applicationId, $actorId, $password) {
            $username = $this->users->generateUsername($application['first_name'], $application['last_name']);

            $userId = $this->users->createAccount(
                [
                    'username'             => $username,
                    'email'                => $application['email'],
                    'first_name'           => $application['first_name'],
                    'last_name'            => $application['last_name'],
                    'other_name'           => $application['other_name'],
                    'gender'               => $application['gender'],
                    'date_of_birth'        => $application['date_of_birth'],
                    'phone'                => $application['phone'],
                    'user_type'            => 'student',
                    'status'               => 'active',
                    'must_change_password' => 1,
                ],
                $this->studentRoleIds(),
                $password,
                $actorId
            );

            $admissionNumber = $this->students->nextAdmissionNumber((string) $application['program_code']);

            $studentId = $this->students->create([
                'user_id'                => $userId,
                'admission_number'       => $admissionNumber,
                'registration_number'    => $admissionNumber,
                'program_id'             => (int) $application['program_id'],
                'intake_id'              => (int) $application['intake_id'],
                'year_of_study'          => 1,
                'current_semester'       => 1,
                'study_mode'             => $application['study_mode'],
                'admission_date'         => date('Y-m-d'),
                'sponsor_type'           => $application['sponsor_type'],
                'nationality'            => $application['nationality'],
                'national_id'            => $application['national_id'],
                'physical_address'       => $application['address'],
                'county'                 => $application['county'],
                'previous_school'        => $application['previous_school'],
                'previous_qualification' => $application['qualification'],
                'previous_grade'         => $application['grade_obtained'],
                'status'                 => 'active',
            ]);

            $this->applications->update($applicationId, [
                'status'  => 'enrolled',
                'user_id' => $userId,
            ]);

            return [
                'user_id'          => $userId,
                'student_id'       => $studentId,
                'admission_number' => $admissionNumber,
                'username'         => $username,
            ];
        });

        $this->sendWelcome($application, $result, $password);

        Notify::send(
            (int) $result['user_id'],
            'Welcome to the university',
            'Your student account is ready. Admission number ' . $result['admission_number'] . '.',
            '/portal',
            'success',
            'student'
        );

        return [
            'ok'          => true,
            'message'     => 'Applicant enrolled as ' . $result['admission_number'] . '.',
            'student_id'  => $result['student_id'],
            'credentials' => [
                'username'         => $result['username'],
                'password'         => $password,
                'admission_number' => $result['admission_number'],
            ],
        ];
    }

    private function studentRoleIds(): array
    {
        $id = Database::scalar('SELECT id FROM roles WHERE slug = ? LIMIT 1', ['student']);
        return $id === null ? [] : [(int) $id];
    }

    private function sendWelcome(array $application, array $result, string $password): void
    {
        $body = '<p>Dear ' . e($application['first_name']) . ',</p>'
              . '<p>Congratulations! You have been admitted to <strong>' . e($application['program_name']) . '</strong>.</p>'
              . '<table style="border-collapse:collapse">'
              . '<tr><td style="padding:4px 12px 4px 0"><strong>Admission number</strong></td><td>' . e($result['admission_number']) . '</td></tr>'
              . '<tr><td style="padding:4px 12px 4px 0"><strong>Username</strong></td><td>' . e($result['username']) . '</td></tr>'
              . '<tr><td style="padding:4px 12px 4px 0"><strong>Temporary password</strong></td><td>' . e($password) . '</td></tr>'
              . '</table>'
              . '<p>You will be asked to set a new password the first time you sign in.</p>';

        Mailer::send((string) $application['email'], 'Your admission has been confirmed', $body);
    }

    /** Move an application through the review workflow. */
    public function review(int $applicationId, string $status, ?string $remarks, ?float $score, ?int $reviewerId): array
    {
        $allowed = ['under_review', 'shortlisted', 'accepted', 'rejected', 'withdrawn'];
        if (!in_array($status, $allowed, true)) {
            return ['ok' => false, 'message' => 'Invalid review decision.'];
        }
        $application = $this->applications->find($applicationId);
        if ($application === null) {
            return ['ok' => false, 'message' => 'Application not found.'];
        }
        if ($application['status'] === 'enrolled') {
            return ['ok' => false, 'message' => 'Enrolled applications can no longer be reviewed.'];
        }

        $this->applications->update($applicationId, [
            'status'      => $status,
            'remarks'     => $remarks,
            'score'       => $score,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if (in_array($status, ['accepted', 'rejected'], true)) {
            $accepted = $status === 'accepted';
            $subject  = $accepted ? 'Your application has been accepted' : 'Update on your application';
            $lead     = $accepted
                ? 'We are pleased to inform you that your application has been <strong>accepted</strong>. Admission details will follow shortly.'
                : 'After careful consideration we are unable to offer you a place at this time.';
            $body = '<p>Dear ' . e($application['first_name']) . ',</p><p>' . $lead . '</p>'
                  . ($remarks ? '<p>' . e($remarks) . '</p>' : '');
            Mailer::send((string) $application['email'], $subject, $body);
        }

        return ['ok' => true, 'message' => 'Application marked as ' . humanize($status) . '.'];
    }
}
