<?php

namespace App\Http\Controllers;

use App\Security\SafeBackUrl;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Syarat" and "Privasi" (F-001 A1). Placeholder text until the legal copy
 * is ready before pilot (F-009 Q9).
 */
class LegalController extends Controller
{
    public function terms(): Response
    {
        return $this->page('terms_title');
    }

    public function privacy(): Response
    {
        return $this->page('privacy_title');
    }

    private function page(string $titleKey): Response
    {
        return Inertia::render('Legal', [
            'title' => __("account.legal.{$titleKey}"),
            'body' => __('account.legal.placeholder'),
            'back' => ['label' => __('account.legal.back'), 'url' => SafeBackUrl::get()],
        ]);
    }
}
