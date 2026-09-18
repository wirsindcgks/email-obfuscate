<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Verschleiert E-Mail-Adressen in einer fertigen HTML-Seite.
 *
 * Jedes Zeichen einer Adresse wird zur Zeichenreferenz (`&#64;` statt `@`).
 * Browser zeigen und verlinken die Adresse unveraendert, im Quelltext - dem,
 * was Adresssammler lesen - steht sie nicht mehr. Anders als WordPress'
 * antispambot() wird *jedes* Zeichen kodiert: antispambot() laesst zufaellig
 * etwa die Haelfte offen, auch das `@`.
 *
 * Zeichenreferenzen wirken nur dort, wo der Browser HTML liest: im Text und
 * in Attributwerten (also auch in `href="mailto:…"`). Deshalb bleiben aussen
 * vor:
 *   - <script> und <style>: dort sind `&#64;` einfach fuenf Zeichen. Einzige
 *     Ausnahme sind JSON-Bloecke (JSON-LD), dort wird das `@` zu `\u0040` -
 *     gueltiges JSON mit unveraendertem Wert.
 *   - HTML-Kommentare: unsichtbar, und bedingte Kommentare alter IE-Weichen
 *     sollen unangetastet bleiben.
 */
final class Encoder
{
    /**
     * Eine Adresse in Klartext. Bewusst schlicht - es geht darum, was ein
     * Sammler im Quelltext findet, nicht um jede Form, die RFC 5322 erlaubt.
     */
    public const PATTERN = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/';

    private const PROTECTED = '#(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<!--.*?-->)#is';

    public static function encodeHtml(string $html): string
    {
        if (strpos($html, '@') === false) {
            return $html;
        }

        $parts = preg_split(self::PROTECTED, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = self::encodeText($part);
            } elseif (self::isJsonScript($part)) {
                $parts[$index] = self::encodeJson($part);
            }
        }

        return implode('', $parts);
    }

    /** Adressen in HTML-Text und Attributwerten als Zeichenreferenzen. */
    public static function encodeText(string $text): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => self::entities($match[0]),
            $text
        );
    }

    /** Adressen in einem JSON-Block: nur das `@`, als `\u0040`. */
    public static function encodeJson(string $script): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => str_replace('@', '\\u0040', $match[0]),
            $script
        );
    }

    private static function isJsonScript(string $block): bool
    {
        return (bool) preg_match('#^<script\b[^>]*\btype\s*=\s*["\']?application/(ld\+)?json#i', $block);
    }

    private static function entities(string $address): string
    {
        $encoded = '';
        foreach (str_split($address) as $char) {
            $encoded .= '&#' . ord($char) . ';';
        }

        return $encoded;
    }
}
