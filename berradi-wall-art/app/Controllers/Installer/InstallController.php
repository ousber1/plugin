<?php

declare(strict_types=1);

namespace App\Controllers\Installer;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\ImageUploadService;
use App\Services\Installer\InstallerService;
use App\Services\Installer\RequirementsChecker;
use App\Services\SettingsService;
use App\Services\UploadException;

/**
 * 10-step installation wizard. Locked permanently by storage/installed.lock (InstallerMiddleware).
 */
final class InstallController extends Controller
{
    public const STEPS = [
        1 => 'welcome',
        2 => 'server',
        3 => 'extensions',
        4 => 'database',
        5 => 'connection',
        6 => 'install',
        7 => 'company',
        8 => 'admin',
        9 => 'finish',
        10 => 'locked',
    ];

    /** @return array<string,mixed> */
    private function state(): array
    {
        $s = Session::get('installer');
        return is_array($s) ? $s : ['max_step' => 1];
    }

    /** @param array<string,mixed> $values */
    private function save(array $values): void
    {
        Session::put('installer', array_merge($this->state(), $values));
    }

    private function unlock(int $step): void
    {
        $max = (int) ($this->state()['max_step'] ?? 1);
        if ($step > $max) {
            $this->save(['max_step' => $step]);
        }
    }

    private function service(): InstallerService
    {
        return new InstallerService(app()->basePath());
    }

    private function checker(): RequirementsChecker
    {
        return new RequirementsChecker(app()->basePath(), app()->publicPath());
    }

    private function applyLocale(): void
    {
        $locale = (string) ($this->state()['locale'] ?? '');
        if (in_array($locale, Translator::SUPPORTED, true) && $this->request->query('lang') === null) {
            Translator::setLocale($locale);
            View::share('locale', $locale);
            View::share('dir', Translator::dir());
        }
    }

    public function index(): Response
    {
        return $this->redirect(route('installer.step', ['step' => 1]));
    }

    public function show(string $step): Response
    {
        $n = (int) $step;
        if (!isset(self::STEPS[$n]) || $n === 10) {
            throw new HttpException(404);
        }
        if ($n > (int) ($this->state()['max_step'] ?? 1)) {
            return $this->redirect(route('installer.step', ['step' => (int) ($this->state()['max_step'] ?? 1)]));
        }
        $this->applyLocale();
        $data = ['step' => $n, 'steps' => self::STEPS, 'state' => $this->state()];

        switch ($n) {
            case 2:
                $data['rows'] = $this->checker()->server();
                $data['passes'] = RequirementsChecker::passes($data['rows']);
                break;
            case 3:
                $data['extensions'] = $this->checker()->extensions();
                $data['permissions'] = $this->checker()->permissions();
                $data['passes'] = RequirementsChecker::passes(array_merge($data['extensions'], $data['permissions']));
                break;
            case 4:
                $db = $this->state()['db'] ?? [];
                $data['db'] = array_merge(['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '', 'password' => ''], is_array($db) ? $db : []);
                $data['app_url'] = $this->state()['app_url'] ?? $this->request->rootUrl();
                break;
            case 5:
                $cfg = $this->state()['db'] ?? null;
                if (!is_array($cfg)) {
                    return $this->redirect(route('installer.step', ['step' => 4]));
                }
                $data['result'] = $this->service()->testConnection($cfg);
                $data['db'] = $cfg;
                break;
            case 6:
                $data['log'] = Session::getFlash('install_log', []);
                $data['manualEnv'] = Session::getFlash('manual_env');
                $data['installed'] = (bool) ($this->state()['db_installed'] ?? false);
                break;
            case 7:
                $data['company'] = $this->companyDefaults();
                break;
            case 8:
                break;
            case 9:
                $data['cronCommand'] = 'php ' . app()->basePath('bin/console') . ' cron:run';
                break;
        }
        return $this->view('installer/step', $data);
    }

    public function store(string $step): Response
    {
        $n = (int) $step;
        if (!isset(self::STEPS[$n]) || $n > (int) ($this->state()['max_step'] ?? 1)) {
            throw new HttpException(404);
        }
        $this->applyLocale();
        return match ($n) {
            1 => $this->storeWelcome(),
            2 => $this->storeServer(),
            3 => $this->storeExtensions(),
            4 => $this->storeDatabase(),
            5 => $this->storeConnection(),
            6 => $this->storeInstall(),
            7 => $this->storeCompany(),
            8 => $this->storeAdmin(),
            9 => $this->storeFinish(),
            default => throw new HttpException(404),
        };
    }

    private function next(int $step): Response
    {
        $this->unlock($step);
        return $this->redirect(route('installer.step', ['step' => $step]));
    }

    private function storeWelcome(): Response
    {
        $data = $this->validate(['locale' => 'required|locale']);
        $this->save(['locale' => $data['locale']]);
        Session::put('locale', $data['locale']);
        return $this->next(2);
    }

    private function storeServer(): Response
    {
        if (!RequirementsChecker::passes($this->checker()->server())) {
            $this->flash('danger', __('installer.fix_requirements'));
            return $this->redirect(route('installer.step', ['step' => 2]));
        }
        return $this->next(3);
    }

    private function storeExtensions(): Response
    {
        $rows = array_merge($this->checker()->extensions(), $this->checker()->permissions());
        if (!RequirementsChecker::passes($rows)) {
            $this->flash('danger', __('installer.fix_requirements'));
            return $this->redirect(route('installer.step', ['step' => 3]));
        }
        return $this->next(4);
    }

    private function storeDatabase(): Response
    {
        $data = $this->validate([
            'db_host' => 'required|max:191|regex:/^[A-Za-z0-9.\-_:]+$/',
            'db_port' => 'required|integer|between:1,65535',
            'db_database' => 'required|max:64|regex:/^[A-Za-z0-9_$\-]+$/',
            'db_username' => 'required|max:80',
            'app_url' => 'required|url|max:191',
        ], [
            'db_host' => __('installer.db_host'),
            'db_port' => __('installer.db_port'),
            'db_database' => __('installer.db_name'),
            'db_username' => __('installer.db_user'),
            'app_url' => __('installer.app_url'),
        ]);
        $password = $this->request->input('db_password', '');
        $this->save([
            'db' => [
                'host' => $data['db_host'],
                'port' => (int) $data['db_port'],
                'database' => $data['db_database'],
                'username' => $data['db_username'],
                'password' => is_string($password) ? $password : '',
            ],
            'app_url' => rtrim((string) $data['app_url'], '/'),
            'db_ok' => false,
        ]);
        return $this->next(5);
    }

    private function storeConnection(): Response
    {
        $cfg = $this->state()['db'] ?? null;
        if (!is_array($cfg)) {
            return $this->redirect(route('installer.step', ['step' => 4]));
        }
        $result = $this->service()->testConnection($cfg);
        if (!$result['ok']) {
            $this->flash('danger', __('installer.db_failed'));
            return $this->redirect(route('installer.step', ['step' => 5]));
        }
        $this->save(['db_ok' => true]);
        return $this->next(6);
    }

    private function storeInstall(): Response
    {
        $state = $this->state();
        $cfg = $state['db'] ?? null;
        if (!is_array($cfg) || empty($state['db_ok'])) {
            return $this->redirect(route('installer.step', ['step' => 5]));
        }
        $service = $this->service();
        $envFile = app()->basePath('.env');

        // keep an existing APP_KEY if re-running the step (encrypted settings stay readable)
        $existingKey = is_file($envFile) ? (string) Env::get('APP_KEY', '') : '';
        $values = $service->envValues($cfg, (string) ($state['app_url'] ?? $this->request->rootUrl()), (string) ($state['locale'] ?? 'fr'), $existingKey ?: ($state['app_key'] ?? null));
        $this->save(['app_key' => $values['APP_KEY']]);

        if (!$service->writeEnv($values)) {
            // .env not writable: accept a manually created file that matches the chosen database
            $manualOk = false;
            if (is_file($envFile)) {
                Env::reset();
                Env::load($envFile);
                $manualOk = Env::get('DB_DATABASE') === $cfg['database'] && preg_match('/^[0-9a-f]{64}$/', (string) Env::get('APP_KEY', ''));
            }
            if (!$manualOk) {
                Session::flash('manual_env', $service->envContent($values));
                $this->flash('warning', __('installer.env_manual'));
                return $this->redirect(route('installer.step', ['step' => 6]));
            }
        }
        Config::set('app.key', (string) $values['APP_KEY']);

        try {
            $db = new Database(Database::connect($cfg));
            Database::setInstance($db);
            $log = $service->installDatabase($db);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Installer database step failed: ' . $e->getMessage());
            $this->flash('danger', __('installer.install_failed') . ' ' . $e->getMessage());
            return $this->redirect(route('installer.step', ['step' => 6]));
        }
        Session::flash('install_log', $log);
        $this->save(['db_installed' => true]);
        $this->unlock(7);
        $this->flash('success', __('installer.install_ok'));
        return $this->redirect(route('installer.step', ['step' => 6]));
    }

    /** @return array<string,mixed> */
    private function companyDefaults(): array
    {
        $saved = $this->state()['company'] ?? [];
        return array_merge([
            'company_name' => 'BERRADI WALL ART',
            'company_email' => '',
            'company_phone' => '',
            'company_whatsapp' => '',
            'company_address' => '',
            'company_city' => '',
            'currency_code' => 'MAD',
            'currency_symbol' => 'DH',
            'default_language' => $this->state()['locale'] ?? 'fr',
            'tax_default_rate' => '20',
            'invoice_prefix' => 'FAC',
            'quotation_prefix' => 'DEV',
            'order_prefix' => 'BWA',
            'shipping_flat_rate' => '40.00',
            'shipping_free_threshold' => '1000.00',
        ], is_array($saved) ? $saved : []);
    }

    private function db(): Database
    {
        $cfg = $this->state()['db'] ?? null;
        if (is_array($cfg)) {
            $db = new Database(Database::connect($cfg));
            Database::setInstance($db);
            return $db;
        }
        return Database::instance();
    }

    private function storeCompany(): Response
    {
        $data = $this->validate([
            'company_name' => 'required|max:150',
            'company_email' => 'required|email',
            'company_phone' => 'required|phone',
            'company_whatsapp' => 'nullable|phone',
            'company_address' => 'nullable|max:500',
            'company_city' => 'nullable|max:100',
            'currency_code' => 'required|in:MAD',
            'currency_symbol' => 'required|in:DH,MAD,د.م.',
            'default_language' => 'required|locale',
            'tax_default_rate' => 'required|decimal:2|between:0,100',
            'invoice_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
            'quotation_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
            'order_prefix' => 'required|max:10|regex:/^[A-Za-z0-9]+$/',
            'shipping_flat_rate' => 'required|decimal:2|min:0',
            'shipping_free_threshold' => 'nullable|decimal:2|min:0',
        ]);
        $this->db();
        SettingsService::flush();
        $values = $data;
        $values['shipping_free_threshold'] = $data['shipping_free_threshold'] ?? '';
        $logo = $this->request->file('company_logo');
        if ($logo !== null && (int) ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $values['company_logo'] = ImageUploadService::storePublic($logo, 'branding', 800);
            } catch (UploadException $e) {
                throw new \App\Core\ValidationException(['company_logo' => $e->getMessage()], $this->request->all());
            }
        }
        $values['mail_from_name'] = $data['company_name'];
        $values['mail_from_address'] = $data['company_email'];
        $values['admin_notification_email'] = $data['company_email'];
        SettingsService::setMany($values);
        $this->save(['company' => $data]);
        return $this->next(8);
    }

    private function storeAdmin(): Response
    {
        $data = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email',
            'password' => 'required|password|confirmed|max:128',
        ], [
            'name' => __('installer.admin_name'),
        ]);
        $db = $this->db();
        $id = $this->service()->createAdmin($db, (string) $data['name'], (string) $data['email'], (string) $data['password'], (string) ($this->state()['locale'] ?? 'fr'));
        $this->save(['admin_email' => mb_strtolower((string) $data['email']), 'admin_id' => $id]);
        return $this->next(9);
    }

    private function storeFinish(): Response
    {
        if (empty($this->state()['admin_id']) || empty($this->state()['db_installed'])) {
            return $this->redirect(route('installer.step', ['step' => 8]));
        }
        $demo = null;
        if ($this->request->bool('install_demo')) {
            try {
                $demo = (new \App\Services\Demo\DemoCatalog($this->db(), app()->publicPath(), app()->basePath()))->install();
            } catch (\Throwable $e) {
                \App\Core\Logger::error('Demo catalogue install failed: ' . $e->getMessage());
                $this->flash('danger', __('installer.demo_failed') . ' ' . $e->getMessage());
                return $this->redirect(route('installer.step', ['step' => 9]));
            }
        }
        if (!$this->service()->lock((string) Config::get('app.version', '1.0.0'))) {
            $this->flash('danger', __('installer.lock_failed'));
            return $this->redirect(route('installer.step', ['step' => 9]));
        }
        $adminEmail = (string) ($this->state()['admin_email'] ?? '');
        Session::forget('installer'); // drop DB credentials from the session
        $this->db();
        return $this->view('installer/step', [
            'step' => 10,
            'steps' => self::STEPS,
            'state' => ['max_step' => 10],
            'adminEmail' => $adminEmail,
            'demo' => $demo,
            'adminUrl' => url(Config::get('app.admin_path', 'admin') . '/login'),
        ]);
    }
}
