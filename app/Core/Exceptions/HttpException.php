<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(private int $statusCode = 500, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function defaultMessage(int $code): string
    {
        return match ($code) {
            400     => 'Bad request.',
            401     => 'You need to sign in to continue.',
            403     => 'You do not have permission to perform this action.',
            404     => 'The page you requested could not be found.',
            405     => 'Method not allowed.',
            419     => 'Your session expired. Please try again.',
            422     => 'The submitted data could not be processed.',
            429     => 'Too many requests. Please slow down.',
            default => 'Something went wrong on our side.',
        };
    }
}
