<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Actions;

use Illuminate\Support\Facades\DB;
use LeeOvery\RulesEngine\DraftVersion;
use LeeOvery\RulesEngine\Events\RuleSetVersionPublished;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\PendingRuleSet;
use LeeOvery\RulesEngine\RuleDefinition;
use LeeOvery\RulesEngine\VersionDrafter;

class PublishRuleSetVersionAction
{
    public function __construct(
        private readonly VersionDrafter $versionDrafter,
    ) {}

    public function __invoke(PendingRuleSet $pending): RuleSetVersion
    {
        return DB::transaction(function () use ($pending): RuleSetVersion {
            $ruleSet = $this->lockedRuleSetFor($pending);
            $draft = $this->versionDrafter->draft($pending, $ruleSet);
            $version = $this->createVersion($ruleSet, $pending, $draft);

            event(new RuleSetVersionPublished(
                ruleSet: $ruleSet->name,
                version: $version->version,
                effectiveFrom: $version->effective_from,
                publishedBy: $version->published_by,
                note: $version->note,
                changes: $draft->changes,
            ));

            return $version;
        });
    }

    private function lockedRuleSetFor(PendingRuleSet $pending): RuleSet
    {
        $ruleSet = RuleSet::query()->firstOrCreate(
            ['name' => $pending->getName()],
            ['description' => $pending->getDescription()],
        );

        // Publishes of one set queue on this lock, so each drafts from the version committed before it.
        return RuleSet::query()->lockForUpdate()->findOrFail($ruleSet->id);
    }

    private function createVersion(RuleSet $ruleSet, PendingRuleSet $pending, DraftVersion $draft): RuleSetVersion
    {
        $version = $ruleSet->versions()->create([
            'version' => $draft->version,
            'effective_from' => $pending->getEffectiveFrom(),
            'published_at' => now(),
            'published_by' => $pending->getPublishedBy(),
            'note' => $pending->getNote(),
        ]);

        $rules = $version->rules()->createMany(
            collect($draft->rules)
                ->map(fn (RuleDefinition $rule, int $index): array => [
                    'uuid' => $rule->uuid,
                    'key' => $rule->key,
                    'position' => $index + 1,
                    'condition' => $rule->condition,
                    'condition_hash' => $rule->conditionHash(),
                    'value' => $rule->value,
                    'metadata' => $rule->metadata,
                ])
                ->all(),
        );

        return $version->setRelation('ruleSet', $ruleSet)->setRelation('rules', $rules);
    }
}
