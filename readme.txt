=== Email Obfuscate ===
Contributors: wirsindcgks
Tags: email, spam, antispam, obfuscation
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Verschleiert alle E-Mail-Adressen der Website im Quelltext – auch in Header, Footer, Widgets und Theme-Optionen.

== Description ==

Adresssammler lesen den Quelltext einer Seite und suchen darin nach allem, was wie `name@domain.de` aussieht. Dieses Plugin nimmt jede Seite, bevor sie an den Browser geht, und schreibt jede E-Mail-Adresse darin als Zeichenreferenzen (`&#103;&#101;…&#64;…`). Der Browser zeigt und verlinkt sie unverändert, im Quelltext steht keine Adresse mehr.

Anders als Plugins, die nur den Beitragsinhalt durchsuchen, erfasst es die ganze Seite: Kontaktkästen des Themes, Footer, Widgets, Seitenbaukästen wie WPBakery.

* Keine Einstellungen, kein JavaScript, keine Datenbankeinträge.
* Jedes Zeichen wird kodiert, auch das `@`.
* `<script>`, `<style>` und HTML-Kommentare bleiben unberührt – dort lesen Browser keine Zeichenreferenzen. In JSON-Blöcken (JSON-LD) wird das `@` einer Adresse zu `@`.
* Nur HTML-Seiten im Frontend. Admin, Feeds, Sitemaps, REST-API, Kalender-Dateien und der Frontend-Editor von WPBakery bleiben unverändert.
* Verträgt sich mit Seiten-Caches wie W3 Total Cache: Im Cache landet schon die verschleierte Seite. Nach der Installation einmal den Cache leeren.

Abschalten für einzelne Anfragen: `add_filter( 'email_obfuscate_enabled', '__return_false' );`

Prüfen: im Seitenquelltext (nicht im Web-Inspektor, der zeigt die übersetzte Fassung) nach `@` plus Domain suchen.

== Changelog ==

= 1.0.0 =
* Erste Version
