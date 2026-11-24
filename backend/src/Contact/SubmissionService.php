<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

use FormularioDorado\Security\FieldCipher;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class SubmissionService
{
    public const int PAGE_SIZE = 25;

    public function __construct(
        private SubmissionRepository $repository,
        private FieldCipher $cipher,
        private SubmissionNotifier $notifier,
        private ClockInterface $clock,
    ) {
    }

    public function submit(ContactMessage $message): string
    {
        $id = bin2hex(random_bytes(16));
        $this->repository->save(new StoredSubmission(
            $id,
            $this->cipher->encrypt($message->name, $this->context($id, 'name')),
            $this->cipher->encrypt($message->phone, $this->context($id, 'phone')),
            $this->cipher->encrypt($message->email, $this->context($id, 'email')),
            $this->cipher->encrypt($message->message, $this->context($id, 'message')),
            $this->clock->now(),
            null,
        ));

        try {
            $this->notifier->notify($id, $message);
            $this->repository->markNotified($id, $this->clock->now());
        } catch (Throwable $failure) {
            error_log('mail_delivery_failed submission=' . $id . ' type=' . $failure::class);
        }

        return $id;
    }

    public function read(string $id): ?DecryptedSubmission
    {
        $stored = $this->repository->find($id);

        return $stored === null ? null : $this->decrypt($stored);
    }

    public function list(int $page): array
    {
        $offset = (max(1, $page) - 1) * self::PAGE_SIZE;

        return array_map($this->decrypt(...), $this->repository->page(self::PAGE_SIZE, $offset));
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->repository->count() / self::PAGE_SIZE));
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function purgeOlderThanDays(int $days): int
    {
        return $this->repository->deleteOlderThan($this->clock->now()->modify('-' . $days . ' days'));
    }

    private function decrypt(StoredSubmission $stored): DecryptedSubmission
    {
        return new DecryptedSubmission(
            $stored->id,
            new ContactMessage(
                $this->cipher->decrypt($stored->nameCiphertext, $this->context($stored->id, 'name')),
                $this->cipher->decrypt($stored->phoneCiphertext, $this->context($stored->id, 'phone')),
                $this->cipher->decrypt($stored->emailCiphertext, $this->context($stored->id, 'email')),
                $this->cipher->decrypt($stored->messageCiphertext, $this->context($stored->id, 'message')),
            ),
            $stored->createdAt,
            $stored->notifiedAt,
        );
    }

    private function context(string $id, string $field): string
    {
        return 'submission:' . $id . ':' . $field;
    }
}
