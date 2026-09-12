<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $external_id
 * @property string|null $sku
 * @property string $name
 * @property string $price
 * @property string $currency
 * @property string|null $recommended_price
 * @property bool $below_floor
 * @property string|null $floor_price
 * @property Carbon|null $recommendation_updated_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_applied_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 */
#[Fillable([
    'shop_id',
    'external_id',
    'sku',
    'name',
    'price',
    'currency',
    'recommended_price',
    'below_floor',
    'floor_price',
    'recommendation_updated_at',
    'last_synced_at',
    'last_applied_price',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'below_floor' => 'boolean',
            'recommendation_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return HasMany<AppliedPrice, $this>
     */
    public function appliedPrices(): HasMany
    {
        return $this->hasMany(AppliedPrice::class);
    }
}
