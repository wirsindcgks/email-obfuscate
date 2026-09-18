<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Faengt die fertige Seite in einem Ausgabepuffer ab und laesst den Encoder
 * darueber laufen - so erfasst er auch Header, Footer, Widgets und
 * Theme-Optionen, nicht nur den Beitragsinhalt.
 *
 * Der Puffer startet bei `template_redirect`, also nur fuer Seiten, die
 * WordPress im Frontend ausliefert. Ein Seiten-Cache wie W3 Total Cache
 * startet seinen Puffer frueher und liegt damit aussen: PHP leert Puffer von
 * innen nach aussen, der Cache bekommt und speichert also schon die
 * verschleierte Seite.
 */
final class Plugin
{
    public static function register(string $pluginFile): void
    {
        add_action('template_redirect', [self::class, 'startBuffer'], PHP_INT_MAX);

        // Nicht nur im Admin: WordPress prueft Updates auch per Cron.
        Updater::register($pluginFile);

        if (is_admin()) {
            Admin::register($pluginFile);
        }
    }

    public static function startBuffer(): void
    {
        if (!self::appliesToRequest()) {
            return;
        }

        ob_start([self::class, 'filterOutput']);
    }

    /**
     * Der Puffer-Callback. Er prueft den Content-Type erst hier, weil ein
     * Endpunkt ihn auch nach `template_redirect` noch setzen kann (die
     * ICS-Datei des ChurchTools-Plugins, Sitemaps): Alles, was nicht HTML ist,
     * geht unveraendert durch.
     */
    public static function filterOutput(string $buffer): string
    {
        if ($buffer === '' || !self::isHtmlResponse()) {
            return $buffer;
        }

        $settings = Settings::get();

        return Encoder::encodeHtml($buffer, $settings['excluded_addresses'], $settings['encode_json']);
    }

    private static function appliesToRequest(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (is_feed() || is_embed() || is_customize_preview()) {
            return false;
        }

        // Frontend-Editor von WPBakery: Dort wird Inhalt bearbeitet, nicht
        // ausgeliefert, und eine verschleierte Adresse koennte in ein
        // Eingabefeld und von dort zurueck in den Inhalt geraten.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur gelesen, um den Editor zu erkennen.
        if (isset($_GET['vc_editable']) || (function_exists('vc_is_inline') && vc_is_inline())) {
            return false;
        }

        $settings = Settings::get();
        if (!$settings['enabled'] || Settings::isExcludedPath(self::requestPath(), $settings['excluded_paths'])) {
            return false;
        }

        /** Abschalten fuer einzelne Anfragen: add_filter('email_obfuscate_enabled', '__return_false'). */
        return (bool) apply_filters('email_obfuscate_enabled', true);
    }

    /** Der angefragte Pfad relativ zur Startseite, auch bei WordPress im Unterordner. */
    private static function requestPath(): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nur mit Mustern verglichen.
        $path = rawurldecode((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $home = rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home !== '' && str_starts_with($path, $home)) {
            $path = substr($path, strlen($home));
        }

        return '/' . ltrim($path, '/');
    }

    private static function isHtmlResponse(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0) {
                return stripos($header, 'text/html') !== false;
            }
        }

        // Ohne eigenen Content-Type schickt PHP text/html.
        return true;
    }
}
