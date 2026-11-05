<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Exceptions\HttpException;
use App\Models\Student;

/** The student record of the signed-in user, for portal controllers. */
trait CurrentStudent
{
    private ?array $currentStudent = null;

    private function student(): array
    {
        if ($this->currentStudent === null) {
            $student = (new Student())->byUserId((int) Auth::id());
            if ($student === null) {
                throw new HttpException(403, 'No student profile is linked to your account.');
            }
            $this->currentStudent = $student;
        }
        return $this->currentStudent;
    }
}
