<?php
declare(strict_types=1);

/*
 * Public content pages for search engines and people: /de/<slug> and /en/<slug>.
 * Each page exists in both languages (linked via hreflang). Texts are trusted HTML.
 * Keep claims true to what the tool actually does.
 */

function content_pages(): array
{
    return [
        'faq' => [
            'en' => ['slug' => 'faq', 'nav' => 'FAQ',
                'title' => 'FAQ: free dynamic QR codes that never expire',
                'description' => 'Answers about our free QR code generator: changing the target after printing, expiry, privacy, print size, logos, campaigns and mini-apps.',
                'h1' => 'Questions & answers',
                'faq' => [
                    ['Is it really free?', 'Yes. You can create codes without an account (up to 10 per browser). A free account has unlimited codes with campaigns, styles and stats.'],
                    ['Do my QR codes expire?', 'No. There is no trial that runs out and no subscription trap. Your printed codes keep working, also on the free plan.'],
                    ['Can I change where the code leads after printing?', 'Yes, that is the whole idea. The QR code points to a short link, and you can change its target any time. The printed code stays the same.'],
                    ['What do you track when someone scans?', 'Scans per day, unique visitors (counted with a hash that changes daily), approximate city and country, device, language and time. We set no tracking cookies and never store IP addresses.'],
                    ['How small can I print a QR code?', 'At least 2 cm wide for close-up scanning, larger for posters seen from a distance. Keep the white border around the code and use dark colors on a light background.'],
                    ['Can I add colors, dots or my logo?', 'Yes: dots or squares, round corners, color presets or your own colors, an emoji or your logo in the middle. Every style is test-scanned before it is saved.'],
                    ['PNG or SVG?', 'SVG for print (sharp at any size, works in InDesign, Illustrator, Affinity), PNG for quick use on screens and in documents.'],
                    ['Can I make many codes at once?', 'Yes. Campaigns create numbered codes in bulk, for example one per poster location, and download them all as a zip.'],
                    ['What are mini-apps?', 'Something people see before the target: a hosted flyer page, a sign-up form, a poll, a photo upload or a ticket button. A code can even have just a flyer page and no target.'],
                    ['Who runs this?', 'qr is built in Vienna, for artists, bands, galleries, off-spaces and collectives. The service is hosted in the EU.'],
                ]],
            'de' => ['slug' => 'faq', 'nav' => 'FAQ',
                'title' => 'FAQ: kostenlose dynamische QR-Codes, die nicht ablaufen',
                'description' => 'Antworten zu unserem gratis QR-Code-Generator: Ziel nach dem Druck ändern, Ablauf, Datenschutz, Druckgröße, Logos, Kampagnen und Mini-Apps.',
                'h1' => 'Fragen & Antworten',
                'faq' => [
                    ['Ist das wirklich gratis?', 'Ja. Ohne Konto kannst du bis zu 10 Codes pro Browser erstellen. Ein gratis Konto hat unbegrenzt viele Codes mit Kampagnen, Stilen und Statistik.'],
                    ['Laufen meine QR-Codes ab?', 'Nein. Es gibt keine Testphase, die ausläuft, und keine Abo-Falle. Deine gedruckten Codes funktionieren weiter, auch im Gratis-Plan.'],
                    ['Kann ich nach dem Druck ändern, wohin der Code führt?', 'Ja, genau darum geht es. Der QR-Code zeigt auf einen Kurzlink, dessen Ziel du jederzeit ändern kannst. Der gedruckte Code bleibt gleich.'],
                    ['Was wird beim Scannen erfasst?', 'Scans pro Tag, eindeutige Besucher (gezählt mit einem täglich wechselnden Hash), ungefähre Stadt und Land, Gerät, Sprache und Uhrzeit. Wir setzen keine Tracking-Cookies und speichern keine IP-Adressen.'],
                    ['Wie klein darf ich einen QR-Code drucken?', 'Mindestens 2 cm breit für das Scannen aus der Nähe, größer bei Plakaten, die man aus Distanz sieht. Lass den weißen Rand stehen und nimm dunkle Farben auf hellem Grund.'],
                    ['Kann ich Farben, Punkte oder mein Logo verwenden?', 'Ja: Punkte oder Quadrate, runde Ecken, Farbvorlagen oder eigene Farben, ein Emoji oder dein Logo in der Mitte. Jeder Stil wird vor dem Speichern testweise gescannt.'],
                    ['PNG oder SVG?', 'SVG für den Druck (scharf in jeder Größe, funktioniert in InDesign, Illustrator, Affinity), PNG für Bildschirm und Dokumente.'],
                    ['Kann ich viele Codes auf einmal erstellen?', 'Ja. Kampagnen erstellen nummerierte Codes in einem Schwung, zum Beispiel einen pro Plakatstandort, und laden alle als ZIP herunter.'],
                    ['Was sind Mini-Apps?', 'Etwas, das Leute vor dem Ziel sehen: eine gehostete Flyer-Seite, ein Anmeldeformular, eine Abstimmung, ein Foto-Upload oder ein Ticket-Button. Ein Code kann sogar nur eine Flyer-Seite haben, ganz ohne Ziel.'],
                    ['Wer steckt dahinter?', 'qr wird in Wien gebaut, für Künstler:innen, Bands, Galerien, Off-Spaces und Kollektive. Der Dienst ist in der EU gehostet.'],
                ]],
        ],
        'expiry' => [
            'en' => ['slug' => 'qr-codes-that-never-expire', 'nav' => 'Never expire',
                'title' => 'QR codes that never expire: free dynamic QR codes without a subscription',
                'description' => 'Many "free" QR generators switch your dynamic codes off after a trial. Ours never expire: change the target any time, no subscription trap, no tracking cookies.',
                'h1' => 'QR codes that never expire.',
                'html' => '<p class="lead">Your flyers are printed, your posters are on the wall, and suddenly the QR code leads to a paywall. That happens with many "free" QR generators: dynamic codes only work during a trial, then you are asked to subscribe.</p>
<h2>How the trap works</h2>
<p>A dynamic QR code points to a short link on the generator\'s server. Whoever controls that server decides whether the code keeps working. When the trial ends, many services redirect your code to a "please upgrade" page. Your printed material is now held hostage.</p>
<h2>How qr is different</h2>
<ul><li><b>No trial, no expiry.</b> Codes keep working on the free plan.</li><li><b>Change the target any time.</b> Same printed code, new destination.</li><li><b>Unlimited codes for free</b> with an account, 10 per browser without one.</li><li><b>No tracking cookies</b> and no stored IP addresses for the people who scan.</li><li><b>Pause instead of break.</b> A paused code shows a friendly note, not an ad.</li></ul>
<h2>Already stuck with a code that stopped working?</h2>
<p>You cannot move a printed code to another service, because the short link belongs to the old one. For your next print run, make the code here, and you will never have that problem again.</p>'],
            'de' => ['slug' => 'qr-codes-die-nicht-ablaufen', 'nav' => 'Läuft nie ab',
                'title' => 'QR-Codes, die nicht ablaufen: dynamische QR-Codes gratis, ohne Abo',
                'description' => 'Viele „kostenlose“ QR-Generatoren schalten dynamische Codes nach einer Testphase ab. Unsere laufen nie ab: Ziel jederzeit ändern, keine Abo-Falle, keine Tracking-Cookies.',
                'h1' => 'QR-Codes, die nicht ablaufen.',
                'html' => '<p class="lead">Die Flyer sind gedruckt, die Plakate hängen, und plötzlich führt der QR-Code auf eine Bezahlschranke. Das passiert bei vielen „kostenlosen“ QR-Generatoren: Dynamische Codes funktionieren nur während einer Testphase, danach sollst du ein Abo abschließen.</p>
<h2>So funktioniert die Falle</h2>
<p>Ein dynamischer QR-Code zeigt auf einen Kurzlink am Server des Anbieters. Wer diesen Server kontrolliert, entscheidet, ob der Code weiter funktioniert. Endet die Testphase, leiten viele Dienste deinen Code auf eine „Bitte upgraden“-Seite um. Deine Drucksorten sind dann Geiseln.</p>
<h2>Was qr anders macht</h2>
<ul><li><b>Keine Testphase, kein Ablauf.</b> Codes funktionieren auch im Gratis-Plan weiter.</li><li><b>Ziel jederzeit ändern.</b> Gleicher gedruckter Code, neues Ziel.</li><li><b>Unbegrenzt viele Codes gratis</b> mit Konto, 10 pro Browser ohne.</li><li><b>Keine Tracking-Cookies</b> und keine gespeicherten IP-Adressen bei denen, die scannen.</li><li><b>Pausieren statt kaputt.</b> Ein pausierter Code zeigt einen freundlichen Hinweis, keine Werbung.</li></ul>
<h2>Schon einen Code, der nicht mehr geht?</h2>
<p>Einen gedruckten Code kannst du nicht zu einem anderen Dienst mitnehmen, weil der Kurzlink dem alten Anbieter gehört. Mach den Code für deinen nächsten Druck hier, dann hast du das Problem nie wieder.</p>'],
        ],
        'developers' => [
            'en' => ['slug' => 'developers', 'nav' => 'AI & API',
                'title' => 'QR code MCP server and API for AI agents',
                'description' => 'Free MCP server and REST API: let Claude, ChatGPT, Cursor and other AI agents create dynamic QR codes that never expire. Works without a key.',
                'h1' => 'QR codes for AI agents.',
                'html' => <<<'HTML'
<p class="lead">qr has a free MCP server, so AI assistants can make QR codes for you: "make me a QR code for my show page with a guitar in the middle" just works. There is also a plain REST API.</p>
<h2>Connect your AI app</h2>
<p>Add a <b>remote MCP server</b> with this URL. It works without any key (anonymous: up to 10 codes a day, each with a private edit link for you).</p>
<pre><code>{base}/mcp</code></pre>
<p><b>Claude (desktop, web):</b> Settings → Connectors → Add custom connector → paste the URL.<br><b>Claude Code:</b> <code>claude mcp add --transport http qr {base}/mcp</code><br><b>Cursor, VS Code and others:</b> add it to the MCP settings:</p>
<pre><code>{
  "mcpServers": {
    "qr": {
      "url": "{base}/mcp",
      "headers": { "Authorization": "Bearer YOUR_API_KEY" }
    }
  }
}</code></pre>
<p>The <code>headers</code> part is optional. With a free API key (create one in your account under "API &amp; AI agents") the assistant can also list your codes, change targets, show stats and create codes in bulk.</p>
<h2>Tools</h2>
<ul><li><b>create_qr_code</b>: QR code for a link, optional style (dots, corners, colors, emoji). Returns the image, the short URL, PNG and SVG links.</li><li><b>update_qr_code</b>: change where a code leads (printed codes keep working).</li><li><b>get_qr_code_stats</b>: scans, unique visitors, cities, devices.</li><li><b>list_qr_codes</b>, <b>create_qr_codes_bulk</b> (with an API key).</li><li><b>get_limits</b>: your plan and quotas.</li></ul>
<h2>REST API</h2>
<pre><code>curl -X POST {base}/api/v1/codes \
  -H "Content-Type: application/json" \
  -d '{"target_url": "https://example.com", "style": {"shape": "dots", "emoji": "1f3a8"}}'</code></pre>
<p>OpenAPI description: <a href="{base}/api/v1/openapi.json">{base}/api/v1/openapi.json</a>. Images of any code: <code>{base}/qr/CODE.png</code> and <code>.svg</code>.</p>
<h2>Limits</h2>
<table class="data"><thead><tr><th>Plan</th><th>New codes / day</th><th>Requests / hour</th><th>Bulk per call</th></tr></thead><tbody>
<tr><td>No key</td><td>10</td><td>30</td><td>–</td></tr><tr><td>Free key</td><td>100</td><td>120</td><td>20</td></tr><tr><td>Pro (on request)</td><td>5,000</td><td>2,000</td><td>500</td></tr></tbody></table>
<p>Codes made through the API never expire, like all qr codes. No tracking cookies, no stored IPs.</p>
HTML],
            'de' => ['slug' => 'entwickler', 'nav' => 'KI & API',
                'title' => 'QR-Code MCP-Server und API für KI-Agenten',
                'description' => 'Kostenloser MCP-Server und REST-API: Lass Claude, ChatGPT, Cursor und andere KI-Agenten dynamische QR-Codes erstellen, die nie ablaufen. Ohne Schlüssel nutzbar.',
                'h1' => 'QR-Codes für KI-Agenten.',
                'html' => <<<'HTML'
<p class="lead">qr hat einen kostenlosen MCP-Server, damit KI-Assistenten QR-Codes für dich machen: „Mach mir einen QR-Code für meine Show-Seite mit einer Gitarre in der Mitte" funktioniert einfach. Dazu gibt es eine schlichte REST-API.</p>
<h2>KI-App verbinden</h2>
<p>Füg einen <b>Remote-MCP-Server</b> mit dieser URL hinzu. Er funktioniert ohne Schlüssel (anonym: bis zu 10 Codes pro Tag, jeweils mit privatem Bearbeitungslink für dich).</p>
<pre><code>{base}/mcp</code></pre>
<p><b>Claude (Desktop, Web):</b> Einstellungen → Connectors → Eigenen Connector hinzufügen → URL einfügen.<br><b>Claude Code:</b> <code>claude mcp add --transport http qr {base}/mcp</code><br><b>Cursor, VS Code und andere:</b> in die MCP-Einstellungen eintragen:</p>
<pre><code>{
  "mcpServers": {
    "qr": {
      "url": "{base}/mcp",
      "headers": { "Authorization": "Bearer DEIN_API_SCHLUESSEL" }
    }
  }
}</code></pre>
<p>Der <code>headers</code>-Teil ist optional. Mit einem gratis API-Schlüssel (in deinem Konto unter „API &amp; KI-Agenten“) kann der Assistent auch deine Codes auflisten, Ziele ändern, Statistiken zeigen und Codes in Massen erstellen.</p>
<h2>Werkzeuge</h2>
<ul><li><b>create_qr_code</b>: QR-Code für einen Link, optional mit Stil (Punkte, Ecken, Farben, Emoji). Liefert Bild, Kurzlink, PNG- und SVG-Links.</li><li><b>update_qr_code</b>: Ziel eines Codes ändern (gedruckte Codes funktionieren weiter).</li><li><b>get_qr_code_stats</b>: Scans, eindeutige Besucher, Städte, Geräte.</li><li><b>list_qr_codes</b>, <b>create_qr_codes_bulk</b> (mit API-Schlüssel).</li><li><b>get_limits</b>: dein Plan und deine Kontingente.</li></ul>
<h2>REST-API</h2>
<pre><code>curl -X POST {base}/api/v1/codes \
  -H "Content-Type: application/json" \
  -d '{"target_url": "https://example.com", "style": {"shape": "dots", "emoji": "1f3a8"}}'</code></pre>
<p>OpenAPI-Beschreibung: <a href="{base}/api/v1/openapi.json">{base}/api/v1/openapi.json</a>. Bilder jedes Codes: <code>{base}/qr/CODE.png</code> und <code>.svg</code>.</p>
<h2>Limits</h2>
<table class="data"><thead><tr><th>Plan</th><th>Neue Codes / Tag</th><th>Anfragen / Stunde</th><th>Massen pro Aufruf</th></tr></thead><tbody>
<tr><td>Ohne Schlüssel</td><td>10</td><td>30</td><td>–</td></tr><tr><td>Gratis-Schlüssel</td><td>100</td><td>120</td><td>20</td></tr><tr><td>Pro (auf Anfrage)</td><td>5.000</td><td>2.000</td><td>500</td></tr></tbody></table>
<p>Per API erstellte Codes laufen nie ab, wie alle qr-Codes. Keine Tracking-Cookies, keine gespeicherten IPs.</p>
HTML],
        ],
        'artists' => [
            'en' => ['slug' => 'qr-codes-for-artists', 'nav' => 'For artists',
                'title' => 'QR codes for artists, galleries, bands and exhibitions',
                'description' => 'Free changeable QR codes for wall labels, exhibition posters, flyers, merch and tickets. Styled codes, anonymous scan stats and hosted flyer pages. Made in Vienna.',
                'h1' => 'QR codes for the art scene.',
                'html' => '<p class="lead">Wall labels, exhibition posters, flyers, stickers, merch tables, zines: printed things that should lead somewhere online. With a changeable QR code, they keep working after the show changes.</p>
<h2>Exhibitions & galleries</h2>
<p>Put a code on each wall label that leads to the work\'s page, an audio guide or the artist\'s portfolio. After the exhibition, point the same labels to the catalogue or the next show.</p>
<h2>Bands, gigs & merch</h2>
<p>One code on every poster: first the ticket shop, after the gig the recording or your Bandcamp. Add a poll for the encore or a newsletter sign-up as a mini-app before the link.</p>
<h2>Off-spaces & collectives</h2>
<p>Make a campaign with one code per poster location and see which spot gets scanned most. No tracking cookies, no stored IP addresses: your audience stays anonymous.</p>
<h2>A code without a website</h2>
<p>No page yet? Give the code a hosted flyer page with an image, a text and buttons to tickets, Instagram or a map.</p>'],
            'de' => ['slug' => 'qr-codes-fuer-kuenstler', 'nav' => 'Für Künstler:innen',
                'title' => 'QR-Codes für Künstler:innen, Galerien, Bands und Ausstellungen',
                'description' => 'Kostenlose änderbare QR-Codes für Werkbeschriftungen, Ausstellungsplakate, Flyer, Merch und Tickets. Gestylte Codes, anonyme Scan-Statistik und Flyer-Seiten. Aus Wien.',
                'h1' => 'QR-Codes für die Kunstszene.',
                'html' => '<p class="lead">Werkbeschriftungen, Ausstellungsplakate, Flyer, Sticker, Merch-Tische, Zines: Gedrucktes, das online weiterführen soll. Mit einem änderbaren QR-Code funktioniert es auch noch, wenn sich die Show ändert.</p>
<h2>Ausstellungen & Galerien</h2>
<p>Ein Code auf jeder Werkbeschriftung, der zur Werkseite, zum Audioguide oder zum Portfolio führt. Nach der Ausstellung zeigen dieselben Schilder auf den Katalog oder die nächste Show.</p>
<h2>Bands, Gigs & Merch</h2>
<p>Ein Code auf jedem Plakat: zuerst zum Ticketshop, nach dem Gig zur Aufnahme oder zu Bandcamp. Davor als Mini-App eine Abstimmung über die Zugabe oder eine Newsletter-Anmeldung.</p>
<h2>Off-Spaces & Kollektive</h2>
<p>Mach eine Kampagne mit einem Code pro Plakatstandort und sieh, welcher Ort am meisten gescannt wird. Keine Tracking-Cookies, keine gespeicherten IP-Adressen: Dein Publikum bleibt anonym.</p>
<h2>Ein Code ohne Website</h2>
<p>Noch keine Seite? Gib dem Code eine gehostete Flyer-Seite mit Bild, Text und Buttons zu Tickets, Instagram oder einer Karte.</p>'],
        ],
    ];
}

/** [key, lang] for a URL slug, or null. */
function content_page_by_slug(string $lang, string $slug): ?string
{
    foreach (content_pages() as $key => $langs) {
        if (($langs[$lang]['slug'] ?? null) === $slug) return $key;
    }
    return null;
}
