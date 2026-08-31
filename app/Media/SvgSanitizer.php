<?php

declare(strict_types=1);

namespace FlatFileCms\Media;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SvgSanitizer
{
    private const array BLOCKED_ELEMENTS = [
        'audio', 'embed', 'foreignobject', 'iframe', 'object', 'script', 'style', 'video',
    ];

    public function sanitize(string $contents): string
    {
        if (\preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) === 1) {
            throw new MediaException('SVG document declarations and entities are not allowed.');
        }

        $document = new DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previous);
        }
        if (!$loaded || !$document->documentElement instanceof DOMElement || \strtolower($document->documentElement->localName ?? '') !== 'svg') {
            throw new MediaException('Uploaded SVG is not a valid SVG document.');
        }

        $nodes = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }
        foreach ($nodes as $element) {
            if (\in_array(\strtolower($element->localName ?? ''), self::BLOCKED_ELEMENTS, true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }
            $attributes = [];
            foreach ($element->attributes as $attribute) {
                $attributes[] = $attribute;
            }
            foreach ($attributes as $attribute) {
                $name = \strtolower($attribute->nodeName);
                $value = \trim($attribute->nodeValue ?? '');
                if (
                    \str_starts_with($name, 'on')
                    || $name === 'style'
                    || (\in_array($name, ['href', 'xlink:href', 'src'], true) && !$this->safeReference($value))
                    || \preg_match('/(?:javascript|vbscript|data)\s*:/i', $value) === 1
                    || \preg_match('/url\s*\(/i', $value) === 1
                ) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }

        $sanitized = $document->saveXML($document->documentElement);
        if (!\is_string($sanitized) || $sanitized === '') {
            throw new MediaException('Sanitized SVG could not be serialized.');
        }

        return $sanitized;
    }

    private function safeReference(string $value): bool
    {
        return $value === '' || (\str_starts_with($value, '#') && \preg_match('/^#[A-Za-z_][A-Za-z0-9_.:-]*$/D', $value) === 1);
    }
}
