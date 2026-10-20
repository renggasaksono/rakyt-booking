<?php

namespace Tests\Feature\Media;

use App\Media\PrivateMedia;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * F-006 AC-31 / F-009 R2: the private disk is never web-served. The framework
 * registers a signed GET and PUT /storage/{path} route for a local disk only
 * when `serve => true`; evidence is streamed from an authorised controller
 * instead, and no code issues a signed upload URL.
 */
class PrivateDiskGuardTest extends TestCase
{
    public function test_ac_31_the_private_disk_is_not_served(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.'.PrivateMedia::disk().'.serve'), 'The private disk must be configured serve => false.');
    }

    public function test_ac_31_no_public_signed_storage_upload_route_is_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('storage/{path}', $route->uri(), "A /storage serve route is registered: {$route->uri()}");
        }

        $this->assertFalse(Route::has('storage.'.PrivateMedia::disk()));
        $this->assertFalse(Route::has('storage.'.PrivateMedia::disk().'.upload'));
    }

    public function test_ac_31_temporary_upload_url_is_never_used(): void
    {
        $offending = [];

        foreach (Finder::create()->files()->in(base_path('app'))->name('*.php') as $file) {
            if (str_contains($file->getContents(), 'temporaryUploadUrl(')) {
                $offending[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offending, 'temporaryUploadUrl() must not be used (F-009 R2).');
    }
}
