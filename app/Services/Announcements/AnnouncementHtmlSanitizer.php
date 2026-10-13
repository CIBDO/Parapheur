<?php

namespace App\Services\Announcements;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Liste blanche HTML pour le corps des annonces. Aucune balise exécutable.
 */
final class AnnouncementHtmlSanitizer
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title'],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => [],
        'td' => [],
    ];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body instanceof DOMElement) {
            return '';
        }

        $this->sanitize($body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    private function sanitize(DOMNode $node): void
    {
        $index = 0;
        while ($index < $node->childNodes->length) {
            $child = $node->childNodes->item($index);
            if (! $child instanceof DOMElement) {
                if ($child !== null && ! ($child instanceof \DOMText)) {
                    $node->removeChild($child);

                    continue;
                }
                $index++;

                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
                $node->removeChild($child);

                continue;
            }

            if (! isset(self::ALLOWED[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->filterAttributes($child, self::ALLOWED[$tag]);
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (! $this->safeHref($href)) {
                    $child->removeAttribute('href');
                }
                $child->setAttribute('rel', 'noopener noreferrer');
            }

            $this->sanitize($child);
            $index++;
        }
    }

    /** @param  list<string>  $allowed */
    private function filterAttributes(DOMElement $element, array $allowed): void
    {
        if (! $element->hasAttributes()) {
            return;
        }

        $remove = [];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    private function safeHref(string $href): bool
    {
        if ($href === '' || preg_match('/^\s*javascript:/i', $href) || str_starts_with($href, '//')) {
            return false;
        }

        if (str_starts_with($href, '/')) {
            return true;
        }

        return (bool) preg_match('#^https?://#i', $href);
    }
}
