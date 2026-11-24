<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Contact\ContactMessage;
use FormularioDorado\Mail\PhpMailerNotifier;
use FormularioDorado\Tests\Support\TestConfig;
use PHPUnit\Framework\TestCase;

final class PhpMailerNotifierTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    private function message(): ContactMessage
    {
        return new ContactMessage('Ada Lovelace', '+1 555 010 0000', 'ada@example.test', '<b>private text</b>');
    }

    public function testDefaultNotificationOmitsPersonalData(): void
    {
        $mail = (new PhpMailerNotifier(TestConfig::create()))->compose(self::ID, $this->message());
        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        self::assertStringNotContainsString('Ada', $mime);
        self::assertStringNotContainsString('ada@example.test', $mime);
        self::assertStringNotContainsString('private text', $mime);
        self::assertStringContainsString(self::ID, $mime);
        self::assertSame('text/plain', $mail->ContentType);
    }

    public function testOptInNotificationUsesReplyToAndFixedSender(): void
    {
        $config = TestConfig::create(['MAIL_INCLUDE_PERSONAL_DATA' => 'true']);
        $mail = (new PhpMailerNotifier($config))->compose(self::ID, $this->message());
        $mail->preSend();
        $mime = $mail->getSentMIMEMessage();

        self::assertSame('noreply@example.test', $mail->From);
        self::assertSame('ada@example.test', $mail->getReplyToAddresses()[0][0]);
        self::assertStringContainsString('Reply-To:', $mime);
        self::assertSame('text/plain', $mail->ContentType);
    }

    public function testTransportRequiresEncryptionByDefault(): void
    {
        $mail = (new PhpMailerNotifier(TestConfig::create()))->compose(self::ID, $this->message());

        self::assertSame('tls', $mail->SMTPSecure);
        self::assertTrue($mail->SMTPAutoTLS);
    }
}
