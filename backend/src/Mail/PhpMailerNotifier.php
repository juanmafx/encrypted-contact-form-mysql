<?php

declare(strict_types=1);

namespace FormularioDorado\Mail;

use FormularioDorado\Config\AppConfig;
use FormularioDorado\Contact\ContactMessage;
use FormularioDorado\Contact\SubmissionNotifier;
use PHPMailer\PHPMailer\PHPMailer;

final readonly class PhpMailerNotifier implements SubmissionNotifier
{
    public function __construct(private AppConfig $config)
    {
    }

    public function notify(string $submissionId, ContactMessage $message): void
    {
        $this->compose($submissionId, $message)->send();
    }

    public function compose(string $submissionId, ContactMessage $message): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config->smtpHost;
        $mail->Port = $this->config->smtpPort;
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        $mail->XMailer = ' ';
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->SMTPAuth = $this->config->smtpUser !== '';
        $mail->Username = $this->config->smtpUser;
        $mail->Password = $this->config->smtpPassword;
        $mail->SMTPAutoTLS = $this->config->smtpEncryption !== 'none';
        $mail->SMTPSecure = match ($this->config->smtpEncryption) {
            'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };

        $mail->setFrom($this->config->mailFrom, 'Formulario Dorado', false);
        $mail->addAddress($this->config->mailTo);
        $mail->isHTML(false);
        $mail->Subject = 'New contact form submission';
        $mail->Body = $this->body($submissionId, $message);

        if ($this->config->mailIncludesPersonalData) {
            $mail->addReplyTo($message->email, $message->name);
        }

        return $mail;
    }

    private function body(string $submissionId, ContactMessage $message): string
    {
        $link = $this->config->baseUrl . '/admin/submissions/' . $submissionId;
        if (!$this->config->mailIncludesPersonalData) {
            return "A new contact form submission was received.\n\nRead it in the admin area: " . $link . "\n";
        }

        return "A new contact form submission was received.\n\n"
            . 'Name: ' . $message->name . "\n"
            . 'Phone: ' . $message->phone . "\n"
            . 'Email: ' . $message->email . "\n\n"
            . "Message:\n" . $message->message . "\n\n"
            . 'Admin link: ' . $link . "\n";
    }
}
