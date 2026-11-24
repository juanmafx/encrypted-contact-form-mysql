<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

use DateTimeImmutable;

final readonly class DecryptedSubmission
{
    public function __construct(
        public string $id,
        public ContactMessage $contact,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $notifiedAt,
    ) {
    }
}
