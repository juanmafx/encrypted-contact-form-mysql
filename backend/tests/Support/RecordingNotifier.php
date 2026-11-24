<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Contact\ContactMessage;
use FormularioDorado\Contact\SubmissionNotifier;
use RuntimeException;

final class RecordingNotifier implements SubmissionNotifier
{
    public array $sent = [];
    public bool $fails = false;

    public function notify(string $submissionId, ContactMessage $message): void
    {
        if ($this->fails) {
            throw new RuntimeException('SMTP unavailable');
        }
        $this->sent[] = $submissionId;
    }
}
