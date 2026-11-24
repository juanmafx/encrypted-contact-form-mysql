#!/usr/bin/env php
<?php

declare(strict_types=1);

use FormularioDorado\Cli\AdminCli;
use FormularioDorado\Config\AppConfig;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Mail\PhpMailerNotifier;
use FormularioDorado\Persistence\DatabaseConnection;
use FormularioDorado\Persistence\PdoAdminRepository;
use FormularioDorado\Persistence\PdoRateLimiter;
use FormularioDorado\Persistence\PdoSubmissionRepository;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\SystemClock;
use FormularioDorado\Security\TotpService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = AppConfig::fromEnvironment(getenv());
$pdo = DatabaseConnection::open($config);
$clock = new SystemClock();
$cipher = new FieldCipher($config->dataKeys, $config->activeDataKeyId);
$limiter = new PdoRateLimiter($pdo, $clock);
$service = new SubmissionService(new PdoSubmissionRepository($pdo), $cipher, new PhpMailerNotifier($config), $clock);
$cli = new AdminCli(new PdoAdminRepository($pdo), new PasswordHasher(), new TotpService($clock), $cipher, $service, $limiter, $config->retentionDays);

exit($cli->run($_SERVER['argv'] ?? [], STDIN, STDOUT));
