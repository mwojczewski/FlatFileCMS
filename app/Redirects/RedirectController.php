<?php

declare(strict_types=1);

namespace FlatFileCms\Redirects;

use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class RedirectController
{
    public function __construct(private RedirectRepository $redirects) {}

    public function resolve(Request $request): ?Response
    {
        $rule = array_find(
            $this->redirects->get()->rules(),
            static fn(RedirectRule $candidate): bool => $candidate->enabled()
                && $candidate->source() === $request->path(),
        );
        if ($rule === null) {
            return null;
        }

        return Response::redirect($rule->target(), $rule->status(), [
            'Cache-Control' => $rule->status() === 301 || $rule->status() === 308
                ? 'public, max-age=3600'
                : 'no-store',
        ]);
    }
}
