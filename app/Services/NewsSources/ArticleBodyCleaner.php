<?php

namespace App\Services\NewsSources;

use App\Support\SafeHtml;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class ArticleBodyCleaner
{
    /**
     * @return array{html: string, text: string, score: int, paragraphs: int}
     */
    public function clean(?string $html, ?string $fallbackText = null): array
    {
        $html = trim((string) $html);

        if ($html === '') {
            $text = str((string) $fallbackText)->squish()->toString();

            return [
                'html' => '',
                'text' => $text,
                'score' => mb_strlen($text),
                'paragraphs' => 0,
            ];
        }

        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__article_body">'.$html.'</div>',
            LIBXML_NOWARNING | LIBXML_NOERROR,
        );
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        $root = $document->getElementById('__article_body');

        if (! $root instanceof DOMElement) {
            $text = str(strip_tags($html))->squish()->toString();

            return [
                'html' => (string) SafeHtml::clean($html),
                'text' => $text,
                'score' => mb_strlen($text),
                'paragraphs' => 0,
            ];
        }

        $this->promoteLeafDivsToParagraphs($document, $xpath, $root);

        $unwantedNodes = $xpath->query(
            './/script | .//style | .//noscript | .//iframe | .//form | .//nav | .//aside'
            .' | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "quick_links")]'
            .' | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "mobile_search")]'
            .' | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "related-post")]'
            .' | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "recommended")]'
            .' | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "newsletter")]',
            $root,
        );

        foreach (iterator_to_array($unwantedNodes ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $promotionalPrefixes = [
            'te puede interesar',
            'también puedes leer',
            'tambien puedes leer',
            'lee también',
            'lee tambien',
            'este artículo fue publicado originalmente',
            'este articulo fue publicado originalmente',
            '¿te gustan las fotos y las noticias?',
            'te gustan las fotos y las noticias',
            'síguenos en nuestro',
            'siguenos en nuestro',
            'suscríbete',
            'suscribete',
        ];

        foreach (iterator_to_array($xpath->query('.//p | .//div | .//section', $root) ?: []) as $node) {
            $text = str($node->textContent)->squish()->lower()->ascii()->toString();
            $matchesPromotion = collect($promotionalPrefixes)
                ->map(fn (string $prefix) => str($prefix)->ascii()->toString())
                ->contains(fn (string $prefix) => str_starts_with($text, $prefix));

            if ($matchesPromotion && $this->isLeafBlock($node)) {
                $node->parentNode?->removeChild($node);
            }
        }

        $cleanHtml = '';

        foreach ($root->childNodes as $childNode) {
            $cleanHtml .= $document->saveHTML($childNode) ?: '';
        }

        $cleanHtml = trim((string) SafeHtml::clean($cleanHtml));
        $textDocument = new DOMDocument;
        libxml_use_internal_errors(true);
        $textDocument->loadHTML(
            '<?xml encoding="UTF-8"><div id="__clean_body">'.$cleanHtml.'</div>',
            LIBXML_NOWARNING | LIBXML_NOERROR,
        );
        libxml_clear_errors();
        $textXpath = new DOMXPath($textDocument);
        $textRoot = $textDocument->getElementById('__clean_body');
        $blocks = $textRoot
            ? collect($textXpath->query('.//p | .//h2 | .//h3 | .//li | .//blockquote', $textRoot) ?: [])
            : collect();
        $text = $blocks
            ->map(fn (DOMNode $node) => str(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->toString())
            ->filter()
            ->implode("\n\n");

        if ($text === '') {
            $text = str(html_entity_decode(strip_tags($cleanHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->toString();
        }

        $paragraphs = $blocks->filter(fn (DOMNode $node) => strtolower($node->nodeName) === 'p')->count();
        $headings = $blocks->filter(fn (DOMNode $node) => in_array(strtolower($node->nodeName), ['h2', 'h3'], true))->count();

        return [
            'html' => $cleanHtml,
            'text' => $text,
            'score' => mb_strlen($text) + ($paragraphs * 120) + ($headings * 80),
            'paragraphs' => $paragraphs,
        ];
    }

    /**
     * Algunos medios antiguos publican cada párrafo dentro de un div. Los
     * convertimos antes de sanear el HTML para no perder el cuerpo cuando
     * también existen párrafos cortos de autor o fecha.
     */
    private function promoteLeafDivsToParagraphs(DOMDocument $document, DOMXPath $xpath, DOMElement $root): void
    {
        $divs = iterator_to_array($xpath->query('.//div', $root) ?: []);

        foreach (array_reverse($divs) as $node) {
            if (! $node instanceof DOMElement
                || ! $node->parentNode
                || ! $this->isLeafBlock($node)
                || str($node->textContent)->squish()->isEmpty()) {
                continue;
            }

            $paragraph = $document->createElement('p');

            while ($node->firstChild) {
                $paragraph->appendChild($node->firstChild);
            }

            $node->parentNode->replaceChild($paragraph, $node);
        }
    }

    /**
     * Solo elimina bloques promocionales terminales para no borrar el
     * contenedor completo del artículo cuando su primer párrafo coincide.
     */
    private function isLeafBlock(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'div', 'section', 'article'], true)) {
                return false;
            }
        }

        return true;
    }
}
