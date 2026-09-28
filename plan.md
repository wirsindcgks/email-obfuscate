# Email Obfuscate – Plan

## Offen

- [x] **Kommentar in `src/Encoder.php` (Zeile 13–14) korrigieren.** Dort steht, `antispambot()` lasse zufällig etwa die Hälfte der Zeichen offen, „auch das `@`“. Das stimmt nicht: WordPress ersetzt am Ende von `antispambot()` jedes `@` durch `&#64;` (`return str_replace( '@', '&#64;', … )` in `wp-includes/formatting.php`). Offen bleiben nur zufällig etwa die Hälfte der *übrigen* Zeichen. Nur der Kommentar ist falsch, der Code nicht.

## Release-Tags mit v

Seit Version 1.3.5 tragen Tags ein v (`v1.3.5`), wie in churchtools-infoscreen und connect-churchtools. Der Release-Workflow reagiert nur auf `v[0-9]+.[0-9]+.[0-9]+`, von Hand: `gh workflow run release.yml --ref v1.3.5`. Die Versionsnummer bleibt ohne v: Plugin-Header, `Stable tag`, Changelog-Überschrift, ZIP-Name und Release-Titel. Der Updater entfernt das v aus `tag_name`.

Die alten Tags bis 1.3.4 bleiben ohne v. Sie lassen sich wegen des Regelsatzes weder löschen noch umbenennen, und das ist so gewollt. Der Regelsatz bleibt auf `refs/tags/*`, damit alte und neue Tags geschützt sind.

Beim ersten Release mit v prüfen:

- [ ] Das Release ist bei GitHub als „Latest“ markiert (der Workflow gibt `--latest` mit).
- [ ] Die ZIP-Datei `email-obfuscate-1.3.5.zip` hängt am Release.
- [ ] In einer WordPress-Installation zeigt „Nach Updates suchen“ die neue Version an.

## Zusammenspiel mit dem ChurchTools-Plugin (geprüft 2026-09-18)

Das ChurchTools-Plugin verschleiert seine eigenen Ausgaben selbst: `EventFormatter::safeText()`, `safeAttr()` und `maskEmails()` über `antispambot()`, im JSON-LD `@`. Die beiden Plugins stören sich nicht:

- **Keine doppelte Kodierung.** Die Ausgabe von `antispambot()` enthält nie ein offenes `@`, das JSON-LD des ChurchTools-Plugins auch nicht. `Encoder::PATTERN` braucht ein offenes `@` und findet dort deshalb nichts. Gegenprobe mit dem echten `Encoder`: 100.000 zufällige `antispambot()`-Ausgaben in Text, `mailto:`-Link und JSON-LD, davon wurden 0 verändert.
- **Die Reihenfolge passt.** Das ChurchTools-Plugin kodiert beim Aufbau der Seite, Email Obfuscate erst im Ausgabepuffer.
- **Das ChurchTools-Plugin muss weiter selbst verschleiern.** Email Obfuscate lässt AJAX-Anfragen aus (`wp_doing_ajax()`). Nachgeladene Termine, die Suche im Eventfinder und Popups aus AJAX-Antworten schützt nur das ChurchTools-Plugin selbst.
