<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

final class ContactValidator
{
    public const int NAME_MAX = 120;
    public const int EMAIL_MAX = 254;
    public const int MESSAGE_MAX = 5000;

    public function validate(array $input): ValidationResult
    {
        $name = $this->singleLine($input['name'] ?? '');
        $phone = $this->singleLine($input['phone'] ?? '');
        $email = $this->singleLine($input['email'] ?? '');
        $message = $this->multiLine($input['message'] ?? '');
        $values = compact('name', 'phone', 'email', 'message');
        $errors = [];

        if ($name === null || $name === '' || mb_strlen($name, 'UTF-8') > self::NAME_MAX) {
            $errors['name'] = 'Enter your name (up to 120 characters).';
        }
        if ($phone === null || preg_match('/^\+?[0-9][0-9 ().\/-]{5,31}$/D', $phone) !== 1) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        if (
            $email === null
            || strlen($email) > self::EMAIL_MAX
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($message === null || mb_strlen($message, 'UTF-8') > self::MESSAGE_MAX) {
            $errors['message'] = 'Your message must be valid text of up to 5000 characters.';
        }

        $safeValues = array_map(static fn (?string $value): string => $value ?? '', $values);
        if ($errors !== []) {
            return new ValidationResult($safeValues, $errors, null);
        }

        return new ValidationResult(
            $safeValues,
            [],
            new ContactMessage($safeValues['name'], $safeValues['phone'], $safeValues['email'], $safeValues['message']),
        );
    }

    private function singleLine(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{Cc}\x{2028}\x{2029}]/u', $value) === 1) {
            return null;
        }

        return trim($value);
    }

    private function multiLine(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        if (preg_match('/[^\P{Cc}\n\t]/u', $normalized) === 1) {
            return null;
        }

        return trim($normalized);
    }
}
