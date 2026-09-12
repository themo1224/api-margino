<?php

namespace App\Models;

use App\Enums\AppliedSource;
use Database\Factories\AppliedPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property int $product_id
 * @property string $applied_price
 * @property string $currency
 * @property AppliedSource $source
 * @property Carbon $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read Product $product
 */
#[Fillable(['shop_id', 'product_id', 'applied_price', 'currency', 'source', 'applied_at'])]
class AppliedPrice extends Model
{
    /** @use HasFactory<AppliedPriceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AppliedSource::class,
            'applied_at' => 'datetime',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
