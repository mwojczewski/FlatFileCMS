<?php

declare(strict_types=1);

namespace FlatFileCms\Api;

use FlatFileCms\Http\Request;
use FlatFileCms\Http\Response;

final readonly class ApiResponseFactory
{
    /** @param array<string, mixed> $data */
    public function cacheable(Request $request, array $data, int $modifiedAt): Response
    {
        $response = Response::json($data);
        $etag = '"' . hash('sha256', $response->body()) . '"';
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

            return new Response($response->body(), 200, $headers);
        }

        $ifModifiedSince = $request->header('if-modified-since');
        if ($ifModifiedSince !== null) {
            $timestamp = strtotime($ifModifiedSince);
            if ($timestamp !== false && $timestamp >= $modifiedAt) {
                return new Response('', 304, $headers);
            }
        }

        return new Response($response->body(), 200, $headers);
    }
}
