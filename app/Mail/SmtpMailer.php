<?php

declare(strict_types=1);

namespace FlatFileCms\Mail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final readonly class SmtpMailer implements Mailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        private string $password,
        private string $fromAddress,
        private string $fromName,
    ) {
        if (!\in_array($this->encryption, ['none', 'starttls', 'smtps'], true)) {
            throw new MailException('MAIL_ENCRYPTION must be none, starttls or smtps.');
        }
    }

    public function send(string $recipient, string $subject, string $text, string $html): void
    {
        try {
            $message = new PHPMailer(true);
            $message->isSMTP();
            $message->Host = $this->host;
            $message->Port = $this->port;
            $message->SMTPAuth = $this->username !== '';
            $message->Username = $this->username;
            $message->Password = $this->password;
            $message->CharSet = PHPMailer::CHARSET_UTF8;
            if ($this->encryption === 'starttls') {
                $message->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'smtps') {
                $message->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $message->SMTPAutoTLS = false;
            }

            $message->setFrom($this->fromAddress, $this->fromName);
            $message->addAddress($recipient);
            $message->Subject = $subject;
            $message->isHTML(true);
            $message->Body = $html;
            $message->AltBody = $text;
            $message->send();
        } catch (Exception $exception) {
            throw new MailException('Unable to send the email.', previous: $exception);
        }
    }
}
