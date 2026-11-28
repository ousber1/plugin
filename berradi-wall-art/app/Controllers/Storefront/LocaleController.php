<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;

final class LocaleController extends Controller
{
    public function switch(string $locale): Response
    {
        if (!in_array($locale, Translator::SUPPORTED, true)) {
            $locale = default_locale();
        }
        Session::put('locale', $locale);
        $response = $this->back();
        if (!headers_sent()) {
            setcookie('bwa_locale', $locale, [
                'expires' => time() + 31536000,
                'path' => ($this->request->basePath() ?: '') . '/',
                'secure' => $this->request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $response;
    }
}
