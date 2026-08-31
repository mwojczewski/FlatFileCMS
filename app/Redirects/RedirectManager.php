<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

use FlatFileCms\Infrastructure\Filesystem\FileRevision;
use FlatFileCms\Support\UuidV7;
use InvalidArgumentException;

final readonly class RedirectManager
{
    public function __construct(private RedirectRepository $redirects) {}

    public function create(
        string $source,
        string $target,
        int $status,
        bool $enabled,
        FileRevision $revision,
    ): RedirectDocument {
        $rules = $this->redirects->get()->rules();
        $rules[] = new RedirectRule(UuidV7::generate(), $source, $target, $status, $enabled);

        return $this->redirects->update($rules, $revision);
    }

    public function update(
        string $id,
        string $source,
        string $target,
        int $status,
        bool $enabled,
        FileRevision $revision,
    ): RedirectDocument {
        $rules = $this->redirects->get()->rules();
        $found = false;
        foreach ($rules as $index => $rule) {
            if ($rule->id() === $id) {
                $rules[$index] = new RedirectRule($id, $source, $target, $status, $enabled);
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new InvalidArgumentException('Redirect rule not found.');
        }

        return $this->redirects->update($rules, $revision);
    }

    public function delete(string $id, FileRevision $revision): RedirectDocument
    {
        $current = $this->redirects->get()->rules();
        $rules = array_values(array_filter(
            $current,
            static fn(RedirectRule $rule): bool => $rule->id() !== $id,
        ));
        if (\count($rules) === \count($current)) {
            throw new InvalidArgumentException('Redirect rule not found.');
        }

        return $this->redirects->update($rules, $revision);
    }
}
