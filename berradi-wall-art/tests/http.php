<?php

declare(strict_types=1);

/*
 * HTTP end-to-end tests against a running instance on a FRESH (empty) database:
 * walks the 10-step installer, then exercises storefront, auth, RBAC, settings and security behaviour.
 *
 *   php -S 127.0.0.1:8099 -t public tests/server-router.php   (with no .env / installed.lock)
 *   BWA_HTTP_URL=http://127.0.0.1:8099 BWA_TEST_DB_DATABASE=bwa_install BWA_TEST_DB_USERNAME=u BWA_TEST_DB_PASSWORD=p php tests/http.php
 */

require __DIR__ . '/TestRunner.php';

final class Browser
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    /** @var array<string,string> */
    public array $headers = [];

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'bwajar');
    }

    /** @param array<string,mixed>|null $post */
    public function request(string $method, string $path, ?array $post = null, array $files = [], array $extraHeaders = []): self
    {
        $ch = curl_init(str_starts_with($path, 'http') ? $path : $this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'BWA-HTTP-Tests',
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $extraHeaders,
        ]);
        if ($post !== null) {
            $fields = $post;
            foreach ($files as $k => $f) {
                $fields[$k] = new CURLFile($f[0], $f[1], $f[2]);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $fields : http_build_query($fields));
        }
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $this->headers = [];
        foreach (explode("\r\n", substr($raw, 0, $hsize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->headers[strtolower(trim($k))] = trim($v);
            }
        }
        $this->body = substr($raw, $hsize);
        return $this;
    }

    /** POST a JSON body (AJAX style) with the CSRF header. @param array<string,mixed> $data */
    public function json(string $path, array $data, ?string $token = null): self
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => (string) json_encode($data), CURLOPT_USERAGENT => 'BWA-HTTP-Tests', CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: ' . ($token ?? $this->token())],
        ]);
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $this->body = substr($raw, $hsize);
        return $this;
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        return (array) json_decode($this->body, true);
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    /** POST with the CSRF token taken from the last page (or from $tokenPage). */
    public function post(string $path, array $data = [], ?string $tokenPage = null, array $files = []): self
    {
        if (!array_key_exists('_token', $data)) {
            if ($tokenPage !== null) {
                $this->get($tokenPage);
            }
            $data['_token'] = $this->token();
        }
        // a browser always sends the page the form came from (validation errors redirect back to it)
        $headers = $tokenPage !== null ? ['Referer: ' . $this->base . $tokenPage] : [];
        return $this->request('POST', $path, $data, $files, $headers);
    }

    public function token(): string
    {
        preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $this->body, $m);
        return $m[1] ?? '';
    }

    public function location(): string
    {
        $loc = $this->headers['location'] ?? '';
        return (string) preg_replace('#^https?://[^/]+#', '', $loc);
    }

    public function follow(): self
    {
        $i = 0;
        while (in_array($this->status, [301, 302, 303], true) && $i++ < 5) {
            $this->get($this->location());
        }
        return $this;
    }
}

$base = rtrim((string) (getenv('BWA_HTTP_URL') ?: 'http://127.0.0.1:8099'), '/');
$dbName = (string) getenv('BWA_TEST_DB_DATABASE');
if ($dbName === '') {
    fwrite(STDERR, "BWA_TEST_DB_DATABASE is required (fresh database used by the installer)\n");
    exit(2);
}
$db = [
    'host' => getenv('BWA_TEST_DB_HOST') ?: 'localhost',
    'port' => (string) (getenv('BWA_TEST_DB_PORT') ?: '3306'),
    'name' => $dbName,
    'user' => getenv('BWA_TEST_DB_USERNAME') ?: 'root',
    'pass' => getenv('BWA_TEST_DB_PASSWORD') ?: '',
];
$t = new TestRunner();
$b = new Browser($base);
$adminPass = 'Admin2026!x';

$t->section('Installer (fresh database) — ' . $base);
$t->test('every page redirects to the installer before installation', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals(302, $b->status);
    $t->equals('/install', $b->location());
    $b->get('/admin/login');
    $t->equals('/install', $b->location());
});
$t->test('step 1 welcome → choose Arabic → RTL installer', function (TestRunner $t) use ($b): void {
    $b->get('/install')->follow();
    $t->equals(200, $b->status);
    $t->contains('Assistant d’installation', $b->body);
    $b->post('/install/1', ['locale' => 'ar']);
    $t->equals('/install/2', $b->location());
    $b->get('/install/2');
    $t->contains('dir="rtl"', $b->body);
    $t->contains('bootstrap.rtl.min.css', $b->body);
    $b->post('/install/1', ['locale' => 'fr'], '/install/1');
});
$t->test('cannot skip ahead to later steps', function (TestRunner $t) use ($b): void {
    $b->get('/install/8');
    $t->equals(302, $b->status);
    $t->equals('/install/2', $b->location());
});
$t->test('steps 2-3 server, extensions & permission checks pass', function (TestRunner $t) use ($b): void {
    $b->get('/install/2');
    $t->contains(PHP_VERSION, $b->body, 'real PHP version shown');
    $b->post('/install/2');
    $t->equals('/install/3', $b->location());
    $b->get('/install/3');
    $t->contains('pdo_mysql', $b->body);
    $b->post('/install/3');
    $t->equals('/install/4', $b->location());
});
$t->test('step 4-5 wrong DB credentials are reported, then the correct ones pass', function (TestRunner $t) use ($b, $db, $base): void {
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => 'wrong-password', 'app_url' => $base], '/install/4');
    $b->get('/install/5');
    $t->contains('Connexion impossible', $b->body);
    $t->assert(!str_contains($b->body, 'wrong-password'), 'password never echoed');
    $b->post('/install/5');
    $t->equals('/install/5', $b->location(), 'cannot continue');
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => $db['pass'], 'app_url' => $base], '/install/4');
    $b->get('/install/5');
    $t->contains('Connexion réussie', $b->body);
    $b->post('/install/5');
    $t->equals('/install/6', $b->location());
});
$t->test('step 4 validation errors keep the user on the form', function (TestRunner $t) use ($b): void {
    $b->post('/install/4', ['db_host' => 'bad host!', 'db_port' => 'abc', 'db_database' => '', 'db_username' => '', 'app_url' => 'nope'], '/install/4');
    $t->equals(302, $b->status);
    $b->get('/install/4');
    $t->contains('is-invalid', $b->body);
});
$t->test('step 6 creates .env, tables and seed data', function (TestRunner $t) use ($b, $db, $base): void {
    // restore valid credentials after the validation test
    $b->post('/install/4', ['db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['name'], 'db_username' => $db['user'], 'db_password' => $db['pass'], 'app_url' => $base], '/install/4');
    $b->post('/install/5', [], '/install/5');
    $b->post('/install/6', [], '/install/6');
    $t->equals('/install/6', $b->location());
    $b->get('/install/6');
    $t->contains('Migrated: 2026_01_01_000008_marketing_content', $b->body);
    $t->contains('Seeded: 06_print_options', $b->body);
    $t->contains('/install/7', $b->body);
});
$t->test('step 7 company settings with logo upload', function (TestRunner $t) use ($b): void {
    $img = imagecreatetruecolor(300, 120);
    imagefill($img, 0, 0, imagecolorallocate($img, 23, 23, 23));
    $logo = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    imagepng($img, $logo);
    $b->post('/install/7', [
        'company_name' => 'BERRADI WALL ART', 'company_email' => 'contact@berradi.test', 'company_phone' => '+212 5 22 00 00 00',
        'company_whatsapp' => '+212 6 61 00 00 00', 'company_address' => '12 Rue Exemple', 'company_city' => 'Casablanca',
        'currency_code' => 'MAD', 'currency_symbol' => 'DH', 'default_language' => 'fr', 'tax_default_rate' => '20',
        'invoice_prefix' => 'FAC', 'quotation_prefix' => 'DEV', 'order_prefix' => 'BWA', 'shipping_flat_rate' => '40.00', 'shipping_free_threshold' => '1000.00',
    ], '/install/7', ['company_logo' => [$logo, 'image/png', 'logo.png']]);
    $t->equals('/install/8', $b->location());
});
$t->test('step 8 rejects weak password, then creates the super admin', function (TestRunner $t) use ($b, $adminPass): void {
    $b->post('/install/8', ['name' => 'Owner', 'email' => 'owner@berradi.test', 'password' => 'short', 'password_confirmation' => 'short'], '/install/8');
    $t->equals('/install/8', $b->location());
    $b->post('/install/8', ['name' => 'Owner Berradi', 'email' => 'owner@berradi.test', 'password' => $adminPass, 'password_confirmation' => $adminPass], '/install/8');
    $t->equals('/install/9', $b->location());
});
$t->test('step 9-10 finish (with demo catalogue) locks the installer permanently', function (TestRunner $t) use ($b): void {
    $b->get('/install/9');
    $t->contains('cron:run', $b->body);
    $t->contains('name="install_demo"', $b->body);
    $b->post('/install/9', ['install_demo' => '1']);
    $t->equals(200, $b->status);
    $t->contains('verrouillé de façon permanente', $b->body);
    $t->contains('19 produits', $b->body, 'demo installed');
    $b->get('/install/1');
    $t->equals(404, $b->status, 'installer locked');
    $b->get('/install');
    $t->equals(404, $b->status);
});

$t->section('Storefront, languages & security headers');
$t->test('home page renders in French (LTR) with real categories', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals(200, $b->status);
    $t->contains('<html lang="fr" dir="ltr">', $b->body);
    $t->contains('Transformez vos souvenirs', $b->body);
    $t->contains('Calligraphie arabe', $b->body);
    $t->contains('wa.me/212661000000', $b->body, 'WhatsApp click-to-chat');
    $t->contains('/uploads/branding/', $b->body, 'uploaded logo used');
});
$t->test('language switch to Arabic gives RTL layout and Arabic content', function (TestRunner $t) use ($b): void {
    $b->get('/lang/ar');
    $t->equals(302, $b->status);
    $b->get('/');
    $t->contains('<html lang="ar" dir="rtl">', $b->body);
    $t->contains('حوّل ذكرياتك', $b->body);
    $t->contains('الخط العربي', $b->body);
    $t->contains('bootstrap.rtl.min.css', $b->body);
    $b->get('/lang/fr');
});
$t->test('security headers are sent', function (TestRunner $t) use ($b): void {
    $b->get('/');
    $t->equals('nosniff', $b->headers['x-content-type-options'] ?? '');
    $t->equals('SAMEORIGIN', $b->headers['x-frame-options'] ?? '');
    $t->contains("script-src 'self'", $b->headers['content-security-policy'] ?? '');
    $t->equals('strict-origin-when-cross-origin', $b->headers['referrer-policy'] ?? '');
});
$t->test('session cookie is HttpOnly + SameSite', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->get('/');
    $cookie = $c->headers['set-cookie'] ?? '';
    $t->contains('bwa_session=', $cookie);
    $t->contains('HttpOnly', $cookie);
    $t->contains('SameSite=Lax', $cookie);
});
$t->test('404 page is branded and non-existent routes return 404', function (TestRunner $t) use ($b): void {
    $b->get('/this-page-does-not-exist');
    $t->equals(404, $b->status);
    $t->contains('Page introuvable', $b->body);
});
$t->test('POST without CSRF token is refused (419)', function (TestRunner $t) use ($b): void {
    $b->request('POST', '/account/login', ['email' => 'x@y.ma', 'password' => 'x', '_token' => 'forged']);
    $t->equals(419, $b->status);
});
$t->test('private folders and dotfiles are not served', function (TestRunner $t) use ($b): void {
    $b->get('/.env');
    $t->assert(in_array($b->status, [403, 404], true), '.env status ' . $b->status);
    $t->assert(!str_contains($b->body, 'DB_PASSWORD'));
    $b->get('/../.env');
    $t->assert(!str_contains($b->body, 'DB_PASSWORD'));
});

$t->section('Catalogue storefront (Phase 2)');
$t->test('homepage shows real catalogue sections and JSON-LD', function (TestRunner $t) use ($b): void {
    $b->get('/');
    foreach (['Nos collections', 'Meilleures ventes', 'Nouveautés', 'Offres spéciales', 'Comment ça marche', 'Questions fréquentes', 'Inspirations &amp; avant-premières', 'Héritage Marocain', '/product/arches-de-marrakech'] as $needle) {
        $t->contains($needle, $b->body);
    }
    $t->assert(!str_contains($b->body, 'Avis clients'), 'no review section without real reviews');
    $t->assert(!str_contains($b->body, 'Nos œuvres installées'), 'no gallery section without real installations');
    $t->contains('"@type":"Organization"', $b->body);
    $t->contains('<link rel="canonical"', $b->body);
    $t->contains('property="og:title"', $b->body);
});
$t->test('shop listing, filters, sorting, search and pagination', function (TestRunner $t) use ($b): void {
    $b->get('/shop');
    $t->equals(200, $b->status);
    $t->contains('19 œuvre(s)', $b->body);
    $t->equals(19, substr_count($b->body, '<article class="product-card">'), 'all 19 cards on one page (24 per page)');
    $b->get('/shop?q=dunes');
    $t->contains('2 œuvre(s)', $b->body);
    $t->contains('noindex', $b->body, 'filtered pages not indexed');
    $b->get('/shop?type=framed&sort=price_desc');
    $t->contains('3 œuvre(s)', $b->body);
    $b->get('/shop?min=abc&max=%27%20OR%201=1&sort=%3Cscript%3E&type=zzz');
    $t->equals(200, $b->status, 'garbage filters are ignored safely');
    $t->assert(!str_contains($b->body, '<script>'), 'no reflected script');
    $b->get('/shop?q=%3Cscript%3Ealert(1)%3C/script%3E');
    $t->assert(!str_contains($b->body, '<script>alert(1)</script>'), 'search term escaped');
    $t->contains('&lt;script&gt;', $b->body);
});
$t->test('category, best sellers, new arrivals, offers and collections pages', function (TestRunner $t) use ($b): void {
    foreach (['/shop/category/nature-landscapes' => 'Nature &amp; Paysages', '/best-sellers' => 'Meilleures ventes', '/new-arrivals' => 'Nouveautés', '/offers' => 'Offres spéciales',
        '/collections' => 'Lumière d’Atlas', '/collections/noir-et-or' => 'Édition limitée'] as $url => $needle) {
        $b->get($url);
        $t->equals(200, $b->status, $url);
        $t->contains($needle, $b->body, $url);
        $t->contains('"@type":"BreadcrumbList"', $b->body, $url);
    }
    $b->get('/shop/category/unknown-cat');
    $t->equals(404, $b->status);
    $b->get('/collections/unknown');
    $t->equals(404, $b->status);
});
$t->test('product page: variants, price, structured data, WhatsApp order', function (TestRunner $t) use ($b): void {
    $b->get('/product/vallee-du-dades');
    $t->equals(200, $b->status);
    $t->contains('<h1 class="product-name mt-2">Vallée du Dadès</h1>', $b->body);
    $t->contains('"@type":"Product"', $b->body);
    $t->contains('"@type":"AggregateOffer"', $b->body);
    $t->contains('"priceCurrency":"MAD"', $b->body);
    $t->contains('data-variants=', $b->body);
    $t->contains('Cadre doré', $b->body);
    $t->contains('wa.me/212661000000?text=', $b->body);
    $t->contains('Vous aimerez aussi', $b->body);
    $t->contains('og:type" content="product"', $b->body);
    $b->get('/product/does-not-exist');
    $t->equals(404, $b->status);
});
$t->test('product page in Arabic (RTL, translated content)', function (TestRunner $t) use ($base): void {
    $a = new Browser($base);
    $a->get('/lang/ar');
    $a->get('/product/zellige-de-fes');
    $t->contains('dir="rtl"', $a->body);
    $t->contains('زليج فاس', $a->body);
    $t->contains('الفن الإسلامي', $a->body);
    $a->get('/shop?q=' . rawurlencode('شفشاون'));
    $t->contains('الباب الأزرق بشفشاون', $a->body);
});
$t->test('about and FAQ pages (FAQPage schema)', function (TestRunner $t) use ($b): void {
    $b->get('/about');
    $t->equals(200, $b->status);
    $t->contains('L’art de transformer vos souvenirs', $b->body);
    $b->get('/faq');
    $t->contains('"@type":"FAQPage"', $b->body);
    $t->contains('Comment accrocher mon tableau', $b->body);
});
$t->test('contact form: validation, honeypot, stored + e-mailed', function (TestRunner $t) use ($b, $db): void {
    $b->post('/contact', ['name' => '', 'email' => 'x', 'subject' => 'hack', 'message' => 'short'], '/contact');
    $b->get('/contact');
    $t->contains('is-invalid', $b->body);
    $b->post('/contact', ['name' => 'Bot', 'email' => 'bot@spam.test', 'subject' => 'other', 'message' => 'Buy cheap things now!!!', 'website' => 'http://spam'], '/contact');
    $t->equals('/contact', $b->location());
    $b->post('/contact', ['name' => 'Karim Test', 'email' => 'karim@client.test', 'phone' => '0611223344', 'subject' => 'b2b', 'message' => 'Bonjour, nous équipons un hôtel de 40 chambres.'], '/contact');
    $b->follow();
    $t->contains('Votre message a bien été envoyé', $b->body);
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
    $t->equals(1, (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE event = 'contact_message'")->fetchColumn(), 'honeypot message not stored, real one stored');
    $t->contains('40 chambres', (string) $pdo->query("SELECT body FROM notifications WHERE event = 'contact_message'")->fetchColumn());
});
$t->test('newsletter: consent required, JSON for AJAX, one-click unsubscribe', function (TestRunner $t) use ($b, $db): void {
    $b->get('/');
    $token = $b->token();
    $b->request('POST', '/newsletter', ['email' => 'fan@client.test', '_token' => $token], [], ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']);
    $t->equals(422, $b->status, 'consent missing');
    $b->request('POST', '/newsletter', ['email' => 'fan@client.test', 'consent' => '1', '_token' => $token], [], ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']);
    $t->equals(200, $b->status);
    $t->contains('"ok":true', $b->body);
    $b->request('POST', '/newsletter', ['email' => 'FAN@client.test', 'consent' => '1', '_token' => $token], [], ['Accept: application/json']);
    $t->contains('"ok":true', $b->body, 'same answer for known address');
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
    $t->equals(1, (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE email = 'fan@client.test'")->fetchColumn());
    $tok = (string) $pdo->query("SELECT unsubscribe_token FROM newsletter_subscribers WHERE email = 'fan@client.test'")->fetchColumn();
    $b->get('/newsletter/unsubscribe/' . $tok);
    $t->equals(200, $b->status);
    $t->contains('Désinscription confirmée', $b->body);
    $t->equals('unsubscribed', (string) $pdo->query("SELECT status FROM newsletter_subscribers WHERE email = 'fan@client.test'")->fetchColumn());
    $b->get('/newsletter/unsubscribe/' . str_repeat('0', 64));
    $t->equals(404, $b->status);
});

$t->section('Custom Wall Art Builder (Phase 3)');
$mkJpeg = function (int $w, int $h): string {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 60, 110, 170));
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($w, 2), imagecolorallocate($im, 240, 210, 140));
    $f = tempnam(sys_get_temp_dir(), 'bwaimg') . '.jpg';
    imagejpeg($im, $f, 90);
    return $f;
};
$guest = new Browser($base);
$state = [];
$t->test('builder page renders with JSON config (FR & AR)', function (TestRunner $t) use ($guest, $base): void {
    $guest->get('/custom-wall-art');
    $t->equals(200, $guest->status);
    $t->contains('id="builder-config"', $guest->body);
    $t->contains('cropper.min.js', $guest->body);
    $t->contains('bwa_owner=', $guest->headers['set-cookie'] ?? '', 'ownership cookie');
    preg_match('#<script type="application/json" id="builder-config">(.*?)</script>#s', $guest->body, $m);
    $cfg = json_decode($m[1] ?? '', true);
    $t->assert(is_array($cfg) && count($cfg['materials']) === 5 && count($cfg['frames']) === 6, 'materials & frames');
    $t->assert(count($cfg['layouts']) === 5 && isset($cfg['i18n']['save']), 'layouts + i18n');
    $a = new Browser($base);
    $a->get('/lang/ar');
    $a->get('/custom-wall-art?product=toile-photo-personnalisee');
    $t->contains('dir="rtl"', $a->body);
    $t->contains('لوحة كانفاس بصورتك', $a->body, 'product title translated');
    $t->contains('كانفاس مشدود', $a->body, 'material description translated');
});
$t->test('price API: server-side pricing and validation', function (TestRunner $t) use ($guest): void {
    $guest->get('/custom-wall-art');
    $tok = $guest->token();
    preg_match('#"materials":\[\{"id":(\d+),"code":"canvas"#', $guest->body, $m);
    $guest->json('/builder/price', ['material_id' => (int) ($m[1] ?? 0), 'layout' => 'triptych', 'panel_width_cm' => 40, 'panel_height_cm' => 30, 'border' => 'wrap', 'quantity' => 2], $tok);
    $t->equals(200, $guest->status);
    $t->equals('687.00', $guest->data()['unit_price'] ?? null);
    $t->equals('1374.00', $guest->data()['total'] ?? null);
    $guest->json('/builder/price', ['material_id' => (int) ($m[1] ?? 0), 'panel_width_cm' => 999, 'panel_height_cm' => 30], $tok);
    $t->equals(422, $guest->status);
    $guest->request('POST', '/builder/price', ['material_id' => 1], [], ['Accept: application/json']);
    $t->equals(419, $guest->status, 'CSRF required');
});
$t->test('photo upload: stored privately, served only to its owner', function (TestRunner $t) use ($guest, $base, $mkJpeg, &$state): void {
    $guest->get('/custom-wall-art');
    $guest->request('POST', '/builder/upload', ['_token' => $guest->token()], ['photo' => [$mkJpeg(2400, 1600), 'image/jpeg', 'souvenir.jpg']], ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']);
    $t->equals(200, $guest->status, $guest->body);
    $img = $guest->data()['image'] ?? [];
    $t->equals(2400, $img['width'] ?? null);
    $state['uuid'] = $img['uuid'] ?? '';
    $guest->get('/builder/image/' . $state['uuid'] . '/preview');
    $t->equals(200, $guest->status);
    $t->equals('image/jpeg', $guest->headers['content-type'] ?? '');
    $t->contains('no-store', $guest->headers['cache-control'] ?? '');
    $guest->get('/builder/image/' . $state['uuid'] . '/original');
    $t->equals(403, $guest->status, 'originals only for the workshop');
    $other = new Browser($base);
    $other->get('/builder/image/' . $state['uuid'] . '/preview');
    $t->equals(404, $other->status, 'other visitor');
    $other->get('/builder/image/../../.env/preview');
    $t->assert(in_array($other->status, [400, 403, 404], true), 'path traversal refused (' . $other->status . ')');
    $t->assert(!str_contains($other->body, 'DB_PASSWORD'));
    $guest->get('/custom-wall-art');
    $t->contains($state['uuid'], $guest->body, 'recent photos restored after reload');
});
$t->test('upload rejects fake, tiny and non-image files', function (TestRunner $t) use ($guest, $mkJpeg): void {
    $php = tempnam(sys_get_temp_dir(), 'bwa') . '.jpg';
    file_put_contents($php, '<?php system($_GET["c"]); ?>');
    foreach ([[$php, 'shell.jpg'], [$mkJpeg(120, 90), 'tiny.jpg']] as [$file, $name]) {
        $guest->get('/custom-wall-art');
        $guest->request('POST', '/builder/upload', ['_token' => $guest->token()], ['photo' => [$file, 'image/jpeg', $name]], ['Accept: application/json']);
        $t->equals(422, $guest->status, $name);
    }
    $t->contains('trop petite', $guest->data()['message'] ?? '');
});
$t->test('design save → private design page → WhatsApp order → delete', function (TestRunner $t) use ($guest, $base, &$state): void {
    $guest->get('/custom-wall-art');
    preg_match('#"materials":\[\{"id":(\d+),"code":"canvas"#', $guest->body, $m);
    $tok = $guest->token();
    $guest->json('/builder/design', [
        'material_id' => (int) $m[1], 'layout' => 'single', 'mode' => 'span', 'panel_width_cm' => 60, 'panel_height_cm' => 40, 'border' => 'mirror', 'quantity' => 1,
        'images' => [['uuid' => $state['uuid'], 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 2400, 'height' => 1600, 'rotate' => 0]]],
        'price' => '1.00', // ignored: the server always prices
    ], $tok);
    $t->equals(200, $guest->status, $guest->body);
    $d = $guest->data()['design'] ?? [];
    $t->equals('199.00', $d['unit_price'] ?? null);
    $state['design'] = $d['uuid'] ?? '';
    $guest->get('/builder/design/' . $state['design']);
    $t->equals(200, $guest->status);
    $t->contains('60 × 40 cm', $guest->body);
    $t->contains('Bords miroir', $guest->body);
    $t->contains('wa.me/212661000000?text=', $guest->body);
    $t->contains('noindex', $guest->body);
    $other = new Browser($base);
    $other->get('/builder/design/' . $state['design']);
    $t->equals(404, $other->status, 'designs are private');
    $guest->json('/builder/design', ['material_id' => (int) $m[1], 'layout' => 'single', 'panel_width_cm' => 60, 'panel_height_cm' => 40,
        'images' => [['uuid' => $state['uuid'], 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 2400, 'height' => 600, 'rotate' => 0]]]], $tok);
    $t->equals(422, $guest->status, 'tampered crop ratio');
});
$t->test('workshop staff can open customer photos and originals', function (TestRunner $t) use ($base, $adminPass, &$state): void {
    $a = new Browser($base);
    $a->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => $adminPass], '/admin/login');
    $a->get('/builder/image/' . $state['uuid'] . '/original');
    $t->equals(200, $a->status);
    $t->contains('attachment', $a->headers['content-disposition'] ?? '');
    $a->get('/builder/design/' . $state['design']);
    $t->equals(200, $a->status);
});
$t->test('customer account: claimed photos & designs, delete photo now', function (TestRunner $t) use ($guest, &$state): void {
    $guest->post('/account/register', ['first_name' => 'Imane', 'last_name' => 'B', 'email' => 'imane@client.test', 'phone' => '0612000000', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $guest->get('/account/designs');
    $t->equals(200, $guest->status);
    $t->contains($state['design'], $guest->body, 'guest design now in the account');
    $t->contains('souvenir.jpg', $guest->body);
    $guest->post('/builder/design/' . $state['design'] . '/delete', [], '/account/designs');
    $guest->get('/account/designs');
    $t->assert(!str_contains($guest->body, $state['design']), 'design deleted');
    $guest->post('/builder/image/' . $state['uuid'] . '/delete', [], '/account/designs');
    $guest->follow();
    $t->contains('supprimée définitivement', $guest->body);
    $guest->get('/builder/image/' . $state['uuid'] . '/preview');
    $t->equals(404, $guest->status, 'purged');
});

$t->section('Cart, checkout & orders (Phase 4)');
$pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
$pdo->exec("INSERT INTO coupons (code, discount_type, discount_value, is_active) VALUES ('ATELIER10', 'percent', 10, 1)");
$shopper = new Browser($base);
$ord = [];
$t->test('add to cart from the product page, header badge, cart page', function (TestRunner $t) use ($shopper): void {
    $shopper->get('/product/vallee-du-dades');
    preg_match('/name="product_id" value="(\d+)"/', $shopper->body, $p);
    preg_match('/name="variant_id" value="(\d+)"/', $shopper->body, $v);
    $t->contains('Ajouter au panier', $shopper->body);
    $shopper->post('/cart/add', ['product_id' => $p[1], 'variant_id' => $v[1], 'quantity' => '2']);
    $t->equals('/cart', $shopper->location());
    $shopper->get('/cart');
    $t->contains('Vallée du Dadès', $shopper->body);
    $t->contains('data-cart-count>2<', $shopper->body);
    $shopper->get('/product/toile-photo-personnalisee');
    $t->assert(!str_contains($shopper->body, 'Ajouter au panier'), 'customizable product: builder only');
    $t->contains('/custom-wall-art?product=toile-photo-personnalisee', $shopper->body);
});
$t->test('coupon, live shipping estimate and quantity update', function (TestRunner $t) use ($shopper): void {
    $shopper->post('/cart/coupon', ['code' => 'nope'], '/cart');
    $shopper->get('/cart');
    $t->contains('n’existe pas', $shopper->body);
    $shopper->post('/cart/coupon', ['code' => 'atelier10'], '/cart');
    $shopper->get('/cart');
    $t->contains('ATELIER10', $shopper->body);
    $shopper->json('/cart/shipping', ['city' => 'Marrakech']);
    $t->equals('40,00 DH', $shopper->data()['shipping'] ?? null);
    $shopper->get('/cart');
    $shopper->json('/cart/shipping', ['city' => 'Tata']);
    $t->equals('40,00 DH', $shopper->data()['shipping'] ?? null, 'unknown city → flat rate');
    $shopper->get('/cart');
    preg_match('#action="[^"]*/cart/item/(\d+)"#', $shopper->body, $m);
    $shopper->post('/cart/item/' . $m[1], ['quantity' => '1'], '/cart');
    $shopper->get('/cart');
    $t->contains('data-cart-count>1<', $shopper->body);
});
$t->test('checkout validation keeps input and shows errors', function (TestRunner $t) use ($shopper): void {
    $shopper->get('/checkout');
    $t->equals(200, $shopper->status);
    $t->contains('Paiement à la livraison', $shopper->body);
    $t->contains('Virement bancaire', $shopper->body);
    $t->assert(!str_contains($shopper->body, 'Carte bancaire (CMI)'), 'online payment not offered');
    $shopper->post('/checkout', ['full_name' => 'Al', 'phone' => 'abc', 'country' => 'MA', 'city' => 'Rabat', 'address' => '', 'payment_method' => 'cod'], '/checkout');
    $shopper->get('/checkout');
    $t->contains('is-invalid', $shopper->body);
    $t->contains('value="Rabat"', $shopper->body);
});
$t->test('guest order → confirmation page (private) → e-mail tracking link', function (TestRunner $t) use ($shopper, $base, $pdo, &$ord): void {
    $shopper->post('/checkout', [
        'full_name' => 'Hind Bennani', 'phone' => '0662 11 22 33', 'email' => 'hind@client.test', 'country' => 'MA', 'city' => 'Rabat',
        'address' => '8 Avenue Fal Ould Oumeir, Agdal', 'postal_code' => '10090', 'payment_method' => 'bank_transfer', 'accept_terms' => '1',
    ], '/checkout');
    $t->assert(str_starts_with($shopper->location(), '/order/confirmation/BWA-'), 'redirect ' . $shopper->location());
    $ord['number'] = substr($shopper->location(), strlen('/order/confirmation/'));
    $shopper->get($shopper->location());
    $t->equals(200, $shopper->status);
    $t->contains('Merci pour votre commande', $shopper->body);
    $t->contains($ord['number'], $shopper->body);
    $t->contains('Paiement par virement', $shopper->body);
    $t->contains('ATELIER10', $shopper->body);
    $t->contains('data-cart-count>0<', str_replace('d-none" data-cart-count', '" data-cart-count', $shopper->body), 'cart emptied');
    preg_match('#href="([^"]*/track/' . preg_quote($ord['number'], '#') . '/([a-f0-9]{48}))"#', $shopper->body, $m);
    $ord['token'] = $m[2] ?? '';
    $other = new Browser($base);
    $other->get('/order/confirmation/' . $ord['number']);
    $t->equals(404, $other->status, 'confirmation is private to the buyer');
    $other->get('/track/' . $ord['number'] . '/' . $ord['token']);
    $t->equals(200, $other->status);
    $t->contains('Reçue', $other->body);
    $t->assert(!str_contains($other->body, 'Fal Ould Oumeir'), 'tracking page does not expose the address');
    $row = $pdo->query("SELECT status, payment_status, grand_total FROM orders WHERE order_number = " . $pdo->quote($ord['number']))->fetch(PDO::FETCH_ASSOC);
    $t->equals(['new', 'unpaid'], [$row['status'], $row['payment_status']]);
    $t->equals(2, (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE related_type = 'order' AND event IN ('order_placed','order_new_admin')")->fetchColumn(), 'customer + admin e-mails recorded');
});
$t->test('public tracking by number + phone; wrong contact refused', function (TestRunner $t) use ($base, &$ord): void {
    $v = new Browser($base);
    $v->post('/track', ['number' => $ord['number'], 'contact' => '+212 662 11 22 33'], '/track');
    $t->equals(200, $v->status);
    $t->contains('Statut actuel', $v->body);
    $v->post('/track', ['number' => $ord['number'], 'contact' => '0600000000'], '/track');
    $v->get('/track');
    $t->contains('Aucune commande ne correspond', $v->body);
});
$t->test('logged-in customer: order with saved address, history, detail, cancel', function (TestRunner $t) use ($base, $pdo): void {
    $c = new Browser($base);
    $c->post('/account/register', ['first_name' => 'Rachid', 'last_name' => 'Tazi', 'email' => 'rachid@client.test', 'phone' => '0612121212', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $c->post('/account/addresses', ['label' => 'Maison', 'full_name' => 'Rachid Tazi', 'phone' => '0612121212', 'city' => 'Tanger', 'address_line1' => '3 Rue de Fès, Malabata'], '/account/addresses');
    $c->get('/account/addresses');
    $t->contains('Malabata', $c->body);
    $c->get('/product/orbites');
    preg_match('/name="product_id" value="(\d+)"/', $c->body, $p);
    preg_match('/name="variant_id" value="(\d+)"/', $c->body, $v);
    $c->post('/cart/add', ['product_id' => $p[1], 'variant_id' => $v[1], 'quantity' => '1']);
    $c->get('/checkout');
    $t->contains('Malabata', $c->body, 'saved address offered');
    $t->contains('value="rachid@client.test"', $c->body);
    $c->post('/checkout', ['full_name' => 'Rachid Tazi', 'phone' => '0612121212', 'email' => 'attacker@evil.test', 'country' => 'MA', 'city' => 'Tanger', 'address' => '3 Rue de Fès, Malabata', 'payment_method' => 'cod', 'accept_terms' => '1'], '/checkout');
    $number = substr($c->location(), strlen('/order/confirmation/'));
    $t->equals('rachid@client.test', (string) $pdo->query("SELECT customer_email FROM orders WHERE order_number = " . $pdo->quote($number))->fetchColumn(), 'account e-mail cannot be overridden');
    $c->get('/account/orders');
    $t->contains($number, $c->body);
    $c->get('/account/orders/' . $number);
    $t->contains('Annuler la commande', $c->body);
    $c->post('/account/orders/' . $number . '/cancel', ['reason' => 'Changement d’avis'], '/account/orders/' . $number);
    $c->follow();
    $t->contains('Votre commande a été annulée', $c->body);
    $t->assert(!str_contains($c->body, 'Annuler la commande'), 'no second cancel');
    $other = new Browser($base);
    $other->post('/account/register', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'other@client.test', 'phone' => '0613131313', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $other->get('/account/orders/' . $number);
    $t->equals(404, $other->status, 'orders are private to their customer');
});
$t->test('a saved design goes to the cart from its page', function (TestRunner $t) use ($base, $mkJpeg): void {
    $g = new Browser($base);
    $g->get('/custom-wall-art');
    $g->request('POST', '/builder/upload', ['_token' => $g->token()], ['photo' => [$mkJpeg(2400, 1600), 'image/jpeg', 'p.jpg']], ['Accept: application/json']);
    $uuid = $g->data()['image']['uuid'] ?? '';
    $g->get('/custom-wall-art');
    preg_match('#"materials":\[\{"id":(\d+),"code":"canvas"#', $g->body, $m);
    $g->json('/builder/design', ['material_id' => (int) $m[1], 'layout' => 'single', 'panel_width_cm' => 60, 'panel_height_cm' => 40, 'border' => 'wrap',
        'images' => [['uuid' => $uuid, 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 2400, 'height' => 1600, 'rotate' => 0]]]]);
    $design = $g->data()['design']['uuid'] ?? '';
    $g->get('/builder/design/' . $design);
    $t->contains('Ajouter au panier', $g->body);
    $g->post('/cart/add', ['design' => $design]);
    $g->get('/cart');
    $t->contains('Création personnalisée', $g->body);
    $t->contains('60 × 40 cm', $g->body);
});
$t->test('empty cart redirects from checkout; terms page; payment webhook inactive', function (TestRunner $t) use ($base): void {
    $e = new Browser($base);
    $e->get('/checkout');
    $t->equals('/cart', $e->location());
    $e->get('/terms');
    $t->equals(200, $e->status);
    $t->contains('Créations personnalisées', $e->body);
    $e->request('POST', '/webhooks/payments/cmi', ['order' => 'x', 'amount' => '999']);
    $t->equals(404, $e->status, 'no gateway → cannot mark anything paid');
});

$t->section('Admin authentication, RBAC & settings');
$t->test('protected admin pages redirect to login', function (TestRunner $t) use ($b): void {
    $b->get('/admin/settings');
    $t->equals('/admin/login', $b->location());
});
$t->test('wrong admin password shows a generic error', function (TestRunner $t) use ($b): void {
    $b->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => 'bad'], '/admin/login');
    $t->equals(302, $b->status);
    $b->get('/admin/login');
    $t->contains('Ces identifiants ne correspondent', $b->body);
});
$t->test('admin login → intended page → dashboard', function (TestRunner $t) use ($b, $adminPass): void {
    $b->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => $adminPass], '/admin/login');
    $t->equals('/admin/settings', $b->location(), 'redirect to intended URL');
    $b->get('/admin');
    $t->equals(200, $b->status);
    $t->contains('Bonjour, Owner Berradi', $b->body);
    $t->contains('Configuration de la boutique', $b->body);
    $t->assert(!preg_match('/chiffre d.affaires\s*:\s*\d/i', $b->body), 'no fake statistics');
});
$t->test('all settings tabs render and company tab saves', function (TestRunner $t) use ($b): void {
    foreach (['company', 'localization', 'taxes', 'documents', 'shipping', 'orders', 'mail', 'seo'] as $tab) {
        $b->get('/admin/settings/' . $tab);
        $t->equals(200, $b->status, "tab {$tab}");
    }
    $b->post('/admin/settings/taxes', ['tax_enabled' => '1', 'tax_default_rate' => '14', 'tax_prices_include_tax' => '0', 'tax_label' => 'TVA'], '/admin/settings/taxes');
    $t->equals('/admin/settings/taxes', $b->location());
    $b->get('/admin/settings/taxes');
    $t->contains('value="14"', $b->body);
    $t->contains('Modifications enregistrées', $b->body);
    $b->post('/admin/settings/taxes', ['tax_default_rate' => '150', 'tax_label' => 'TVA'], '/admin/settings/taxes');
    $b->get('/admin/settings/taxes');
    $t->contains('is-invalid', $b->body, 'rate > 100 rejected');
});
$t->test('SMTP password is stored encrypted and never re-displayed', function (TestRunner $t) use ($b): void {
    $b->post('/admin/settings/mail', ['mail_driver' => 'log', 'mail_host' => 'mail.berradi.test', 'mail_port' => '465', 'mail_encryption' => 'ssl', 'mail_username' => 'noreply', 'mail_password' => 'Sup3rSecretSMTP'], '/admin/settings/mail');
    $b->get('/admin/settings/mail');
    $t->assert(!str_contains($b->body, 'Sup3rSecretSMTP'), 'not in HTML');
    $t->contains('Laisser vide pour conserver', $b->body);
    $b->post('/admin/settings-test-mail', [], '/admin/settings/mail');
    $b->follow();
    $t->contains('E-mail de test envoyé', $b->body);
});
$t->test('create a Designer staff account', function (TestRunner $t) use ($b): void {
    $b->get('/admin/users/create');
    preg_match('/<option value="(\d+)"[^>]*>Designer<\/option>/', $b->body, $m);
    $t->assert(isset($m[1]), 'designer role option');
    $b->post('/admin/users', ['name' => 'Yasmine Designer', 'email' => 'designer@berradi.test', 'phone' => '0600000000', 'role_id' => $m[1] ?? '0', 'locale' => 'fr', 'is_active' => '1', 'password' => 'Design2026', 'password_confirmation' => 'Design2026']);
    $t->equals('/admin/users', $b->location());
    $b->get('/admin/users');
    $t->contains('designer@berradi.test', $b->body);
});
$t->test('duplicate e-mail is rejected', function (TestRunner $t) use ($b): void {
    $b->post('/admin/users', ['name' => 'X', 'email' => 'designer@berradi.test', 'role_id' => '1', 'locale' => 'fr', 'password' => 'Design2026', 'password_confirmation' => 'Design2026'], '/admin/users/create');
    $b->get('/admin/users/create');
    $t->contains('déjà utilisée', $b->body);
});
$t->test('roles matrix renders; super admin role is locked', function (TestRunner $t) use ($b): void {
    $b->get('/admin/roles');
    $t->equals(200, $b->status);
    $t->contains('Production Manager', $b->body);
    $b->get('/admin/roles/1/edit');
    $b->post('/admin/roles/1', ['name' => 'Hacked', 'permissions' => []]);
    $b->follow();
    $t->contains('ne peut pas être modifié', $b->body);
});
$t->test('activity log records the actions', function (TestRunner $t) use ($b): void {
    $b->get('/admin/activity-logs');
    $t->equals(200, $b->status);
    $t->contains('settings.updated', $b->body);
    $t->contains('user.created', $b->body);
    $t->contains('auth.failed', $b->body);
});
$t->test('logout requires POST + CSRF and ends the session', function (TestRunner $t) use ($b): void {
    $b->get('/admin/logout');
    $t->equals(405, $b->status, 'GET logout not allowed');
    $b->post('/admin/logout', [], '/admin');
    $t->equals('/admin/login', $b->location());
    $b->get('/admin');
    $t->equals('/admin/login', $b->location());
});
$t->test('designer cannot access settings, users or roles (403)', function (TestRunner $t) use ($base): void {
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    $t->equals('/admin', $d->location());
    $d->get('/admin');
    $t->equals(200, $d->status);
    $t->assert(!str_contains($d->body, '/admin/settings"'), 'settings hidden in menu');
    foreach (['/admin/settings', '/admin/users', '/admin/roles', '/admin/activity-logs'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $d->post('/admin/settings/company', ['company_name' => 'Hacked'], '/admin');
    $t->equals(403, $d->status, 'POST settings forbidden');
});
$t->test('admin password reset request + invalid token refused', function (TestRunner $t) use ($base): void {
    $r = new Browser($base);
    $r->post('/admin/forgot-password', ['email' => 'designer@berradi.test'], '/admin/forgot-password');
    $t->equals('/admin/login', $r->location());
    $r->get('/admin/reset-password/' . str_repeat('a', 64));
    $t->equals('/admin/forgot-password', $r->location(), 'invalid token refused');
});

$t->section('Customer accounts');
$t->test('registration validates, creates the account and logs in', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/register', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'bad', 'phone' => '0612', 'password' => 'x', 'password_confirmation' => 'y'], '/account/register');
    $c->get('/account/register');
    $t->contains('is-invalid', $c->body);
    $t->contains('value="Salma"', $c->body, 'old input kept');
    $t->assert(!str_contains($c->body, 'value="x"'), 'password not re-filled');
    $c->post('/account/register', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'salma@client.test', 'phone' => '0612345678', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $t->equals('/account', $c->location());
    $c->get('/account');
    $t->contains('Bonjour Salma', $c->body);
    $c->post('/account/profile', ['first_name' => 'Salma', 'last_name' => 'Alaoui', 'email' => 'salma@client.test', 'phone' => '0612345678', 'locale' => 'ar', 'ice' => '001234567000089'], '/account/profile');
    $c->get('/account/profile');
    $t->contains('001234567000089', $c->body);
    $c->post('/account/logout', [], '/account');
    $c->get('/account');
    $t->equals('/account/login', $c->location());
});
$t->test('customer login works; customer cannot reach admin', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/login', ['email' => 'salma@client.test', 'password' => 'Client2026'], '/account/login');
    $t->equals('/account', $c->location());
    $c->get('/admin');
    $t->equals('/admin/login', $c->location());
    $c->post('/admin/login', ['email' => 'salma@client.test', 'password' => 'Client2026'], '/admin/login');
    $c->get('/admin');
    $t->equals('/admin/login', $c->location(), 'customer credentials are not staff credentials');
});
$t->test('XSS payloads are escaped on output', function (TestRunner $t) use ($base): void {
    $c = new Browser($base);
    $c->post('/account/register', ['first_name' => '<script>alert(1)</script>', 'last_name' => 'X', 'email' => 'xss@client.test', 'phone' => '0612345679', 'password' => 'Client2026', 'password_confirmation' => 'Client2026', 'accept_terms' => '1'], '/account/register');
    $c->get('/account');
    $t->assert(!str_contains($c->body, '<script>alert(1)</script>'), 'raw script not present');
    $t->contains('&lt;script&gt;alert(1)&lt;/script&gt;', $c->body);
});

$t->section('Admin catalogue, customers & dashboard (Phase 5)');
$pdo5 = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
$q = fn (string $sql) => $pdo5->query($sql)->fetchColumn();
$o = new Browser($base);
$o->post('/admin/login', ['email' => 'owner@berradi.test', 'password' => $adminPass], '/admin/login');
$jpeg = sys_get_temp_dir() . '/bwa-admin-test.jpg';
$im = imagecreatetruecolor(900, 1200);
imagefilledrectangle($im, 0, 0, 900, 1200, (int) imagecolorallocate($im, 198, 166, 107));
imagejpeg($im, $jpeg, 85);
imagedestroy($im);
$pid = 0;

$t->test('dashboard: real KPIs, chart data as JSON (no inline script), profit formula, quick actions', function (TestRunner $t) use ($o, $q): void {
    $o->get('/admin');
    $t->equals(200, $o->status);
    $t->contains('<script type="application/json" id="dashboard-data">', $o->body);
    preg_match('#<script type="application/json" id="dashboard-data">(.*?)</script>#s', $o->body, $m);
    $data = json_decode($m[1] ?? '', true);
    $t->assert(is_array($data) && count($data['monthlySales']['values'] ?? []) === 12, '12 months of sales');
    $t->equals((int) $q('SELECT COUNT(*) FROM orders'), array_sum(array_column($data['ordersByStatus'] ?? [], 'value')), 'status chart matches orders table');
    $t->contains('Bénéfice estimé =', $o->body);
    $t->contains('/admin/products/create', $o->body);
    $t->contains('js/dashboard.js', $o->body);
    $t->assert(!preg_match('#<script>(?!</script>)#', $o->body), 'no inline executable script');
});
$t->test('product list: filters, search and sorting', function (TestRunner $t) use ($o): void {
    $o->get('/admin/products');
    $t->equals(200, $o->status);
    $t->contains('19 produit(s)', $o->body);
    $o->get('/admin/products?q=orbites');
    $t->contains('1 produit(s)', $o->body);
    $o->get('/admin/products?type=multi_panel&sort=price&dir=asc');
    $t->equals(200, $o->status);
});
$t->test('create product with image upload and Arabic translation', function (TestRunner $t) use ($o, $jpeg, $q, &$pid): void {
    $o->post('/admin/products', [
        'title' => 'Atlas Bleu', 'product_type' => 'canvas', 'selling_price' => '590,00', 'cost_price' => '140', 'min_panels' => '1', 'max_panels' => '1',
        'is_active' => '1', 'is_new' => '1', 'short_description' => 'Montagnes au crépuscule', 'ar_title' => 'الأطلس الأزرق',
    ], '/admin/products/create', ['images[0]' => [$jpeg, 'image/jpeg', 'atlas.jpg']]);
    $t->assert(preg_match('#^/admin/products/(\d+)/edit$#', $o->location(), $m) === 1, 'redirect to editor: ' . $o->location());
    $pid = (int) ($m[1] ?? 0);
    $t->equals('atlas-bleu', (string) $q("SELECT slug FROM products WHERE id = {$pid}"));
    $t->equals('590.00', (string) $q("SELECT selling_price FROM products WHERE id = {$pid}"));
    $t->equals(1, (int) $q("SELECT COUNT(*) FROM product_images WHERE product_id = {$pid}"));
    $main = (string) $q("SELECT main_image FROM products WHERE id = {$pid}");
    $t->assert($main !== '' && str_starts_with($main, 'products/'), 'main image set: ' . $main);
    $o->get('/product/atlas-bleu');
    $t->equals(200, $o->status);
    $t->contains('Atlas Bleu', $o->body);
    $o->get('/lang/ar');
    $o->get('/product/atlas-bleu');
    $t->contains('الأطلس الأزرق', $o->body);
    $o->get('/lang/fr');
});
$t->test('validation: promo price must be lower than the selling price', function (TestRunner $t) use ($o, &$pid): void {
    $o->post('/admin/products/' . $pid, ['title' => 'Atlas Bleu', 'product_type' => 'canvas', 'selling_price' => '590', 'promo_price' => '600', 'min_panels' => '1', 'max_panels' => '1', 'tab' => 'pricing'], '/admin/products/' . $pid . '/edit');
    $o->follow();
    $t->contains('Le prix promo doit être inférieur au prix de vente.', $o->body);
});
$t->test('variants: generate from print options, then edit default and delete one', function (TestRunner $t) use ($o, $q, &$pid): void {
    $canvas = (int) $q("SELECT id FROM product_materials WHERE code = 'canvas'");
    $sizes = $o->get('/admin/products/' . $pid . '/edit')->body;
    preg_match_all('#name="g_sizes\[\]" value="([^"]+)"#', $sizes, $m);
    $t->assert(count($m[1]) >= 2, 'size options listed');
    $o->post('/admin/products/' . $pid . '/variants/generate', ['g_sizes' => array_slice($m[1], 0, 2), 'g_materials' => [$canvas], 'g_frames' => ['none'], 'g_panels' => '1']);
    $o->follow();
    $t->contains('2 variante(s) créée(s).', $o->body);
    $ids = $o->body;
    preg_match_all('#name="variants\[(\d+)\]\[name\]"#', $ids, $v);
    $t->equals(2, count($v[1]));
    [$a, $b2] = $v[1];
    $fields = ['default_variant' => $b2];
    foreach ([$a, $b2] as $id) {
        $fields["variants[$id][name]"] = 'V' . $id;
        $fields["variants[$id][price]"] = $id === $a ? '399' : '799';
        $fields["variants[$id][cost_price]"] = '50';
        $fields["variants[$id][stock_quantity]"] = '0';
        $fields["variants[$id][sort_order]"] = '1';
        $fields["variants[$id][is_active]"] = '1';
    }
    $fields["variants[$a][delete]"] = '1';
    $o->post('/admin/products/' . $pid . '/variants', $fields, '/admin/products/' . $pid . '/edit');
    $t->equals(1, (int) $q("SELECT COUNT(*) FROM product_variants WHERE product_id = {$pid}"));
    $t->equals('799.00', (string) $q("SELECT price FROM product_variants WHERE product_id = {$pid} AND is_default = 1"));
    $t->equals('799.00', (string) $q("SELECT selling_price FROM products WHERE id = {$pid}"), 'from-price follows variants');
});
$t->test('duplicate → inactive copy; bulk deactivate hides products; delete keeps orders', function (TestRunner $t) use ($o, $q, &$pid): void {
    $o->post('/admin/products/' . $pid . '/duplicate', [], '/admin/products/' . $pid . '/edit');
    $copy = (int) $q("SELECT id FROM products WHERE slug = 'atlas-bleu-copie'");
    $t->assert($copy > 0, 'copy exists');
    $t->equals(0, (int) $q("SELECT is_active FROM products WHERE id = {$copy}"));
    $o->get('/product/atlas-bleu-copie');
    $t->equals(404, $o->status, 'inactive copy hidden');
    $orb = (int) $q("SELECT id FROM products WHERE slug = 'orbites'");
    $o->post('/admin/products/bulk', ['bulk_action' => 'deactivate', 'ids' => [$orb, $pid]], '/admin/products');
    $o->follow();
    $t->contains('2 produit(s) mis à jour.', $o->body);
    $o->get('/product/orbites');
    $t->equals(404, $o->status);
    $o->post('/admin/products/bulk', ['bulk_action' => 'activate', 'ids' => [$orb, $pid]], '/admin/products');
    $o->get('/product/orbites');
    $t->equals(200, $o->status);
    $ordered = (int) $q('SELECT product_id FROM order_items WHERE product_id IS NOT NULL LIMIT 1');
    $lines = (int) $q("SELECT COUNT(*) FROM order_items WHERE product_id = {$ordered}");
    $o->post('/admin/products/' . $ordered . '/delete', [], '/admin/products');
    $t->assert($q("SELECT deleted_at FROM products WHERE id = {$ordered}") !== null, 'soft deleted');
    $t->equals($lines, (int) $q("SELECT COUNT(*) FROM order_items WHERE product_id = {$ordered}"), 'order lines intact');
});
$t->test('categories: create with Arabic name, cycle refused, reorder, delete needs confirmation', function (TestRunner $t) use ($o, $q): void {
    $o->post('/admin/categories', ['name' => 'Paysages marocains', 'ar_name' => 'مناظر مغربية', 'is_active' => '1'], '/admin/categories/create');
    $parent = (int) $q("SELECT id FROM categories WHERE slug = 'paysages-marocains'");
    $t->assert($parent > 0);
    $o->post('/admin/categories', ['name' => 'Désert', 'parent_id' => (string) $parent, 'is_active' => '1'], '/admin/categories/create');
    $child = (int) $q("SELECT id FROM categories WHERE slug = 'desert'");
    $o->post('/admin/categories/' . $parent, ['name' => 'Paysages marocains', 'parent_id' => (string) $child, 'is_active' => '1'], '/admin/categories/' . $parent . '/edit');
    $o->follow();
    $t->contains('ne peut pas être son propre parent', $o->body);
    $t->equals(null, $q("SELECT parent_id FROM categories WHERE id = {$parent}") ?: null);
    $ids = array_map('intval', $o->get('/admin/categories')->body ? (preg_match_all('#name="order\[\]" value="(\d+)"#', $o->body, $m) ? $m[1] : []) : []);
    $t->assert(count($ids) >= 3, 'sortable list');
    $o->post('/admin/categories/reorder', ['order' => array_reverse($ids)], '/admin/categories');
    $t->equals(10, (int) $q('SELECT sort_order FROM categories WHERE id = ' . end($ids)));
    $withProducts = (int) $q('SELECT category_id FROM products WHERE category_id IS NOT NULL AND deleted_at IS NULL LIMIT 1');
    $o->post('/admin/categories/' . $withProducts . '/delete', [], '/admin/categories');
    $t->assert((int) $q("SELECT COUNT(*) FROM categories WHERE id = {$withProducts}") === 1, 'refused without confirmation');
    $o->post('/admin/categories/' . $child . '/delete', ['confirm_detach' => '1'], '/admin/categories');
    $t->equals(0, (int) $q("SELECT COUNT(*) FROM categories WHERE id = {$child}"));
    $o->get('/lang/ar');
    $o->get('/shop/category/paysages-marocains');
    $t->contains('مناظر مغربية', $o->body);
    $o->get('/lang/fr');
});
$t->test('collections: limited edition with date order validation', function (TestRunner $t) use ($o, $q): void {
    $o->post('/admin/collections', ['name' => 'Ramadan 2027', 'is_limited' => '1', 'starts_at' => '2027-02-10T00:00', 'ends_at' => '2027-02-01T00:00', 'is_active' => '1'], '/admin/collections/create');
    $o->follow();
    $t->contains('La date de fin doit être après la date de début.', $o->body);
    $o->post('/admin/collections', ['name' => 'Ramadan 2027', 'is_limited' => '1', 'starts_at' => '2027-02-01T00:00', 'ends_at' => '2027-03-10T00:00', 'is_active' => '1'], '/admin/collections/create');
    $t->equals(1, (int) $q("SELECT is_limited FROM collections WHERE slug = 'ramadan-2027'"));
});
$t->test('customers: list with spend, profile, create, anonymize', function (TestRunner $t) use ($o, $q): void {
    $o->get('/admin/customers?sort=spent');
    $t->equals(200, $o->status);
    $t->contains('Salma', $o->body);
    $o->post('/admin/customers', ['first_name' => 'Karim', 'last_name' => 'Bennani', 'phone' => '0661234567', 'email' => 'karim@b2b.test', 'company_name' => 'Hôtel Atlas', 'ice' => '001525252000077', 'locale' => 'fr', 'is_active' => '1'], '/admin/customers/create');
    $t->assert(preg_match('#^/admin/customers/(\d+)$#', $o->location(), $m) === 1, 'redirect to profile');
    $o->get($o->location());
    $t->contains('Hôtel Atlas', $o->body);
    $t->contains('001525252000077', $o->body);
    $o->post('/admin/customers', ['first_name' => 'Dup', 'phone' => '0661234568', 'email' => 'karim@b2b.test', 'locale' => 'fr'], '/admin/customers/create');
    $o->follow();
    $t->contains('déjà utilisée', $o->body);
    $o->post('/admin/customers/' . $m[1] . '/anonymize', [], '/admin/customers/' . $m[1]);
    $t->equals(null, $q('SELECT email FROM customers WHERE id = ' . (int) $m[1]) ?: null);
    $t->assert($q('SELECT deleted_at FROM customers WHERE id = ' . (int) $m[1]) !== null, 'flagged deleted');
    $o->get('/admin/customers?q=karim');
    $t->assert(!str_contains($o->body, 'Hôtel Atlas'), 'gone from the list');
});
$t->test('print options: a price change drives the builder price API', function (TestRunner $t) use ($o, $q, $base): void {
    $canvas = (int) $q("SELECT id FROM product_materials WHERE code = 'canvas'");
    $o->get('/admin/print-options?tab=materials');
    $t->equals(200, $o->status);
    $t->contains('id="row-product_material-' . $canvas . '"', $o->body);
    $g = new Browser($base);
    $g->get('/custom-wall-art');
    $tok = $g->token();
    $g->json('/builder/price', ['material_id' => $canvas, 'panel_width_cm' => 100, 'panel_height_cm' => 100], $tok);
    $before = (string) ($g->data()['unit_price'] ?? '');
    $cur = $o->body;
    $field = fn (string $n) => preg_match('#<details[^>]+id="row-product_material-' . $canvas . '".*?name="' . $n . '" value="([^"]*)"#s', $cur, $mm) ? html_entity_decode($mm[1]) : '';
    $post = [];
    foreach (['code', 'name', 'description', 'minimum_price', 'cost_per_m2', 'consumption_factor', 'min_width_cm', 'max_width_cm', 'min_height_cm', 'max_height_cm', 'sort_order', 'ar_name'] as $n) {
        $post[$n] = $field($n);
    }
    $post += ['material_type' => 'canvas', 'price_per_m2' => '1000.00', 'supports_frame' => '1', 'is_active' => '1'];
    $o->post('/admin/print-options/materials/' . $canvas, $post, '/admin/print-options');
    $t->equals('/admin/print-options?tab=materials', strtok($o->location(), '#'));
    $t->equals('1000.00', (string) $q("SELECT price_per_m2 FROM product_materials WHERE id = {$canvas}"));
    $g->json('/builder/price', ['material_id' => $canvas, 'panel_width_cm' => 100, 'panel_height_cm' => 100], $tok);
    $after = (string) ($g->data()['unit_price'] ?? '');
    $t->assert($after !== '' && $after !== $before && (float) $after >= 1000.0, "builder price follows ({$before} → {$after})");
    $o->post('/admin/print-options/materials/' . $canvas, ['price_per_m2' => 'abc'] + $post, '/admin/print-options');
    $o->follow();
    $t->contains('Non enregistré', $o->body);
});
$t->test('maintenance page: migrations status, system info and safe re-run', function (TestRunner $t) use ($o): void {
    $o->get('/admin/maintenance');
    $t->equals(200, $o->status);
    $t->contains('La base de données est à jour.', $o->body);
    $t->contains('cron:run', $o->body);
    $o->post('/admin/maintenance/update', [], '/admin/maintenance');
    $o->follow();
    $t->contains('Mise à jour terminée (0 migration(s)).', $o->body);
});
$t->test('RBAC: designer manages the catalogue but not customers, finance or maintenance', function (TestRunner $t) use ($base): void {
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    $d->get('/admin');
    $t->equals(200, $d->status);
    $t->assert(!str_contains($d->body, 'Bénéfice du mois'), 'no finance KPIs');
    $t->assert(!str_contains($d->body, 'monthlyProfit'), 'no profit chart data');
    foreach (['/admin/products', '/admin/products/create', '/admin/categories', '/admin/print-options'] as $p) {
        $d->get($p);
        $t->equals(200, $d->status, $p);
    }
    foreach (['/admin/customers', '/admin/maintenance'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $d->post('/admin/maintenance/update', [], '/admin/products');
    $t->equals(403, $d->status);
});
$t->test('admin pages render in Arabic (RTL)', function (TestRunner $t) use ($o): void {
    $o->get('/lang/ar');
    foreach (['/admin', '/admin/products', '/admin/customers', '/admin/print-options', '/admin/maintenance', '/admin/categories'] as $p) {
        $o->get($p);
        $t->equals(200, $o->status, $p);
        $t->contains('dir="rtl"', $o->body, $p);
    }
    $o->get('/lang/fr');
});
@unlink($jpeg);

$t->section('Orders, artwork proofs, production & inventory (Phase 6)');
$p6 = ['order' => 0, 'number' => '', 'item' => 0];
$q6 = fn (string $sql) => $pdo5->query($sql)->fetchColumn();
$t->test('a customer orders a custom design (photo stays private)', function (TestRunner $t) use ($base, $mkJpeg, $q6, &$p6): void {
    $g = new Browser($base);
    $g->get('/custom-wall-art');
    $g->request('POST', '/builder/upload', ['_token' => $g->token()], ['photo' => [$mkJpeg(3000, 2000), 'image/jpeg', 'famille.jpg']], ['Accept: application/json']);
    $uuid = $g->data()['image']['uuid'] ?? '';
    $g->get('/custom-wall-art');
    preg_match('#"materials":\[\{"id":(\d+),"code":"canvas"#', $g->body, $m);
    $g->json('/builder/design', ['material_id' => (int) ($m[1] ?? 0), 'layout' => 'single', 'panel_width_cm' => 60, 'panel_height_cm' => 40, 'border' => 'wrap',
        'images' => [['uuid' => $uuid, 'panel' => 0, 'crop' => ['x' => 0, 'y' => 0, 'width' => 3000, 'height' => 2000, 'rotate' => 0]]]]);
    $g->post('/cart/add', ['design' => $g->data()['design']['uuid'] ?? ''], '/cart');
    $g->post('/checkout', ['full_name' => 'Nadia Chraibi', 'phone' => '0661 77 88 99', 'email' => 'nadia@client.test', 'country' => 'MA', 'city' => 'Casablanca',
        'address' => '25 Boulevard d’Anfa', 'payment_method' => 'cod', 'accept_terms' => '1'], '/checkout');
    $t->assert(str_starts_with($g->location(), '/order/confirmation/BWA-'), 'order placed: ' . $g->location());
    $p6['number'] = substr($g->location(), strlen('/order/confirmation/'));
    $p6['order'] = (int) $q6("SELECT id FROM orders WHERE order_number = '{$p6['number']}'");
    $p6['item'] = (int) $q6("SELECT id FROM order_items WHERE order_id = {$p6['order']} AND custom_design_id IS NOT NULL");
    $t->assert($p6['item'] > 0, 'custom line');
    $anon = new Browser($base);
    $anon->get('/admin/artwork/' . $p6['item']);
    $t->equals('/admin/login', $anon->location(), 'artwork pages need staff login');
});
$t->test('order back office: list, detail, artwork review, private preview & original download', function (TestRunner $t) use ($o, &$p6): void {
    $o->get('/admin/orders?status=open&q=' . urlencode('Nadia'));
    $t->equals(200, $o->status);
    $t->contains($p6['number'], $o->body);
    $t->contains('1 visuel(s) à valider', $o->body);
    $o->get('/admin/orders/' . $p6['order']);
    $t->equals(200, $o->status);
    $t->contains('Vérifier', $o->body);
    $t->contains('value="awaiting_confirmation"', $o->body, 'allowed next statuses offered');
    $t->assert(!str_contains($o->body, 'value="shipped"'), 'impossible transitions not offered');
    $o->get('/admin/orders/' . $p6['order'] . '/items/' . $p6['item'] . '/preview');
    $t->equals(200, $o->status);
    $t->equals('image/jpeg', $o->headers['content-type'] ?? '');
    $o->get('/admin/artwork?status=open');
    $t->contains($p6['number'], $o->body);
    $o->get('/admin/artwork/' . $p6['item']);
    $t->equals(200, $o->status);
    $t->contains('Envoyer l’épreuve au client', $o->body);
    preg_match('#/admin/artwork/' . $p6['item'] . '/original/(\d+)"#', $o->body, $m);
    $o->get('/admin/artwork/' . $p6['item'] . '/original/' . ($m[1] ?? 0));
    $t->equals(200, $o->status);
    $t->contains('attachment', $o->headers['content-disposition'] ?? '', 'original is a download');
    $o->get('/admin/orders/' . $p6['order'] . '/print');
    $t->contains($p6['number'], $o->body);
    $t->contains('Bon de préparation', $o->body);
});
$t->test('status workflow: confirm, refuse an impossible jump', function (TestRunner $t) use ($o, $q6, &$p6): void {
    $o->post('/admin/orders/' . $p6['order'] . '/status', ['status' => 'shipped'], '/admin/orders/' . $p6['order']);
    $o->follow();
    $t->contains('Passage impossible', $o->body);
    $o->post('/admin/orders/' . $p6['order'] . '/status', ['status' => 'confirmed', 'comment' => 'Confirmé par téléphone', 'notify' => '1'], '/admin/orders/' . $p6['order']);
    $t->equals('confirmed', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $t->equals(1, (int) $q6("SELECT COUNT(*) FROM notifications WHERE event = 'order_status_confirmed' AND related_id = {$p6['order']}"), 'customer e-mail queued/sent');
    $o->post('/admin/orders/' . $p6['order'] . '/status', ['status' => 'approved_for_production'], '/admin/orders/' . $p6['order']);
    $o->follow();
    $t->contains('ne sont pas encore validés', $o->body, 'artwork must be approved first');
});
$t->test('proof link: customer sees the mock-up, asks for a change, then approves a new proof (single-use links)', function (TestRunner $t) use ($o, $base, $q6, &$p6): void {
    $o->post('/admin/artwork/' . $p6['item'] . '/proof', ['message' => 'Voici votre visuel'], '/admin/artwork/' . $p6['item']);
    $o->follow();
    $t->assert(preg_match('#value="https?://[^"]+/artwork/([a-f0-9]{64})"#', $o->body, $m) === 1, 'link shown once to staff');
    $raw = $m[1] ?? '';
    $t->equals('awaiting_artwork_approval', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $o->get('/admin/artwork/' . $p6['item']);
    $t->assert(!str_contains($o->body, $raw), 'link not shown again');
    $c = new Browser($base);
    $c->get('/artwork/' . $raw);
    $t->equals(200, $c->status);
    $t->contains('noindex', strtolower($c->headers['x-robots-tag'] ?? ''));
    $t->contains($p6['number'], $c->body);
    $c->get('/artwork/' . $raw . '/preview');
    $t->equals('image/jpeg', $c->headers['content-type'] ?? '');
    $c->post('/artwork/' . $raw, ['decision' => 'changes', 'feedback' => 'ok'], '/artwork/' . $raw);
    $c->follow();
    $t->contains('5 caractères minimum', $c->body, 'feedback required');
    $c->post('/artwork/' . $raw, ['decision' => 'changes', 'feedback' => 'Recentrer sur les visages svp'], '/artwork/' . $raw);
    $t->contains('Demande bien reçue', $c->body);
    $c->get('/artwork/' . $raw);
    $t->equals(404, $c->status, 'used link is dead');
    $o->get('/admin/artwork/' . $p6['item']);
    $t->contains('Recentrer sur les visages svp', $o->body, 'feedback visible to the studio');
    $o->post('/admin/artwork/' . $p6['item'] . '/proof', [], '/admin/artwork/' . $p6['item']);
    $o->follow();
    preg_match('#/artwork/([a-f0-9]{64})"#', $o->body, $m2);
    $c->post('/artwork/' . ($m2[1] ?? 'x'), ['decision' => 'approve'], '/artwork/' . ($m2[1] ?? 'x'));
    $t->contains('Merci, c’est validé', $c->body);
    $t->equals('approved_for_production', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $t->equals(1, (int) $q6("SELECT COUNT(*) FROM production_jobs WHERE order_id = {$p6['order']}"), 'production job created');
});
$t->test('inventory: create a stocked material, link it to canvas, movement refused below zero', function (TestRunner $t) use ($o, $pdo5, $q6): void {
    $o->post('/admin/inventory', ['sku' => 'CANVAS-380', 'name' => 'Toile canvas 380 g', 'category' => 'canvas', 'unit' => 'm2', 'min_stock' => '5', 'initial_stock' => '25,5', 'unit_cost' => '48', 'is_active' => '1'], '/admin/inventory/create');
    $mid = (int) $q6("SELECT id FROM materials WHERE sku = 'CANVAS-380'");
    $t->equals('/admin/inventory/' . $mid, $o->location());
    $t->equals('25.500', (string) $q6("SELECT stock_quantity FROM materials WHERE id = {$mid}"));
    $pdo5->exec("UPDATE product_materials SET inventory_material_id = {$mid} WHERE code = 'canvas'");
    $o->post('/admin/inventory/' . $mid . '/movements', ['type' => 'out', 'quantity' => '99'], '/admin/inventory/' . $mid);
    $o->follow();
    $t->contains('Stock insuffisant', $o->body);
    $o->post('/admin/inventory/' . $mid . '/movements', ['type' => 'waste', 'quantity' => '0,5', 'notes' => 'Chute'], '/admin/inventory/' . $mid);
    $t->equals('25.000', (string) $q6("SELECT stock_quantity FROM materials WHERE id = {$mid}"));
    $o->get('/admin/inventory');
    $t->contains('Toile canvas 380 g', $o->body);
});
$t->test('production board: work the stages, print consumes stock, order becomes ready to ship', function (TestRunner $t) use ($o, $q6, $pdo5, &$p6): void {
    $o->post('/admin/machines', ['code' => 'EPS-P9', 'name' => 'Epson P9500', 'machine_type' => 'printer', 'status' => 'active', 'hourly_cost' => '120'], '/admin/machines');
    $machine = (int) $q6("SELECT id FROM machines WHERE code = 'EPS-P9'");
    $job = (int) $q6("SELECT id FROM production_jobs WHERE order_id = {$p6['order']}");
    $o->get('/admin/production');
    $t->equals(200, $o->status);
    $t->contains((string) $q6("SELECT job_number FROM production_jobs WHERE id = {$job}"), $o->body);
    $o->post('/admin/production/jobs/' . $job, ['priority' => 'urgent', 'machine_id' => (string) $machine, 'labor_cost' => '35'], '/admin/production/jobs/' . $job);
    $t->equals('urgent', (string) $q6("SELECT priority FROM production_jobs WHERE id = {$job}"));
    $before = (float) $q6("SELECT stock_quantity FROM materials WHERE sku = 'CANVAS-380'");
    $guard = 0;
    while ($guard++ < 12) {
        $task = $pdo5->query("SELECT id, stage, status FROM production_tasks WHERE production_job_id = {$job} AND status IN ('pending','in_progress') ORDER BY sort_order LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$task || $task['stage'] === 'ship') {
            break;
        }
        if ($task['status'] === 'pending') {
            $o->post('/admin/production/jobs/' . $job . '/tasks/' . $task['id'], ['action' => 'start'], '/admin/production/jobs/' . $job);
        }
        $o->post('/admin/production/jobs/' . $job . '/tasks/' . $task['id'], ['action' => 'done'], '/admin/production/jobs/' . $job);
    }
    $after = (float) $q6("SELECT stock_quantity FROM materials WHERE sku = 'CANVAS-380'");
    $t->assert($after < $before, "canvas consumed ({$before} → {$after})");
    $t->assert((float) $q6("SELECT material_cost FROM production_jobs WHERE id = {$job}") > 0, 'material cost booked');
    $t->equals('ready_for_shipping', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $o->get('/admin/production/jobs/' . $job);
    $t->contains('Se termine automatiquement', $o->body);
});
$t->test('shipping with tracking e-mail, COD cash recorded, delivery', function (TestRunner $t) use ($o, $base, $q6, &$p6): void {
    $due = (string) $q6("SELECT balance_due FROM orders WHERE id = {$p6['order']}");
    $o->post('/admin/orders/' . $p6['order'] . '/shipments', ['courier' => 'Amana', 'tracking_number' => 'CB000111222MA', 'tracking_url' => 'http://insecure.example', 'cod_amount' => $due, 'mark_shipped' => '1', 'notify' => '1'], '/admin/orders/' . $p6['order']);
    $o->follow();
    $t->contains('https://', $o->body, 'validation message for non-https tracking link');
    $o->post('/admin/orders/' . $p6['order'] . '/shipments', ['courier' => 'Amana', 'tracking_number' => 'CB000111222MA', 'shipping_cost' => '40', 'cod_amount' => $due, 'mark_shipped' => '1', 'notify' => '1'], '/admin/orders/' . $p6['order']);
    $t->equals('shipped', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $t->contains('CB000111222MA', (string) $q6("SELECT body FROM notifications WHERE event = 'order_status_shipped' AND related_id = {$p6['order']}"), 'tracking number e-mailed');
    $t->equals('completed', (string) $q6("SELECT status FROM production_jobs WHERE order_id = {$p6['order']}"));
    $sid = (int) $q6("SELECT id FROM shipments WHERE order_id = {$p6['order']}");
    $o->post('/admin/orders/' . $p6['order'] . '/shipments/' . $sid, ['status' => 'delivered'], '/admin/orders/' . $p6['order']);
    $t->equals('delivered', (string) $q6("SELECT status FROM orders WHERE id = {$p6['order']}"));
    $o->post('/admin/orders/' . $p6['order'] . '/payments', ['amount' => '999999', 'payment_method_id' => (string) $q6("SELECT id FROM payment_methods WHERE code = 'cod'")], '/admin/orders/' . $p6['order']);
    $o->follow();
    $t->contains('dépasse le reste à payer', $o->body);
    $o->post('/admin/orders/' . $p6['order'] . '/payments', ['amount' => $due, 'payment_method_id' => (string) $q6("SELECT id FROM payment_methods WHERE code = 'cod'"), 'reference' => 'Amana COD'], '/admin/orders/' . $p6['order']);
    $t->equals('paid', (string) $q6("SELECT payment_status FROM orders WHERE id = {$p6['order']}"));
    $c = new Browser($base);
    $c->post('/track', ['number' => $p6['number'], 'contact' => 'nadia@client.test'], '/track');
    $c->follow();
    $t->contains('Livrée', $c->body, 'customer tracking reflects delivery');
});
$t->test('phone order with a catalogue line and a custom line', function (TestRunner $t) use ($o, $q6): void {
    $o->get('/admin/orders/create');
    $t->equals(200, $o->status);
    preg_match('#<option value="(\d+:\d*)"[^>]*>#', $o->body, $m);
    $o->post('/admin/orders', [
        'source' => 'phone', 'full_name' => 'Hôtel Oasis', 'phone' => '0522 33 44 55', 'email' => 'achat@oasis.test', 'city' => 'Marrakech', 'address' => 'Route de l’Ourika km 7',
        'locale' => 'fr', 'shipping' => '0', 'lines' => [
            ['product' => $m[1] ?? '', 'quantity' => '4', 'unit_price' => ''],
            ['product' => '', 'title' => 'Triptyque lobby 3×(60×90)', 'width_cm' => '190', 'height_cm' => '90', 'quantity' => '1', 'unit_price' => '4500'],
            ['product' => '', 'title' => '', 'quantity' => '1'],
        ],
    ], '/admin/orders/create');
    $t->assert(preg_match('#^/admin/orders/(\d+)$#', $o->location(), $mm) === 1, 'redirect to the order: ' . $o->location());
    $id = (int) ($mm[1] ?? 0);
    $t->equals('phone', (string) $q6("SELECT source FROM orders WHERE id = {$id}"));
    $t->equals('confirmed', (string) $q6("SELECT status FROM orders WHERE id = {$id}"));
    $t->equals(2, (int) $q6("SELECT COUNT(*) FROM order_items WHERE order_id = {$id}"), 'empty row ignored');
});
$t->test('suppliers & purchases: reception adds stock at purchase cost', function (TestRunner $t) use ($o, $q6): void {
    $o->post('/admin/suppliers', ['name' => 'Toiles du Maroc', 'city' => 'Casablanca', 'phone' => '0522000000', 'is_active' => '1'], '/admin/suppliers');
    $sup = (int) $q6("SELECT id FROM suppliers WHERE name = 'Toiles du Maroc'");
    $mid = (int) $q6("SELECT id FROM materials WHERE sku = 'CANVAS-380'");
    $stock = (float) $q6("SELECT stock_quantity FROM materials WHERE id = {$mid}");
    $o->post('/admin/purchases', ['supplier_id' => (string) $sup, 'purchase_date' => date('Y-m-d'), 'status' => 'ordered', 'lines' => [
        ['material_id' => (string) $mid, 'description' => '', 'quantity' => '50', 'unit_cost' => '52', 'tax_rate' => '20'],
    ]], '/admin/purchases/create');
    $t->assert(preg_match('#^/admin/purchases/(\d+)$#', $o->location(), $m) === 1, 'purchase saved');
    $pid = (int) ($m[1] ?? 0);
    $t->equals('3120.00', (string) $q6("SELECT total FROM purchases WHERE id = {$pid}"));
    $o->post('/admin/purchases/' . $pid . '/receive', [], '/admin/purchases/' . $pid);
    $t->equals(number_format($stock + 50, 3, '.', ''), (string) $q6("SELECT stock_quantity FROM materials WHERE id = {$mid}"));
    $o->post('/admin/purchases/' . $pid . '/pay', ['amount' => '3120', 'payment_method' => 'bank_transfer', 'paid_at' => date('Y-m-d')], '/admin/purchases/' . $pid);
    $t->equals('3120.00', (string) $q6("SELECT amount_paid FROM purchases WHERE id = {$pid}"));
    $o->get('/admin/suppliers');
    $t->contains('Toiles du Maroc', $o->body);
});
$t->test('RBAC: designer reviews artwork but cannot change orders, run production or see stock', function (TestRunner $t) use ($base, &$p6): void {
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    foreach (['/admin/orders', '/admin/orders/' . $p6['order'], '/admin/artwork', '/admin/production'] as $p) {
        $d->get($p);
        $t->equals(200, $d->status, $p);
    }
    foreach (['/admin/inventory', '/admin/purchases', '/admin/suppliers', '/admin/machines', '/admin/orders/create'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $d->post('/admin/orders/' . $p6['order'] . '/status', ['status' => 'returned'], '/admin/orders');
    $t->equals(403, $d->status, 'status change forbidden');
    $d->post('/admin/orders/' . $p6['order'] . '/payments', ['amount' => '1', 'payment_method_id' => '1'], '/admin/orders');
    $t->equals(403, $d->status, 'payments forbidden');
});
$t->test('Phase 6 admin pages render in Arabic (RTL)', function (TestRunner $t) use ($o, &$p6): void {
    $o->get('/lang/ar');
    foreach (['/admin/orders', '/admin/orders/' . $p6['order'], '/admin/artwork/' . $p6['item'], '/admin/production', '/admin/inventory', '/admin/purchases', '/admin/suppliers', '/admin/machines', '/admin/orders/create'] as $p) {
        $o->get($p);
        $t->equals(200, $o->status, $p);
        $t->contains('dir="rtl"', $o->body, $p);
    }
    $o->get('/lang/fr');
});

$t->section('Quotations, invoices, credit notes, payments & finance (Phase 7)');
$p7 = ['quote' => 0, 'order' => 0, 'invoice' => 0];
$t->test('quotation: create, send, customer views the PDF and accepts online, converted into an order', function (TestRunner $t) use ($o, $base, $q6, &$p7): void {
    $o->post('/admin/quotations', [
        'customer_name' => 'Yassine Alami', 'customer_company' => 'Hôtel Kasbah', 'customer_ice' => '001234567000088', 'customer_email' => 'yassine@kasbah.test',
        'customer_phone' => '0524 11 22 33', 'customer_address' => 'Route d’Amizmiz, Marrakech', 'issue_date' => date('Y-m-d'), 'valid_until' => date('Y-m-d', strtotime('+15 days')),
        'locale' => 'fr', 'terms' => 'Acompte 50 %', 'lines' => [
            ['description' => 'Toiles chambres 40×60', 'specifications' => 'Toile canvas, bords miroir', 'quantity' => '20', 'unit_price' => '250', 'tax_rate' => '20'],
            ['description' => 'Grand format lobby 150×100', 'quantity' => '1', 'unit_price' => '2 400,00', 'discount' => '200', 'tax_rate' => '20'],
            ['description' => '', 'unit_price' => ''],
        ],
    ], '/admin/quotations/create');
    $t->assert(preg_match('#^/admin/quotations/(\d+)$#', $o->location(), $m) === 1, 'saved: ' . $o->location());
    $p7['quote'] = (int) ($m[1] ?? 0);
    $t->equals('8640.00', (string) $q6("SELECT total FROM quotations WHERE id = {$p7['quote']}"), '(5000 + 2200) × 1.2');
    $o->get('/admin/quotations/' . $p7['quote'] . '/pdf');
    $t->equals('application/pdf', $o->headers['content-type'] ?? '');
    $t->assert(str_starts_with($o->body, '%PDF-'), 'quotation PDF');
    $o->post('/admin/quotations/' . $p7['quote'] . '/send', ['message' => 'Comme convenu au téléphone.'], '/admin/quotations/' . $p7['quote']);
    $o->follow();
    $t->assert(preg_match('#value="https?://[^"]+/quote/([a-f0-9]{64})"#', $o->body, $mm) === 1, 'share link shown once');
    $raw = $mm[1] ?? '';
    $t->contains('/quote/' . $raw, (string) $q6("SELECT body FROM notifications WHERE event = 'quotation_sent' AND related_id = {$p7['quote']}"), 'e-mailed link');
    $c = new Browser($base);
    $c->get('/quote/' . $raw);
    $t->equals(200, $c->status);
    $t->contains('Toiles chambres 40×60', $c->body);
    $t->contains('noindex', strtolower($c->headers['x-robots-tag'] ?? ''));
    $c->get('/quote/' . $raw . '/pdf');
    $t->assert(str_starts_with($c->body, '%PDF-'), 'customer PDF');
    $c->get('/quote/' . str_repeat('0', 64));
    $t->equals(404, $c->status);
    $c->post('/quote/' . $raw, ['decision' => 'accept', 'comment' => 'Livraison avant le 15 svp'], '/quote/' . $raw);
    $c->follow();
    $t->contains('Merci !', $c->body);
    $t->equals('accepted', (string) $q6("SELECT status FROM quotations WHERE id = {$p7['quote']}"));
    $o->post('/admin/quotations/' . $p7['quote'] . '/convert', ['address' => 'Route d’Amizmiz km 12', 'city' => 'Marrakech', 'phone' => '0524112233'], '/admin/quotations/' . $p7['quote']);
    $t->assert(preg_match('#^/admin/orders/(\d+)$#', $o->location(), $om) === 1, 'converted: ' . $o->location());
    $p7['order'] = (int) ($om[1] ?? 0);
    $t->equals('8640.00', (string) $q6("SELECT grand_total FROM orders WHERE id = {$p7['order']}"));
    $t->equals('converted', (string) $q6("SELECT status FROM quotations WHERE id = {$p7['quote']}"));
});
$t->test('invoice from the order: draft without number, issue + e-mail, integrity badge, PDF, private customer link', function (TestRunner $t) use ($o, $base, $q6, &$p7): void {
    $o->get('/admin/orders/' . $p7['order']);
    $t->contains('Créer la facture', $o->body);
    $o->post('/admin/invoices/from-order/' . $p7['order'], [], '/admin/orders/' . $p7['order']);
    $t->assert(preg_match('#^/admin/invoices/(\d+)$#', $o->location(), $m) === 1, 'draft: ' . $o->location());
    $p7['invoice'] = (int) ($m[1] ?? 0);
    $t->equals(null, $q6("SELECT invoice_number FROM invoices WHERE id = {$p7['invoice']}") ?: null);
    $o->post('/admin/invoices/' . $p7['invoice'], ['customer_name' => 'Yassine Alami', 'customer_company' => 'Hôtel Kasbah SARL', 'customer_ice' => '001234567000088', 'customer_email' => 'yassine@kasbah.test', 'locale' => 'fr'], '/admin/invoices/' . $p7['invoice'] . '/edit');
    $t->equals('Hôtel Kasbah SARL', (string) $q6("SELECT customer_company FROM invoices WHERE id = {$p7['invoice']}"));
    $o->post('/admin/invoices/' . $p7['invoice'] . '/issue', ['send' => '1'], '/admin/invoices/' . $p7['invoice']);
    $o->follow();
    $number = (string) $q6("SELECT invoice_number FROM invoices WHERE id = {$p7['invoice']}");
    $t->assert(preg_match('/^FAC-' . date('Y') . '-\d{6}$/', $number) === 1, 'numbered at issue: ' . $number);
    $t->contains('Intègre', $o->body);
    $t->equals('8640.00', (string) $q6("SELECT total_ttc FROM invoices WHERE id = {$p7['invoice']}"));
    $o->get('/admin/invoices/' . $p7['invoice'] . '/pdf');
    $t->assert(str_starts_with($o->body, '%PDF-'), 'invoice PDF');
    $body = (string) $q6("SELECT body FROM notifications WHERE event = 'invoice_sent' AND related_id = {$p7['invoice']}");
    $t->assert(preg_match('#/invoice/' . $p7['invoice'] . '/([a-f0-9]{64})#', $body, $lm) === 1, 'download link e-mailed');
    $c = new Browser($base);
    $c->get('/invoice/' . $p7['invoice'] . '/' . ($lm[1] ?? ''));
    $t->equals('application/pdf', $c->headers['content-type'] ?? '');
    $c->get('/invoice/' . $p7['invoice'] . '/' . str_repeat('a', 64));
    $t->equals(404, $c->status, 'wrong token');
    $o->post('/admin/invoices/' . $p7['invoice'] . '/delete', [], '/admin/invoices/' . $p7['invoice']);
    $o->follow();
    $t->contains('un document émis se corrige par un avoir', $o->body);
    $t->equals(1, (int) $q6("SELECT COUNT(*) FROM invoices WHERE id = {$p7['invoice']}"), 'issued invoice kept');
    $o->post('/admin/invoices/from-order/' . $p7['order'], [], '/admin/orders/' . $p7['order']);
    $o->follow();
    $t->contains('déjà une facture', $o->body, 'one invoice per order');
});
$t->test('payment on the order updates the invoice; partial then final credit note; refund capped', function (TestRunner $t) use ($o, $q6, &$p7): void {
    $bank = (string) $q6("SELECT id FROM payment_methods WHERE code = 'bank_transfer'");
    $o->post('/admin/orders/' . $p7['order'] . '/payments', ['amount' => '4320', 'payment_method_id' => $bank, 'reference' => 'Acompte 50 %'], '/admin/orders/' . $p7['order']);
    $t->equals('partially_paid', (string) $q6("SELECT status FROM invoices WHERE id = {$p7['invoice']}"));
    $o->post('/admin/invoices/' . $p7['invoice'] . '/credit', ['mode' => 'partial', 'amount' => '240', 'reason' => 'Geste commercial'], '/admin/invoices/' . $p7['invoice']);
    $t->assert(preg_match('#^/admin/invoices/(\d+)$#', $o->location(), $m) === 1 && (int) $m[1] !== $p7['invoice'], 'credit note page');
    $t->assert(preg_match('/^AV-' . date('Y') . '-\d{6}$/', (string) $q6('SELECT invoice_number FROM invoices WHERE id = ' . (int) $m[1])) === 1, 'AV number');
    $o->post('/admin/orders/' . $p7['order'] . '/refunds', ['amount' => '5000', 'payment_method_id' => $bank, 'reason' => 'Trop'], '/admin/orders/' . $p7['order']);
    $o->follow();
    $t->contains('ne peut pas dépasser le montant encaissé', $o->body);
    $o->post('/admin/orders/' . $p7['order'] . '/refunds', ['amount' => '240', 'payment_method_id' => $bank, 'reason' => 'Geste commercial remboursé'], '/admin/orders/' . $p7['order']);
    $t->equals('partially_refunded', (string) $q6("SELECT payment_status FROM orders WHERE id = {$p7['order']}"));
    $o->post('/admin/invoices/' . $p7['invoice'] . '/credit', ['mode' => 'partial', 'amount' => '8400', 'reason' => 'Annulation du solde'], '/admin/invoices/' . $p7['invoice']);
    $t->equals('cancelled', (string) $q6("SELECT status FROM invoices WHERE id = {$p7['invoice']}"), 'fully credited → cancelled, never deleted');
    $o->get('/admin/invoices/' . $p7['invoice']);
    $t->contains('Facture annulée par avoir', $o->body);
    $o->get('/admin/payments?type=refund');
    $t->contains('Geste commercial remboursé', $o->body);
});
$t->test('standalone invoice: typed lines, issue, payment on the invoice, customer download from the account', function (TestRunner $t) use ($o, $base, $q6): void {
    $salma = (int) $q6("SELECT id FROM customers WHERE email = 'salma@client.test'");
    $o->post('/admin/invoices', ['customer_id' => (string) $salma, 'customer_name' => 'Salma Alaoui', 'customer_email' => 'salma@client.test', 'locale' => 'ar', 'lines' => [
        ['description' => 'Atelier décoration (2 h)', 'quantity' => '2', 'unit_price' => '300', 'tax_rate' => '20'],
    ]], '/admin/invoices/create');
    $t->assert(preg_match('#^/admin/invoices/(\d+)$#', $o->location(), $m) === 1, 'draft saved');
    $id = (int) ($m[1] ?? 0);
    $o->post('/admin/invoices/' . $id . '/issue', [], '/admin/invoices/' . $id);
    $t->equals('720.00', (string) $q6("SELECT total_ttc FROM invoices WHERE id = {$id}"));
    $o->get('/admin/invoices/' . $id . '/pdf');
    $t->assert(str_starts_with($o->body, '%PDF-'), 'Arabic invoice PDF');
    $o->post('/admin/invoices/' . $id . '/payments', ['amount' => '720', 'payment_method_id' => (string) $q6("SELECT id FROM payment_methods WHERE code = 'cod'")], '/admin/invoices/' . $id);
    $t->equals('paid', (string) $q6("SELECT status FROM invoices WHERE id = {$id}"));
    $c = new Browser($base);
    $c->post('/account/login', ['email' => 'salma@client.test', 'password' => 'Client2026'], '/account/login');
    $c->get('/account/invoices/' . $id);
    $t->equals('application/pdf', $c->headers['content-type'] ?? '', 'own invoice');
    $x = new Browser($base);
    $x->post('/account/login', ['email' => 'other@client.test', 'password' => 'Client2026'], '/account/login');
    $x->get('/account/invoices/' . $id);
    $t->equals(404, $x->status, 'not another customer\'s');
});
$t->test('expenses with a private receipt; finance overview from recorded documents', function (TestRunner $t) use ($o, $q6): void {
    $pdf = sys_get_temp_dir() . '/bwa-receipt.pdf';
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $o->post('/admin/expenses', ['expense_date' => date('Y-m-d'), 'category' => 'rent', 'description' => 'Loyer atelier', 'amount' => '6 000,00', 'tax_amount' => '1000', 'payment_method' => 'bank_transfer'],
        '/admin/expenses', ['receipt' => [$pdf, 'application/pdf', 'loyer.pdf']]);
    $id = (int) $q6("SELECT id FROM expenses WHERE description = 'Loyer atelier' ORDER BY id DESC LIMIT 1");
    $t->assert($id > 0, 'expense saved');
    $t->equals('6000.00', (string) $q6("SELECT amount FROM expenses WHERE id = {$id}"));
    $o->get('/admin/expenses/' . $id . '/receipt');
    $t->equals('application/pdf', $o->headers['content-type'] ?? '');
    $o->post('/admin/expenses', ['expense_date' => date('Y-m-d'), 'category' => 'rent', 'description' => 'Erreur', 'amount' => '100', 'tax_amount' => '100'], '/admin/expenses');
    $o->follow();
    $t->contains('La TVA doit être inférieure', $o->body);
    $o->get('/admin/finance');
    $t->equals(200, $o->status);
    $t->contains('TVA collectée', $o->body);
    $t->contains('id="dashboard-data"', $o->body);
    $t->contains('Loyer', $o->body);
    $o->get('/admin/finance?period=year');
    $t->equals(200, $o->status);
    @unlink($pdf);
});
$t->test('RBAC: designer has no access to documents and finance; Arabic rendering of Phase 7 pages', function (TestRunner $t) use ($base, $o, &$p7): void {
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    foreach (['/admin/quotations', '/admin/invoices', '/admin/payments', '/admin/expenses', '/admin/finance', '/admin/invoices/' . $p7['invoice'] . '/pdf'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $o->get('/lang/ar');
    foreach (['/admin/quotations', '/admin/quotations/' . $p7['quote'], '/admin/quotations/create', '/admin/invoices', '/admin/invoices/' . $p7['invoice'], '/admin/invoices/create', '/admin/payments', '/admin/expenses', '/admin/finance'] as $p) {
        $o->get($p);
        $t->equals(200, $o->status, $p);
        $t->contains('dir="rtl"', $o->body, $p);
    }
    $o->get('/lang/fr');
});

$t->section('SEO, reports, notifications, backups & security (Phase 8)');
$p8 = (string) $q6("SELECT p.slug FROM products p WHERE p.is_active = 1 AND p.deleted_at IS NULL AND p.is_customizable = 0 AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) ORDER BY p.id LIMIT 1");
$t->test('SEO: hreflang + localized canonical, sitemap.xml, robots.txt, noindex on private areas', function (TestRunner $t) use ($base, $p8): void {
    $g = new Browser($base);
    $g->get('/product/' . $p8);
    $t->contains('hreflang="ar" href="' . $base . '/product/' . $p8 . '?lang=ar"', $g->body);
    $t->contains('rel="canonical" href="' . $base . '/product/' . $p8 . '"', $g->body);
    $g->get('/product/' . $p8 . '?lang=ar');
    $t->contains('rel="canonical" href="' . $base . '/product/' . $p8 . '?lang=ar"', $g->body, 'Arabic page canonical');
    $t->contains('dir="rtl"', $g->body);
    $g->get('/lang/fr');
    $g->get('/sitemap.xml');
    $t->equals(200, $g->status);
    $t->contains('application/xml', $g->headers['content-type'] ?? '');
    $t->assert(simplexml_load_string($g->body) !== false, 'valid XML');
    $t->contains('/product/' . $p8 . '?lang=ar</loc>', $g->body);
    $g->get('/robots.txt');
    $t->contains('Sitemap: ' . $base . '/sitemap.xml', $g->body);
    $t->assert(!str_contains($g->body, 'Disallow: /admin'), 'admin path not advertised');
    $g->get('/cart');
    $t->contains('noindex', $g->headers['x-robots-tag'] ?? '');
    $g->get('/admin/login');
    $t->contains('noindex', $g->headers['x-robots-tag'] ?? '');
    $g->get('/shop');
    $t->assert(!isset($g->headers['x-robots-tag']), 'shop indexable');
});
$t->test('open redirect via a look-alike Referer is refused', function (TestRunner $t) use ($base): void {
    $g = new Browser($base);
    $g->request('GET', '/lang/ar', null, [], ['Referer: ' . $base . '.evil.example/phish']);
    $t->assert(!str_contains($g->location(), 'evil'), 'stays on the site: ' . ($g->headers['location'] ?? ''));
    $g->request('GET', '/lang/fr', null, [], ['Referer: ' . $base . '/shop']);
    $t->equals('/shop', $g->location());
});
$t->test('reports: every tab renders, CSV export downloads', function (TestRunner $t) use ($o): void {
    foreach (['sales', 'products', 'categories', 'customers', 'cities', 'channels', 'production', 'inventory', 'expenses', 'catalogue'] as $r) {
        $o->get('/admin/reports?report=' . $r . '&period=year');
        $t->equals(200, $o->status, $r);
    }
    $o->get('/admin/reports?report=sales&period=year&group=month');
    $t->contains('id="dashboard-data"', $o->body);
    $o->get('/admin/reports?report=products&period=year&export=csv');
    $t->contains('text/csv', $o->headers['content-type'] ?? '');
    $t->contains('attachment', $o->headers['content-disposition'] ?? '');
    $t->assert(str_starts_with($o->body, "\xEF\xBB\xBF"), 'BOM for Excel');
    $t->contains(';', $o->body);
});
$t->test('notifications: a storefront order alerts the team; opening marks it read and lands on the order', function (TestRunner $t) use ($o, $base, $q6, $p8): void {
    $o->post('/admin/notifications/read-all', [], '/admin');
    $g = new Browser($base);
    $pid = (string) $q6("SELECT id FROM products WHERE slug = '{$p8}'");
    $g->post('/cart/add', ['product_id' => $pid, 'variant_id' => (string) $q6("SELECT id FROM product_variants WHERE product_id = {$pid} AND is_active = 1 ORDER BY sort_order LIMIT 1"), 'quantity' => '1'], '/product/' . $p8);
    $g->post('/checkout', ['full_name' => 'Omar Tazi', 'phone' => '0661 00 11 22', 'email' => 'omar@client.test', 'country' => 'MA', 'city' => 'Fès',
        'address' => '3 rue Talaa Kebira', 'payment_method' => 'cod', 'accept_terms' => '1'], '/checkout');
    $number = substr($g->location(), strlen('/order/confirmation/'));
    $t->assert(str_starts_with($number, 'BWA-'), 'order placed');
    $o->get('/admin');
    $t->contains('class="notif-count"', $o->body, 'unread badge');
    $t->contains($number, $o->body, 'listed in the bell');
    $nid = (string) $q6("SELECT n.id FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.email = 'owner@berradi.test' AND n.event = 'order_new' ORDER BY n.id DESC LIMIT 1");
    $o->get('/admin/notifications/' . $nid);
    $t->assert(preg_match('#^/admin/orders/\d+$#', $o->location()) === 1, 'goes to the order: ' . $o->location());
    $t->equals('read', (string) $q6("SELECT status FROM notifications WHERE id = {$nid}"));
    $o->get('/admin/notifications');
    $t->contains($number, $o->body);
    $o->get('/admin/notifications/emails');
    $t->equals(200, $o->status);
    $t->contains('omar@client.test', $o->body, 'customer confirmation logged');
    $eid = (string) $q6("SELECT id FROM notifications WHERE channel = 'email' AND recipient = 'omar@client.test' ORDER BY id DESC LIMIT 1");
    $o->get('/admin/notifications/emails/' . $eid);
    $t->contains($number, $o->body);
    $t->assert(!str_contains($o->body, '<table role="presentation"'), 'stored HTML shown as text, not rendered');
});
$t->test('backups: create, list, download (gzip), delete; customer data export', function (TestRunner $t) use ($o, $q6): void {
    $o->post('/admin/backups', [], '/admin/backups');
    $o->follow();
    $t->assert(preg_match('#(bwa-db-\d{8}-\d{6}-[a-f0-9]{8}\.sql\.gz)#', $o->body, $m) === 1, 'listed');
    $o->get('/admin/backups/' . $m[1]);
    $t->equals('application/gzip', $o->headers['content-type'] ?? '');
    $t->assert(str_contains((string) gzdecode($o->body), 'CREATE TABLE `orders`'), 'real dump');
    $o->get('/admin/backups/..%2F..%2F.env');
    $t->assert(in_array($o->status, [403, 404], true), 'no traversal (' . $o->status . ')');
    $o->get('/admin/backups/bwa-db-20200101-000000-deadbeef.sql.gz');
    $t->equals(404, $o->status, 'unknown backup');
    $o->post('/admin/backups/' . $m[1] . '/delete', [], '/admin/backups');
    $o->get('/admin/backups');
    $t->assert(!str_contains($o->body, $m[1]), 'deleted');
    $o->post('/admin/backups/settings', ['backup_auto' => '1', 'backup_keep' => '7', 'retention_unordered_days' => '30', 'retention_ordered_days' => '365', 'retention_logs_days' => '365', 'retention_notifications_days' => '180', 'retention_carts_days' => '60'], '/admin/backups');
    $t->equals('60', (string) $q6("SELECT setting_value FROM settings WHERE setting_key = 'retention_carts_days'"));
    $cid = (string) $q6("SELECT id FROM customers WHERE email = 'salma@client.test'");
    $o->get('/admin/customers/' . $cid . '/export');
    $t->contains('application/json', $o->headers['content-type'] ?? '');
    $data = json_decode($o->body, true);
    $t->equals('salma@client.test', $data['customer']['email'] ?? null);
    $t->assert(!str_contains($o->body, 'password_hash'), 'no password hash');
});
$t->test('maintenance shows the security self-check; RBAC on Phase 8 pages; Arabic rendering', function (TestRunner $t) use ($o, $base): void {
    $o->get('/admin/maintenance');
    $t->contains('Contrôle de sécurité', $o->body);
    $t->contains('Installateur verrouillé', $o->body);
    $d = new Browser($base);
    $d->post('/admin/login', ['email' => 'designer@berradi.test', 'password' => 'Design2026'], '/admin/login');
    foreach (['/admin/reports', '/admin/backups', '/admin/notifications/emails'] as $p) {
        $d->get($p);
        $t->equals(403, $d->status, $p);
    }
    $d->get('/admin/notifications');
    $t->equals(200, $d->status, 'own notifications');
    $o->get('/lang/ar');
    foreach (['/admin/reports', '/admin/reports?report=inventory', '/admin/notifications', '/admin/notifications/emails', '/admin/backups', '/admin/maintenance'] as $p) {
        $o->get($p);
        $t->equals(200, $o->status, $p);
        $t->contains('dir="rtl"', $o->body, $p);
    }
    $o->get('/lang/fr');
});

exit($t->finish());
