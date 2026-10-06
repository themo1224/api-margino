<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property PlanStatus $status
 * @property int $rival_refresh_hours
 * @property int $max_rival_products
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'label', 'status', 'rival_refresh_hours', 'max_rival_products'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'rival_refresh_hours' => 'integer',
            'max_rival_products' => 'integer',
        ];
    }

    /**
     * @return HasMany<Shop, $this>
     */
    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
