<?php

declare(strict_types=1);

namespace FlatFileCms\Http;

final readonly class HtmlResponseFactory
{
    public function cacheable(Request $request, string $html, int $modifiedAt): Response
    {
        $response = Response::html($html);
        $etag = '"' . hash('sha256', $html) . '"';
        $headers = [
            ...$response->headers(),
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ];

        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
                    return new Response('', 304, $headers);
                }
            }

            return new Response($html, 200, $headers);
        }

        $ifModifiedSince = $request->header('if-modified-since');
        if ($ifModifiedSince !== null) {
            $timestamp = strtotime($ifModifiedSince);
            if ($timestamp !== false && $timestamp >= $modifiedAt) {
                return new Response('', 304, $headers);
            }
        }

        return new Response($html, 200, $headers);
    }
}
