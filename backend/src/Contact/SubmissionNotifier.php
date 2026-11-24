<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

interface SubmissionNotifier
{
    public function notify(string $submissionId, ContactMessage $message): void;
}
