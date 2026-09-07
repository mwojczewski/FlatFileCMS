<?php

declare(strict_types=1);

namespace FlatFileCms\Forms;

use FlatFileCms\Auth\AuthenticationException;
use FlatFileCms\Auth\RateLimiter;
use FlatFileCms\Config\LanguageRepository;
use FlatFileCms\Content\PageRepository;
use FlatFileCms\Domain\Content\PageIdentity;
use FlatFileCms\Mail\Mailer;

final readonly class ContactFormService
{
    private const array FIELD_TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];

    public function __construct(
        private LanguageRepository $languages,
        private PageRepository $pages,
        private RateLimiter $rateLimiter,
        private Mailer $mailer,
    ) {}

    /** @param array<string, mixed> $input */
    public function submit(
        string $pageId,
        string $blockId,
        string $locale,
        array $input,
        string $clientIp,
    ): void {
        if (($input['website'] ?? '') !== '') {
            return;
        }

        try {
            $this->rateLimiter->assertAllowed('contact-form', $clientIp);
        } catch (AuthenticationException $exception) {
            throw new ContactFormRateLimitException('Zbyt wiele prób. Spróbuj ponownie później.', previous: $exception);
        }

        $languages = $this->languages->get();
        if (!$languages->has($locale)) {
            throw new ContactFormException('Nieobsługiwany język formularza.');
        }

        $page = $this->pages->get(PageIdentity::fromString($pageId), $languages);
        $block = null;
        foreach ($page->blocks() as $candidate) {
            if (($candidate['id'] ?? null) === $blockId && ($candidate['type'] ?? null) === 'contact-form'
                && ($candidate['enabled'] ?? true) === true) {
                $block = $candidate;
                break;
            }
        }
        if ($block === null || !\is_array($block['data'] ?? null)) {
            throw new ContactFormException('Formularz nie istnieje lub jest wyłączony.');
        }

        $config = $block['data'];
        $recipient = $this->requiredEmail($config['recipient'] ?? null, 'Nieprawidłowy adres odbiorcy.');
        $submitted = \is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $rows = [];
        $replyTo = null;

        foreach ($this->configuredFields($config['fields'] ?? null) as $field) {
            $name = $this->fieldName($field['name'] ?? null);
            $type = \is_string($field['type'] ?? null) ? $field['type'] : '';
            if (!\in_array($type, self::FIELD_TYPES, true)) {
                throw new ContactFormException('Formularz ma nieobsługiwany typ pola.');
            }
            $label = $this->localized($field['label'] ?? null, $locale, $languages->default());
            $required = ($field['required'] ?? false) === true;
            $raw = $submitted[$name] ?? null;
            $value = $type === 'checkbox'
                ? (\in_array($raw, ['1', 'true', 'on'], true) ? 'Tak' : '')
                : (\is_string($raw) ? trim($raw) : '');

            if ($required && $value === '') {
                throw new ContactFormException("Pole „{$label}” jest wymagane.");
            }
            if (mb_strlen($value) > 5000 || str_contains($value, "\0")) {
                throw new ContactFormException("Pole „{$label}” ma nieprawidłową wartość.");
            }
            if ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new ContactFormException("Pole „{$label}” musi zawierać poprawny adres e-mail.");
            }
            if ($type === 'select' && $value !== '' && !\in_array($value, $this->options($field['options'] ?? ''), true)) {
                throw new ContactFormException("Pole „{$label}” ma niedozwoloną wartość.");
            }
            if ($type === 'email' && $replyTo === null && $value !== '') {
                $replyTo = $value;
            }
            $rows[] = [$label, $value === '' ? '—' : $value];
        }

        $this->rateLimiter->hit('contact-form', $clientIp);
        $subject = $this->localized($config['subject'] ?? null, $locale, $languages->default());
        [$text, $html] = $this->message($subject, $rows, $pageId);
        $this->mailer->send($recipient, $subject, $text, $html, $replyTo);

        if (($config['send_confirmation'] ?? false) === true && $replyTo !== null) {
            $confirmationSubject = $this->localized(
                $config['confirmation_subject'] ?? null,
                $locale,
                $languages->default(),
            );
            $confirmationText = $this->localized(
                $config['confirmation_message'] ?? null,
                $locale,
                $languages->default(),
            );
            $this->mailer->send(
                $replyTo,
                $confirmationSubject,
                $confirmationText,
                '<p>' . nl2br(htmlspecialchars($confirmationText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>',
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function configuredFields(mixed $fields): array
    {
        if (!\is_array($fields) || $fields === []) {
            throw new ContactFormException('Formularz nie zawiera pól.');
        }
        foreach ($fields as $field) {
            if (!\is_array($field)) {
                throw new ContactFormException('Nieprawidłowa konfiguracja pola.');
            }
        }

        /** @var list<array<string, mixed>> $fields */
        return $fields;
    }

    private function fieldName(mixed $name): string
    {
        if (!\is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $name) !== 1) {
            throw new ContactFormException('Nieprawidłowa nazwa pola formularza.');
        }

        return $name;
    }

    private function requiredEmail(mixed $value, string $message): string
    {
        if (!\is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ContactFormException($message);
        }

        return $value;
    }

    private function localized(mixed $value, string $locale, string $fallback): string
    {
        if (!\is_array($value)) {
            throw new ContactFormException('Brak tłumaczenia konfiguracji formularza.');
        }
        $localized = $value[$locale] ?? $value[$fallback] ?? null;
        if (!\is_string($localized) || trim($localized) === '') {
            throw new ContactFormException('Brak tłumaczenia konfiguracji formularza.');
        }

        return trim($localized);
    }

    /** @return list<string> */
    private function options(mixed $value): array
    {
        if (!\is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), preg_split('/\R/u', $value) ?: []), static fn(string $item): bool => $item !== ''));
    }

    /**
     * @param list<array{string, string}> $rows
     * @return array{string, string}
     */
    private function message(string $subject, array $rows, string $pageId): array
    {
        $text = $subject . "\nStrona: {$pageId}\n\n";
        $html = '<h1>' . htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>'
            . '<p><strong>Strona:</strong> ' . htmlspecialchars($pageId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><dl>';
        foreach ($rows as [$label, $value]) {
            $text .= "{$label}: {$value}\n";
            $html .= '<dt><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></dt><dd>'
                . nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</dd>';
        }

        return [$text, $html . '</dl>'];
    }
}
