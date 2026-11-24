<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

use FormularioDorado\Http\Controller\AdminController;
use FormularioDorado\Http\Controller\ContactController;
use FormularioDorado\Security\CsrfGuard;
use Throwable;

final readonly class Kernel
{
    public const int MAX_BODY_BYTES = 65536;

    private const string SUBMISSION_ROUTE = '#^/admin/submissions/([a-f0-9]{32})(/delete)?$#D';

    public function __construct(
        private ContactController $contact,
        private AdminController $admin,
        private TemplateRenderer $renderer,
        private CsrfGuard $csrf,
        private SecurityHeaders $securityHeaders,
        private ClientIpResolver $clientIp,
        private string $allowedOrigin,
        private string $assetDirectory,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $failure) {
            error_log('unhandled_exception type=' . $failure::class);
            $response = $this->error('Something went wrong. Please try again later.', 500);
        }

        return $this->securityHeaders->apply($response);
    }

    private function dispatch(Request $request): Response
    {
        if ($request->contentLength > self::MAX_BODY_BYTES) {
            return $this->error('The request is too large.', 413);
        }
        if ($request->method === 'POST' && !$this->isTrustedPost($request)) {
            return $this->error('The request could not be verified. Reload the page and try again.', 403);
        }

        if ($request->method === 'GET' && $this->isCrossSiteAdminNavigation($request)) {
            return $this->renderer->page('admin/continue', 'Continue to admin', ['path' => $request->path]);
        }

        $ip = $this->clientIp->resolve($request);
        $route = $request->method . ' ' . $request->path;

        $response = match ($route) {
            'GET /' => $this->contact->show(),
            'POST /contact' => $this->contact->submit($request, $ip),
            'GET /assets/app.css' => $this->stylesheet(),
            'GET /admin' => $this->admin->home(),
            'GET /admin/login' => $this->admin->loginForm(),
            'POST /admin/login' => $this->admin->login($request, $ip),
            'GET /admin/verify' => $this->admin->verifyForm(),
            'POST /admin/verify' => $this->admin->verify($request),
            'POST /admin/logout' => $this->admin->logout(),
            'GET /admin/submissions' => $this->admin->index($request),
            default => null,
        };

        return $response ?? $this->dispatchSubmission($request);
    }

    private function dispatchSubmission(Request $request): Response
    {
        if (preg_match(self::SUBMISSION_ROUTE, $request->path, $matches) === 1) {
            $isDelete = ($matches[2] ?? '') !== '';
            if ($request->method === 'GET' && !$isDelete) {
                return $this->admin->show($matches[1]);
            }
            if ($request->method === 'POST' && $isDelete) {
                return $this->admin->delete($matches[1]);
            }
        }

        return $this->error('The requested page was not found.', 404);
    }

    private function isCrossSiteAdminNavigation(Request $request): bool
    {
        return in_array($request->header('sec-fetch-site'), ['cross-site', 'same-site'], true)
            && ($request->path === '/admin' || str_starts_with($request->path, '/admin/'));
    }

    private function isTrustedPost(Request $request): bool
    {
        $fetchSite = $request->header('sec-fetch-site');
        if ($fetchSite !== null && $fetchSite !== 'same-origin') {
            return false;
        }

        $origin = $request->header('origin');
        if ($origin !== null && strtolower($origin) !== $this->allowedOrigin) {
            return false;
        }

        return $this->csrf->isValid($request->input('csrf_token'));
    }

    private function stylesheet(): Response
    {
        $css = file_get_contents($this->assetDirectory . '/css/app.css');
        if ($css === false) {
            return $this->error('The requested page was not found.', 404);
        }

        return new Response(200, $css, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function error(string $message, int $status): Response
    {
        return $this->renderer->page('error', 'Error', ['message' => $message], $status);
    }
}
