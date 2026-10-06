<?php

namespace App\Models;

use App\Enums\RivalMatchStatus;
use App\Enums\RivalSource;
use Database\Factories\ProductRivalMatchFactory;
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
 * @property string|null $listing_url
 * @property string|null $search_query
 * @property string $confidence
 * @property RivalMatchStatus $status
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
#[Fillable([
    'product_id',
    'source',
    'listing_identity',
    'listing_title',
    'listing_url',
    'search_query',
    'confidence',
    'status',
    'confirmed_at',
])]
class ProductRivalMatch extends Model
{
    /** @use HasFactory<ProductRivalMatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RivalSource::class,
            'status' => RivalMatchStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function isLinked(): bool
    {
        return $this->status === RivalMatchStatus::AutoLinked
            || ($this->status === RivalMatchStatus::NeedsConfirm && $this->confirmed_at !== null);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
