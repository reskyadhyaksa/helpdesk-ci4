<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Menyaring HTML dari editor komentar dengan daftar putih: hanya tag pemformatan sederhana yang lolos, semua atribut
 * dibuang (kecuali penanda mention). Tanpa akses database. HTML dari browser tidak pernah dipercaya; hasil fungsi ini
 * yang disimpan dan ditampilkan.
 */
class CommentSanitizer
{
    // Tag yang dipertahankan; <b>, <i>, dan <div> (Enter di contenteditable) diubah ke padanannya
    private const ALLOWED = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'code', 'span'];
    private const RENAMED = ['b' => 'strong', 'i' => 'em', 'div' => 'p'];

    // Isinya dibuang seluruhnya, bukan hanya tag-nya
    private const DROPPED = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'noscript', 'head', 'title'];

    private const MENTION_ID = '/^[0-9A-Za-z-]{1,36}$/';

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        // Awalan <?xml memaksa pembacaan UTF-8 tanpa mengubah karakter non-ASCII menjadi entitas
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);
        $out  = $body === null ? '' : $this->children($body);

        // Paragraf kosong di ujung (Enter berlebih) dibuang
        return trim(preg_replace('#(<p>(<br>)?</p>)+$#', '', $out) ?? '');
    }

    /**
     * @return list<string> id user dari penanda mention pada HTML yang sudah disaring
     */
    public function mentionIds(string $cleanHtml): array
    {
        preg_match_all('/<span class="mention" data-user-id="([^"]+)">/', $cleanHtml, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Teks polos dari HTML yang sudah disaring, untuk memeriksa kosong dan panjang pesan.
     */
    public function plainText(string $cleanHtml): string
    {
        $spaced = preg_replace('#<br>|</p>|</li>#', ' ', $cleanHtml) ?? '';

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function children(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $this->node($child);
        }

        return $html;
    }

    private function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);
        $name = self::RENAMED[$name] ?? $name;

        if (in_array($name, self::DROPPED, true)) {
            return '';
        }

        if (! in_array($name, self::ALLOWED, true)) {
            return $this->children($node);
        }

        if ($name === 'br') {
            return '<br>';
        }

        if ($name === 'span') {
            return $this->span($node);
        }

        return "<{$name}>" . $this->children($node) . "</{$name}>";
    }

    /**
     * <span> hanya dipertahankan sebagai penanda mention yang sah; selain itu dibuka (isinya saja).
     */
    private function span(DOMElement $span): string
    {
        $userId = $span->getAttribute('data-user-id');

        if ($span->getAttribute('class') !== 'mention' || preg_match(self::MENTION_ID, $userId) !== 1) {
            return $this->children($span);
        }

        return '<span class="mention" data-user-id="' . $userId . '">' . $this->children($span) . '</span>';
    }
}
