<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    public const string GlobalScope = 'global';

    public const string DepartmentScope = 'department';

    protected $fillable = ['key', 'name', 'is_system', 'scope'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
