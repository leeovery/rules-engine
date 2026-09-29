<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LeeOvery\RulesEngine\Models\RuleSet;

/**
 * @extends Factory<RuleSet>
 */
class RuleSetFactory extends Factory
{
    protected $model = RuleSet::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
