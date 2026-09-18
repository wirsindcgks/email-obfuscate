<?php

/**
 * Entfernt Einstellungen, letzten Pruefbericht und zwischengespeichertes
 * Release beim Loeschen des Plugins, in einer Multisite auf jeder Website.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
        switch_to_blog($siteId);
        delete_option('email_obfuscate_settings');
        delete_option('email_obfuscate_last_scan');
        restore_current_blog();
    }
} else {
    delete_option('email_obfuscate_settings');
    delete_option('email_obfuscate_last_scan');
}

delete_site_transient('email_obfuscate_release');
