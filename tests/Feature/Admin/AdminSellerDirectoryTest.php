<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AdminSellerController;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\TestCase;

/**
 * F-012: the admin seller directory (`/admin/penjual`) — every organization,
 * searchable by name, slug or owner phone, filterable by account status,
 * paginated, behind the F-010 gate. Also the organization lookup that the
 * F-015 audit view's organization picker uses.
 */
class AdminSellerDirectoryTest extends TestCase
{
    use BuildsAdmins, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    public function test_ac_6_the_directory_and_lookup_are_behind_the_admin_gate(): void
    {
        $seller = $this->seller('kencana', 'Kencana Trip');
        $admin = $this->platformAdmin();

        $urls = [route('admin.sellers.index'), route('admin.sellers.lookup', ['cari' => 'ken'])];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($urls as $url) {
            $this->actingAsTenant($seller)->get($url)->assertForbidden();
            // A seller cannot read the JSON either.
            $this->actingAsTenant($seller)->getJson($url)->assertForbidden()->assertDontSee('Kencana Trip');
            $this->actingAs($admin)->get($url)->assertRedirect(route('admin.two-factor.challenge'));
        }

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/Sellers/Index'));

        $this->actingAsConfirmedAdmin($admin)->getJson(route('admin.sellers.lookup', ['cari' => 'ken']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_every_organization_is_listed_newest_registration_first_with_its_columns(): void
    {
        $this->freezeClock('2026-10-20 09:00');
        $first = $this->seller('bromo', 'Bromo Adventure', ['city' => 'Malang, Jawa Timur', 'whatsapp' => '+6281211112222']);
        $this->travel(1)->days();
        $second = $this->seller('kencana', 'Kencana Trip');
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index'))
            ->assertInertia(fn ($page) => $page
                ->has('sellers', 2)
                ->where('sellers.0.id', $second->id())
                ->where('sellers.1.id', $first->id())
                ->where('sellers.1.name', 'Bromo Adventure')
                ->where('sellers.1.slug', 'bromo')
                ->where('sellers.1.whatsapp', '+6281211112222')
                ->where('sellers.1.city', 'Malang, Jawa Timur')
                ->where('sellers.1.status', 'active')
                ->where('sellers.1.status_label', 'Aktif')
                ->where('sellers.1.registered_label', '20 Okt 2026')
                ->where('sellers.1.url', route('admin.sellers.show', $first->id()))
            );
    }

    public function test_ac_1_search_by_organization_name_or_slug(): void
    {
        $bromo = $this->seller('bromo-tour', 'Bromo Adventure');
        $kencana = $this->seller('kencana', 'Kencana Trip');
        $admin = $this->platformAdmin();

        $this->assertSame([$bromo->id()], $this->ids($admin, ['cari' => 'adventure']), 'Name, any case.');
        $this->assertSame([$bromo->id()], $this->ids($admin, ['cari' => 'bromo-t']), 'Slug part.');
        $this->assertSame([$kencana->id()], $this->ids($admin, ['cari' => '  KENCANA ']), 'Trimmed.');
        $this->assertSame([], $this->ids($admin, ['cari' => 'tidak-ada']));
        // LIKE wildcards are literal.
        $this->assertSame([], $this->ids($admin, ['cari' => '%_%']));
    }

    public function test_ac_1_search_by_owner_phone_in_any_format(): void
    {
        $match = $this->seller('bromo', 'Bromo Adventure', ['whatsapp' => '+6281234567890']);
        $this->seller('kencana', 'Kencana Trip', ['whatsapp' => '+6285700001111']);
        $admin = $this->platformAdmin();

        foreach (['081234567890', '+62 812-3456-7890', '6281234567890', '7890'] as $term) {
            $this->assertSame([$match->id()], $this->ids($admin, ['cari' => $term]), $term);
        }
    }

    public function test_ac_1_terms_pasted_from_whatsapp_are_normalised_before_matching(): void
    {
        $match = $this->seller('bromo', 'Bromo Adventure', ['whatsapp' => '+6281234567890']);
        $this->seller('kencana', 'Kencana Trip', ['whatsapp' => '+6285700001111']);
        $admin = $this->platformAdmin();

        $terms = [
            "\u{202A}+62 812-3456-7890\u{202C}",    // Bidi embedding WhatsApp adds on copy.
            "\u{FEFF}0812\u{2011}3456\u{2011}7890", // BOM and non-breaking hyphens.
            "+62 812\u{2212}3456\u{2013}7890",      // Minus sign and en dash.
            '０８１２３４５６７８９０',                    // Full-width digits.
            "\u{2066}Bromo Adventure\u{2069}",      // A name, isolated.
            'ｂｒｏｍｏ',
        ];

        foreach ($terms as $term) {
            $this->assertSame([$match->id()], $this->ids($admin, ['cari' => $term]), json_encode($term));
        }

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['cari' => "\u{202A}+62 812-3456-7890\u{202C}"]))
            ->assertInertia(fn ($page) => $page->where('filters.cari', '+62 812-3456-7890'));

        // The organization picker's lookup shares the term.
        $this->actingAsConfirmedAdmin($admin)->getJson(route('admin.sellers.lookup', ['cari' => "\u{202A}bromo\u{202C}"]))
            ->assertJsonPath('organizations.0.slug', 'bromo')
            ->assertJsonCount(1, 'organizations');

        // Other control characters still drop the term.
        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['cari' => "bromo\x07"]))
            ->assertInertia(fn ($page) => $page->where('filters.cari', null));
    }

    public function test_ac_1_results_are_paginated(): void
    {
        $this->freezeClock('2026-10-01 09:00');
        $tenants = [];
        for ($i = 0; $i < AdminSellerController::PER_PAGE + 2; $i++) {
            $tenants[] = $this->createTenant();
            $this->travel(1)->minutes();
        }
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['cari' => 'kencana']))
            ->assertInertia(fn ($page) => $page
                ->has('sellers', AdminSellerController::PER_PAGE)
                ->where('sellers.0.id', end($tenants)->id())
                ->where('pagination.page', 1)
                ->where('pagination.prev_url', null)
                ->where('pagination.next_url', route('admin.sellers.index', ['cari' => 'kencana', 'halaman' => 2]))
            );

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['cari' => 'kencana', 'halaman' => 2]))
            ->assertInertia(fn ($page) => $page
                ->has('sellers', 2)
                ->where('sellers.1.id', $tenants[0]->id())
                ->where('pagination.page', 2)
                ->where('pagination.next_url', null)
                ->where('pagination.prev_url', route('admin.sellers.index', ['cari' => 'kencana']))
            );
    }

    public function test_ac_2_filter_by_account_status(): void
    {
        $active = $this->seller('bromo', 'Bromo Adventure');
        $suspended = $this->seller('kencana', 'Kencana Trip');
        $this->suspendOrganization($suspended);
        $admin = $this->platformAdmin();

        $this->assertSame([$active->id()], $this->ids($admin, ['status' => 'active']));
        $this->assertSame([$suspended->id()], $this->ids($admin, ['status' => 'suspended']));

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['status' => 'suspended']))
            ->assertInertia(fn ($page) => $page
                ->where('sellers.0.status_label', 'Ditangguhkan')
                ->where('filters', ['cari' => null, 'status' => 'suspended'])
                ->where('options.statuses', [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'suspended', 'label' => 'Ditangguhkan'],
                ])
            );
    }

    public function test_invalid_filters_are_dropped_and_the_list_still_renders(): void
    {
        $this->seller();
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index').'?status=hapus&halaman=-3&cari[]=x')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sellers', 1)
                ->where('filters', ['cari' => null, 'status' => null])
                ->where('pagination.page', 1)
            );

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', ['cari' => str_repeat('a', 101)]))
            ->assertInertia(fn ($page) => $page->where('filters.cari', null)->has('sellers', 1));
    }

    public function test_the_lookup_returns_at_most_ten_matches_for_two_or_more_characters(): void
    {
        $this->seller('bromo', 'Bromo Adventure');
        for ($i = 0; $i < 11; $i++) {
            $this->createTenant();
        }
        $admin = $this->platformAdmin();

        $this->actingAsConfirmedAdmin($admin)->getJson(route('admin.sellers.lookup', ['cari' => 'bro']))
            ->assertOk()
            ->assertExactJson(['organizations' => [
                ['id' => (string) Organization::query()->where('slug', 'bromo')->value('id'), 'name' => 'Bromo Adventure', 'slug' => 'bromo'],
            ]]);

        $this->actingAsConfirmedAdmin($admin)->getJson(route('admin.sellers.lookup', ['cari' => 'kencana']))
            ->assertJsonCount(10, 'organizations');

        $this->actingAsConfirmedAdmin($admin)->getJson(route('admin.sellers.lookup', ['cari' => 'b']))
            ->assertExactJson(['organizations' => []]);
    }

    public function test_the_admin_nav_links_to_the_directory(): void
    {
        $seller = $this->seller();
        $admin = $this->platformAdmin();

        foreach ([route('admin.sellers.index'), route('admin.sellers.show', $seller->id())] as $url) {
            $this->actingAsConfirmedAdmin($admin)->get($url)
                ->assertInertia(fn ($page) => $page
                    ->where('admin.nav.1.key', 'sellers')
                    ->where('admin.nav.1.label', 'Penjual')
                    ->where('admin.nav.1.url', route('admin.sellers.index'))
                    ->where('admin.nav.1.active', true)
                    ->where('admin.nav.0.active', false)
                );
        }
    }

    public function test_ac_8_the_directory_is_phone_first(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Sellers/Index.vue'));

        // Rows are cards that stack; the filter controls keep 44 px+ targets.
        $this->assertStringContainsString('min-h-12', $vue);
        $this->assertStringNotContainsString('<table', $vue);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function ids($admin, array $query): array
    {
        $ids = [];

        $this->actingAsConfirmedAdmin($admin)->get(route('admin.sellers.index', $query))
            ->assertOk()
            ->assertInertia(function ($page) use (&$ids) {
                $ids = array_column($page->toArray()['props']['sellers'], 'id');
            });

        return $ids;
    }
}
