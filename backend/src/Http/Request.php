<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $form = [],
        public array $headers = [],
        public string $remoteAddress = '',
        public int $contentLength = 0,
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($name) && is_string($value) && str_starts_with($name, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
            }
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
        );
    }

    public function input(string $name): string
    {
        $value = $this->form[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function pageNumber(): int
    {
        $page = filter_var($this->query['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ]);

        return $page === false ? 1 : $page;
    }
}
