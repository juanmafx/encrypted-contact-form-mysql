<?php

declare(strict_types=1);

namespace FormularioDorado\Http\Controller;

use FormularioDorado\Contact\ContactValidator;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Http\Request;
use FormularioDorado\Http\Response;
use FormularioDorado\Http\TemplateRenderer;
use FormularioDorado\Security\CsrfGuard;
use FormularioDorado\Security\SessionStore;
use FormularioDorado\Security\ThrottlePolicy;

final readonly class ContactController
{
    private const string SENT_FLASH = 'contact_sent';
    private const string TITLE = 'Formulario Dorado - Contact';

    public function __construct(
        private TemplateRenderer $renderer,
        private ContactValidator $validator,
        private SubmissionService $submissions,
        private ThrottlePolicy $throttle,
        private CsrfGuard $csrf,
        private SessionStore $session,
    ) {
    }

    public function show(): Response
    {
        $sent = $this->session->get(self::SENT_FLASH) === true;
        $this->session->remove(self::SENT_FLASH);

        return $this->form(['sent' => $sent]);
    }

    public function submit(Request $request, string $clientIp): Response
    {
        if ($request->input('website') !== '') {
            return $this->acknowledge();
        }

        $result = $this->validator->validate($request->form);
        if (!$result->isValid()) {
            return $this->form(['values' => $result->values, 'errors' => $result->errors], 422);
        }
        if (!$this->throttle->allowContact($clientIp)) {
            return $this->form([
                'values' => $result->values,
                'notice' => 'Too many messages were sent. Please try again later.',
            ], 429);
        }

        $this->submissions->submit($result->message);

        return $this->acknowledge();
    }

    private function acknowledge(): Response
    {
        $this->csrf->rotate();
        $this->session->set(self::SENT_FLASH, true);

        return Response::redirect('/');
    }

    private function form(array $data, int $status = 200): Response
    {
        return $this->renderer->page('contact', self::TITLE, $data + [
            'csrf' => $this->csrf->token(),
            'sent' => false,
            'values' => ['name' => '', 'phone' => '', 'email' => '', 'message' => ''],
            'errors' => [],
            'notice' => null,
        ], $status);
    }
}
