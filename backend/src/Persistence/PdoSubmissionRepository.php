<?php

declare(strict_types=1);

namespace FormularioDorado\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use FormularioDorado\Contact\StoredSubmission;
use FormularioDorado\Contact\SubmissionRepository;
use PDO;

final readonly class PdoSubmissionRepository implements SubmissionRepository
{
    private const string FORMAT = 'Y-m-d H:i:s.u';
    private const string COLUMNS = 'id, name_ciphertext, phone_ciphertext, email_ciphertext, message_ciphertext, created_at, notified_at';

    public function __construct(private PDO $pdo)
    {
    }

    public function save(StoredSubmission $submission): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO contact_submissions (' . self::COLUMNS . ') VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $submission->id,
            $submission->nameCiphertext,
            $submission->phoneCiphertext,
            $submission->emailCiphertext,
            $submission->messageCiphertext,
            $this->format($submission->createdAt),
            $submission->notifiedAt === null ? null : $this->format($submission->notifiedAt),
        ]);
    }

    public function find(string $id): ?StoredSubmission
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM contact_submissions WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function page(int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM contact_submissions ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->bindValue(2, $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM contact_submissions')->fetchColumn();
    }

    public function markNotified(string $id, DateTimeImmutable $at): void
    {
        $statement = $this->pdo->prepare('UPDATE contact_submissions SET notified_at = ? WHERE id = ?');
        $statement->execute([$this->format($at), $id]);
    }

    public function delete(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM contact_submissions WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $statement = $this->pdo->prepare('DELETE FROM contact_submissions WHERE created_at < ?');
        $statement->execute([$this->format($cutoff)]);

        return $statement->rowCount();
    }

    private function hydrate(array $row): StoredSubmission
    {
        $utc = new DateTimeZone('UTC');

        return new StoredSubmission(
            (string) $row['id'],
            (string) $row['name_ciphertext'],
            (string) $row['phone_ciphertext'],
            (string) $row['email_ciphertext'],
            (string) $row['message_ciphertext'],
            new DateTimeImmutable((string) $row['created_at'], $utc),
            $row['notified_at'] === null ? null : new DateTimeImmutable((string) $row['notified_at'], $utc),
        );
    }

    private function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
