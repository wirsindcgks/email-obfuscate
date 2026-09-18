<?php

declare(strict_types=1);

namespace EmailObfuscate;

/**
 * Die Seite unter Einstellungen > E-Mail-Verschleierung, gebaut mit der
 * Settings API, dazu der Link "Einstellungen" in der Plugin-Liste und der
 * Bericht "Website pruefen" (JavaScript nur auf dieser Seite).
 */
final class Admin
{
    private const PAGE = 'email-obfuscate';

    private const GROUP = 'email_obfuscate';

    private static string $pluginFile = '';

    public static function register(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        add_action('admin_menu', [self::class, 'addPage']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_filter('plugin_action_links_' . plugin_basename($pluginFile), [self::class, 'addActionLink']);
    }

    public static function addPage(): void
    {
        add_options_page(
            __('E-Mail-Verschleierung', 'email-obfuscate'),
            __('E-Mail-Verschleierung', 'email-obfuscate'),
            'manage_options',
            self::PAGE,
            [self::class, 'renderPage']
        );
    }

    public static function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_' . self::PAGE) {
            return;
        }

        $dir = plugin_dir_path(self::$pluginFile) . 'assets/';
        $url = plugin_dir_url(self::$pluginFile) . 'assets/';
        wp_enqueue_style('email-obfuscate-scan', $url . 'scan.css', [], (string) filemtime($dir . 'scan.css'));
        wp_enqueue_script('email-obfuscate-scan', $url . 'scan.js', [], (string) filemtime($dir . 'scan.js'), true);

        $config = [
            'root' => esc_url_raw(rest_url(ScanApi::NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'concurrency' => 3,
            'last' => get_option(ScanApi::RESULT_OPTION, null),
            'strings' => [
                'collecting' => __('Sammle Seiten aus den Sitemaps …', 'email-obfuscate'),
                /* translators: 1: geprüfte Seiten, 2: alle Seiten */
                'progress' => __('%1$d von %2$d Seiten geprüft', 'email-obfuscate'),
                'failed' => __('Prüfung fehlgeschlagen:', 'email-obfuscate'),
                /* translators: 1: Datum, 2: Anzahl Seiten */
                'summaryHead' => __('Letzte Prüfung: %1$s, %2$d Seiten.', 'email-obfuscate'),
                /* translators: %d: Anzahl */
                'summaryOpen' => __('%d offene Adressen', 'email-obfuscate'),
                /* translators: %d: Anzahl */
                'summaryEncoded' => __('%d verschleiert', 'email-obfuscate'),
                /* translators: %d: Anzahl */
                'summaryPartial' => __('%d teilweise verschleiert', 'email-obfuscate'),
                /* translators: %d: Anzahl */
                'summaryErrors' => __('nicht abrufbar: %d', 'email-obfuscate'),
                'allClean' => __('Keine offene Adresse gefunden.', 'email-obfuscate'),
                'hintCache' => __('Einige Seiten liefert der Seiten-Cache noch in einer alten Fassung aus. Cache leeren und erneut prüfen.', 'email-obfuscate'),
                'hintLoopback' => __('Keine Seite war abrufbar. Vermutlich blockiert der Server oder eine Firewall (etwa Cloudflare) Anfragen der Website an sich selbst.', 'email-obfuscate'),
                'onlyOpen' => __('Nur Seiten mit offenen Adressen zeigen', 'email-obfuscate'),
                'colPage' => __('Seite', 'email-obfuscate'),
                'colOpen' => __('Offen', 'email-obfuscate'),
                'colEncoded' => __('Verschleiert', 'email-obfuscate'),
                'partial' => __('teilweise', 'email-obfuscate'),
                'noAddresses' => __('Auf keiner Seite steht eine E-Mail-Adresse.', 'email-obfuscate'),
                'contexts' => [
                    'script' => __('in <script>', 'email-obfuscate'),
                    'style' => __('in <style>', 'email-obfuscate'),
                    'comment' => __('in HTML-Kommentar', 'email-obfuscate'),
                    'json' => __('in JSON-LD', 'email-obfuscate'),
                ],
                'reasons' => [
                    'cache' => __('Veraltete Fassung im Seiten-Cache – Cache leeren.', 'email-obfuscate'),
                    'protected' => __('Steht in Script, Style oder Kommentar – dort verschleiert das Plugin nicht, weil Browser dort keine Zeichenreferenzen lesen.', 'email-obfuscate'),
                    'excluded' => __('Seite ist unter „Ausgeschlossene Seiten“ eingetragen.', 'email-obfuscate'),
                    'exception' => __('Adresse ist unter „Ausgenommene Adressen“ eingetragen.', 'email-obfuscate'),
                    'disabled' => __('Die Verschleierung ist ausgeschaltet.', 'email-obfuscate'),
                    'unknown' => __('Die Seite läuft nicht durch das Plugin, etwa weil ein anderes Plugin sie direkt ausliefert.', 'email-obfuscate'),
                ],
            ],
        ];
        wp_add_inline_script('email-obfuscate-scan', 'window.emailObfuscateScan = ' . wp_json_encode($config) . ';', 'before');
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public static function addActionLink(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::PAGE);

        return ['settings' => '<a href="' . esc_url($url) . '">' . esc_html__('Einstellungen', 'email-obfuscate') . '</a>'] + $links;
    }

    public static function registerSettings(): void
    {
        register_setting(self::GROUP, Settings::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [Settings::class, 'sanitize'],
            'default' => Settings::defaults(),
        ]);

        add_settings_section('general', '', '__return_null', self::PAGE);

        add_settings_field('enabled', __('Verschleierung', 'email-obfuscate'), [self::class, 'renderCheckbox'], self::PAGE, 'general', [
            'key' => 'enabled',
            'label' => __('E-Mail-Adressen im Quelltext verschleiern', 'email-obfuscate'),
            'description' => __('Ausgeschaltet liefert die Website alle Adressen im Klartext aus, das Plugin bleibt aber aktiv.', 'email-obfuscate'),
        ]);

        add_settings_field('encode_json', __('JSON-LD', 'email-obfuscate'), [self::class, 'renderCheckbox'], self::PAGE, 'general', [
            'key' => 'encode_json',
            'label' => __('Adressen in strukturierten Daten (JSON-LD) mitkodieren', 'email-obfuscate'),
            'description' => __('Das @ wird dort zu \u0040. Suchmaschinen lesen die Adresse unverändert.', 'email-obfuscate'),
        ]);

        add_settings_field('excluded_paths', __('Ausgeschlossene Seiten', 'email-obfuscate'), [self::class, 'renderTextarea'], self::PAGE, 'general', [
            'key' => 'excluded_paths',
            'placeholder' => "/impressum/\n/shop/*",
            'description' => __('Ein Pfad pro Zeile, relativ zur Startseite. * steht für beliebig viele Zeichen, /shop/* trifft /shop und alle Unterseiten. Ganze URLs werden auf den Pfad gekürzt.', 'email-obfuscate'),
        ]);

        add_settings_field('excluded_addresses', __('Ausgenommene Adressen', 'email-obfuscate'), [self::class, 'renderTextarea'], self::PAGE, 'general', [
            'key' => 'excluded_addresses',
            'placeholder' => "info@example.org\n@example.org",
            'description' => __('Eine Adresse pro Zeile, oder @domain.de für alle Adressen einer Domain. Diese Adressen bleiben im Klartext.', 'email-obfuscate'),
        ]);
    }

    /** @param array{key: string, label: string, description: string} $args */
    public static function renderCheckbox(array $args): void
    {
        $settings = Settings::get();
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label><p class="description">%5$s</p>',
            esc_attr(Settings::OPTION),
            esc_attr($args['key']),
            checked($settings[$args['key']], true, false),
            esc_html($args['label']),
            esc_html($args['description'])
        );
    }

    /** @param array{key: string, placeholder: string, description: string} $args */
    public static function renderTextarea(array $args): void
    {
        $settings = Settings::get();
        printf(
            '<textarea name="%1$s[%2$s]" rows="5" class="large-text code" placeholder="%3$s">%4$s</textarea><p class="description">%5$s</p>',
            esc_attr(Settings::OPTION),
            esc_attr($args['key']),
            esc_attr($args['placeholder']),
            esc_textarea(implode("\n", $settings[$args['key']])),
            esc_html($args['description'])
        );
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('Schreibt jede E-Mail-Adresse der Website im Quelltext als Zeichenreferenzen. Besucher sehen und klicken sie unverändert.', 'email-obfuscate'); ?></p>
            <p><?php esc_html_e('Nutzt die Website einen Seiten-Cache (etwa W3 Total Cache), nach dem Speichern den Cache leeren – sonst liefert er noch die alte Fassung aus.', 'email-obfuscate'); ?></p>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::GROUP);
                do_settings_sections(self::PAGE);
                submit_button();
                ?>
            </form>

            <hr>
            <?php self::renderTester(); ?>

            <hr>
            <?php self::renderScan(); ?>
        </div>
        <?php
    }

    /** Geruest fuer den Bericht, gefuellt von assets/scan.js. */
    private static function renderScan(): void
    {
        ?>
        <h2><?php esc_html_e('Website prüfen', 'email-obfuscate'); ?></h2>
        <p><?php esc_html_e('Ruft jede Seite aus den Sitemaps so ab, wie Besucher und Adresssammler sie bekommen – mit Seiten-Cache – und zeigt, welche Adressen im Quelltext offen stehen und welche verschleiert sind. Inhalte, die per AJAX nachgeladen werden, und PDF-Dateien erfasst die Prüfung nicht.', 'email-obfuscate'); ?></p>
        <p>
            <button type="button" class="button button-primary" id="eo-scan-start"><?php esc_html_e('Website prüfen', 'email-obfuscate'); ?></button>
            <span id="eo-scan-status" class="eo-scan-status" aria-live="polite"></span>
        </p>
        <progress id="eo-scan-progress" class="eo-scan-progress" hidden></progress>
        <div id="eo-scan-report" class="eo-scan-report"></div>
        <noscript><p><?php esc_html_e('Die Prüfung braucht JavaScript.', 'email-obfuscate'); ?></p></noscript>
        <?php
    }

    /**
     * Zeigt, was aus einem Text im Quelltext wird - mit den gespeicherten
     * Einstellungen und demselben Encoder wie im Frontend.
     */
    private static function renderTester(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur angezeigt, nichts gespeichert.
        $text = isset($_GET['eo_test']) ? sanitize_text_field(wp_unslash($_GET['eo_test'])) : '';
        $settings = Settings::get();

        ?>
        <h2><?php esc_html_e('Testen', 'email-obfuscate'); ?></h2>
        <p><?php esc_html_e('Gib eine Adresse oder einen Satz mit Adresse ein und sieh, was im Quelltext steht.', 'email-obfuscate'); ?></p>
        <form method="get" action="<?php echo esc_url(admin_url('options-general.php')); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
            <input type="text" name="eo_test" class="regular-text" value="<?php echo esc_attr($text); ?>" placeholder="info@example.org">
            <?php submit_button(__('Anzeigen', 'email-obfuscate'), 'secondary', '', false); ?>
        </form>
        <?php

        if ($text === '') {
            return;
        }

        $html = esc_html($text);
        $encoded = Encoder::encodeHtml($html, $settings['excluded_addresses']);

        if (!$settings['enabled']) {
            $status = __('Die Verschleierung ist ausgeschaltet. Mit eingeschalteter Verschleierung stünde im Quelltext:', 'email-obfuscate');
        } elseif (!preg_match(Encoder::PATTERN, $text)) {
            $status = __('Keine E-Mail-Adresse gefunden.', 'email-obfuscate');
        } elseif ($encoded === $html) {
            $status = __('Die Adresse ist ausgenommen und bleibt im Klartext.', 'email-obfuscate');
        } else {
            $status = __('Im Quelltext steht:', 'email-obfuscate');
        }

        ?>
        <p><strong><?php echo esc_html($status); ?></strong></p>
        <?php
        // Nicht esc_html(): das laesst vorhandene Zeichenreferenzen stehen, der
        // Browser wuerde sie wieder uebersetzen und hier Klartext zeigen.
        $source = htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8', true);
        ?>
        <p><code style="word-break: break-all;"><?php echo $source; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- mit htmlspecialchars() maskiert. ?></code></p>
        <p><?php esc_html_e('Der Browser zeigt:', 'email-obfuscate'); ?> <?php echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aus esc_html() und Zeichenreferenzen gebaut. ?></p>
        <?php
    }
}
