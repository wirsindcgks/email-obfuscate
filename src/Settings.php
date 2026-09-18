<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Die Einstellungen, gespeichert als eine einzige Option.
 *
 * Nur `get()` und `sanitize()` brauchen WordPress; der Pfadabgleich ist
 * reines PHP und damit ohne WordPress testbar.
 */
final class Settings
{
    public const OPTION = 'email_obfuscate_settings';

    /** Eine ganze Adresse oder, mit `@` vorn, eine Domain. */
    private const EXCEPTION_PATTERN = '/^(?:[a-z0-9._%+-]+)?@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/';

    /**
     * @return array{enabled: bool, encode_json: bool, excluded_paths: list<string>, excluded_addresses: list<string>}
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'encode_json' => true,
            'excluded_paths' => [],
            'excluded_addresses' => [],
        ];
    }

    /**
     * @return array{enabled: bool, encode_json: bool, excluded_paths: list<string>, excluded_addresses: list<string>}
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION, []);

        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * Sanitize-Callback der Settings API. Ungueltige Zeilen fallen weg, mit
     * einem Hinweis, welche.
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];

        $paths = [];
        foreach (self::lines($input['excluded_paths'] ?? '') as $line) {
            // Ganze URLs (aus der Adresszeile kopiert) auf ihren Pfad kuerzen.
            if (str_contains($line, '://')) {
                $line = (string) wp_parse_url($line, PHP_URL_PATH);
            }
            $line = '/' . ltrim(sanitize_text_field($line), '/');
            $paths[] = $line;
        }

        $addresses = [];
        $invalid = [];
        foreach (self::lines($input['excluded_addresses'] ?? '') as $line) {
            $line = strtolower($line);
            if (preg_match(self::EXCEPTION_PATTERN, $line)) {
                $addresses[] = $line;
            } else {
                $invalid[] = $line;
            }
        }

        if ($invalid !== [] && !self::hasError('invalid_addresses')) {
            add_settings_error(
                self::OPTION,
                'invalid_addresses',
                sprintf(
                    /* translators: %s: Liste ungueltiger Eintraege */
                    __('Diese Einträge sind weder Adresse noch Domain und wurden entfernt: %s', 'email-obfuscate'),
                    implode(', ', array_map('esc_html', $invalid))
                ),
                'warning'
            );
        }

        return [
            'enabled' => !empty($input['enabled']),
            'encode_json' => !empty($input['encode_json']),
            'excluded_paths' => array_values(array_unique($paths)),
            'excluded_addresses' => array_values(array_unique($addresses)),
        ];
    }

    /**
     * Ob ein Pfad (relativ zur Startseite, mit `/` vorn) ausgeschlossen ist.
     * Der Schraegstrich am Ende zaehlt nicht, `*` steht fuer beliebig viel, und
     * `/shop/*` trifft auch `/shop` selbst.
     *
     * @param list<string> $patterns
     */
    public static function isExcludedPath(string $path, array $patterns): bool
    {
        $path = rtrim($path, '/');

        foreach ($patterns as $pattern) {
            $suffix = '';
            if (str_ends_with($pattern, '/*')) {
                $pattern = substr($pattern, 0, -2);
                $suffix = '(?:/.*)?';
            }
            $regex = str_replace('\*', '.*', preg_quote(rtrim($pattern, '/'), '#'));
            if (preg_match('#^' . $regex . $suffix . '$#i', $path)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> Nicht-leere, getrimmte Zeilen. */
    private static function lines(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode("\n", $value);
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $value) ?: []),
            static fn (string $line): bool => $line !== ''
        ));
    }

    /**
     * WordPress ruft den Sanitize-Callback beim ersten Speichern zweimal auf;
     * der Hinweis soll trotzdem nur einmal erscheinen.
     */
    private static function hasError(string $code): bool
    {
        foreach (get_settings_errors(self::OPTION) as $error) {
            if ($error['code'] === $code) {
                return true;
            }
        }

        return false;
    }
}
