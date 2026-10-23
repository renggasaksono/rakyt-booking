<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin area landing page (F-010): the shell that F-011–F-015 hang their
 * pages on. Behind `auth` + `platform-admin`.
 */
class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $response = Inertia::render('Admin/Dashboard', [
            'copy' => __('admin'),
        ])->toResponse($request);

        // Cross-seller data will appear here (F-011+): never cached or stored.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
