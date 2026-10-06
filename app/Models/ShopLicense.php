<?php

namespace App\Models;

use App\Enums\LicenseSource;
use App\Enums\LicenseStatus;
use Database\Factories\ShopLicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shop_id
 * @property LicenseSource $source
 * @property string $external_license_id
 * @property string $plan_code
 * @property LicenseStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Shop $shop
 */
#[Fillable([
    'shop_id',
    'source',
    'external_license_id',
    'plan_code',
    'status',
    'starts_at',
    'ends_at',
    'meta',
])]
class ShopLicense extends Model
{
    /** @use HasFactory<ShopLicenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LicenseSource::class,
            'status' => LicenseStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== LicenseStatus::Active) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
