<?php

declare(strict_types=1);

namespace FlatFileCms\Tests\Support;

use FlatFileCms\Mail\Mailer;

final class InMemoryMailer implements Mailer
{
    /** @var list<array{recipient: string, subject: string, text: string, html: string}> */
    private array $messages = [];

    public function send(string $recipient, string $subject, string $text, string $html): void
    {
        $this->messages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ];
    }

    /** @return array{recipient: string, subject: string, text: string, html: string} */
    public function lastMessage(): array
    {
        if ($this->messages === []) {
            throw new \RuntimeException('No email was sent.');
        }

        return $this->messages[\count($this->messages) - 1];
    }

    public function count(): int
    {
        return \count($this->messages);
    }
}
