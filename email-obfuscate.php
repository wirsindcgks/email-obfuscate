<?php

/**
 * Plugin Name:       Email Obfuscate
 * Description:       Verschleiert alle E-Mail-Adressen der Website im Quelltext, auch in Header, Footer, Widgets und Theme-Optionen. Besucher sehen und klicken sie unverändert.
 * Version:           1.3.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            wirsindcgks
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       email-obfuscate
 * Update URI:        https://github.com/wirsindcgks/email-obfuscate
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Encoder.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Admin.php';
require_once __DIR__ . '/src/Scanner.php';
require_once __DIR__ . '/src/ScanApi.php';
require_once __DIR__ . '/src/Updater.php';
require_once __DIR__ . '/src/Plugin.php';

\EmailObfuscate\Plugin::register(__FILE__);
