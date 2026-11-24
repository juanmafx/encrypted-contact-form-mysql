<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

use Closure;
use Throwable;

final readonly class TemplateRenderer
{
    public function __construct(private string $templateDirectory)
    {
    }

    public function page(string $template, string $title, array $data = [], int $status = 200): Response
    {
        $escape = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
        );
        $content = $this->capture($template, $data, $escape);
        $html = $this->capture('layout', ['title' => $title, 'content' => $content], $escape);

        return Response::html($html, $status);
    }

    private function capture(string $template, array $view, Closure $e): string
    {
        ob_start();
        try {
            include $this->templateDirectory . '/' . $template . '.php';
        } catch (Throwable $failure) {
            ob_end_clean();
            throw $failure;
        }

        return (string) ob_get_clean();
    }
}
