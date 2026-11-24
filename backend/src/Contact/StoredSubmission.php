<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

use DateTimeImmutable;

final readonly class StoredSubmission
{
    public function __construct(
        public string $id,
        public string $nameCiphertext,
        public string $phoneCiphertext,
        public string $emailCiphertext,
        public string $messageCiphertext,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $notifiedAt,
    ) {
    }
}
