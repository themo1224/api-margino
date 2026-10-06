<?php

namespace App\Models;

use App\Enums\RivalSource;
use Database\Factories\RivalSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property RivalSource $source
 * @property string $listing_identity
 * @property string|null $listing_title
 * @property string $cheapest_price
 * @property string|null $median_price
 * @property int $competitor_count
 * @property string $currency
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
#[Fillable([
    'product_id',
    'source',
    'listing_identity',
    'listing_title',
    'cheapest_price',
    'median_price',
    'competitor_count',
    'currency',
    'captured_at',
])]
class RivalSnapshot extends Model
{
    /** @use HasFactory<RivalSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RivalSource::class,
            'competitor_count' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isStale(?int $refreshHours = null): bool
    {
        $hours = $refreshHours;
        if ($hours === null) {
            $this->loadMissing('product.shop.plan');
            $hours = (int) ($this->product?->shop?->plan?->rival_refresh_hours ?? 24);
        }

        $staleAfter = (int) ceil($hours * (float) config('rivals.stale_multiplier', 1.5));

        return $this->captured_at === null
            || $this->captured_at->lt(now()->subHours($staleAfter));
    }
}
