<?php

namespace App\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who made an audited change. Types match the audit_events CHECK constraint (A18b).
 */
final readonly class Actor
{
    /** Every actor type, as allowed by the audit_events CHECK constraint. */
    public const TYPES = ['user', 'customer', 'system', 'rakyt_staff', 'platform_admin'];

    private function __construct(
        public string $type,
        public ?string $id,
    ) {}

    public static function user(User $user): self
    {
        return new self('user', (string) $user->getKey());
    }

    public static function customer(int|string $bookingId): self
    {
        return new self('customer', (string) $bookingId);
    }

    /**
     * A scheduled task or queued job, named by its class or command (A11).
     */
    public static function system(string $job): self
    {
        return new self('system', $job);
    }

    public static function rakytStaff(string $name): self
    {
        return new self('rakyt_staff', $name);
    }

    public static function platformAdmin(User $admin): self
    {
        return new self('platform_admin', (string) $admin->getKey());
    }

    public static function fromAuth(): ?self
    {
        $user = Auth::user();

        return $user instanceof User ? self::user($user) : null;
    }

    public function hasIp(): bool
    {
        return in_array($this->type, ['user', 'customer', 'platform_admin'], true);
    }
}
