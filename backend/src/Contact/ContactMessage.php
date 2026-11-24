<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

final readonly class ContactMessage
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $email,
        public string $message,
    ) {
    }
}
