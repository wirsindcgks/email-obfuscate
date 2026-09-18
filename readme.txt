=== Email Obfuscate ===
Contributors: wirsindcgks
Tags: email, spam, antispam, obfuscation
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schützt alle E-Mail-Adressen deiner Website vor Spam-Sammlern – automatisch, auf jeder Seite, ohne dass Besucher etwas merken.

== Description ==

Spam-Programme durchsuchen Websites nach E-Mail-Adressen. Email Obfuscate verschleiert jede Adresse im Quelltext der Seite: Besucher sehen und klicken sie ganz normal, ein Sammler findet nur eine Zeichenfolge wie `&#105;&#110;&#102;&#111;&#64;…`.

**Das Plugin arbeitet sofort nach der Aktivierung.** Du musst nichts einrichten.

= Was das Plugin kann =

* Erfasst die ganze Seite: Inhalte, Header, Footer, Widgets, Theme-Optionen und Seitenbaukästen wie WPBakery – nicht nur den Beitragstext.
* Verschleiert jedes Zeichen einer Adresse, auch das @.
* Mailto-Links funktionieren weiter, Browser, Screenreader und Suchmaschinen lesen die Adressen unverändert.
* Lädt kein JavaScript für Besucher.
* Verträgt sich mit Seiten-Caches wie W3 Total Cache.
* Prüft auf Knopfdruck die ganze Website und zeigt, ob irgendwo noch eine Adresse lesbar im Quelltext steht.
* Holt Updates direkt von GitHub, sie erscheinen im Dashboard wie bei jedem anderen Plugin.

= Was das Plugin nicht erfasst =

* Inhalte, die eine Seite nachträglich per AJAX nachlädt, etwa Suchergebnisse mancher Plugins.
* PDF-Dateien, andere Downloads und Adressen in Bildern.
* Adressen in Scripts, Stylesheets und HTML-Kommentaren: Dort könnten Browser eine verschleierte Adresse nicht mehr lesen. Die Website-Prüfung zeigt solche Fundstellen an.
* Den Admin-Bereich, RSS-Feeds und E-Mails, die WordPress verschickt.

Einen vollständigen Schutz gibt es nicht: Die Verschleierung hält die allermeisten Sammler ab, ein gezielt programmierter findet die Adresse trotzdem. Wer eine Adresse gar nicht zeigen will, nimmt ein Kontaktformular.

== Installation ==

1. Auf https://github.com/wirsindcgks/email-obfuscate/releases beim neuesten Release die Datei `email-obfuscate-x.y.z.zip` herunterladen – nicht „Source code“.
2. In WordPress unter *Plugins → Installieren → Plugin hochladen* die Datei auswählen, *Jetzt installieren* und dann *Aktivieren* klicken.
3. Nutzt die Website einen Seiten-Cache, ihn jetzt leeren (W3 Total Cache: *Performance → Purge All Caches*). Sonst liefert der Cache noch die Seiten von vorher aus.
4. Unter *Einstellungen → E-Mail-Verschleierung* auf **Website prüfen** klicken. Der Bericht zeigt, ob alle Adressen verschleiert sind.

== Einstellungen ==

Alle Einstellungen liegen unter *Einstellungen → E-Mail-Verschleierung*. Für die meisten Websites passen die Voreinstellungen.

* **Verschleierung** – schaltet den Schutz aus und ein, ohne das Plugin zu deaktivieren. Praktisch bei der Fehlersuche.
* **JSON-LD** – verschleiert Adressen auch in den strukturierten Daten für Suchmaschinen (das @ wird dort zu `\u0040`). Suchmaschinen lesen sie trotzdem richtig. Voreinstellung: an.
* **Ausgeschlossene Seiten** – Seiten, die das Plugin nicht verändert. Ein Pfad pro Zeile, zum Beispiel `/impressum/`. `/shop/*` schließt `/shop` und alle Unterseiten aus.
* **Ausgenommene Adressen** – Adressen, die lesbar bleiben sollen. Eine pro Zeile, oder `@domain.de` für alle Adressen einer Domain.

Nach dem Speichern den Seiten-Cache leeren.

= Testen =

Gib eine Adresse ein und sieh, wie sie im Quelltext erscheint.

= Website prüfen =

Ruft jede Seite aus den Sitemaps so ab, wie Besucher und Sammler sie bekommen, und zeigt pro Seite:

* **Offen** (rot) – die Adresse steht lesbar im Quelltext. Der Bericht nennt den Grund und was zu tun ist.
* **Verschleiert** – vollständig geschützt.
* **Teilweise verschleiert** – ein anderes Plugin hat die Adresse mit der WordPress-eigenen Funktion verschleiert. Das @ ist immer geschützt, einzelne Buchstaben nicht. Das reicht gegen einfache Sammler.

Je nach Größe der Website dauert die Prüfung ein bis drei Minuten. Der letzte Bericht bleibt gespeichert.

== Updates ==

Neue Versionen erscheinen unter *Dashboard → Aktualisierungen* und in der Plugin-Liste, wie bei jedem anderen Plugin. Dort lassen sich auch automatische Updates einschalten.

WordPress sucht von sich aus etwa zweimal am Tag nach Updates. Sofort prüfen: in der Plugin-Liste bei Email Obfuscate auf **Nach Updates suchen** klicken.

Läuft noch Version 1.1.0 oder älter, die neue Version einmal von Hand hochladen (siehe Installation). Ab 1.2.0 kommen Updates automatisch.

== Frequently Asked Questions ==

= Wie sehe ich selbst, ob eine Adresse verschleiert ist? =

Am einfachsten mit **Website prüfen**. Von Hand: Seite im Browser öffnen, den Seitenquelltext anzeigen (Windows: Strg+U, Mac: ⌥⌘U) und nach @ suchen. Nicht den Web-Inspektor (F12) nehmen – der zeigt die Seite so, wie der Browser sie übersetzt hat, also mit lesbarer Adresse.

= Die Prüfung meldet „Veraltete Fassung im Seiten-Cache“ =

Der Cache hat die Seite gespeichert, bevor das Plugin aktiv war oder bevor du eine Einstellung geändert hast. Cache leeren und erneut prüfen.

= Die Prüfung meldet, keine Seite sei abrufbar =

Die Prüfung ruft die Website vom eigenen Server aus ab. Manche Hoster und Firewalls – etwa Cloudflare mit Bot-Schutz – blockieren solche Anfragen. Die Verschleierung selbst funktioniert trotzdem. Prüfe dann von Hand wie oben beschrieben oder frage beim Hoster nach.

= Eine Adresse steht „in <script>“ oder „in HTML-Kommentar“ =

Dort verschleiert das Plugin bewusst nicht, weil der Browser die Adresse sonst nicht mehr lesen könnte. Meist stammt so eine Adresse aus einem Theme- oder Plugin-Baustein. Entferne sie dort oder frage den Hersteller.

= Nach der Aktivierung sieht eine Seite anders aus oder ein Formular geht nicht mehr =

Die Seite unter *Ausgeschlossene Seiten* eintragen, Cache leeren und prüfen, ob der Fehler verschwindet. Dann bitte ein Issue auf GitHub anlegen: https://github.com/wirsindcgks/email-obfuscate/issues

= Für Entwickler =

Abschalten für einzelne Anfragen: `add_filter( 'email_obfuscate_enabled', '__return_false' );`

Tests: `php tests/run.php`. Ein Release entsteht, wenn ein Tag wie `1.4.0` gepusht wird; der Workflow prüft Versionsnummer, `Stable tag` und Changelog-Eintrag.

== Changelog ==

= 1.3.1 =
* Link „Nach Updates suchen“ in der Plugin-Liste: fragt GitHub sofort statt nach bis zu sechs Stunden

= 1.3.0 =
* Website prüfen: Bericht über alle Seiten aus den Sitemaps, mit Grund für jede offene Adresse

= 1.2.0 =
* Updates direkt aus den GitHub-Releases, im Dashboard wie jedes andere Plugin
* Testfeld zeigt den Quelltext jetzt kodiert statt im Klartext

= 1.1.0 =
* Einstellungsseite unter Einstellungen → E-Mail-Verschleierung
* Seiten ausschließen, Adressen und Domains ausnehmen
* JSON-LD-Kodierung abschaltbar
* Testfeld für einzelne Adressen
* Link „Einstellungen“ in der Plugin-Liste

= 1.0.0 =
* Erste Version
