<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Department extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'code');
    }

    /** @return HasMany<Knowledge, $this> */
    public function knowledge(): HasMany
    {
        return $this->hasMany(Knowledge::class, 'department_id', 'code');
    }

    /** @return list<array{value: string, title: string}> */
    public static function options(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (self $department): array => [
                'value' => $department->code,
                'title' => $department->name,
            ])
            ->values()
            ->all();
    }
}
