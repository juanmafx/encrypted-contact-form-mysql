<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Functional;

use FormularioDorado\Http\Kernel;
use FormularioDorado\Http\Request;
use FormularioDorado\Tests\Support\AppHarness;
use PHPUnit\Framework\TestCase;

final class ContactFlowTest extends TestCase
{
    private const array VALID = [
        'name' => 'Ada Lovelace',
        'phone' => '+1 555 010 0000',
        'email' => 'ada@example.test',
        'message' => 'Hello there',
    ];

    private AppHarness $app;

    protected function setUp(): void
    {
        $this->app = new AppHarness();
    }

    public function testFormRendersInEnglishWithSecurityHeaders(): void
    {
        $response = $this->app->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<html lang="en">', $response->body);
        self::assertStringContainsString('name="csrf_token"', $response->body);
        self::assertStringContainsString("default-src 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertArrayHasKey('Strict-Transport-Security', $response->headers);
    }

    public function testValidSubmissionIsEncryptedStoredAndNotified(): void
    {
        $response = $this->app->post('/contact', self::VALID);

        self::assertSame(303, $response->status);
        self::assertCount(1, $this->app->submissions->rows);
        self::assertCount(1, $this->app->notifier->sent);

        $stored = array_values($this->app->submissions->rows)[0];
        self::assertStringNotContainsString('Ada', $stored->nameCiphertext);
        self::assertStringNotContainsString('ada@example.test', $stored->emailCiphertext);
        self::assertNotNull($stored->notifiedAt);
        self::assertStringContainsString('Your message was sent', $this->app->get('/')->body);
    }

    public function testMailFailureKeepsSubmission(): void
    {
        $this->app->notifier->fails = true;
        $previous = ini_set('error_log', '/dev/null');

        $response = $this->app->post('/contact', self::VALID);
        ini_set('error_log', (string) $previous);

        self::assertSame(303, $response->status);
        self::assertNull(array_values($this->app->submissions->rows)[0]->notifiedAt);
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $this->app->get('/');
        $response = $this->app->kernel->handle(new Request('POST', '/contact', [], self::VALID, [], '203.0.113.10'));

        self::assertSame(403, $response->status);
        self::assertSame([], $this->app->submissions->rows);
    }

    public function testCrossOriginPostIsRejected(): void
    {
        self::assertSame(403, $this->app->post('/contact', self::VALID, ['origin' => 'https://evil.example'])->status);
        self::assertSame(403, $this->app->post('/contact', self::VALID, ['sec-fetch-site' => 'cross-site'])->status);
        self::assertSame([], $this->app->submissions->rows);
    }

    public function testSameOriginPostIsAccepted(): void
    {
        $response = $this->app->post('/contact', self::VALID, [
            'origin' => 'https://forms.example.test',
            'sec-fetch-site' => 'same-origin',
        ]);

        self::assertSame(303, $response->status);
    }

    public function testValidationErrorsEscapeReflectedInput(): void
    {
        $payload = '"><script>alert(1)</script>';
        $response = $this->app->post('/contact', ['name' => $payload, 'email' => 'bad'] + self::VALID);

        self::assertSame(422, $response->status);
        self::assertStringNotContainsString('<script>', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
        self::assertSame([], $this->app->submissions->rows);
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $response = $this->app->post('/contact', ['email' => "a@example.test\r\nBcc: b@example.test"] + self::VALID);

        self::assertSame(422, $response->status);
        self::assertSame([], $this->app->notifier->sent);
    }

    public function testHoneypotSilentlyDiscards(): void
    {
        $response = $this->app->post('/contact', ['website' => 'https://spam.example'] + self::VALID);

        self::assertSame(303, $response->status);
        self::assertSame([], $this->app->submissions->rows);
    }

    public function testRateLimitBlocksSixthSubmission(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(303, $this->app->post('/contact', self::VALID)->status);
        }

        self::assertSame(429, $this->app->post('/contact', self::VALID)->status);
        self::assertCount(5, $this->app->submissions->rows);
    }

    public function testOversizedBodyIsRejected(): void
    {
        $response = $this->app->kernel->handle(new Request('POST', '/contact', [], [], [], '203.0.113.10', Kernel::MAX_BODY_BYTES + 1));

        self::assertSame(413, $response->status);
    }

    public function testStylesheetIsServedAndUnknownRouteIs404(): void
    {
        $css = $this->app->get('/assets/app.css');

        self::assertSame(200, $css->status);
        self::assertStringStartsWith('text/css', $css->headers['Content-Type']);
        self::assertSame(404, $this->app->get('/assets/../index.php')->status);
        self::assertSame(404, $this->app->get('/uploads/x.php')->status);
    }
}
