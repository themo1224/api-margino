<?php

namespace App\Models;

use App\Enums\AlertType;
use App\Enums\PricingMode;
use App\Enums\PricingStrategy;
use App\Enums\ShopStatus;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $public_id
 * @property string $name
 * @property ShopStatus $status
 * @property int $plan_id
 * @property PricingMode $pricing_mode
 * @property bool $alerts_enabled
 * @property array<string, bool>|null $alert_prefs
 * @property string $rival_undercut_threshold_percent
 * @property int $cost_stale_days
 * @property PricingStrategy $pricing_strategy
 * @property string $undercut_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Plan $plan
 * @property-read ShopCostProfile|null $costProfile
 */
#[Fillable([
    'user_id',
    'public_id',
    'name',
    'status',
    'plan_id',
    'pricing_mode',
    'alerts_enabled',
    'alert_prefs',
    'rival_undercut_threshold_percent',
    'cost_stale_days',
    'pricing_strategy',
    'undercut_percent',
])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'pricing_mode' => PricingMode::class,
            'pricing_strategy' => PricingStrategy::class,
            'alerts_enabled' => 'boolean',
            'alert_prefs' => 'array',
            'cost_stale_days' => 'integer',
        ];
    }

    public function wantsAlert(AlertType $type): bool
    {
        return (bool) ($this->alert_prefs[$type->value] ?? true);
    }

    protected static function booted(): void
    {
        static::creating(function (Shop $shop): void {
            if (filled($shop->public_id)) {
                return;
            }

            $shop->public_id = 'shop_'.strtolower((string) Str::ulid());
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasOne<ShopCostProfile, $this>
     */
    public function costProfile(): HasOne
    {
        return $this->hasOne(ShopCostProfile::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<AppliedPrice, $this>
     */
    public function appliedPrices(): HasMany
    {
        return $this->hasMany(AppliedPrice::class);
    }

    /**
     * @return HasMany<ShopAlertDispatch, $this>
     */
    public function alertDispatches(): HasMany
    {
        return $this->hasMany(ShopAlertDispatch::class);
    }

    /**
     * @return HasMany<ShopLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(ShopLicense::class);
    }
}
