<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

final readonly class ValidationResult
{
    public function __construct(
        public array $values,
        public array $errors,
        public ?ContactMessage $message,
    ) {
    }

    public function isValid(): bool
    {
        return $this->message !== null;
    }
}
