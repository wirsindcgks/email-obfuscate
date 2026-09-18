<?php

/**
 * Tests fuer Encoder und Pfadabgleich, ohne Composer: `php tests/run.php`.
 * Exit-Code 0 = alles gruen, 1 = mindestens ein Fehler.
 *
 * Jeder Fall prueft beide Seiten: roh keine Adresse mehr, und nach dem
 * Dekodieren (so, wie der Browser liest) exakt der Ausgangstext.
 */

declare(strict_types=1);

require __DIR__ . '/../src/Encoder.php';
require __DIR__ . '/../src/Settings.php';

use EmailObfuscate\Encoder;
use EmailObfuscate\Settings;

$failures = 0;
$count = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures++;
        echo "FEHLER: {$name}" . ($detail !== '' ? "\n  {$detail}" : '') . "\n";
    }
}

$address = 'gebet@cg-ks.de';

// Text: roh weg, dekodiert unveraendert, kein @ mehr.
$html = '<p>Infos unter: gebet@cg-ks.de und mehr.</p>';
$out = Encoder::encodeHtml($html);
check('Text: keine Adresse roh', strpos($out, $address) === false, $out);
check('Text: kein @ roh', strpos($out, '@') === false, $out);
check('Text: dekodiert gleich', html_entity_decode($out, ENT_QUOTES) === $html, $out);

// mailto, gequotet und ungequotet (W3TC minifiziert Attribute ohne Anfuehrungszeichen).
foreach (['<a href="mailto:gebet@cg-ks.de">gebet@cg-ks.de</a>', '<a href=mailto:gebet@cg-ks.de>gebet@cg-ks.de</a>'] as $link) {
    $out = Encoder::encodeHtml($link);
    check('mailto: keine Adresse roh', strpos($out, $address) === false, $out);
    check('mailto: dekodiert gleich', html_entity_decode($out, ENT_QUOTES) === $link, $out);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8"?>' . $out);
    $a = $dom->getElementsByTagName('a')->item(0);
    check('mailto: Browser liest href', $a !== null && $a->getAttribute('href') === 'mailto:' . $address, $out);
    check('mailto: Browser liest Text', $a !== null && $a->textContent === $address, $out);
}

// Adresse am Satzzeichen und in Klammern.
$html = '<p>(jugend@cg-ks.de), frauen@cg-ks.de.</p>';
$out = Encoder::encodeHtml($html);
check('Satzzeichen: keine Adresse roh', strpos($out, '@') === false, $out);
check('Satzzeichen: dekodiert gleich', html_entity_decode($out, ENT_QUOTES) === $html, $out);

// JavaScript und CSS bleiben unberuehrt.
$html = '<script>var a = "gebet@cg-ks.de";</script><style>@media (min-width: 1px) { a { color: red } }</style>';
check('script/style unveraendert', Encoder::encodeHtml($html) === $html, Encoder::encodeHtml($html));

// Kommentare bleiben unberuehrt.
$html = '<!-- kontakt: gebet@cg-ks.de --><!--[if IE]><p>x</p><![endif]-->';
check('Kommentare unveraendert', Encoder::encodeHtml($html) === $html);

// JSON-LD: @ als @, Schluessel mit @ bleiben, JSON bleibt gueltig und gleich.
$json = '{"@context":"https://schema.org","@type":"Organization","email":"gebet@cg-ks.de"}';
$out = Encoder::encodeHtml('<script type="application/ld+json">' . $json . '</script>');
check('JSON-LD: keine Adresse roh', strpos($out, $address) === false, $out);
check('JSON-LD: @context bleibt', strpos($out, '"@context"') !== false, $out);
$inner = (string) preg_replace('#^<script[^>]*>(.*)</script>$#s', '$1', $out);
check('JSON-LD: dekodiert gleich', json_decode($inner, true) === json_decode($json, true), $inner);

// Kein Fehltreffer: Handles, CSS-at-Regeln im Text, einzelnes @.
$html = '<p>Folgt @cgks auf Instagram, Treffen @ Jugendraum, Preis 5 @ 2 Euro.</p>';
check('Kein Fehltreffer', Encoder::encodeHtml($html) === $html, Encoder::encodeHtml($html));

// Schon verschleierte Adressen (antispambot, ChurchTools-Plugin) bleiben, wie sie sind.
$html = '<p>g&#101;bet&#64;cg&#45;ks.de</p>';
check('Bereits verschleiert unveraendert', Encoder::encodeHtml($html) === $html, Encoder::encodeHtml($html));

// Mehrere Adressen, auch in einem <template> (Popup-Inhalt).
$html = '<template><p>a@b.de</p></template><p>c.d+e@f-g.co.uk</p>';
$out = Encoder::encodeHtml($html);
check('Mehrere: kein @ roh', strpos($out, '@') === false, $out);
check('Mehrere: dekodiert gleich', html_entity_decode($out, ENT_QUOTES) === $html, $out);

// Seite ohne @: unveraendert zurueck.
check('Ohne @ unveraendert', Encoder::encodeHtml('<p>nichts</p>') === '<p>nichts</p>');

// Ausnahmen: ganze Adresse und Domain, ohne Ruecksicht auf Gross-/Kleinschreibung.
$html = '<p>Info@Example.org, a@team.example.org, b@example.org, gebet@cg-ks.de</p>';
$out = Encoder::encodeHtml($html, ['info@example.org', '@team.example.org']);
check('Ausnahme: Adresse bleibt', strpos($out, 'Info@Example.org') !== false, $out);
check('Ausnahme: Domain bleibt', strpos($out, 'a@team.example.org') !== false, $out);
check('Ausnahme: andere Domain kodiert', strpos($out, 'b@example.org') === false, $out);
check('Ausnahme: uebrige kodiert', strpos($out, $address) === false, $out);
check('Ausnahme: dekodiert gleich', html_entity_decode($out, ENT_QUOTES) === $html, $out);
check('Ausnahme: keine Teildomain', !Encoder::isException('a@xexample.org', ['@example.org']));

// Ausnahmen gelten auch in JSON-LD.
$ld = '<script type="application/ld+json">{"email":"info@example.org"}</script>';
check('Ausnahme: JSON-LD bleibt', Encoder::encodeHtml($ld, ['@example.org']) === $ld);

// JSON-LD abgeschaltet: Block unveraendert, Text trotzdem kodiert.
$html = '<script type="application/ld+json">{"email":"gebet@cg-ks.de"}</script><p>gebet@cg-ks.de</p>';
$out = Encoder::encodeHtml($html, [], false);
check('JSON aus: Block unveraendert', strpos($out, '{"email":"gebet@cg-ks.de"}') !== false, $out);
check('JSON aus: Text kodiert', substr_count($out, $address) === 1, $out);

// Pfadabgleich.
$patterns = ['/impressum/', '/shop/*', '/blog/*-entwurf'];
foreach (['/impressum', '/impressum/', '/IMPRESSUM/', '/shop', '/shop/', '/shop/a/b/', '/blog/2026-entwurf/'] as $path) {
    check("Pfad ausgeschlossen: {$path}", Settings::isExcludedPath($path, $patterns));
}
foreach (['/', '/impressum/alt/', '/shopping/', '/blog/beitrag/', '/kontakt/'] as $path) {
    check("Pfad nicht ausgeschlossen: {$path}", !Settings::isExcludedPath($path, $patterns));
}
check('Pfad: Startseite per /', Settings::isExcludedPath('/', ['/']));
check('Pfad: / trifft nicht alles', !Settings::isExcludedPath('/kontakt/', ['/']));
check('Pfad: Regex-Zeichen woertlich', !Settings::isExcludedPath('/axb/', ['/a.b/']));

echo $failures === 0 ? "OK ({$count} Pruefungen)\n" : "{$failures} von {$count} Pruefungen fehlgeschlagen\n";
exit($failures === 0 ? 0 : 1);
