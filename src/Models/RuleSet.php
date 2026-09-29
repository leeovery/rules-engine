<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LeeOvery\RulesEngine\Builders\RuleSetBuilder;
use LeeOvery\RulesEngine\Database\Factories\RuleSetFactory;

#[UseFactory(RuleSetFactory::class)]
#[Fillable([
    'name',
    'description',
])]
class RuleSet extends Model
{
    /** @use HasFactory<RuleSetFactory> */
    use HasFactory;

    public function newEloquentBuilder($query): RuleSetBuilder
    {
        return new RuleSetBuilder($query);
    }

    /**
     * @return HasMany<RuleSetVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RuleSetVersion::class);
    }
}
