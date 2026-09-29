<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Exceptions\RuleSetNotFoundException;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

class RulesEngine
{
    public function __construct(
        private readonly VersionSelector $versionSelector,
        private readonly RuleMatcher $ruleMatcher,
    ) {}

    public function for(BackedEnum|string $ruleSet): PendingRule
    {
        return new PendingRule($ruleSet, $this);
    }

    public function ruleSet(BackedEnum|string $ruleSet): PendingRuleSet
    {
        return new PendingRuleSet($ruleSet);
    }

    /**
     * @return Collection<int, RuleSetVersion>
     */
    public function versions(BackedEnum|string $ruleSet): Collection
    {
        return $this->find($ruleSet)->versions()->orderBy('version')->get();
    }

    public function version(BackedEnum|string $ruleSet, int $version): RuleSetVersion
    {
        return $this->versionOf($this->find($ruleSet), $version);
    }

    // The version published last, whatever its effective date. A set is created with its first version.
    public function newestVersion(BackedEnum|string $ruleSet): RuleSetVersion
    {
        return $this->find($ruleSet)->versions()->newestFirst()->firstOrFail();
    }

    public function changes(BackedEnum|string $ruleSet, int $from, int $to): RuleSetChanges
    {
        $model = $this->find($ruleSet);

        return RuleSetChanges::between($this->rulesOf($model, $from), $this->rulesOf($model, $to));
    }

    public function resolve(PendingRule $pendingRule): RuleMatchCollection
    {
        $version = $this->versionSelector->select($this->find($pendingRule->getRuleSetName()), $pendingRule);
        $ttl = $pendingRule->getCacheTtl();

        if ($ttl === null) {
            return $this->ruleMatcher->match($version, $pendingRule->getFacts());
        }

        // Laravel 13 apps refuse to unserialise objects from the cache by default, so matches are cached as arrays.
        $matches = Cache::remember(
            $this->cacheKey($version, $pendingRule->getFacts()),
            $ttl,
            fn (): array => $this->ruleMatcher->match($version, $pendingRule->getFacts())->toArray(),
        );

        return new RuleMatchCollection(array_map(RuleMatch::fromArray(...), $matches), $version->ruleSet->name);
    }

    private function find(BackedEnum|string $ruleSet): RuleSet
    {
        return RuleSet::query()->whereName($ruleSet)->first()
            ?? throw RuleSetNotFoundException::forName(Name::of($ruleSet));
    }

    private function versionOf(RuleSet $ruleSet, int $version): RuleSetVersion
    {
        return $ruleSet->versions()->whereVersion($version)->first()
            ?? throw NoApplicableVersionException::numbered($ruleSet->name, $version);
    }

    /**
     * @return list<RuleDefinition>
     */
    private function rulesOf(RuleSet $ruleSet, int $version): array
    {
        return array_values($this->versionOf($ruleSet, $version)->rules->map(RuleDefinition::fromRule(...))->all());
    }

    /**
     * @param  array<array-key, mixed>  $facts
     */
    private function cacheKey(RuleSetVersion $version, array $facts): string
    {
        ksort($facts);

        return sprintf('%s:%d:%s', config('rules-engine.cache.prefix'), $version->id, hash('xxh128', serialize($facts)));
    }
}
