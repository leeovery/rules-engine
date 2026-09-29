<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use LeeOvery\RulesEngine\Exceptions\NoApplicableVersionException;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

class VersionSelector
{
    public function select(RuleSet $ruleSet, PendingRule $pendingRule): RuleSetVersion
    {
        $version = $this->find($ruleSet, $pendingRule) ?? throw $this->noVersion($ruleSet, $pendingRule);

        return $version->setRelation('ruleSet', $ruleSet);
    }

    private function find(RuleSet $ruleSet, PendingRule $pendingRule): ?RuleSetVersion
    {
        if ($pendingRule->getVersion() !== null) {
            return $ruleSet->versions()->whereVersion($pendingRule->getVersion())->first();
        }

        return $ruleSet->versions()
            ->knownAt($pendingRule->getKnownAt())
            ->inForceOn($pendingRule->getAsOf())
            ->first();
    }

    private function noVersion(RuleSet $ruleSet, PendingRule $pendingRule): NoApplicableVersionException
    {
        return $pendingRule->getVersion() === null
            ? NoApplicableVersionException::inForce($ruleSet->name, $pendingRule->getAsOf(), $pendingRule->getKnownAt())
            : NoApplicableVersionException::numbered($ruleSet->name, $pendingRule->getVersion());
    }
}
