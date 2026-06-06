<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPrimaryKey;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPrefixedPrimaryKey;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const PREFIXED_PRIMARY_KEY_COUNTER = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'profile_photo',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password' => 'hashed',
        ];
    }

    public function hasVerifiedEmail()
    {
        // Staff/admin accounts are treated as verified (no email verification required).
        if (in_array(($this->role ?? null), ['staff', 'admin'], true)) {
            return true;
        }

        return parent::hasVerifiedEmail();
    }

    public function sendEmailVerificationNotification()
    {
        // Don't send verification emails for staff/admin accounts.
        if (in_array(($this->role ?? null), ['staff', 'admin'], true)) {
            return;
        }

        parent::sendEmailVerificationNotification();
    }

    public function isCheckoutProfileComplete(): bool
    {
        if (($this->role ?? null) !== 'customer') {
            return true;
        }

        if ($this->addresses()->exists()) {
            return true;
        }

        // Backward compatibility: older accounts may still rely on the single shipping fields.
        return filled($this->phone)
            && filled($this->shipping_address)
            && filled($this->shipping_city)
            && filled($this->shipping_state)
            && filled($this->shipping_postcode)
            && filled($this->shipping_country);
    }

    public function missingCheckoutProfileFields(): array
    {
        if (($this->role ?? null) !== 'customer') {
            return [];
        }

        if ($this->addresses()->exists()) {
            return [];
        }

        $missing = [];
        if (!filled($this->phone)) {
            $missing[] = 'phone number';
        }
        if (!filled($this->shipping_address)) {
            $missing[] = 'shipping address';
        }
        if (!filled($this->shipping_city)) {
            $missing[] = 'city';
        }
        if (!filled($this->shipping_state)) {
            $missing[] = 'state';
        }
        if (!filled($this->shipping_postcode)) {
            $missing[] = 'postcode';
        }
        if (!filled($this->shipping_country)) {
            $missing[] = 'country';
        }

        return $missing;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'user_id', 'user_id')->orderByDesc('is_default')->orderByDesc('id');
    }

    public function orderReturnRequests(): HasMany
    {
        return $this->hasMany(OrderReturnRequest::class, 'user_id', 'user_id')->orderByDesc('created_at');
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'user_id', 'user_id')->latest();
    }
}
