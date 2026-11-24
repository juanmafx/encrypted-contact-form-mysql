<?php

declare(strict_types=1);

namespace FormularioDorado\Http\Controller;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Admin\AuditLog;
use FormularioDorado\Admin\AuthOutcome;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Http\Request;
use FormularioDorado\Http\Response;
use FormularioDorado\Http\TemplateRenderer;
use FormularioDorado\Security\CsrfGuard;

final readonly class AdminController
{
    public function __construct(
        private TemplateRenderer $renderer,
        private AdminAuthenticator $authenticator,
        private SubmissionService $submissions,
        private AuditLog $audit,
        private CsrfGuard $csrf,
    ) {
    }

    public function home(): Response
    {
        return Response::redirect($this->authenticator->currentAdminId() === null ? '/admin/login' : '/admin/submissions');
    }

    public function loginForm(): Response
    {
        if ($this->authenticator->currentAdminId() !== null) {
            return Response::redirect('/admin/submissions');
        }

        return $this->loginPage(null);
    }

    public function login(Request $request, string $clientIp): Response
    {
        return match ($this->authenticator->attemptPassword($request->input('username'), $request->input('password'), $clientIp)) {
            AuthOutcome::Success => Response::redirect('/admin/verify'),
            AuthOutcome::Throttled => $this->loginPage('Too many attempts. Please try again later.', 429),
            AuthOutcome::Invalid => $this->loginPage('Invalid username or password.', 401),
        };
    }

    public function verifyForm(): Response
    {
        if (!$this->authenticator->hasPendingLogin()) {
            return Response::redirect('/admin/login');
        }

        return $this->verifyPage(null);
    }

    public function verify(Request $request): Response
    {
        if (!$this->authenticator->hasPendingLogin()) {
            return Response::redirect('/admin/login');
        }

        return match ($this->authenticator->attemptTotp($request->input('code'))) {
            AuthOutcome::Success => Response::redirect('/admin/submissions'),
            AuthOutcome::Throttled => $this->verifyPage('Too many attempts. Please try again later.', 429),
            AuthOutcome::Invalid => $this->verifyPage('Invalid authentication code.', 401),
        };
    }

    public function logout(): Response
    {
        $this->authenticator->logout();

        return Response::redirect('/admin/login');
    }

    public function index(Request $request): Response
    {
        $adminId = $this->authenticator->currentAdminId();
        if ($adminId === null) {
            return Response::redirect('/admin/login');
        }

        $page = $request->pageNumber();
        $submissions = $this->submissions->list($page);
        $this->audit->record($adminId, 'list_submissions', null);

        return $this->renderer->page('admin/submissions', 'Submissions', [
            'csrf' => $this->csrf->token(),
            'submissions' => $submissions,
            'page' => $page,
            'pageCount' => $this->submissions->pageCount(),
        ]);
    }

    public function show(string $id): Response
    {
        $adminId = $this->authenticator->currentAdminId();
        if ($adminId === null) {
            return Response::redirect('/admin/login');
        }

        $submission = $this->submissions->read($id);
        if ($submission === null) {
            return $this->notFound();
        }
        $this->audit->record($adminId, 'read_submission', $id);

        return $this->renderer->page('admin/submission', 'Submission', [
            'csrf' => $this->csrf->token(),
            'submission' => $submission,
        ]);
    }

    public function delete(string $id): Response
    {
        $adminId = $this->authenticator->currentAdminId();
        if ($adminId === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->submissions->delete($id)) {
            return $this->notFound();
        }
        $this->audit->record($adminId, 'delete_submission', $id);

        return Response::redirect('/admin/submissions');
    }

    private function notFound(): Response
    {
        return $this->renderer->page('error', 'Not found', ['message' => 'The requested page was not found.'], 404);
    }

    private function loginPage(?string $error, int $status = 200): Response
    {
        return $this->renderer->page('admin/login', 'Admin sign in', [
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ], $status);
    }

    private function verifyPage(?string $error, int $status = 200): Response
    {
        return $this->renderer->page('admin/verify', 'Two-factor authentication', [
            'csrf' => $this->csrf->token(),
            'error' => $error,
        ], $status);
    }
}
