<?php

namespace Database\Factories;

use App\Models\PaymentRecord;
use App\Payments\PaymentDecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside asTenant(): the organization comes from the current context.
 * Prefer the F-006 upload / verify flows in tests over this factory where the
 * lifecycle matters.
 *
 * @extends Factory<PaymentRecord>
 */
class PaymentRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount_due' => 2_500_000,
            'evidence_path' => null,
            'evidence_uploaded_at' => null,
            'verified_by' => null,
            'verified_at' => null,
            'decision' => null,
            'reject_reason' => null,
        ];
    }

    public function withEvidence(?string $path = null): static
    {
        return $this->state(fn () => [
            'evidence_path' => $path ?? 'org/test/evidence/'.fake()->uuid().'.jpg',
            'evidence_uploaded_at' => CarbonImmutable::now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'verified_at' => CarbonImmutable::now(),
            'decision' => PaymentDecision::Confirmed,
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn () => [
            'verified_at' => CarbonImmutable::now(),
            'decision' => PaymentDecision::Rejected,
            'reject_reason' => $reason,
        ]);
    }
}
