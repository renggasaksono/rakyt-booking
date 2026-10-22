<?php

namespace Database\Seeders;

use App\Media\MediaPath;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\TripPhoto;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use App\Trips\DepartureService;
use App\Trips\TripService;
use App\Trips\TripStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo data (not for production): one seller "Rakyt Travel" with a login,
 * an active payment method and 10 published trips — a mix of open (scheduled)
 * and private (request-date) trips, each with a cover photo so the public page
 * renders a real image.
 *
 * Tenant-scoped models are created inside CurrentOrganization::runAs() so their
 * organization_id is applied and the scope guards pass, and trips are published
 * through App\Trips\TripService so the real publish checklist must pass.
 */
class RakytTravelDemoSeeder extends Seeder
{
    private const ORG_SLUG = 'rakyt-travel';

    private const LOGIN_EMAIL = 'seller@rakyt.id';

    /** Trip photo variant widths (config rakyt.media.trip_photo.variant_widths). */
    private const VARIANT_WIDTHS = [400, 800, 1600];

    public function run(): void
    {
        if (Organization::query()->where('slug', self::ORG_SLUG)->exists()) {
            $this->command?->warn('RakytTravelDemoSeeder: "'.self::ORG_SLUG.'" already seeded, skipping.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => self::LOGIN_EMAIL],
            [
                'name' => 'Rakyt Travel',
                'password' => Hash::make('Password'),
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ],
        );

        $organization = Organization::factory()->create([
            'name' => 'Rakyt Travel',
            'slug' => self::ORG_SLUG,
            'city' => 'Yogyakarta, DI Yogyakarta',
            'whatsapp' => '+628119894646',
            'timezone' => 'Asia/Jakarta',
        ]);

        OrganizationMember::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => OrganizationMember::ROLE_OWNER,
        ]);

        $current = app(CurrentOrganization::class);
        $tripService = app(TripService::class);
        $departureService = app(DepartureService::class);

        // Trip/departure writes record audit events, which need an authenticated
        // actor; sign in as the seller for the duration of the seed.
        Auth::login($user);

        try {
            $current->runAs($organization, function () use ($organization, $tripService, $departureService) {
                $this->seedPaymentMethods();

                foreach ($this->trips() as $index => $data) {
                    $this->seedTrip($organization, $tripService, $departureService, $data, $index);
                }
            });
        } finally {
            Auth::logout();
        }

        $this->command?->info('RakytTravelDemoSeeder: seeded Rakyt Travel with 10 published trips.');
        $this->command?->info('Login: '.self::LOGIN_EMAIL.' / Password');
    }

    private function seedPaymentMethods(): void
    {
        PaymentMethod::query()->create([
            'type' => 'bank',
            'provider' => 'bca',
            'account_number' => '8465012345',
            'account_holder' => 'Rakyt Travel Indonesia',
            'position' => 0,
            'active' => true,
        ]);

        PaymentMethod::query()->create([
            'type' => 'ewallet',
            'provider' => 'gopay',
            'account_number' => '628119894646',
            'account_holder' => 'Rakyt Travel',
            'position' => 1,
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedTrip(
        Organization $organization,
        TripService $tripService,
        DepartureService $departureService,
        array $data,
        int $index,
    ): void {
        $trip = new Trip([
            'type' => $data['type'],
            'booking_mode' => $data['booking_mode'],
            'name' => $data['name'],
            'short_description' => $data['short_description'],
            'duration_label' => 'days',
            'duration_days' => $data['days'],
            'duration_nights' => $data['nights'],
            'inclusions' => $data['inclusions'],
            'exclusions' => $data['exclusions'],
            'meeting_point' => $data['meeting_point'],
            'meeting_time' => $data['meeting_time'],
            'map_url' => $data['map_url'],
            'itinerary' => $data['itinerary'],
            'terms' => $data['terms'],
            'base_price' => $data['base_price'],
        ]);
        $trip->slug = Str::slug($data['name']);
        $trip->status = TripStatus::Draft;
        $trip->save();

        $this->seedPhotos($organization, $trip, $data['name'], $index);

        // Open (scheduled) trips get future departures; private trips stay dateless.
        if ($data['booking_mode'] === 'scheduled') {
            foreach ($data['departures'] as $departure) {
                $departureService->create($trip, $departure);
            }
        }

        $tripService->publish($trip);
    }

    private function seedPhotos(Organization $organization, Trip $trip, string $label, int $index): void
    {
        $disk = Storage::disk(config('rakyt.media.public_disk'));
        $palette = $this->palette($index);
        $count = 2 + ($index % 3); // 2–4 photos per trip

        for ($position = 1; $position <= $count; $position++) {
            $original = MediaPath::for($organization, 'trips/'.$trip->public_id, 'jpg');
            $disk->put($original, $this->image(1600, $label, $palette, $position));

            $variants = [];

            foreach (self::VARIANT_WIDTHS as $width) {
                $path = MediaPath::for($organization, 'trips/'.$trip->public_id, 'jpg');
                $disk->put($path, $this->image($width, $label, $palette, $position));
                $variants[(string) $width] = $path;
            }

            TripPhoto::query()->create([
                'trip_id' => $trip->getKey(),
                'path' => $original,
                'variants' => $variants,
                'position' => $position,
            ]);
        }
    }

    /**
     * A small, offline-generated placeholder JPEG at a 3:2 ratio: a two-tone
     * diagonal gradient with the trip name. No network, no bundled assets.
     *
     * @param  array{0: array{int, int, int}, 1: array{int, int, int}}  $palette
     */
    private function image(int $width, string $label, array $palette, int $position): string
    {
        $height = (int) round($width * 2 / 3);
        $image = imagecreatetruecolor($width, $height);
        [$from, $to] = $palette;

        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($from[0] + ($to[0] - $from[0]) * $t),
                (int) round($from[1] + ($to[1] - $from[1]) * $t),
                (int) round($from[2] + ($to[2] - $from[2]) * $t),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $scale = max(1, (int) round($width / 200));
        imagestring($image, 5, 10 * $scale, 10 * $scale, $label.' #'.$position, $white);

        ob_start();
        imagejpeg($image, null, 82);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    /**
     * @return array{0: array{int, int, int}, 1: array{int, int, int}}
     */
    private function palette(int $index): array
    {
        $palettes = [
            [[14, 116, 144], [22, 78, 99]],
            [[234, 88, 12], [124, 45, 18]],
            [[21, 128, 61], [20, 83, 45]],
            [[79, 70, 229], [49, 46, 129]],
            [[219, 39, 119], [131, 24, 67]],
            [[2, 132, 199], [12, 74, 110]],
            [[180, 83, 9], [120, 53, 15]],
            [[13, 148, 136], [19, 78, 74]],
            [[217, 119, 6], [120, 53, 15]],
            [[109, 40, 217], [76, 29, 149]],
        ];

        return $palettes[$index % count($palettes)];
    }

    /**
     * The 10 demo trips. Each itinerary has exactly one detailed line per day.
     *
     * @return list<array<string, mixed>>
     */
    private function trips(): array
    {
        $open = [
            [
                'name' => 'Karimunjawa Snorkeling 3D2N',
                'days' => 3,
                'nights' => 2,
                'base_price' => 1_350_000,
                'short_description' => 'Island hopping dan snorkeling di perairan jernih Karimunjawa dengan penginapan di pulau utama.',
                'meeting_point' => 'Pelabuhan Kartini, Jepara',
                'meeting_time' => '06:30',
                'inclusions' => ['Kapal penyeberangan PP', 'Homestay 2 malam', 'Makan 6x', 'Alat snorkeling', 'Guide lokal', 'Dokumentasi underwater'],
                'exclusions' => ['Tiket masuk taman nasional', 'Transportasi menuju Jepara', 'Pengeluaran pribadi', 'Tips guide'],
                'itinerary' => [
                    'Penyeberangan dari Jepara, check-in homestay, keliling Pulau Karimunjawa dan sunset di Bukit Love.',
                    'Island hopping ke Pulau Menjangan Kecil, snorkeling di spot terumbu karang, dan makan siang seafood di pulau.',
                    'Snorkeling pagi di penangkaran hiu, belanja oleh-oleh, lalu penyeberangan kembali ke Jepara.',
                ],
                'terms' => 'DP 50% untuk mengunci kursi. Jadwal kapal mengikuti kondisi cuaca dan keputusan syahbandar. Pembatalan H-7 dikenakan biaya administrasi.',
                'map_url' => 'https://maps.google.com/?q=Pelabuhan+Kartini+Jepara',
            ],
            [
                'name' => 'Bromo Sunrise Midnight Trip',
                'days' => 1,
                'nights' => 0,
                'base_price' => 450_000,
                'short_description' => 'Trip tengah malam mengejar matahari terbit di Penanjakan dengan jeep dan lautan pasir Bromo.',
                'meeting_point' => 'Terminal Malang Kota',
                'meeting_time' => '00:30',
                'inclusions' => ['Jeep 4x4 Bromo', 'Tiket Penanjakan', 'Guide', 'Air mineral'],
                'exclusions' => ['Tiket masuk TNBTS', 'Sewa kuda', 'Sarapan', 'Sewa jaket'],
                'itinerary' => [
                    'Berangkat tengah malam ke Penanjakan untuk sunrise, lanjut jeep ke kawah Bromo, Pasir Berbisik, dan Bukit Teletubbies sebelum kembali siang hari.',
                ],
                'terms' => 'Trip berangkat dini hari, mohon datang tepat waktu. Suhu sangat dingin, bawa jaket tebal. Minimal 2 peserta per jeep.',
                'map_url' => 'https://maps.google.com/?q=Terminal+Malang',
            ],
            [
                'name' => 'Dieng Plateau Golden Sunrise 2D1N',
                'days' => 2,
                'nights' => 1,
                'base_price' => 850_000,
                'short_description' => 'Negeri di atas awan: golden sunrise Sikunir, telaga warna, dan kompleks candi tertua di Jawa.',
                'meeting_point' => 'Alun-Alun Wonosobo',
                'meeting_time' => '14:00',
                'inclusions' => ['Homestay 1 malam', 'Makan 3x', 'Tiket wisata Dieng', 'Jeep sunrise Sikunir', 'Guide'],
                'exclusions' => ['Transportasi ke Wonosobo', 'Carica dan oleh-oleh', 'Pengeluaran pribadi'],
                'itinerary' => [
                    'Check-in homestay, kunjungi Telaga Warna, Kawah Sikidang, dan kompleks Candi Arjuna saat sore.',
                    'Golden sunrise di Bukit Sikunir, sarapan, mampir Telaga Cebong dan Batu Ratapan Angin sebelum pulang.',
                ],
                'terms' => 'Suhu Dieng bisa mencapai 5°C, bawa pakaian hangat. Trek Sikunir ringan sekitar 30 menit. DP 40%.',
                'map_url' => 'https://maps.google.com/?q=Alun-Alun+Wonosobo',
            ],
            [
                'name' => 'Nusa Penida Barat Timur 3D2N',
                'days' => 3,
                'nights' => 2,
                'base_price' => 1_500_000,
                'short_description' => 'Jelajah ikonik Kelingking Beach, Angel Billabong, dan snorkeling bareng manta di Nusa Penida.',
                'meeting_point' => 'Pelabuhan Sanur, Bali',
                'meeting_time' => '08:00',
                'inclusions' => ['Fast boat PP', 'Hotel 2 malam', 'Mobil + sopir', 'Makan 6x', 'Tiket wisata', 'Snorkeling manta'],
                'exclusions' => ['Tiket pesawat ke Bali', 'Makan di luar paket', 'Tips sopir', 'Asuransi perjalanan'],
                'itinerary' => [
                    'Fast boat Sanur ke Nusa Penida, check-in, lalu Nusa Penida Barat: Kelingking Beach, Broken Beach, Angel Billabong, Crystal Bay.',
                    'Nusa Penida Timur: Diamond Beach, Atuh Beach, Rumah Pohon, dan Teletubbies Hill.',
                    'Snorkeling manta point dan Gamat Bay, lalu fast boat kembali ke Sanur.',
                ],
                'terms' => 'Jalur menuju pantai cukup curam dan licin, gunakan alas kaki yang aman. Snorkeling manta bergantung cuaca. DP 50%.',
                'map_url' => 'https://maps.google.com/?q=Pelabuhan+Sanur+Bali',
            ],
            [
                'name' => 'Ijen Blue Fire 2D1N',
                'days' => 2,
                'nights' => 1,
                'base_price' => 650_000,
                'short_description' => 'Pendakian malam melihat api biru Kawah Ijen dan sunrise danau belerang berwarna toska.',
                'meeting_point' => 'Stasiun Banyuwangi Kota',
                'meeting_time' => '20:00',
                'inclusions' => ['Homestay 1 malam', 'Tiket masuk Ijen', 'Guide pendakian', 'Masker gas', 'Sarapan'],
                'exclusions' => ['Sewa troli', 'Transportasi ke Banyuwangi', 'Makan malam', 'Pengeluaran pribadi'],
                'itinerary' => [
                    'Check-in homestay di Banyuwangi, istirahat, dan briefing persiapan pendakian dini hari.',
                    'Mulai pendakian pukul 01.00 melihat blue fire, sunrise di bibir kawah, turun, sarapan, lalu kembali ke kota.',
                ],
                'terms' => 'Pendakian sekitar 2 jam, butuh kondisi fisik prima. Masker wajib dipakai di area kawah. Minimal usia 10 tahun.',
                'map_url' => 'https://maps.google.com/?q=Stasiun+Banyuwangi+Kota',
            ],
            [
                'name' => 'Gili Trawangan Lombok 3D2N',
                'days' => 3,
                'nights' => 2,
                'base_price' => 1_750_000,
                'short_description' => 'Tiga gili dengan pasir putih, snorkeling penyu, dan sunset di ayunan laut Gili Trawangan.',
                'meeting_point' => 'Bandara Internasional Lombok (BIL)',
                'meeting_time' => '10:00',
                'inclusions' => ['Fast boat antar gili', 'Penginapan 2 malam', 'Makan 6x', 'Snorkeling 3 gili', 'Sepeda', 'Guide'],
                'exclusions' => ['Tiket pesawat', 'Dinner seafood premium', 'Diving', 'Tips guide'],
                'itinerary' => [
                    'Penjemputan bandara, menyeberang ke Gili Trawangan, check-in, dan sunset di ayunan laut.',
                    'Snorkeling tiga gili (Trawangan, Meno, Air), berenang bareng penyu, dan makan siang di Gili Meno.',
                    'Bersepeda keliling pulau, belanja oleh-oleh, lalu menyeberang kembali dan transfer bandara.',
                ],
                'terms' => 'Tidak ada kendaraan bermotor di gili, mobilitas dengan sepeda atau cidomo. Jadwal boat mengikuti cuaca. DP 50%.',
                'map_url' => 'https://maps.google.com/?q=Bandara+Internasional+Lombok',
            ],
        ];

        $private = [
            [
                'name' => 'Private Komodo Labuan Bajo 4D3N',
                'days' => 4,
                'nights' => 3,
                'base_price' => 4_500_000,
                'short_description' => 'Open trip privat kapal phinisi menjelajah Komodo, Padar, Pink Beach, dan Manta Point.',
                'meeting_point' => 'Bandara Komodo, Labuan Bajo',
                'meeting_time' => '09:00',
                'inclusions' => ['Kapal phinisi privat', 'Kabin AC 3 malam', 'Makan full board', 'Tiket TN Komodo', 'Ranger', 'Alat snorkeling'],
                'exclusions' => ['Tiket pesawat', 'Tips kru kapal', 'Minuman beralkohol', 'Asuransi perjalanan'],
                'itinerary' => [
                    'Penjemputan bandara, naik kapal, berlayar ke Pulau Kelor dan Pulau Kalong menyaksikan ribuan kelelawar saat senja.',
                    'Trekking Pulau Padar, Pink Beach, lalu melihat komodo di Pulau Komodo bersama ranger.',
                    'Snorkeling di Manta Point dan Taka Makassar, lanjut Pulau Kanawa untuk berenang.',
                    'Sunrise di geladak kapal, mampir Pulau Rinca, kembali ke Labuan Bajo dan transfer bandara.',
                ],
                'terms' => 'Tanggal fleksibel sesuai permintaan, hubungi via WhatsApp. Minimal 6 peserta untuk kapal privat. DP 50% mengunci tanggal.',
                'map_url' => 'https://maps.google.com/?q=Bandara+Komodo+Labuan+Bajo',
            ],
            [
                'name' => 'Private Raja Ampat Explorer 5D4N',
                'days' => 5,
                'nights' => 4,
                'base_price' => 9_500_000,
                'short_description' => 'Ekspedisi privat surga bawah laut Raja Ampat: Piaynemo, Wayag, dan desa wisata Arborek.',
                'meeting_point' => 'Bandara Domine Eduard Osok, Sorong',
                'meeting_time' => '08:00',
                'inclusions' => ['Speedboat privat', 'Homestay/resort 4 malam', 'Makan full board', 'Tiket masuk kawasan', 'Guide', 'Snorkeling gear'],
                'exclusions' => ['Tiket pesawat ke Sorong', 'Diving bersertifikat', 'Pajak bandara', 'Tips guide'],
                'itinerary' => [
                    'Penjemputan Sorong, menyeberang ke Waisai, check-in, dan snorkeling perdana di sekitar homestay.',
                    'Island hopping ke Piaynemo melihat gugusan karst ikonik dan telaga bintang.',
                    'Jelajah Desa Arborek, snorkeling dermaga, dan melihat burung Cenderawasih saat pagi.',
                    'Perjalanan ke Wayag, trekking puncak karst, dan berenang bersama ikan pari.',
                    'Snorkeling terakhir, kembali ke Waisai lalu Sorong untuk penerbangan pulang.',
                ],
                'terms' => 'Tanggal disesuaikan permintaan, konfirmasi via WhatsApp. Lokasi terpencil, bawa kebutuhan pribadi secukupnya. DP 50%.',
                'map_url' => 'https://maps.google.com/?q=Bandara+Sorong',
            ],
            [
                'name' => 'Private Bali Highlights 3D2N',
                'days' => 3,
                'nights' => 2,
                'base_price' => 2_250_000,
                'short_description' => 'Tur privat Bali dengan mobil dan sopir: Ubud, Kintamani, Tanah Lot, dan pantai selatan.',
                'meeting_point' => 'Bandara I Gusti Ngurah Rai, Denpasar',
                'meeting_time' => '11:00',
                'inclusions' => ['Mobil + sopir privat', 'Hotel 2 malam', 'Sarapan', 'BBM + parkir', 'Air mineral'],
                'exclusions' => ['Tiket pesawat', 'Tiket masuk objek wisata', 'Makan siang dan malam', 'Tips sopir'],
                'itinerary' => [
                    'Penjemputan bandara, Tanah Lot untuk sunset, check-in hotel di area Ubud.',
                    'Tegalalang rice terrace, Pura Tirta Empul, Kintamani dengan panorama Gunung Batur, dan pasar seni Ubud.',
                    'Pantai Melasti dan Pandawa, pusat oleh-oleh, lalu transfer bandara.',
                ],
                'terms' => 'Itinerary dapat disesuaikan sesuai permintaan tamu. Hubungi via WhatsApp untuk mengatur tanggal keberangkatan.',
                'map_url' => 'https://maps.google.com/?q=Bandara+Ngurah+Rai',
            ],
            [
                'name' => 'Private Lombok Sembalun 4D3N',
                'days' => 4,
                'nights' => 3,
                'base_price' => 3_750_000,
                'short_description' => 'Tur privat Lombok: desa adat Sade, air terjun Sendang Gile, dan panorama Sembalun kaki Rinjani.',
                'meeting_point' => 'Bandara Internasional Lombok (BIL)',
                'meeting_time' => '10:00',
                'inclusions' => ['Mobil + sopir privat', 'Penginapan 3 malam', 'Sarapan', 'BBM + parkir', 'Guide lokal'],
                'exclusions' => ['Tiket pesawat', 'Tiket masuk wisata', 'Makan siang dan malam', 'Tips sopir dan guide'],
                'itinerary' => [
                    'Penjemputan bandara, kunjungi Desa Sade dan pantai Kuta Mandalika, check-in penginapan.',
                    'Perjalanan ke Senaru, air terjun Sendang Gile dan Tiu Kelep, menginap di kaki Rinjani.',
                    'Panorama Bukit Selong Sembalun dan kebun stroberi, sore santai di perbukitan.',
                    'Mampir sentra tenun dan gerabah, belanja oleh-oleh, lalu transfer bandara.',
                ],
                'terms' => 'Tanggal fleksibel sesuai permintaan tamu, atur via WhatsApp. Jalur Sembalun berkelok, tidak disarankan bagi yang mudah mabuk perjalanan.',
                'map_url' => 'https://maps.google.com/?q=Bandara+Internasional+Lombok',
            ],
        ];

        $open = array_map(fn (array $t) => $t + [
            'type' => 'open_trip',
            'booking_mode' => 'scheduled',
            'departures' => $this->departuresFor($t['base_price']),
        ], $open);

        $private = array_map(fn (array $t) => $t + [
            'type' => 'private',
            'booking_mode' => 'request_date',
            'departures' => [],
        ], $private);

        return [...$open, ...$private];
    }

    /**
     * A few future departures with varied dates, capacity and the occasional
     * price override. Seats are left at zero; F-005 maintains seats_taken.
     *
     * @return list<array{start_date: string, capacity: int, price_override: ?int}>
     */
    private function departuresFor(int $basePrice): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        return [
            [
                'start_date' => $today->addDays(14)->toDateString(),
                'capacity' => 16,
                'price_override' => null,
            ],
            [
                'start_date' => $today->addDays(35)->toDateString(),
                'capacity' => 12,
                'price_override' => $basePrice + 150_000,
            ],
            [
                'start_date' => $today->addDays(63)->toDateString(),
                'capacity' => 20,
                'price_override' => null,
            ],
        ];
    }
}
