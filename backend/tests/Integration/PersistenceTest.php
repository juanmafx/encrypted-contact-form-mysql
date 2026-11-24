<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use FormularioDorado\Contact\StoredSubmission;
use FormularioDorado\Persistence\PdoAdminRepository;
use FormularioDorado\Persistence\PdoAuditLog;
use FormularioDorado\Persistence\PdoRateLimiter;
use FormularioDorado\Persistence\PdoSubmissionRepository;
use FormularioDorado\Tests\Support\FrozenClock;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PersistenceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('TEST_DB_DSN is not set.');
        }

        $this->pdo = new PDO($dsn, (string) getenv('TEST_DB_USER'), (string) getenv('TEST_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        foreach (['admin_audit_log', 'admin_users', 'contact_submissions', 'rate_limit_buckets'] as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/db/schema.sql');
        foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private function submission(string $id, string $createdAt): StoredSubmission
    {
        return new StoredSubmission($id, 'v1:n', 'v1:p', 'v1:e', 'v1:m', new DateTimeImmutable($createdAt, new DateTimeZone('UTC')), null);
    }

    public function testSubmissionLifecycle(): void
    {
        $repository = new PdoSubmissionRepository($this->pdo);
        $id = str_repeat('a', 32);
        $repository->save($this->submission($id, '2026-01-02 03:04:05.123456'));

        $found = $repository->find($id);
        self::assertSame('v1:n', $found->nameCiphertext);
        self::assertSame('2026-01-02 03:04:05.123456', $found->createdAt->format('Y-m-d H:i:s.u'));
        self::assertNull($found->notifiedAt);

        $repository->markNotified($id, new DateTimeImmutable('2026-01-02 03:05:00', new DateTimeZone('UTC')));
        self::assertNotNull($repository->find($id)->notifiedAt);
        self::assertSame(1, $repository->count());
        self::assertTrue($repository->delete($id));
        self::assertFalse($repository->delete($id));
        self::assertNull($repository->find($id));
    }

    public function testPagingOrdersNewestFirst(): void
    {
        $repository = new PdoSubmissionRepository($this->pdo);
        $repository->save($this->submission(str_repeat('a', 32), '2026-01-01 00:00:00'));
        $repository->save($this->submission(str_repeat('b', 32), '2026-01-03 00:00:00'));
        $repository->save($this->submission(str_repeat('c', 32), '2026-01-02 00:00:00'));

        $page = $repository->page(2, 0);
        self::assertSame([str_repeat('b', 32), str_repeat('c', 32)], array_map(static fn (StoredSubmission $row): string => $row->id, $page));
        self::assertCount(1, $repository->page(2, 2));
    }

    public function testRetentionPurgeDeletesOnlyOldRows(): void
    {
        $repository = new PdoSubmissionRepository($this->pdo);
        $repository->save($this->submission(str_repeat('a', 32), '2025-01-01 00:00:00'));
        $repository->save($this->submission(str_repeat('b', 32), '2026-06-01 00:00:00'));

        self::assertSame(1, $repository->deleteOlderThan(new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'))));
        self::assertSame(1, $repository->count());
    }

    public function testInjectionPayloadIsTreatedAsData(): void
    {
        $repository = new PdoSubmissionRepository($this->pdo);
        $repository->save($this->submission(str_repeat('a', 32), '2026-01-01 00:00:00'));

        self::assertNull($repository->find("' OR '1'='1"));
        self::assertFalse($repository->delete("' OR '1'='1"));
        self::assertSame(1, $repository->count());
    }

    public function testAdminLifecycleAndAtomicTotpClaim(): void
    {
        $admins = new PdoAdminRepository($this->pdo);
        $id = $admins->create('admin', '$argon2id$placeholder', 'v1:secret');

        self::assertSame('admin', $admins->findByUsername('admin')->username);
        self::assertTrue($admins->claimTotpStep($id, 100));
        self::assertFalse($admins->claimTotpStep($id, 100));
        self::assertFalse($admins->claimTotpStep($id, 99));
        self::assertTrue($admins->claimTotpStep($id, 101));

        $admins->replaceTotpSecret($id, 'v1:new');
        $admin = $admins->findById($id);
        self::assertSame('v1:new', $admin->totpSecretCiphertext);
        self::assertNull($admin->lastTotpStep);

        $admins->updatePasswordHash($id, '$argon2id$other');
        self::assertSame('$argon2id$other', $admins->findById($id)->passwordHash);
    }

    public function testOnlyCredentialChangesAdvanceSessionVersion(): void
    {
        $admins = new PdoAdminRepository($this->pdo);
        $id = $admins->create('admin', '$argon2id$placeholder', 'v1:secret');
        self::assertSame(1, $admins->findById($id)->sessionVersion);

        $admins->updatePasswordHash($id, '$argon2id$rehashed');
        $admins->claimTotpStep($id, 100);
        self::assertSame(1, $admins->findById($id)->sessionVersion);

        $admins->changePassword($id, '$argon2id$changed');
        self::assertSame('$argon2id$changed', $admins->findById($id)->passwordHash);
        self::assertSame(2, $admins->findById($id)->sessionVersion);

        $admins->replaceTotpSecret($id, 'v1:new');
        self::assertSame(3, $admins->findById($id)->sessionVersion);
    }

    public function testSchemaRejectsDuplicateAndInvalidUsernames(): void
    {
        $admins = new PdoAdminRepository($this->pdo);
        $admins->create('admin', 'hash', 'v1:secret');

        try {
            $admins->create('admin', 'hash', 'v1:secret');
            self::fail('Duplicate username was accepted.');
        } catch (PDOException) {
            self::assertTrue(true);
        }

        $this->expectException(PDOException::class);
        $admins->create('Bad Name', 'hash', 'v1:secret');
    }

    public function testAuditLogPersistsEntries(): void
    {
        $id = (new PdoAdminRepository($this->pdo))->create('admin', 'hash', 'v1:secret');
        (new PdoAuditLog($this->pdo))->record($id, 'read_submission', str_repeat('a', 32));

        $row = $this->pdo->query('SELECT admin_id, action, submission_id FROM admin_audit_log')->fetch();
        self::assertSame('read_submission', $row['action']);
        self::assertSame(str_repeat('a', 32), $row['submission_id']);
    }

    public function testRateLimiterCountsAndResetsWindow(): void
    {
        $clock = new FrozenClock(1_800_000_000);
        $limiter = new PdoRateLimiter($this->pdo, $clock);
        $bucket = random_bytes(32);

        self::assertTrue($limiter->hit($bucket, 2, 60));
        self::assertTrue($limiter->hit($bucket, 2, 60));
        self::assertFalse($limiter->hit($bucket, 2, 60));

        $clock->advance(61);
        self::assertTrue($limiter->hit($bucket, 2, 60));

        $limiter->clear($bucket);
        self::assertTrue($limiter->hit($bucket, 1, 60));

        $clock->advance(7200);
        self::assertSame(1, $limiter->purgeExpired(3600));
    }
}
