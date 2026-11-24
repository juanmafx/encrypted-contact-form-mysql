<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Contact\ContactValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactValidatorTest extends TestCase
{
    private const array VALID = [
        'name' => 'Ada Lovelace',
        'phone' => '+1 (555) 010-0000',
        'email' => 'ada@example.test',
        'message' => "Hello\nWorld",
    ];

    public function testValidInputProducesMessage(): void
    {
        $result = (new ContactValidator())->validate(self::VALID);

        self::assertTrue($result->isValid());
        self::assertSame('Ada Lovelace', $result->message->name);
        self::assertSame("Hello\nWorld", $result->message->message);
    }

    public function testMessageIsOptionalAndLineEndingsNormalize(): void
    {
        $validator = new ContactValidator();

        self::assertTrue($validator->validate(['message' => ''] + self::VALID)->isValid());
        self::assertSame("a\nb", $validator->validate(['message' => "a\r\nb"] + self::VALID)->message->message);
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputIsRejected(string $field, mixed $value): void
    {
        $result = (new ContactValidator())->validate([$field => $value] + self::VALID);

        self::assertFalse($result->isValid());
        self::assertArrayHasKey($field, $result->errors);
    }

    public static function invalidInputs(): array
    {
        return [
            'empty name' => ['name', ''],
            'name with CRLF header injection' => ['name', "Eve\r\nBcc: victim@example.test"],
            'name with LF' => ['name', "Eve\nX: y"],
            'name with NUL' => ['name', "Eve\0"],
            'name with line separator' => ['name', "Eve\u{2028}X"],
            'name too long' => ['name', str_repeat('a', 121)],
            'name as array' => ['name', ['x']],
            'name invalid UTF-8' => ['name', "\xC3\x28"],
            'email with CRLF' => ['email', "a@example.test\r\nBcc: b@example.test"],
            'email malformed' => ['email', 'not-an-email'],
            'email too long' => ['email', str_repeat('a', 250) . '@example.test'],
            'phone with letters' => ['phone', '555-CALL-NOW'],
            'phone too short' => ['phone', '123'],
            'phone with trailing newline' => ['phone', "5550100000\n"],
            'message too long' => ['message', str_repeat('a', 5001)],
            'message with control byte' => ['message', "bad\x07byte"],
            'message invalid UTF-8' => ['message', "\xFF"],
        ];
    }
}
