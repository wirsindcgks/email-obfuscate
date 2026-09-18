<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Updates aus den GitHub-Releases statt von wordpress.org.
 *
 * Der Plugin-Header traegt `Update URI: https://github.com/...`. Damit fragt
 * WordPress fuer dieses Plugin nicht wordpress.org (wo ein fremdes Plugin
 * denselben Slug haben kann), sondern den Filter `update_plugins_github.com`.
 * Die Versionsnummer vergleicht WordPress selbst.
 *
 * Paket ist die ZIP-Datei am Release, nicht das automatische Quellcode-Archiv
 * von GitHub: Nur die ZIP-Datei enthaelt den Ordner `email-obfuscate/`, das
 * Archiv hiesse `email-obfuscate-1.2.0/` und landete neben dem Plugin.
 */
final class Updater
{
    public const REPOSITORY = 'wirsindcgks/email-obfuscate';

    public const SLUG = 'email-obfuscate';

    private const CACHE = 'email_obfuscate_release';

    private const CHECK_ACTION = 'email_obfuscate_check_update';

    private static string $pluginFile = '';

    public static function register(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        add_filter('update_plugins_github.com', [self::class, 'checkUpdate'], 10, 3);
        add_filter('plugins_api', [self::class, 'pluginInfo'], 20, 3);

        add_filter('plugin_row_meta', [self::class, 'addCheckLink'], 10, 2);
        add_action('load-plugins.php', [self::class, 'handleCheck']);
        add_action('admin_notices', [self::class, 'showCheckNotice']);
        add_action('network_admin_notices', [self::class, 'showCheckNotice']);
        add_filter('removable_query_args', [self::class, 'removableQueryArgs']);
    }

    /**
     * "Nach Updates suchen" in der Plugin-Liste, neben "Details ansehen".
     *
     * @param list<string> $meta
     * @return list<string>
     */
    public static function addCheckLink(array $meta, string $pluginFile): array
    {
        if ($pluginFile !== plugin_basename(self::$pluginFile) || !current_user_can('update_plugins')) {
            return $meta;
        }

        $url = wp_nonce_url(add_query_arg('email_obfuscate_check', '1', self_admin_url('plugins.php')), self::CHECK_ACTION);
        $meta[] = '<a href="' . esc_url($url) . '">' . esc_html__('Nach Updates suchen', 'email-obfuscate') . '</a>';

        return $meta;
    }

    /**
     * Fragt GitHub sofort: eigenen Zwischenspeicher und den von WordPress
     * leeren, pruefen lassen, zurueck zur Plugin-Liste mit dem Ergebnis.
     * Die Weiterleitung verhindert, dass ein Neuladen erneut prueft.
     */
    public static function handleCheck(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce folgt in check_admin_referer().
        if (!isset($_GET['email_obfuscate_check'])) {
            return;
        }

        check_admin_referer(self::CHECK_ACTION);
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Du darfst keine Plugins aktualisieren.', 'email-obfuscate'), '', ['response' => 403]);
        }

        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $release = get_site_transient(self::CACHE);
        $updates = get_site_transient('update_plugins');
        $result = match (true) {
            !is_array($release) || $release === [] => 'failed',
            is_object($updates) && isset($updates->response[plugin_basename(self::$pluginFile)]) => 'available',
            default => 'current',
        };

        wp_safe_redirect(add_query_arg([
            'email_obfuscate_checked' => $result,
            'email_obfuscate_version' => is_array($release) ? ($release['version'] ?? '') : '',
        ], self_admin_url('plugins.php')));
        exit;
    }

    public static function showCheckNotice(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur angezeigt.
        if (($GLOBALS['pagenow'] ?? '') !== 'plugins.php' || !isset($_GET['email_obfuscate_checked']) || !current_user_can('update_plugins')) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $result = sanitize_key(wp_unslash($_GET['email_obfuscate_checked']));
        $version = sanitize_text_field(wp_unslash($_GET['email_obfuscate_version'] ?? ''));
        // phpcs:enable

        [$type, $message] = match ($result) {
            'available' => ['warning', sprintf(
                /* translators: %s: Versionsnummer */
                __('Email Obfuscate: Version %s ist verfügbar.', 'email-obfuscate'),
                $version
            )],
            'current' => ['success', sprintf(
                /* translators: %s: Versionsnummer */
                __('Email Obfuscate ist aktuell (Version %s).', 'email-obfuscate'),
                get_plugin_data(self::$pluginFile, false, false)['Version']
            )],
            default => ['error', __('Email Obfuscate: GitHub war nicht erreichbar. Bitte später erneut versuchen.', 'email-obfuscate')],
        };

        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
    }

    /**
     * Die Parameter aus der Adresszeile entfernen, damit ein Lesezeichen
     * oder Neuladen den Hinweis nicht wieder zeigt.
     *
     * @param list<string> $args
     * @return list<string>
     */
    public static function removableQueryArgs(array $args): array
    {
        $args[] = 'email_obfuscate_checked';
        $args[] = 'email_obfuscate_version';

        return $args;
    }

    /**
     * @param array<string, mixed>|false $update
     * @param array<string, string>      $pluginData
     * @return array<string, mixed>|false
     */
    public static function checkUpdate(mixed $update, array $pluginData, string $pluginFile): mixed
    {
        if ($pluginFile !== plugin_basename(self::$pluginFile)) {
            return $update;
        }

        $release = self::latestRelease();
        if ($release === null) {
            return $update;
        }

        return [
            'id' => $pluginData['UpdateURI'],
            'slug' => self::SLUG,
            'plugin' => $pluginFile,
            'version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires' => $pluginData['RequiresWP'] ?? '',
            'requires_php' => $pluginData['RequiresPHP'] ?? '',
        ];
    }

    /** Das Fenster "Details ansehen" in der Plugin-Liste und unter Aktualisierungen. */
    public static function pluginInfo(mixed $result, string $action, object $args): mixed
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $release = self::latestRelease();
        if ($release === null) {
            return $result;
        }

        $plugin = get_plugin_data(self::$pluginFile, false, false);

        return (object) [
            'name' => $plugin['Name'],
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => $plugin['AuthorURI'] !== ''
                ? '<a href="' . esc_url($plugin['AuthorURI']) . '">' . esc_html($plugin['Author']) . '</a>'
                : esc_html($plugin['Author']),
            'homepage' => $plugin['PluginURI'] !== '' ? $plugin['PluginURI'] : 'https://github.com/' . self::REPOSITORY,
            'requires' => $plugin['RequiresWP'],
            'requires_php' => $plugin['RequiresPHP'],
            'last_updated' => $release['published'],
            'download_link' => $release['package'],
            'sections' => [
                'description' => wpautop(esc_html($plugin['Description'])),
                'changelog' => self::notesToHtml($release['notes']),
            ],
        ];
    }

    /**
     * Das neueste Release, fuer einige Stunden zwischengespeichert - GitHub
     * erlaubt ohne Anmeldung 60 Anfragen pro Stunde und IP. "Erneut pruefen"
     * unter Dashboard > Aktualisierungen fragt immer frisch.
     *
     * @return array{version: string, url: string, package: string, notes: string, published: string}|null
     */
    private static function latestRelease(): ?array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur gelesen, WordPress prueft selbst.
        $forceCheck = isset($_GET['force-check']) && current_user_can('update_plugins');

        $cached = get_site_transient(self::CACHE);
        if (!$forceCheck && is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github+json'],
        ]);

        $release = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $release = is_array($data) ? self::parseRelease($data) : null;
        }

        // Fehlschlaege nur kurz merken, damit GitHub nicht bei jedem Aufruf gefragt wird.
        set_site_transient(self::CACHE, $release ?? [], $release === null ? HOUR_IN_SECONDS : 6 * HOUR_IN_SECONDS);

        return $release;
    }

    /**
     * Die Release-Notizen (Markdown) als schlichtes HTML: Listen, fett,
     * Code. Mehr schreibt der Release-Workflow nicht.
     */
    public static function notesToHtml(string $markdown): string
    {
        $html = '';
        $inList = false;
        foreach (preg_split('/\R/', trim($markdown)) ?: [] as $line) {
            $isItem = (bool) preg_match('/^\s*[-*]\s+(.*)$/', $line, $match);
            if ($inList && !$isItem) {
                $html .= "</ul>\n";
                $inList = false;
            }
            if ($isItem) {
                $html .= ($inList ? '' : "<ul>\n") . '<li>' . self::inline($match[1]) . "</li>\n";
                $inList = true;
            } elseif (trim($line) !== '') {
                $html .= '<p>' . self::inline($line) . "</p>\n";
            }
        }

        return $html . ($inList ? "</ul>\n" : '');
    }

    private static function inline(string $text): string
    {
        $text = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        return (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    }

    /**
     * Die Antwort der GitHub-API auf das, was der Updater braucht. Ohne
     * ZIP-Datei am Release kein Update.
     *
     * @param array<string, mixed> $data
     * @return array{version: string, url: string, package: string, notes: string, published: string}|null
     */
    public static function parseRelease(array $data): ?array
    {
        $version = ltrim((string) ($data['tag_name'] ?? ''), 'vV');
        if (!preg_match('/^\d+(\.\d+)*$/', $version) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return null;
        }

        foreach ($data['assets'] ?? [] as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if (str_starts_with($name, self::SLUG) && str_ends_with($name, '.zip')) {
                return [
                    'version' => $version,
                    'url' => (string) ($data['html_url'] ?? ''),
                    'package' => (string) ($asset['browser_download_url'] ?? ''),
                    'notes' => (string) ($data['body'] ?? ''),
                    'published' => (string) ($data['published_at'] ?? ''),
                ];
            }
        }

        return null;
    }
}
