<?php

/**
 * Tests fuer Encoder, Scanner, Pfadabgleich und Updater, ohne Composer: `php tests/run.php`.
 * Exit-Code 0 = alles gruen, 1 = mindestens ein Fehler.
 *
 * Jeder Fall prueft beide Seiten: roh keine Adresse mehr, und nach dem
 * Dekodieren (so, wie der Browser liest) exakt der Ausgangstext.
 */

declare(strict_types=1);

require __DIR__ . '/../src/Encoder.php';
require __DIR__ . '/../src/Settings.php';
require __DIR__ . '/../src/Scanner.php';
require __DIR__ . '/../src/Updater.php';

use EmailObfuscate\Encoder;
use EmailObfuscate\Scanner;
use EmailObfuscate\Settings;
use EmailObfuscate\Updater;

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

// Schon verschleierte Adressen (antispambot() anderer Plugins) bleiben, wie sie sind.
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

// Updater: Release aus der GitHub-API lesen.
$zip = ['name' => 'email-obfuscate-1.2.0.zip', 'browser_download_url' => 'https://github.com/x/email-obfuscate-1.2.0.zip'];
$source = ['name' => 'quelle.zip', 'browser_download_url' => 'https://github.com/x/quelle.zip'];
$release = Updater::parseRelease(['tag_name' => 'v1.2.0', 'html_url' => 'https://github.com/x', 'body' => '* neu', 'assets' => [$source, $zip]]);
check('Release: Version ohne v', ($release['version'] ?? '') === '1.2.0', var_export($release, true));
check('Release: Plugin-ZIP als Paket', ($release['package'] ?? '') === $zip['browser_download_url'], var_export($release, true));
check('Release: ohne ZIP kein Update', Updater::parseRelease(['tag_name' => '1.2.0', 'assets' => [$source]]) === null);
check('Release: Entwurf kein Update', Updater::parseRelease(['tag_name' => '1.2.0', 'draft' => true, 'assets' => [$zip]]) === null);
check('Release: Vorabversion kein Update', Updater::parseRelease(['tag_name' => '1.2.0', 'prerelease' => true, 'assets' => [$zip]]) === null);
check('Release: Tag ohne Version kein Update', Updater::parseRelease(['tag_name' => 'latest', 'assets' => [$zip]]) === null);

// Release-Notizen als HTML, mit maskiertem HTML aus den Notizen.
$html = Updater::notesToHtml("Neu: **fett**\n\n* `a@b` <b>\n- zwei\n\nSchluss");
check('Notizen: HTML', $html === "<p>Neu: <strong>fett</strong></p>\n<ul>\n<li><code>a@b</code> &lt;b&gt;</li>\n<li>zwei</li>\n</ul>\n<p>Schluss</p>\n", $html);

// Scanner: Einordnung als [Adresse, Status, Kontext, Anzahl].
function scanned(string $html): array
{
    return array_map(static fn (array $f): string => "{$f['address']} {$f['status']} {$f['context']} {$f['count']}", Scanner::analyze($html));
}

$page = '<p><a href="mailto:gebet@cg-ks.de">gebet@cg-ks.de</a></p>';
check('Scanner: offen', scanned($page) === ['gebet@cg-ks.de open html 2'], implode(', ', scanned($page)));

$page = Encoder::encodeHtml('<p><a href=mailto:Gebet@cg-ks.de>gebet@cg-ks.de</a></p><script type="application/ld+json">{"email":"jugend@cg-ks.de"}</script>');
check('Scanner: Encoder-Ausgabe verschleiert', scanned($page) === ['gebet@cg-ks.de encoded html 2', 'jugend@cg-ks.de encoded json 1'], implode(', ', scanned($page)));

// So schreibt antispambot(): @ immer kodiert, der Rest gemischt.
$page = '<a href="mailto:fr&#97;u&#101;n&#64;c&#103;&#45;ks&#46;de">fr&#97;u&#101;n&#64;c&#103;&#45;ks&#46;de</a>';
check('Scanner: antispambot teilweise', scanned($page) === ['frauen@cg-ks.de partial html 2'], implode(', ', scanned($page)));

$page = '<p>&#x6A;&#x40;&#x62;&#x2E;&#x64;&#x65; und j&commat;b.de</p>';
check('Scanner: Hex und benannte Entities', scanned($page) === ['j@b.de encoded html 1', 'j@b.de partial html 1'], implode(', ', scanned($page)));

$page = '<script>var m = "a@b.de";</script><style>/* c@d.de */</style><!-- e@f.de --><p>Folgt @cgks, 5 @ 2 Euro</p>';
check('Scanner: geschuetzte Bereiche offen', scanned($page) === ['a@b.de open script 1', 'c@d.de open style 1', 'e@f.de open comment 1'], implode(', ', scanned($page)));

$page = '<p>Mail&#58;&nbsp;&#105;&#64;&#98;&#46;&#100;&#101;&nbsp;x</p>';
check('Scanner: Entities drumherum zaehlen nicht', scanned($page) === ['i@b.de encoded html 1'], implode(', ', scanned($page)));

echo $failures === 0 ? "OK ({$count} Pruefungen)\n" : "{$failures} von {$count} Pruefungen fehlgeschlagen\n";
exit($failures === 0 ? 0 : 1);
