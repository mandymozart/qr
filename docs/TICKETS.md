# Plan: selling tickets through a QR code

Scan → ticket page → pay → ticket arrives by mail with its own QR code → checked in at the door with a phone.
Built as a new mini-app type `ticket`, next to page / form / poll / info.

## Money flow: Stripe Connect (Express), direct charges

- Each artist or organiser connects **their own** Stripe account once (Stripe's hosted onboarding does ID checks, bank details and payouts).
- Buyers pay the organiser directly. qr never holds money, which keeps us out of payment-licence territory (in AT/EU, holding other people's money for them needs a licence).
- Refunds, chargebacks, invoices and payouts all go through the organiser's Stripe dashboard.
- Our cut is `application_fee_amount` per order: that's the money knob, e.g. 0 % on Pro and 2 % on free. Stripe's own fee comes on top (EU cards about 1.5 % + €0.25).
- Rejected alternative, one Stripe account (yours) that collects everything and pays out: more work, legal risk, and you become the seller.

## What gets built

| # | Piece | Notes |
|---|---|---|
| 1 | **Ticket step** in the mini-app editor | Event name, date, venue, ticket types (name, price, quota), sale end. Account holders only, not guest codes. |
| 2 | **Connect onboarding** in Account → "Payments" | "Connect Stripe" button → Stripe-hosted onboarding → back to us. Status shown. |
| 3 | **Ticket page** (`step.php`, type ticket) | Pick type + quantity, email → Stripe Checkout (hosted: cards, Apple/Google Pay, EPS, Klarna, no card data on our server). Shows "sold out" / "sale ended". |
| 4 | **Webhook** `stripe-webhook.php` | `checkout.session.completed` → order + tickets, mail with a QR code per ticket (our own generator). `charge.refunded` → ticket void. Signature check. |
| 5 | **Check-in** `/app/checkin.php` | Phone camera scans the ticket QR (BarcodeDetector, jsQR fallback) → big green / red, "already used at 21:14". Manual code entry too. Shareable door link for helpers without an account. |
| 6 | **Sales view** on the code page | Sold / left per type, revenue, buyer list, CSV export. |
| 7 | **Quotas** | Pending Checkout sessions (expire after 30 min) count toward the quota so nothing is oversold. |

## Data (schema v8)

- `users`: `stripe_account_id`, `stripe_ready`
- `orders`: step_id, stripe_session_id, email, amount, fee, currency, status (pending / paid / refunded), created_at
- `tickets`: order_id, type name, token (random, in the ticket QR), used_at
- Buyer data is deleted 12 months after the event; PRIVACY.md and the privacy page get a payments section.

## Config

- New secrets: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` (+ publishable key) → GitHub production environment → `lib/config.php`.
- Composer: `stripe/stripe-php`.
- Local testing in Stripe test mode with `stripe listen --forward-to localhost:8088/stripe-webhook.php`.

## Order of work

1. Test mode end to end: step editor, Connect onboarding, Checkout, webhook, ticket mail (2–3 sessions).
2. Check-in scanner + sales view (1 session).
3. Live keys, privacy text, a real test sale with a small amount.

## What you need to do

- Create a Stripe account for qr, enable **Connect** (platform profile: "marketplace / platform", Express accounts).
- Decide the platform fee.
- Organisers are the sellers: their name and terms of sale appear on the ticket page; we're the platform (have a lawyer glance at that once, not legal advice from me).

## Later: QAR

AR scene as a further mini-app type (`ar`): scan → WebXR / model-viewer scene from osct/scutoons. Same step system, no changes to the redirect.
