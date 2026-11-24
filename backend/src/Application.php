<?php

declare(strict_types=1);

namespace FormularioDorado;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Admin\AdminRepository;
use FormularioDorado\Admin\AuditLog;
use FormularioDorado\Config\AppConfig;
use FormularioDorado\Contact\ContactValidator;
use FormularioDorado\Contact\SubmissionNotifier;
use FormularioDorado\Contact\SubmissionRepository;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Http\ClientIpResolver;
use FormularioDorado\Http\Controller\AdminController;
use FormularioDorado\Http\Controller\ContactController;
use FormularioDorado\Http\Kernel;
use FormularioDorado\Http\SecurityHeaders;
use FormularioDorado\Http\TemplateRenderer;
use FormularioDorado\Mail\PhpMailerNotifier;
use FormularioDorado\Persistence\DatabaseConnection;
use FormularioDorado\Persistence\PdoAdminRepository;
use FormularioDorado\Persistence\PdoAuditLog;
use FormularioDorado\Persistence\PdoRateLimiter;
use FormularioDorado\Persistence\PdoSubmissionRepository;
use FormularioDorado\Security\CsrfGuard;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\NativeSessionStore;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\RateLimiter;
use FormularioDorado\Security\SessionStore;
use FormularioDorado\Security\SystemClock;
use FormularioDorado\Security\ThrottlePolicy;
use FormularioDorado\Security\TotpService;
use Psr\Clock\ClockInterface;

final class Application
{
    public static function kernel(AppConfig $config): Kernel
    {
        $pdo = DatabaseConnection::open($config);
        $clock = new SystemClock();

        return self::compose(
            $config,
            new PdoSubmissionRepository($pdo),
            new PdoAdminRepository($pdo),
            new PdoAuditLog($pdo),
            new PdoRateLimiter($pdo, $clock),
            new PhpMailerNotifier($config),
            new NativeSessionStore($config->usesHttps()),
            $clock,
        );
    }

    public static function compose(
        AppConfig $config,
        SubmissionRepository $submissions,
        AdminRepository $admins,
        AuditLog $audit,
        RateLimiter $limiter,
        SubmissionNotifier $notifier,
        SessionStore $session,
        ClockInterface $clock,
    ): Kernel {
        $root = dirname(__DIR__, 2);
        $renderer = new TemplateRenderer($root . '/frontend/templates');
        $cipher = new FieldCipher($config->dataKeys, $config->activeDataKeyId);
        $csrf = new CsrfGuard($session);
        $throttle = new ThrottlePolicy($limiter, $config->ipHashKey);
        $service = new SubmissionService($submissions, $cipher, $notifier, $clock);
        $authenticator = new AdminAuthenticator(
            $admins,
            new PasswordHasher(),
            new TotpService($clock),
            $cipher,
            $session,
            $csrf,
            $throttle,
            $audit,
            $clock,
        );

        return new Kernel(
            new ContactController($renderer, new ContactValidator(), $service, $throttle, $csrf, $session),
            new AdminController($renderer, $authenticator, $service, $audit, $csrf),
            $renderer,
            $csrf,
            new SecurityHeaders($config->usesHttps()),
            new ClientIpResolver($config->trustedProxies),
            $config->origin(),
            $root . '/frontend/assets',
        );
    }
}
