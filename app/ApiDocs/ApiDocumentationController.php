<?php

declare(strict_types=1);

namespace FlatFileCms\ApiDocs;

use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class ApiDocumentationController
{
    private const SCALAR_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/@scalar/api-reference';

    public function __construct(private OpenApiDocument $document) {}

    public function specification(Request $request): Response
    {
        $json = $this->document->json();
        $etag = '"' . hash('sha256', $json) . '"';
        $headers = [
            'Content-Type' => 'application/vnd.oai.openapi+json;version=3.1;charset=UTF-8',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $this->document->modifiedAt()) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->etagMatches($request->header('if-none-match'), $etag)) {
            return new Response('', 304, $headers);
        }

        return new Response($json, 200, $headers);
    }

    public function documentation(Request $request): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>FlatfileCMS API</title>
</head>
<body>
  <script id="api-reference" data-url="/api/openapi.json"></script>
  <script src="SCALAR_SCRIPT_URL"></script>
</body>
</html>
HTML;
        $html = str_replace('SCALAR_SCRIPT_URL', self::SCALAR_SCRIPT_URL, $html);

        return Response::html($html, headers: [
            'Cache-Control' => 'public, max-age=300',
            'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; "
                . "script-src https://cdn.jsdelivr.net; connect-src 'self'; "
                . "style-src 'unsafe-inline'; img-src 'self' data: https:; font-src data: https:",
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function etagMatches(?string $header, string $etag): bool
    {
        if ($header === null) {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                return true;
            }
        }

        return false;
    }
}
