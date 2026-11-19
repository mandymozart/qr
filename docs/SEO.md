# SEO & reach: what's built, what's left for you

## Built into the site

| What | Where |
|---|---|
| Title, description, canonical, `hreflang` (de/en/x-default) per public page | `lib/seo.php`, `public_head()` |
| Share previews (Open Graph / Twitter) with a pop-art image | `app/assets/og-de.png`, `og-en.png` |
| `robots.txt` (keeps `/app/`, edit links and mini-app pages out), `sitemap.xml`, `llms.txt` for AI assistants | `seo-files.php` via `.htaccess` |
| schema.org: `WebApplication` (price 0) on the landing page, `FAQPage` on the FAQ | `lib/seo.php`, `page.php` |
| Content pages in DE + EN with clean URLs, linked in the footer | `lib/pages.php` → `/en/faq`, `/de/faq`, `/en/qr-codes-that-never-expire`, `/de/qr-codes-die-nicht-ablaufen`, `/en/qr-codes-for-artists`, `/de/qr-codes-fuer-kuenstler` |
| Everything else stays `noindex` (app, guest edit pages, mini-app pages, 404s) | `seo_tags()` |

Add more content pages by adding an entry to `content_pages()` in `lib/pages.php` (both languages). They appear in the footer, sitemap and `llms.txt` automatically.

## Your checklist (off-site, takes an afternoon)

1. **Google Search Console**: add the property, verify (DNS TXT record in KAS), submit `https://qr.viennastruggle.at/sitemap.xml`.
2. **Bing Webmaster Tools**: same; you can import from Search Console. Bing feeds ChatGPT search and Copilot.
3. **Decide the name/domain before building many backlinks.** If you move to an own domain later, keep `qr.viennastruggle.at` running forever (printed codes) and 301-redirect the content pages.
4. **Listings**: Product Hunt launch, AlternativeTo (as an alternative to QR Code Generator Pro / QR Code Monkey / Bitly), Austrian/German startup and tool directories.
5. **Reddit & forums**: answer real questions ("my QR code stopped working", "free dynamic QR code without subscription") in r/graphic_design, r/smallbusiness, r/Austria, r/de. Be a person, not an ad.
6. **Scene partners**: art schools (Angewandte, Akademie, Kunstuni Linz), off-spaces, festivals (Vienna Art Week, Parallel, curated by), venues. Ask them to link the tool in their artist/exhibitor info.
7. **Press angle**: "Vienna-made QR tool against subscription traps, privacy-friendly", for culture and tech media (Der Standard / Futurezone, Falter, scene newsletters).
8. **Instagram**: the story images in `social/`; later a recurring "before/after" format (same poster, new target).

## Measure

- Search Console: impressions/clicks for "qr code never expire", "dynamischer qr code kostenlos", "qr code ausstellung", …
- Inside the app: Stats → by placement/campaign for your own promo codes.

## Later ideas

- More content pages (one per use case: gigs, menus, stickers, business cards, Swiss German).
- Public API + MCP server so AI agents can create codes directly (listed in MCP directories).
