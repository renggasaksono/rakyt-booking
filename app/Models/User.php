<?php

namespace App\Models;

use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// `is_platform_admin` and `two_factor_secret` are deliberately absent: the
// admin flag and TOTP secret are set only by the admin:grant/admin:revoke
// commands, never through a mass-assigned HTTP payload (F-010, ADR-0011);
// `last_login_at` only by App\Security\RecordLastLogin (F-012).
#[Fillable(['name', 'email', 'password', 'terms_accepted_at', 'signup_started_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Defaults so the admin columns are always present on a freshly made model
     * (e.g. a factory instance), not only when hydrated from the database —
     * strict mode (Model::shouldBeStrict) throws on a missing attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_platform_admin' => false,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'last_login_at' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'signup_started_at' => 'datetime',
            'password' => 'hashed',
            // F-010: the platform-admin flag and TOTP enrolment.
            'is_platform_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            // F-012: the last successful sign-in ("last activity").
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * A platform administrator (F-010, ADR-0011). Never a member of an
     * organization; access is gated server-side by the platform-admin middleware.
     */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /**
     * Stored trimmed and lower-cased (F-001 A2).
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    /**
     * Queued, in Bahasa, signed link valid for 60 minutes (F-001 A3, A10).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Queued, in Bahasa (F-001 A8).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Memberships in the current organization. The resolver in App\Tenancy
     * reads them unscoped to find that organization in the first place.
     *
     * @return HasMany<OrganizationMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}
