<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LeeOvery\RulesEngine\Casts\ConditionCast;
use LeeOvery\RulesEngine\Casts\JsonValueCast;
use LeeOvery\RulesEngine\ConditionBuilder;
use LeeOvery\RulesEngine\ConditionParser;
use LeeOvery\RulesEngine\Database\Factories\RuleFactory;
use LeeOvery\RulesEngine\Models\Concerns\Immutable;

#[UseFactory(RuleFactory::class)]
#[Fillable([
    'rule_set_version_id',
    'uuid',
    'key',
    'position',
    'condition',
    'condition_hash',
    'value',
    'metadata',
])]
#[Hidden([
    'condition_hash',
])]
#[WithoutTimestamps]
class Rule extends Model
{
    /** @use HasFactory<RuleFactory> */
    use HasFactory;

    use Immutable;

    public static function hashCondition(ConditionBuilder $condition): string
    {
        return hash('sha256', (new ConditionParser)->toJson($condition));
    }

    /**
     * @return BelongsTo<RuleSetVersion, $this>
     */
    public function ruleSetVersion(): BelongsTo
    {
        return $this->belongsTo(RuleSetVersion::class);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'condition' => ConditionCast::class,
            'value' => JsonValueCast::class,
            'metadata' => 'array',
        ];
    }
}
