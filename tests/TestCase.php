<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Tests;

use Illuminate\Database\Eloquent\Model;
use LeeOvery\RulesEngine\RulesEngineServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::shouldBeStrict();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RulesEngineServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array.serialize', true);
        $app['config']->set('cache.serializable_classes', false);
    }
}
