# Nibras Academy — Arabic-first free online academy (Laravel)

A complete, installable PHP application for an online academy in Morocco. Every course is **free**. After completing a course, students request a **printed certificate through WhatsApp**, and staff track it until delivery. The academy name, logo and copy are configurable. "Nibras" (نبراس, *lamp*) is a placeholder brand.

- **Stack:** PHP 8.3+, Laravel 13, Blade, MySQL/MariaDB (SQLite for tests), plain CSS/JS (no Node.js needed in production)
- **Languages:** Arabic (default, RTL), French and English (LTR). Content translations fall back to Arabic.
- **Design:** an original identity (deep indigo, amber, ivory, dark footer), self-hosted Tajawal + Inter fonts (OFL), original SVG icons, logo and generated course artwork.

## Main workflow

1. An admin creates a course and uploads MP4 lesson videos directly from the dashboard (resumable chunked uploads, private storage).
2. A student registers and enrolls in any course for free.
3. The student watches lessons, completes required lessons and quizzes, and tracks progress.
4. "اطلب شهادتك عبر واتساب" (available at any time by default, or only after completion if the admin requires it) creates a request with a unique reference and opens WhatsApp with a prefilled Arabic message.
5. Staff confirm the printed name and address in WhatsApp, prepare and ship the certificate.
6. Staff update the status (بانتظار التواصل → تم الاستلام → قيد التجهيز → تم الشحن → تم التسليم), and the student sees the status and tracking number.
7. Printed certificates can be registered for public verification (code + QR link), and revoked if needed.

## Features

| Area | What's included |
|---|---|
| Public site | Homepage with manageable sections, catalog (search, category/level filters, sorting, pagination, URL-preserved filters, empty state), course pages (outcomes, prerequisites, audience, expandable curriculum, previews, reviews, related courses, wishlist, share, sticky mobile CTA) |
| Learning | Free enrollment, lesson player (video, text, quiz, downloadable exercises), next/previous, "إكمال الدرس", bookmarks, resume position, progress, quiz attempts, one editable review per course (moderated) |
| Accounts | Admin / instructor / student roles, registration, login (rate-limited), email verification, password reset, profile |
| Video | Chunked resumable MP4 upload with progress and Arabic errors, server-side MIME and container validation, automatic duration, replace-after-success, private storage, HTTP byte ranges, optional private S3 with signed URLs |
| Certificates | WhatsApp request with idempotent reference, status workflow, carrier/tracking/notes, student tracking view, verification registry (valid / missing / revoked), QR SVG |
| Content | Blog (categories, search, authors, related), library (public or enrollment-restricted downloads), static pages, testimonials, partners |
| Tools | Code editor (sandboxed iframe), CV builder, invoice generator, generic certificate designer, quiz builder, tool suggestion form with moderation |
| AI (optional) | Quiz generation from pasted lesson text with the Anthropic API, only when a key is configured, with explicit consent, strict response validation and editable output |
| Store (optional) | Disabled by default. Digital downloads, separate checkout, manual bank transfer in MAD, admin confirmation |
| Admin | Arabic RTL dashboard: branding, contact, WhatsApp template, social links, navigation links, feature switches, homepage sections, users/roles/instructor assignments, courses, curriculum ordering, enrollments/progress, quizzes, reviews, blog, library, pages, tools, messages, store, certificate requests, verification, audit log |
| Security | CSRF, hashed passwords, policies and gates, server-side permissions, HTML sanitization (Symfony HtmlSanitizer), escaped output, validated uploads, rate limits (login, forms, verification, uploads, AI), unique constraints and transactions for enrollments and certificate requests, private media routes, security headers |
| SEO and accessibility | Titles, descriptions, canonical URLs, Open Graph, `robots.txt`, XML sitemap of published content only, skip link, focus styles, labeled forms, keyboard dropdowns, alt text |

## Quick start (local)

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

For a quick local trial, set `DB_CONNECTION=sqlite` in `.env` and create the database file:

```bash
touch database/database.sqlite
```

```bash
php artisan migrate --seed
```

```bash
php artisan storage:link && php artisan academy:create-admin
```

```bash
php artisan serve
```

Open http://127.0.0.1:8000 for the site and `/admin` for the dashboard. The demo seeder prints two demo logins (`demo.instructor@example.com`, `demo.student@example.com`). Their password is random unless `DEMO_PASSWORD` is set.

## Production

**Easiest (cPanel, no Terminal):** upload `academy-deploy.zip` into `public_html`, extract it, open your domain and fill the one-page installer (no `/public` in the address, no Document Root change). See [docs/CPANEL.md](docs/CPANEL.md).

- [docs/INSTALL.md](docs/INSTALL.md): Apache/Nginx, extensions, writable folders, `/public` document root, cron, large uploads, S3
- [docs/CPANEL.md](docs/CPANEL.md): shared hosting
- [docs/VIDEO.md](docs/VIDEO.md): encoding (H.264/AAC), upload and playback details
- [docs/ADMIN-GUIDE.ar.md](docs/ADMIN-GUIDE.ar.md): admin guide in Arabic

## Useful commands

| Command | Purpose |
|---|---|
| `php artisan academy:install [--demo]` | Requirements check, migrate, storage link, base data, first admin |
| `php artisan academy:create-admin` | Create or promote an administrator (interactive) |
| `php artisan db:seed --class=DemoSeeder` | Demo courses (AR/EN/FR), lessons with a demo video, quizzes, articles, resources, all flagged as demo |
| `php artisan academy:prune-uploads` | Remove abandoned partial uploads (scheduled daily) |
| `php artisan test` | Run the feature test suite |

## Project layout

```
app/
  Enums/            CertificateStatus, Role, Level
  Http/Controllers/ public, Auth/ and Admin/ controllers
  Http/Middleware/  locale, staff/admin guards, feature switches, security headers
  Models/           Eloquent models (translations via HasTranslations)
  Services/         Progress/eligibility, certificate requests, media, MP4 inspector, access rules, AI quiz generator
  Support/          Settings store, HTML sanitizer, audit log, cover art generator
database/
  migrations/       schema with unique constraints
  seeders/          BaseSeeder (required data), DemoSeeder (+ assets/demo-lesson.mp4)
lang/               fr.json, en.json, ar.json + ar/ fr/ validation messages
public/             css/app.css, css/admin.css, js/app.js, js/admin.js, fonts/
resources/views/    Blade templates (public, auth, learn, dashboard, tools, admin)
deploy/             nginx, Apache and php.ini examples
tests/Feature/      end-to-end feature tests
```

## Notes

- Content and UI strings ship in Arabic, French and English. Review them before launch, especially the sample privacy policy and terms.
- Demo testimonials are seeded **unapproved** and never appear publicly until replaced with real, consented ones. Partner logos and accreditation text appear only when an admin adds and enables them.
- Statistics on the homepage are counted from the database.
- Fonts: Tajawal and Inter, SIL Open Font License (see `public/fonts/`).
