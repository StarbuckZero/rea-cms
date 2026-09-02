<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

final class SafeHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'em', 'u', 's',
        'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption', 'hr', 'code', 'pre',
    ];

    private const DANGEROUS_TAGS = [
        'script', 'style', 'template', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math',
    ];

    private const TAG_ALIASES = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'div' => 'p'];
    private const ALIGNMENT_CLASSES = ['align-left', 'align-center', 'align-right'];
    private const IMAGE_SIZE_CLASSES = ['size-small', 'size-medium', 'size-large', 'size-original'];

    private function __construct(public readonly string $value)
    {
    }

    public static function sanitize(string $value): self
    {
        if (trim($value) === '') {
            return new self('');
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<!doctype html><html><body>' . $value . '</body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return new self('');
        }

        $bodies = $document->getElementsByTagName('body');
        $body = $bodies->item(0);
        if (!$body instanceof DOMElement) {
            return new self('');
        }

        foreach (self::children($body) as $child) {
            self::sanitizeNode($child, $document);
        }

        $html = '';
        foreach (self::children($body) as $child) {
            $html .= $document->saveHTML($child) ?: '';
        }

        return new self($html);
    }

    private static function sanitizeNode(DOMNode $node, DOMDocument $document): void
    {
        if ($node instanceof DOMComment) {
            $node->parentNode?->removeChild($node);
            return;
        }
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DANGEROUS_TAGS, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        if (isset(self::TAG_ALIASES[$tag])) {
            $node = self::replaceTag($node, self::TAG_ALIASES[$tag], $document);
            $tag = strtolower($node->tagName);
        }

        foreach (self::children($node) as $child) {
            self::sanitizeNode($child, $document);
        }

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($node);
            return;
        }

        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[strtolower($attribute->name)] = $attribute->value;
        }
        while ($node->attributes->length > 0) {
            $attribute = $node->attributes->item(0);
            if ($attribute !== null) {
                $node->removeAttributeNode($attribute);
            }
        }

        self::restoreClasses($node, $tag, $attributes['class'] ?? '');
        if ($tag === 'a') {
            self::restoreLinkAttributes($node, $attributes);
        }
        if ($tag === 'img') {
            self::restoreImageAttributes($node, $attributes);
        }
        if ($tag === 'figcaption' && trim($node->textContent) === '') {
            $node->parentNode?->removeChild($node);
        }
    }

    /** @param array<string, string> $attributes */
    private static function restoreLinkAttributes(DOMElement $element, array $attributes): void
    {
        $href = self::safeUrl($attributes['href'] ?? '', false);
        if ($href !== '') {
            $element->setAttribute('href', $href);
        }
        if (($attributes['title'] ?? '') !== '') {
            $element->setAttribute('title', $attributes['title']);
        }
        if (($attributes['target'] ?? '') === '_blank') {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /** @param array<string, string> $attributes */
    private static function restoreImageAttributes(DOMElement $element, array $attributes): void
    {
        $source = self::safeUrl($attributes['src'] ?? '', true);
        if ($source !== '') {
            $element->setAttribute('src', $source);
        }
        $element->setAttribute('alt', $attributes['alt'] ?? '');
        if (($attributes['title'] ?? '') !== '') {
            $element->setAttribute('title', $attributes['title']);
        }
        foreach (['width', 'height'] as $dimension) {
            if (preg_match('/^[1-9][0-9]{0,4}$/D', $attributes[$dimension] ?? '') === 1) {
                $element->setAttribute($dimension, $attributes[$dimension]);
            }
        }
        $element->setAttribute('loading', 'lazy');
    }

    private static function restoreClasses(DOMElement $element, string $tag, string $value): void
    {
        if ($value === '') {
            return;
        }
        $allowed = [];
        if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'li', 'figure'], true)) {
            $allowed = [...$allowed, ...self::ALIGNMENT_CLASSES];
        }
        if (in_array($tag, ['figure', 'img'], true)) {
            $allowed = [...$allowed, ...self::IMAGE_SIZE_CLASSES];
        }
        $classes = array_values(array_unique(array_intersect(preg_split('/\s+/', trim($value)) ?: [], $allowed)));
        if ($classes !== []) {
            $element->setAttribute('class', implode(' ', $classes));
        }
    }

    private static function safeUrl(string $value, bool $image): string
    {
        $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[\x00-\x20\x7f]+/u', '', $decoded) ?? '';
        if ($normalized === '') {
            return '';
        }
        if (
            str_starts_with($normalized, '#') || str_starts_with($normalized, '/')
            || str_starts_with($normalized, './') || str_starts_with($normalized, '../')
        ) {
            return trim($value);
        }
        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
        $allowedSchemes = $image ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];
        if ($scheme !== '') {
            return in_array($scheme, $allowedSchemes, true) ? trim($value) : '';
        }
        return preg_match('/^[^:\/?#]+(?:[\/?#]|$)/D', $normalized) === 1 ? trim($value) : '';
    }

    private static function replaceTag(DOMElement $element, string $tag, DOMDocument $document): DOMElement
    {
        $replacement = $document->createElement($tag);
        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($replacement, $element);
        return $replacement;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    /** @return list<DOMNode> */
    private static function children(DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        return $children;
    }
}
