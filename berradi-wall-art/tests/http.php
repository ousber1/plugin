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
        return $this->request('POST', $path, $data, $files);
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

exit($t->finish());
