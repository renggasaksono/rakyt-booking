<?php

namespace Tests\Support;

use App\Models\Trip;
use App\Models\TripPhoto;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * Fixtures for the public pages (F-002). Use with InteractsWithTenants and
 * BuildsTrips; the clock is frozen at Thursday 2024-10-10 10:00 WIB.
 */
trait BuildsPublicPages
{
    /**
     * A seller with a fixed slug and name, as in the mockups.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function seller(string $slug = 'kencana', string $name = 'Kencana Trip', array $attributes = []): Tenant
    {
        $tenant = $this->createTenant();
        $tenant->organization->update(['slug' => $slug, 'name' => $name, ...$attributes]);

        return $tenant;
    }

    /**
     * @param  array<int, string>|null  $variants  widths with a generated variant (default: all)
     */
    protected function photoFor(Tenant $tenant, Trip $trip, int $position = 1, ?array $variants = null): TripPhoto
    {
        $base = "org/{$tenant->organization->public_id}/trips/{$trip->public_id}/foto-{$position}";
        $widths = $variants ?? [400, 800, 1600];

        return $this->asTenant($tenant, fn () => TripPhoto::create([
            'trip_id' => $trip->id,
            'path' => "{$base}.jpg",
            'variants' => collect($widths)->mapWithKeys(fn ($width) => [(string) $width => "{$base}-{$width}.webp"])->all(),
            'position' => $position,
        ]));
    }

    /**
     * The page's Inertia props, after asserting the component.
     *
     * @return array<string, mixed>
     */
    protected function propsOf(TestResponse $response, string $component): array
    {
        $props = [];

        $response->assertOk()->assertInertia(function (AssertableInertia $page) use ($component, &$props) {
            $page->component($component, false);
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sellerProps(string $slug = 'kencana'): array
    {
        return $this->propsOf($this->get('/'.$slug), 'Public/Seller');
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripProps(string $slug, string $tripSlug): array
    {
        return $this->propsOf($this->get('/'.$slug.'/'.$tripSlug), 'Public/Trip');
    }
}
