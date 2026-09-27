# Email Obfuscate – Plan

## Offen

- [x] **Kommentar in `src/Encoder.php` (Zeile 13–14) korrigieren.** Dort steht, `antispambot()` lasse zufällig etwa die Hälfte der Zeichen offen, „auch das `@`“. Das stimmt nicht: WordPress ersetzt am Ende von `antispambot()` jedes `@` durch `&#64;` (`return str_replace( '@', '&#64;', … )` in `wp-includes/formatting.php`). Offen bleiben nur zufällig etwa die Hälfte der *übrigen* Zeichen. Nur der Kommentar ist falsch, der Code nicht.

## Zusammenspiel mit dem ChurchTools-Plugin (geprüft 2026-09-18)

Das ChurchTools-Plugin verschleiert seine eigenen Ausgaben selbst: `EventFormatter::safeText()`, `safeAttr()` und `maskEmails()` über `antispambot()`, im JSON-LD `@`. Die beiden Plugins stören sich nicht:

- **Keine doppelte Kodierung.** Die Ausgabe von `antispambot()` enthält nie ein offenes `@`, das JSON-LD des ChurchTools-Plugins auch nicht. `Encoder::PATTERN` braucht ein offenes `@` und findet dort deshalb nichts. Gegenprobe mit dem echten `Encoder`: 100.000 zufällige `antispambot()`-Ausgaben in Text, `mailto:`-Link und JSON-LD, davon wurden 0 verändert.
- **Die Reihenfolge passt.** Das ChurchTools-Plugin kodiert beim Aufbau der Seite, Email Obfuscate erst im Ausgabepuffer.
- **Das ChurchTools-Plugin muss weiter selbst verschleiern.** Email Obfuscate lässt AJAX-Anfragen aus (`wp_doing_ajax()`). Nachgeladene Termine, die Suche im Eventfinder und Popups aus AJAX-Antworten schützt nur das ChurchTools-Plugin selbst.
