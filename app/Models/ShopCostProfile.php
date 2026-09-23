<?php

namespace App\Models;

use Database\Factories\ShopCostProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property string $staff_cost
 * @property string $rent_cost
 * @property string $utilities_cost
 * @property string $other_overhead
 * @property string $min_margin_percent
 * @property string $allocation_method
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 */
#[Fillable([
    'shop_id',
    'staff_cost',
    'rent_cost',
    'utilities_cost',
    'other_overhead',
    'min_margin_percent',
    'allocation_method',
])]
class ShopCostProfile extends Model
{
    /** @use HasFactory<ShopCostProfileFactory> */
    use HasFactory;

    /**
     * Sum of fixed overhead components as a decimal string.
     */
    public function totalOverhead(): string
    {
        $sum = '0';
        foreach ([$this->staff_cost, $this->rent_cost, $this->utilities_cost, $this->other_overhead] as $part) {
            $sum = bcadd($sum, $this->normalizeDecimal((string) $part), 4);
        }

        return $this->trimDecimal($sum);
    }

    private function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^\d+(\.\d+)?$/', $value)) {
            return '0';
        }

        return $value;
    }

    private function trimDecimal(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
