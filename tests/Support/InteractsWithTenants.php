<?php

namespace Tests\Support;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Closure;

/**
 * Cross-tenant test harness (F-009 A9). Every seller-owned resource uses it:
 *
 *     [$a, $b] = $this->createTenants();
 *     $trip = $this->asTenant($a, fn () => Trip::factory()->create());
 *
 *     $this->assertCrossTenantNotFound($b, [
 *         ['GET', route('trips.show', $trip)],
 *         ['PUT', route('trips.update', $trip), ['name' => 'x']],
 *     ], fn () => $this->assertDatabaseHas('trips', ['id' => $trip->id, 'name' => $trip->name]));
 *
 *     $this->assertCrossTenantPayloadRejected($b, 'POST', route('departures.store'),
 *         ['trip_id' => $trip->id, ...], 'trip_id',
 *         fn () => $this->assertDatabaseCount('departures', 0));
 *
 * After a request, the current organization stays set to the last actor's;
 * check database state with assertDatabase* or acrossOrganizations(), not scoped queries.
 */
trait InteractsWithTenants
{
    protected function createTenant(): Tenant
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();

        $this->asTenantOrganization($organization, fn () => OrganizationMember::factory()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $owner->getKey(),
        ]));

        return new Tenant($organization, $owner);
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    protected function createTenants(): array
    {
        return [$this->createTenant(), $this->createTenant()];
    }

    /**
     * Run a callback (e.g. factories) as the tenant's organization.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function asTenant(Tenant $tenant, Closure $callback): mixed
    {
        return $this->asTenantOrganization($tenant->organization, $callback);
    }

    protected function actingAsTenant(Tenant $tenant): static
    {
        return $this->actingAs($tenant->owner);
    }

    /**
     * A2: each request made by the intruder for another organization's record is a 404,
     * and the record is unchanged afterwards.
     *
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $requests  [method, uri, payload]
     */
    protected function assertCrossTenantNotFound(Tenant $intruder, array $requests, ?Closure $assertUnchanged = null): void
    {
        foreach ($requests as $request) {
            [$method, $uri] = $request;

            $this->actingAsTenant($intruder)
                ->json($method, $uri, $request[2] ?? [])
                ->assertNotFound();

            if ($assertUnchanged) {
                $assertUnchanged();
            }
        }
    }

    /**
     * A3: a payload that references another organization's record fails validation and writes nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function assertCrossTenantPayloadRejected(
        Tenant $actor,
        string $method,
        string $uri,
        array $payload,
        string $field,
        ?Closure $assertNothingWritten = null,
    ): void {
        $this->actingAsTenant($actor)
            ->json($method, $uri, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        if ($assertNothingWritten) {
            $assertNothingWritten();
        }
    }

    private function asTenantOrganization(Organization $organization, Closure $callback): mixed
    {
        return app(CurrentOrganization::class)->runAs($organization, $callback);
    }
}
