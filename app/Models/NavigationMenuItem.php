<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationMenuItem extends Model
{
    public const TYPE_HEADER = 'header';

    public const TYPE_LINK = 'link';

    protected $fillable = [
        'type', 'sort_order', 'label', 'route', 'icon', 'required_role', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isHeader(): bool
    {
        return $this->type === self::TYPE_HEADER;
    }
}
