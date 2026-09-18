<?php

/**
 * Erzeugt .github/README.md (fuer GitHub) aus readme.txt (fuer WordPress):
 * `php bin/readme-md.php` schreibt die Datei, tests/run.php prueft, dass sie
 * aktuell ist. readme.txt bleibt die einzige Quelle. Der Ordner .github hat
 * bei GitHub Vorrang vor readme.txt im Hauptordner und fehlt in der
 * Plugin-ZIP.
 */

declare(strict_types=1);

function readmeToMarkdown(string $txt): string
{
    $sections = [
        'Description' => 'Beschreibung',
        'Frequently Asked Questions' => 'Häufige Fragen',
    ];

    $lines = preg_split('/\R/', trim($txt)) ?: [];
    $out = [];
    $meta = [];
    $inHeader = true;

    foreach ($lines as $index => $line) {
        if ($index === 0 && preg_match('/^===\s*(.+?)\s*===$/', $line, $m)) {
            $out[] = '# ' . $m[1];
            continue;
        }

        // Kopfzeilen "Feld: Wert" bis zur ersten Leerzeile.
        if ($inHeader) {
            if (preg_match('/^([A-Za-z ]+):\s*(.+)$/', $line, $m)) {
                $meta[$m[1]] = $m[2];
                continue;
            }
            $inHeader = false;
            $out[] = '';
            $out[] = sprintf(
                'Version %s · WordPress ab %s · PHP ab %s · Lizenz: [%s](%s)',
                $meta['Stable tag'] ?? '?',
                $meta['Requires at least'] ?? '?',
                $meta['Requires PHP'] ?? '?',
                $meta['License'] ?? '',
                $meta['License URI'] ?? ''
            );
        }

        if (preg_match('/^==\s*(.+?)\s*==$/', $line, $m)) {
            $out[] = '## ' . ($sections[$m[1]] ?? $m[1]);
        } elseif (preg_match('/^=\s*(.+?)\s*=$/', $line, $m)) {
            $out[] = '### ' . $m[1];
        } else {
            $out[] = $line;
        }
    }

    return "<!-- Erzeugt aus readme.txt mit `php bin/readme-md.php`, nicht von Hand bearbeiten. -->\n\n"
        . preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)) . "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    file_put_contents($root . '/.github/README.md', readmeToMarkdown((string) file_get_contents($root . '/readme.txt')));
    echo ".github/README.md geschrieben\n";
}
