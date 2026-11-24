<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Functional;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Contact\ContactMessage;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Http\Request;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Tests\Support\AppHarness;
use PHPUnit\Framework\TestCase;

final class AdminFlowTest extends TestCase
{
    private AppHarness $app;

    protected function setUp(): void
    {
        $this->app = new AppHarness();
        $this->app->seedAdmin();
    }

    private function seedSubmission(string $name = 'Ada Lovelace'): string
    {
        $service = new SubmissionService($this->app->submissions, $this->app->cipher, $this->app->notifier, $this->app->clock);

        return $service->submit(new ContactMessage($name, '+1 555 010 0000', 'ada@example.test', 'Hello'));
    }

    public function testSubmissionsRequireAuthentication(): void
    {
        $id = $this->seedSubmission();

        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
        self::assertSame('/admin/login', $this->app->get('/admin/submissions/' . $id)->headers['Location']);
        self::assertSame('/admin/login', $this->app->post('/admin/submissions/' . $id . '/delete')->headers['Location']);
        self::assertCount(1, $this->app->submissions->rows);
    }

    public function testPasswordAloneDoesNotGrantAccess(): void
    {
        $response = $this->app->post('/admin/login', ['username' => 'admin', 'password' => AppHarness::PASSWORD]);

        self::assertSame('/admin/verify', $response->headers['Location']);
        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testFullLoginReadsDecryptedSubmissionAndAudits(): void
    {
        $id = $this->seedSubmission();
        $this->app->signIn();

        $list = $this->app->get('/admin/submissions');
        $detail = $this->app->get('/admin/submissions/' . $id);

        self::assertSame(200, $list->status);
        self::assertStringContainsString('Ada Lovelace', $list->body);
        self::assertStringContainsString('ada@example.test', $detail->body);
        self::assertSame('no-store', $detail->headers['Cache-Control']);
        self::assertSame(['login', 'list_submissions', 'read_submission'], $this->app->audit->actions());
    }

    public function testStoredXssIsEscapedInAdminViews(): void
    {
        $id = $this->seedSubmission('<img src=x onerror=alert(1)>');
        $this->app->signIn();

        self::assertStringNotContainsString('<img', $this->app->get('/admin/submissions')->body);
        self::assertStringNotContainsString('<img', $this->app->get('/admin/submissions/' . $id)->body);
    }

    public function testWrongPasswordAndUnknownUserGetSameResponse(): void
    {
        $wrong = $this->app->post('/admin/login', ['username' => 'admin', 'password' => 'wrong-password-value']);
        $unknown = $this->app->post('/admin/login', ['username' => 'ghost', 'password' => 'wrong-password-value']);

        self::assertSame(401, $wrong->status);
        self::assertSame(401, $unknown->status);
        self::assertStringContainsString('Invalid username or password.', $wrong->body);
        self::assertStringContainsString('Invalid username or password.', $unknown->body);
    }

    public function testLoginIsRateLimited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(401, $this->app->post('/admin/login', ['username' => 'nobody', 'password' => 'wrong-password-value'])->status);
        }

        self::assertSame(429, $this->app->post('/admin/login', ['username' => 'nobody', 'password' => 'wrong-password-value'])->status);
    }

    public function testWrongTotpIsRejectedAndRateLimited(): void
    {
        $this->app->post('/admin/login', ['username' => 'admin', 'password' => AppHarness::PASSWORD]);
        $wrong = $this->app->currentCode() === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(401, $this->app->post('/admin/verify', ['code' => $wrong])->status);
        }

        self::assertSame(429, $this->app->post('/admin/verify', ['code' => $this->app->currentCode()])->status);
        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testTotpCodeCannotBeReplayed(): void
    {
        $this->app->signIn();
        $code = $this->app->currentCode();
        $this->app->post('/admin/logout');

        $this->app->post('/admin/login', ['username' => 'admin', 'password' => AppHarness::PASSWORD]);
        $replayed = $this->app->post('/admin/verify', ['code' => $code]);

        self::assertSame(401, $replayed->status);
        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testPendingLoginExpires(): void
    {
        $this->app->post('/admin/login', ['username' => 'admin', 'password' => AppHarness::PASSWORD]);
        $this->app->clock->advance(AdminAuthenticator::PENDING_TTL + 1);

        self::assertSame('/admin/login', $this->app->get('/admin/verify')->headers['Location']);
    }

    public function testSessionIsRegeneratedAtEachPrivilegeChange(): void
    {
        $this->app->signIn();

        self::assertSame(2, $this->app->session->regenerations);
    }

    public function testIdleSessionExpires(): void
    {
        $this->app->signIn();
        $this->app->clock->advance(AdminAuthenticator::IDLE_TTL + 1);

        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testAbsoluteSessionLifetimeIsEnforced(): void
    {
        $this->app->signIn();
        for ($elapsed = 0; $elapsed <= AdminAuthenticator::ABSOLUTE_TTL; $elapsed += AdminAuthenticator::IDLE_TTL) {
            $this->app->clock->advance(AdminAuthenticator::IDLE_TTL);
            $last = $this->app->get('/admin/submissions');
        }

        self::assertSame('/admin/login', $last->headers['Location']);
    }

    public function testDeleteRemovesSubmissionAndAudits(): void
    {
        $id = $this->seedSubmission();
        $this->app->signIn();

        $response = $this->app->post('/admin/submissions/' . $id . '/delete');

        self::assertSame('/admin/submissions', $response->headers['Location']);
        self::assertSame([], $this->app->submissions->rows);
        self::assertContains('delete_submission', $this->app->audit->actions());
    }

    public function testDeleteRequiresCsrfToken(): void
    {
        $id = $this->seedSubmission();
        $this->app->signIn();

        $response = $this->app->post('/admin/submissions/' . $id . '/delete', ['csrf_token' => 'forged']);

        self::assertSame(403, $response->status);
        self::assertCount(1, $this->app->submissions->rows);
    }

    public function testPasswordChangeRevokesActiveSession(): void
    {
        $this->app->signIn();
        self::assertSame(200, $this->app->get('/admin/submissions')->status);

        $this->app->admins->changePassword(1, (new PasswordHasher())->hash('a-different-long-password'));

        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testTotpResetRevokesActiveSession(): void
    {
        $this->app->signIn();

        $this->app->admins->replaceTotpSecret(1, $this->app->admins->findById(1)->totpSecretCiphertext);

        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testCredentialChangeInvalidatesPendingLogin(): void
    {
        $this->app->post('/admin/login', ['username' => 'admin', 'password' => AppHarness::PASSWORD]);
        $this->app->admins->changePassword(1, (new PasswordHasher())->hash('a-different-long-password'));

        self::assertSame(401, $this->app->post('/admin/verify', ['code' => $this->app->currentCode()])->status);
        self::assertSame('/admin/login', $this->app->get('/admin/verify')->headers['Location']);
        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }

    public function testTransparentRehashKeepsActiveSession(): void
    {
        $this->app->signIn();

        $this->app->admins->updatePasswordHash(1, (new PasswordHasher())->hash(AppHarness::PASSWORD));

        self::assertSame(200, $this->app->get('/admin/submissions')->status);
    }

    public function testCrossSiteAdminLinkKeepsSessionAndOffersSameOriginContinue(): void
    {
        $id = $this->seedSubmission();
        $this->app->signIn();
        $path = '/admin/submissions/' . $id;
        $auditEntries = count($this->app->audit->actions());

        $response = $this->app->kernel->handle(new Request('GET', $path, [], [], ['sec-fetch-site' => 'cross-site'], '203.0.113.10'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('href="' . $path . '"', $response->body);
        self::assertStringNotContainsString('Ada Lovelace', $response->body);
        self::assertCount($auditEntries, $this->app->audit->actions());
        self::assertSame(200, $this->app->get($path)->status);
    }

    public function testContinuePageEscapesThePath(): void
    {
        $response = $this->app->kernel->handle(new Request('GET', '/admin/"><img src=x>', [], [], ['sec-fetch-site' => 'cross-site'], '203.0.113.10'));

        self::assertStringNotContainsString('<img', $response->body);
    }

    public function testSameOriginAdminNavigationSkipsContinuePage(): void
    {
        $response = $this->app->kernel->handle(new Request('GET', '/admin/submissions', [], [], ['sec-fetch-site' => 'same-origin'], '203.0.113.10'));

        self::assertSame('/admin/login', $response->headers['Location']);
    }

    public function testLogoutEndsSession(): void
    {
        $this->app->signIn();
        $this->app->post('/admin/logout');

        self::assertSame('/admin/login', $this->app->get('/admin/submissions')->headers['Location']);
    }
}
