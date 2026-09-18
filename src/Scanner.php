<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Findet E-Mail-Adressen im Quelltext einer Seite und ordnet sie ein:
 *
 *   - offen:        steht im Klartext, ein Sammler liest sie direkt.
 *   - verschleiert: kein Zeichen offen, so wie der Encoder sie schreibt.
 *   - teilweise:    Zeichenreferenzen und offene Zeichen gemischt, so wie
 *                   antispambot() sie schreibt.
 *
 * Dazu, wo sie steht: im HTML oder in einem Bereich, den der Encoder
 * auslaesst (Script, Style, Kommentar, JSON-Block).
 *
 * Reines PHP ohne WordPress, damit es sich testen laesst.
 */
final class Scanner
{
    public const OPEN = 'open';

    public const ENCODED = 'encoded';

    public const PARTIAL = 'partial';

    /**
     * Eine zusammenhaengende Folge aus Zeichen, die in einer Adresse
     * vorkommen, und kodierten Zeichen (`&#64;`, `&#x40;`, `&commat;`, `%40`).
     */
    private const RUN = '/(?:&#?[A-Za-z0-9]+;|%[0-9A-Fa-f]{2}|[A-Za-z0-9._+@-])+/';

    /** Ein Zeichen der Folge: kodiert oder offen. */
    private const TOKEN = '/&#?[A-Za-z0-9]+;|%[0-9A-Fa-f]{2}|./s';

    /**
     * @return list<array{address: string, status: string, context: string, count: int}>
     */
    public static function analyze(string $html): array
    {
        $parts = preg_split(Encoder::PROTECTED, $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        $found = [];
        foreach ($parts as $index => $part) {
            $hits = $index % 2 === 0 ? self::inHtml($part) : self::inProtected($part);
            $context = $index % 2 === 0 ? 'html' : self::context($part);
            foreach ($hits as [$address, $status]) {
                $key = strtolower($address) . '|' . $status . '|' . $context;
                $found[$key] ??= ['address' => strtolower($address), 'status' => $status, 'context' => $context, 'count' => 0];
                $found[$key]['count']++;
            }
        }

        return array_values($found);
    }

    /** @return list<array{string, string}> Adresse und Einordnung. */
    private static function inHtml(string $html): array
    {
        if (!preg_match_all(self::RUN, $html, $runs)) {
            return [];
        }

        $hits = [];
        foreach ($runs[0] as $run) {
            if (strlen($run) < 6) {
                continue;
            }

            [$decoded, $literal] = self::decodeRun($run);
            if (!str_contains($decoded, '@') || !preg_match_all(Encoder::PATTERN, $decoded, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$address, $offset]) {
                $open = count(array_filter(array_slice($literal, $offset, strlen($address))));
                $status = match (true) {
                    $open === strlen($address) => self::OPEN,
                    $open === 0 => self::ENCODED,
                    default => self::PARTIAL,
                };
                $hits[] = [$address, $status];
            }
        }

        return $hits;
    }

    /**
     * Dekodiert eine Folge und merkt sich fuer jedes Byte des Ergebnisses,
     * ob es im Quelltext offen stand.
     *
     * @return array{string, list<bool>}
     */
    private static function decodeRun(string $run): array
    {
        preg_match_all(self::TOKEN, $run, $tokens);

        $decoded = '';
        $literal = [];
        foreach ($tokens[0] as $token) {
            if ($token[0] === '&') {
                $char = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $isLiteral = $char === $token;
            } elseif ($token[0] === '%' && strlen($token) === 3) {
                $char = rawurldecode($token);
                $isLiteral = false;
            } else {
                $char = $token;
                $isLiteral = true;
            }
            $decoded .= $char;
            array_push($literal, ...array_fill(0, strlen($char), $isLiteral));
        }

        return [$decoded, $literal];
    }

    /**
     * In Script, Style und Kommentaren liest der Browser keine
     * Zeichenreferenzen - dort zaehlt nur Klartext und `@` (JSON).
     *
     * @return list<array{string, string}>
     */
    private static function inProtected(string $block): array
    {
        $hits = [];
        if (preg_match_all(Encoder::PATTERN, $block, $matches)) {
            foreach ($matches[0] as $address) {
                $hits[] = [$address, self::OPEN];
            }
        }

        $escaped = str_replace('@', '\\\\u0040', Encoder::PATTERN);
        if (preg_match_all($escaped, $block, $matches)) {
            foreach ($matches[0] as $address) {
                $hits[] = [str_replace('\\u0040', '@', $address), self::ENCODED];
            }
        }

        return $hits;
    }

    private static function context(string $block): string
    {
        return match (true) {
            str_starts_with($block, '<!--') => 'comment',
            stripos($block, '<style') === 0 => 'style',
            Encoder::isJsonScript($block) => 'json',
            default => 'script',
        };
    }
}
