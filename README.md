# qr

Tiny QR code generator and link tracker. Plain PHP + MariaDB, no framework.

- `https://qr.viennastruggle.at/?c=CODE` → logs the scan, redirects to the target
- `https://qr.viennastruggle.at/app/` → the tool

## Routes

| Route | What |
|---|---|
| `/` | Landing page: create a QR code without an account (guest tier) |
| `/?c=CODE` | Public redirect (bots/link previews are logged but not counted); unknown codes get a "not found" page with a call to action |
| `/manage.php?k=KEY` / `?id=` | Guest code: edit target, pause, delete, downloads, basic stats (secret edit link, or the browser that made it) |
| `/app/register.php` | Sign up with email; guest codes from that browser move into the account on sign-in |
| `/app/guests.php` | Admin: all guest codes, pause/delete abuse |
| `/app/` | Your codes: list, search, campaign filter, 14-day sparklines |
| `/app/edit.php[?id=]` | Create / edit a code |
| `/app/code.php?id=` | Code detail: QR (SVG/PNG), pause, delete, scan stats |
| `/app/campaigns.php` | Campaigns + bulk create (up to 500 codes at once) |
| `/app/campaign.php?id=` | Campaign: QR grid, zip download (SVG + PNG + CSV), retarget all |
| `/app/stats.php` | Overall stats: by campaign, top codes, hour of day, devices, recent scans, CSV export |
| `/app/account.php` | Name, set/change/remove password |
| `/app/users.php` | Admin: add users/admins, send sign-in link, block, delete |
| `/app/login.php`, `/app/auth.php` | Password or secret-link sign-in, forgot password |

## Auth

- Anyone can sign up at `/app/register.php` (secret link by mail). Admins can also add users and other admins under **Users**.
- Guests (no account) can make up to 10 codes per browser, 5 per hour. Their codes are tied to a browser cookie `qr_g` and a secret edit link; signing in moves them into the account.
- Sign in with a password (optional, set under Account) or a one-time secret link by mail (30 min).
- "Forgot password?" mails a link that opens the Account page to set a new one.
- **Master admin** = `ADMIN_USER` secret (an email). Created on first run, can't be blocked/demoted/deleted, and can always sign in with `ADMIN_PASSWORD`.
- Mail goes through PHPMailer using PHP `mail()` by default. Set `SMTP_HOST` (+ `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`) to use SMTP. If a mail to a user fails, admins see the link to pass on manually.

## Deploy

Push to `main` → `.github/workflows/deploy.yml` installs Composer deps, writes `lib/config.php` from the **production** environment and uploads via FTP. Tables are created automatically on the first request.

Environment `production`:

| Kind | Name | Notes |
|---|---|---|
| secret | `FTP_PASSWORD`, `DB_USERNAME`, `DB_PASS`, `ADMIN_USER`, `ADMIN_PASSWORD` | required |
| var | `FTP_SERVER`, `FTP_USER`, `DB_NAME` | required |
| var | `DB_HOST` | default `localhost` |
| var | `BASE_URL` | default `https://qr.viennastruggle.at` |
| var | `FTP_DIR` | default `./` (FTP user's root = web root of the subdomain) |
| var/secret | `MAIL_FROM`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER` / secret `SMTP_PASS` | optional |

## Local dev

```sh
composer install
cp lib/config.sample.php lib/config.php   # point it at a MariaDB
php -S localhost:8088 -t .
```

`drafts/index.html` holds the design drafts (styles adapted from the akin dashboard). Not deployed.

## Credits

- QR rendering and decoding: [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) (MIT)
- Emoji in the middle of codes: [Twemoji](https://github.com/jdecked/twemoji) graphics, CC-BY 4.0 (`app/assets/emoji/`)
- IP geolocation: [DB-IP](https://db-ip.com) Lite, CC-BY 4.0
