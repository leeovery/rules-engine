<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

/**
 * @extends Factory<RuleSetVersion>
 */
class RuleSetVersionFactory extends Factory
{
    protected $model = RuleSetVersion::class;

    public function definition(): array
    {
        return [
            'rule_set_id' => RuleSet::factory(),
            'version' => 1,
            'effective_from' => null,
            'published_at' => now(),
            'published_by' => null,
            'note' => null,
        ];
    }

    public function effectiveFrom(DateTimeInterface|string $date): static
    {
        return $this->state(['effective_from' => $date]);
    }
}
