<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    protected $model = Rule::class;

    public function definition(): array
    {
        return [
            'rule_set_version_id' => RuleSetVersion::factory(),
            'uuid' => fn (): string => (string) Str::uuid7(),
            'key' => $this->faker->unique()->slug(2),
            'position' => 1,
            'condition' => Condition::always(),
            'condition_hash' => fn (array $attributes): string => Rule::hashCondition($attributes['condition']),
            'value' => $this->faker->word(),
            'metadata' => null,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(['metadata' => $metadata]);
    }
}
