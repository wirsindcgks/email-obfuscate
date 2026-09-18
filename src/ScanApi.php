<?php

declare(strict_types=1);

namespace EmailObfuscate;

use WP_Error;
use WP_REST_Request;

/**
 * REST-Endpunkte fuer "Website pruefen", nur fuer Administratoren.
 *
 * Die Seiten werden von aussen abgerufen, so wie Besucher und Sammler sie
 * bekommen - also mit Seiten-Cache. Steht eine Adresse offen, wird die Seite
 * ein zweites Mal am Cache vorbei abgerufen: Ist sie dann sauber, liegt eine
 * veraltete Fassung im Cache.
 *
 * Abgerufen werden nur Adressen der eigenen Website und ohne Weiterleitungen,
 * damit die Endpunkte nicht als Proxy fuer fremde Adressen taugen.
 */
final class ScanApi
{
    public const NAMESPACE = 'email-obfuscate/v1';

    public const RESULT_OPTION = 'email_obfuscate_last_scan';

    private const MAX_URLS = 2000;

    private const MAX_SITEMAPS = 50;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        $permission = static fn (): bool => current_user_can('manage_options');

        register_rest_route(self::NAMESPACE, '/scan/urls', [
            'methods' => 'GET',
            'callback' => [self::class, 'urls'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::NAMESPACE, '/scan/page', [
            'methods' => 'POST',
            'callback' => [self::class, 'page'],
            'permission_callback' => $permission,
            'args' => ['url' => ['type' => 'string', 'required' => true]],
        ]);

        register_rest_route(self::NAMESPACE, '/scan/result', [
            'methods' => 'POST',
            'callback' => [self::class, 'storeResult'],
            'permission_callback' => $permission,
        ]);
    }

    /**
     * Startseite plus alle Seiten aus den Sitemaps, die robots.txt nennt -
     * so kommen auch Sitemaps anderer Plugins dazu. Ohne Eintrag in
     * robots.txt die Sitemap von WordPress.
     */
    public static function urls(): array
    {
        $urls = [home_url('/') => true];
        $errors = [];

        $robots = self::fetch(home_url('/robots.txt'));
        $queue = [];
        if (!is_wp_error($robots) && $robots['code'] === 200 && preg_match_all('/^\s*Sitemap:\s*(\S+)/mi', $robots['body'], $matches)) {
            $queue = $matches[1];
        }
        if ($queue === []) {
            $queue = [home_url('/wp-sitemap.xml')];
        }

        $seen = [];
        while ($queue !== [] && count($seen) < self::MAX_SITEMAPS && count($urls) < self::MAX_URLS) {
            $sitemap = array_shift($queue);
            if (isset($seen[$sitemap]) || !self::isOwnUrl($sitemap)) {
                continue;
            }
            $seen[$sitemap] = true;

            $response = self::fetch($sitemap);
            if (is_wp_error($response) || $response['code'] !== 200) {
                $errors[] = sprintf(
                    /* translators: 1: Sitemap-URL, 2: Fehler */
                    __('Sitemap %1$s nicht abrufbar: %2$s', 'email-obfuscate'),
                    $sitemap,
                    self::errorText($response)
                );
                continue;
            }

            $isIndex = str_contains($response['body'], '<sitemapindex');
            preg_match_all('#<loc>\s*(.*?)\s*</loc>#s', $response['body'], $locs);
            foreach ($locs[1] as $loc) {
                $loc = html_entity_decode(trim(str_replace(['<![CDATA[', ']]>'], '', $loc)), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if (!self::isOwnUrl($loc)) {
                    continue;
                }
                if ($isIndex) {
                    $queue[] = $loc;
                } else {
                    $urls[$loc] = true;
                }
            }
        }

        if (count($seen) === 0) {
            $errors[] = __('Keine Sitemap gefunden. Geprüft wird nur die Startseite.', 'email-obfuscate');
        }

        return [
            'urls' => array_slice(array_keys($urls), 0, self::MAX_URLS),
            'sitemaps' => array_keys($seen),
            'errors' => $errors,
        ];
    }

    /** Eine Seite pruefen. */
    public static function page(WP_REST_Request $request): array|WP_Error
    {
        $url = esc_url_raw((string) $request->get_param('url'));
        if (!self::isOwnUrl($url)) {
            return new WP_Error('email_obfuscate_foreign_url', __('Nur Seiten dieser Website können geprüft werden.', 'email-obfuscate'), ['status' => 400]);
        }

        $response = self::fetch($url);
        if (is_wp_error($response) || $response['code'] !== 200) {
            return ['url' => $url, 'error' => self::errorText($response)];
        }
        if (!str_contains(strtolower($response['type']), 'html')) {
            return ['url' => $url, 'findings' => []];
        }

        $findings = Scanner::analyze($response['body']);
        $open = array_filter($findings, static fn (array $finding): bool => $finding['status'] === Scanner::OPEN);
        if ($open === []) {
            return ['url' => $url, 'findings' => $findings];
        }

        // Am Cache vorbei: Seiten-Caches wie W3 Total Cache speichern Adressen
        // mit Query-String standardmaessig nicht.
        $fresh = self::fetch(add_query_arg('eo_nocache', wp_generate_password(8, false), $url));
        $stillOpen = null;
        if (!is_wp_error($fresh) && $fresh['code'] === 200) {
            $stillOpen = [];
            foreach (Scanner::analyze($fresh['body']) as $finding) {
                if ($finding['status'] === Scanner::OPEN) {
                    $stillOpen[$finding['address'] . '|' . $finding['context']] = true;
                }
            }
        }

        foreach ($findings as &$finding) {
            if ($finding['status'] === Scanner::OPEN) {
                $cached = $stillOpen !== null && !isset($stillOpen[$finding['address'] . '|' . $finding['context']]);
                $finding['reason'] = $cached ? 'cache' : self::reason($finding, $url);
            }
        }
        unset($finding);

        return ['url' => $url, 'findings' => $findings];
    }

    /** Das Ergebnis eines Durchlaufs speichern, fuer die Anzeige beim naechsten Oeffnen. */
    public static function storeResult(WP_REST_Request $request): array
    {
        $data = $request->get_json_params();
        $pages = [];
        foreach (array_slice((array) ($data['pages'] ?? []), 0, self::MAX_URLS) as $page) {
            if (!is_array($page)) {
                continue;
            }

            $clean = ['url' => esc_url_raw((string) ($page['url'] ?? ''))];
            if (isset($page['error'])) {
                $clean['error'] = sanitize_text_field((string) $page['error']);
            }
            $clean['findings'] = [];
            foreach ((array) ($page['findings'] ?? []) as $finding) {
                if (!is_array($finding)) {
                    continue;
                }
                $clean['findings'][] = array_filter([
                    'address' => sanitize_text_field((string) ($finding['address'] ?? '')),
                    'status' => sanitize_key((string) ($finding['status'] ?? '')),
                    'context' => sanitize_key((string) ($finding['context'] ?? '')),
                    'count' => absint($finding['count'] ?? 1),
                    'reason' => sanitize_key((string) ($finding['reason'] ?? '')),
                ], static fn ($value): bool => $value !== '');
            }
            $pages[] = $clean;
        }

        $result = [
            'time' => time(),
            'pages' => $pages,
            'errors' => array_map('sanitize_text_field', array_map('strval', (array) ($data['errors'] ?? []))),
        ];
        update_option(self::RESULT_OPTION, $result, false);

        return ['stored' => count($pages)];
    }

    /**
     * Warum eine Adresse auch ohne Cache offen steht.
     *
     * @param array{address: string, context: string} $finding
     */
    private static function reason(array $finding, string $url): string
    {
        if ($finding['context'] !== 'html') {
            return 'protected';
        }

        $settings = Settings::get();

        return match (true) {
            !$settings['enabled'] => 'disabled',
            Settings::isExcludedPath(Plugin::relativePath($url), $settings['excluded_paths']) => 'excluded',
            Encoder::isException($finding['address'], $settings['excluded_addresses']) => 'exception',
            default => 'unknown',
        };
    }

    /** @return array{code: int, body: string, type: string}|WP_Error */
    private static function fetch(string $url): array|WP_Error
    {
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'redirection' => 0,
            'user-agent' => 'Mozilla/5.0 (compatible; Email-Obfuscate-Scan; ' . home_url('/') . ')',
            /** Wie der Loopback-Test von WordPress: lokale Zertifikate nicht pruefen. */
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        return [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
            'type' => (string) wp_remote_retrieve_header($response, 'content-type'),
        ];
    }

    /** @param array{code: int}|WP_Error $response */
    private static function errorText(array|WP_Error $response): string
    {
        if (is_wp_error($response)) {
            return $response->get_error_message();
        }

        return match (true) {
            $response['code'] >= 300 && $response['code'] < 400 => sprintf(
                /* translators: %d: HTTP-Status */
                __('Weiterleitung (HTTP %d), nicht geprüft', 'email-obfuscate'),
                $response['code']
            ),
            default => sprintf(
                /* translators: %d: HTTP-Status */
                __('HTTP %d', 'email-obfuscate'),
                $response['code']
            ),
        };
    }

    private static function isOwnUrl(string $url): bool
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);

        return is_string($host)
            && in_array($scheme, ['http', 'https'], true)
            && strcasecmp($host, (string) wp_parse_url(home_url('/'), PHP_URL_HOST)) === 0;
    }
}
