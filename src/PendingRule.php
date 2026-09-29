<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class PendingRule
{
    private readonly string $ruleSet;

    /** @var array<array-key, mixed> */
    private array $facts = [];

    private ?CarbonImmutable $asOf = null;

    private ?CarbonImmutable $knownAt = null;

    private ?int $version = null;

    private ?int $cacheTtl;

    public function __construct(
        BackedEnum|string $ruleSet,
        private readonly RulesEngine $rulesEngine,
    ) {
        $this->ruleSet = Name::of($ruleSet);
        $this->cacheTtl = config('rules-engine.cache.ttl');
    }

    /**
     * @param  array<array-key, mixed>|string  $key
     */
    public function with(array|string $key, mixed $value = null): self
    {
        $facts = is_array($key) ? $key : [$key => $value];

        $this->facts = [...$this->facts, ...JsonValue::normalise($facts)];

        return $this;
    }

    public function asOf(DateTimeInterface|string $date): self
    {
        $this->asOf = CarbonImmutable::parse($date)->startOfDay();

        return $this;
    }

    public function knownAt(DateTimeInterface|string $moment): self
    {
        $this->knownAt = CarbonImmutable::parse($moment);

        return $this;
    }

    public function atVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function cache(int $ttl): self
    {
        throw_unless($ttl > 0, InvalidArgumentException::class, 'Cache TTL must be greater than zero.');

        $this->cacheTtl = $ttl;

        return $this;
    }

    public function withoutCache(): self
    {
        $this->cacheTtl = null;

        return $this;
    }

    public function resolve(): RuleMatchCollection
    {
        return $this->rulesEngine->resolve($this);
    }

    public function first(): ?RuleMatch
    {
        return $this->resolve()->first();
    }

    public function firstValue(): mixed
    {
        return $this->resolve()->firstValue();
    }

    public function sole(): RuleMatch
    {
        return $this->resolve()->sole();
    }

    public function soleValue(): mixed
    {
        return $this->resolve()->soleValue();
    }

    /**
     * @return list<RuleMatch>
     */
    public function all(): array
    {
        return array_values($this->resolve()->all());
    }

    public function getRuleSetName(): string
    {
        return $this->ruleSet;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getFacts(): array
    {
        return $this->facts;
    }

    public function getAsOf(): CarbonImmutable
    {
        return $this->asOf ?? CarbonImmutable::today();
    }

    public function getKnownAt(): CarbonImmutable
    {
        return $this->knownAt ?? CarbonImmutable::now();
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }
}
