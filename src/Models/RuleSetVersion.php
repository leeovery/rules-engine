<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Models;

use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LeeOvery\RulesEngine\Builders\RuleSetVersionBuilder;
use LeeOvery\RulesEngine\Casts\CalendarDateCast;
use LeeOvery\RulesEngine\Database\Factories\RuleSetVersionFactory;
use LeeOvery\RulesEngine\Models\Concerns\Immutable;

/**
 * @property-read RuleSet $ruleSet
 */
#[UseFactory(RuleSetVersionFactory::class)]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable([
    'rule_set_id',
    'version',
    'effective_from',
    'published_at',
    'published_by',
    'note',
])]
#[WithoutTimestamps]
class RuleSetVersion extends Model
{
    /** @use HasFactory<RuleSetVersionFactory> */
    use HasFactory;

    use Immutable;

    public function newEloquentBuilder($query): RuleSetVersionBuilder
    {
        return new RuleSetVersionBuilder($query);
    }

    /**
     * @return BelongsTo<RuleSet, $this>
     */
    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(RuleSet::class);
    }

    /**
     * @return HasMany<Rule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'effective_from' => CalendarDateCast::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
