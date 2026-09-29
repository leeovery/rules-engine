<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class RulesEngineServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('rules-engine')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(ConditionEvaluator::class);
        $this->app->scoped(ConditionParser::class);
        $this->app->scoped(RuleMatcher::class);
        $this->app->scoped(VersionSelector::class);
        $this->app->scoped(VersionDrafter::class);
        $this->app->scoped(RulesEngine::class);

        $this->app->alias(RulesEngine::class, 'rules-engine');
    }
}
