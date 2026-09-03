<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rule-string validator:  'required|email|unique:users,email|max:150'
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $labels = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data   = $data;
        $this->rules  = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();
        return $validator;
    }

    public function run(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            // "nullable" short-circuits every other rule for empty values.
            if (in_array('nullable', $rules, true) && ($value === null || $value === '')) {
                continue;
            }
            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
            }
        }
        return $this->errors === [];
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucwords(str_replace(['_id', '_'], ['', ' '], $field));
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        $label = $this->label($field);

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->fail($field, "{$label} is required.");
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->fail($field, "{$label} must be a valid email address.");
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->fail($field, "{$label} must be a number.");
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->fail($field, "{$label} must be a whole number.");
                }
                break;

            case 'min':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value < (float) $param) {
                        $this->fail($field, "{$label} must be at least {$param}.");
                    }
                } elseif ($value !== null && mb_strlen((string) $value) < (int) $param) {
                    $this->fail($field, "{$label} must be at least {$param} characters.");
                }
                break;

            case 'max':
                if (is_numeric($value) && !is_string($value)) {
                    if ((float) $value > (float) $param) {
                        $this->fail($field, "{$label} may not be greater than {$param}.");
                    }
                } elseif ($value !== null && mb_strlen((string) $value) > (int) $param) {
                    $this->fail($field, "{$label} may not exceed {$param} characters.");
                }
                break;

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $param), 2, 0);
                if ((float) $value < (float) $low || (float) $value > (float) $high) {
                    $this->fail($field, "{$label} must be between {$low} and {$high}.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if ($value !== null && $value !== '' && !in_array((string) $value, $allowed, true)) {
                    $this->fail($field, "{$label} holds an invalid value.");
                }
                break;

            case 'date':
                if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
                    $this->fail($field, "{$label} must be a valid date.");
                }
                break;

            case 'after':
                $other = $this->data[$param] ?? $param;
                if ($value && $other && strtotime((string) $value) <= strtotime((string) $other)) {
                    $this->fail($field, "{$label} must be after " . $this->label((string) $param) . '.');
                }
                break;

            case 'confirmed':
                if (($this->data[$field . '_confirmation'] ?? null) !== $value) {
                    $this->fail($field, "{$label} confirmation does not match.");
                }
                break;

            case 'same':
                if (($this->data[$param] ?? null) !== $value) {
                    $this->fail($field, "{$label} must match " . $this->label((string) $param) . '.');
                }
                break;

            case 'alpha_num':
                if ($value !== null && $value !== '' && !preg_match('/^[A-Za-z0-9]+$/', (string) $value)) {
                    $this->fail($field, "{$label} may only contain letters and numbers.");
                }
                break;

            case 'alpha_dash':
                if ($value !== null && $value !== '' && !preg_match('/^[A-Za-z0-9_.-]+$/', (string) $value)) {
                    $this->fail($field, "{$label} may only contain letters, numbers, dashes and underscores.");
                }
                break;

            case 'phone':
                if ($value !== null && $value !== '' && !preg_match('/^[0-9 +()-]{7,20}$/', (string) $value)) {
                    $this->fail($field, "{$label} must be a valid phone number.");
                }
                break;

            case 'unique':
                $this->uniqueRule($field, $value, (string) $param, $label);
                break;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, 'id');
                if ($value !== null && $value !== '') {
                    $found = Database::scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value]);
                    if ((int) $found === 0) {
                        $this->fail($field, "The selected {$label} is invalid.");
                    }
                }
                break;

            case 'password':
                $min = (int) Config::get('security.password_min_length', 8);
                if (mb_strlen((string) $value) < $min) {
                    $this->fail($field, "{$label} must be at least {$min} characters.");
                } elseif (!preg_match('/[A-Za-z]/', (string) $value) || !preg_match('/[0-9]/', (string) $value)) {
                    $this->fail($field, "{$label} must contain both letters and numbers.");
                }
                break;
        }
    }

    private function uniqueRule(string $field, mixed $value, string $param, string $label): void
    {
        if ($value === null || $value === '') {
            return;
        }
        [$table, $column, $exceptId, $exceptColumn] = array_pad(explode(',', $param), 4, null);
        $column       = $column ?: $field;
        $exceptColumn = $exceptColumn ?: 'id';

        $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($exceptId !== null && $exceptId !== '') {
            $sql     .= " AND `{$exceptColumn}` != ?";
            $params[] = $exceptId;
        }
        if ((int) Database::scalar($sql, $params) > 0) {
            $this->fail($field, "That {$label} is already taken.");
        }
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,string> first error per field */
    public function flatErrors(): array
    {
        $out = [];
        foreach ($this->errors as $field => $messages) {
            $out[$field] = $messages[0];
        }
        return $out;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return null;
    }

    public function validated(): array
    {
        $data = array_intersect_key($this->data, $this->rules);

        // An untouched optional number or date arrives as '', which MySQL
        // rejects outright under STRICT_TRANS_TABLES ("Incorrect integer
        // value: ''"). Where the field is declared nullable and typed, an
        // empty submission means "no value", so store NULL. Text fields are
        // left alone: '' is valid for them and may be meaningful.
        foreach ($data as $field => $value) {
            if ($value !== '') {
                continue;
            }
            $rules = $this->rules[$field] ?? '';
            $rules = is_array($rules) ? $rules : explode('|', $rules);
            if (!in_array('nullable', $rules, true)) {
                continue;
            }
            foreach ($rules as $rule) {
                if (in_array(explode(':', $rule, 2)[0], ['integer', 'numeric', 'date'], true)) {
                    $data[$field] = null;
                    break;
                }
            }
        }

        return $data;
    }
}
