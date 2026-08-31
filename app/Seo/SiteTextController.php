<?php

declare(strict_types=1);

namespace FlatFileCms\Seo;

use FlatFileCms\Config\SiteTextDocument;
use FlatFileCms\Config\SiteTextRepository;
use FlatFileCms\Http\HttpException;
use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class SiteTextController
{
    public function __construct(private SiteTextRepository $texts) {}

    public function llms(Request $request): Response
    {
        return $this->response($this->texts->llms(), 'LLMS_FILE_NOT_FOUND');
    }

    public function security(Request $request): Response
    {
        return $this->response($this->texts->security(), 'SECURITY_FILE_NOT_FOUND');
    }

    private function response(SiteTextDocument $document, string $errorCode): Response
    {
        if ($document->contents() === '') {
            throw new HttpException(404, $errorCode, 'Text file not configured.');
        }

        return new Response($document->contents(), headers: [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
