<?php

declare(strict_types=1);

namespace FlatFileCms\Forms;

use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class ContactFormController
{
    public function __construct(private ContactFormService $forms) {}

    public function submit(Request $request): Response
    {
        $body = $request->parsedBody();
        $returnPath = $this->returnPath($body['return_path'] ?? null);

        try {
            $this->forms->submit(
                $this->string($body['page_id'] ?? null),
                $this->string($body['block_id'] ?? null),
                $this->string($body['locale'] ?? null),
                $body,
                $request->clientIp(),
            );

            return Response::redirect($returnPath . '?form=sent#contact-form', 303);
        } catch (ContactFormRateLimitException) {
            return Response::redirect($returnPath . '?form=rate-limited#contact-form', 303);
        } catch (ContactFormException|\InvalidArgumentException) {
            return Response::redirect($returnPath . '?form=invalid#contact-form', 303);
        }
    }

    private function string(mixed $value): string
    {
        if (!\is_string($value) || $value === '') {
            throw new ContactFormException('Brak identyfikatora formularza.');
        }

        return $value;
    }

    private function returnPath(mixed $value): string
    {
        if (!\is_string($value) || preg_match('#^/(?!/)[a-zA-Z0-9/_-]*$#D', $value) !== 1) {
            return '/';
        }

        return $value;
    }
}
