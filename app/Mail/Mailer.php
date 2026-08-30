<?php

declare(strict_types=1);

namespace FlatFileCms\Mail;

interface Mailer
{
    public function send(string $recipient, string $subject, string $text, string $html): void;
}
