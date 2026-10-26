<?php

namespace Tests\Feature\Admin;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Finder\Finder;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-015 AC-6: admin events are platform-internal. P0 has no seller audit view
 * (F-009 Q7); this guards that no seller-facing page shows them anyway.
 */
class AdminEventsHiddenFromSellersTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private const MARKER = 'ALASAN-ADMIN-INTERNAL';

    /**
     * Code allowed to touch `audit_events` besides the writer (app/Audit) and
     * the admin module: the model, and the ADR-0018
     * payment-instruction lock, which only reads the seller's own auth events
     * by action.
     */
    private const AUDIT_READERS = [
        'app/Models/AuditEvent.php',
        'app/Payments/PaymentInstructionLock.php',
    ];

    public function test_ac_6_no_seller_facing_page_shows_an_admin_event_on_the_seller(): void
    {
        $this->freezeClock();
        $seller = $this->createTenant(['slug' => 'kencana']);
        $admin = $this->platformAdmin();
        $trip = $this->bookableTrip($seller);
        $departure = $this->departureFor($seller, $trip);
        $booking = $this->bookingFor($seller, $departure);

        $this->recordAdminEvent($seller->organization, $admin);
        $this->recordAdminEvent($booking, $admin);

        $urls = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route) => in_array('organization', $route->gatherMiddleware(), true))
            ->reject(fn (Route $route) => str_contains($route->uri(), '{'))
            ->map(fn (Route $route) => '/'.ltrim($route->uri(), '/'))
            ->values();

        $this->assertNotEmpty($urls);

        $urls = $urls->merge([
            route('bookings.show', $booking),
            '/kencana',
            '/kencana/'.$trip->slug,
        ]);

        $rendered = 0;

        foreach ($urls as $url) {
            $response = $this->actingAsTenant($seller)->get($url);
            $rendered += $response->isOk() ? 1 : 0;

            $this->assertStringNotContainsString(self::MARKER, (string) $response->getContent(), "{$url} shows an admin event.");
        }

        // The pages really rendered (not just redirected), the booking detail among them.
        $this->assertGreaterThan(5, $rendered);
        $this->actingAsTenant($seller)->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_ac_6_only_the_admin_module_reads_audit_events(): void
    {
        $readers = [];

        foreach (['app', 'routes'] as $directory) {
            foreach (Finder::create()->files()->in(base_path($directory))->name('*.php') as $file) {
                $path = $directory.'/'.$file->getRelativePathname();

                if (str_starts_with($path, 'app/Audit/') || str_starts_with($path, 'app/Admin/') || str_starts_with($path, 'app/Http/Controllers/Admin/')) {
                    continue;
                }

                if (preg_match('/\bAuditEvent\b(?!Immutable)|audit_events/', $file->getContents())) {
                    $readers[] = $path;
                }
            }
        }

        sort($readers);

        $this->assertSame(self::AUDIT_READERS, $readers, 'Seller-facing code must not read audit events: admin events are platform-internal (F-015).');
    }

    private function recordAdminEvent(Model $subject, User $admin): void
    {
        app(CurrentOrganization::class)->set(null);

        DB::transaction(fn () => app(AuditLog::class)->record(
            AuditAction::OrganizationSlugChanged,
            $subject,
            ['slug' => self::MARKER.'-before'],
            ['slug' => self::MARKER.'-after'],
            Actor::platformAdmin($admin),
            self::MARKER,
        ));
    }
}
