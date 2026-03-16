<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ToolCategory;
use Database\Factories\ShopToolSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopToolSetting extends Model
{
    /** @use HasFactory<ShopToolSettingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'category',
        'is_enabled',
        'approval_mode',
        'tool_overrides',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ToolCategory::class,
            'is_enabled' => 'boolean',
            'tool_overrides' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
