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
        foreach ($this->redirects->get()->rules() as $rule) {
            if ($rule->enabled() && $rule->source() === $request->path()) {
                return Response::redirect($rule->target(), $rule->status(), [
                    'Cache-Control' => $rule->status() === 301 || $rule->status() === 308
                        ? 'public, max-age=3600'
                        : 'no-store',
                ]);
            }
        }

        return null;
    }
}
