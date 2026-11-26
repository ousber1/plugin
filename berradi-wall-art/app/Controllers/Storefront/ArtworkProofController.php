<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\Operations\ArtworkService;

/**
 * Customer proof page reached from the e-mail link: see the final mock-up, approve or ask for changes.
 * The link is single-use (only its hash is stored) and gives access to nothing else.
 */
final class ArtworkProofController extends Controller
{
    public function show(string $token): Response
    {
        $found = ArtworkService::findByToken($token);
        return $this->view('storefront/artwork_proof', ['found' => $found, 'token' => $token], $found === null ? 404 : 200)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function preview(string $token): Response
    {
        $found = ArtworkService::findByToken($token);
        $rel = $found !== null ? ArtworkService::previewPathFor($found['item']) : null;
        if ($rel === null) {
            throw new HttpException(404);
        }
        return ArtworkService::servePreview($rel);
    }

    public function respond(string $token): Response
    {
        $decision = $this->request->str('decision');
        $feedback = mb_substr($this->request->str('feedback'), 0, 2000);
        if (!in_array($decision, ['approve', 'changes'], true) || ($decision === 'changes' && mb_strlen($feedback) < 5)) {
            $this->flash('danger', __('artwork.proof_feedback_required'));
            return $this->redirect(url('artwork/' . $token));
        }
        $ok = ArtworkService::customerRespond($token, $decision === 'approve', $feedback);
        if (!$ok) {
            throw new HttpException(404);
        }
        return $this->view('storefront/artwork_proof_done', ['approved' => $decision === 'approve']);
    }
}
