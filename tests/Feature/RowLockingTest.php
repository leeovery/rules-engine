<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Only Postgres lets a second session hold a row lock that these tests can wait on.');
    }

    config()->set('database.connections.second_session', config('database.connections.'.DB::getDefaultConnection()));

    $secondSession = DB::connection('second_session');
    $secondSession->statement("set lock_timeout = '5s'");
    $secondSession->table('rule_sets')->insert(['name' => 'shared', 'created_at' => now(), 'updated_at' => now()]);

    // A share lock lets a new version's foreign key check through but not a lock for update,
    // so only work that locks the rule set itself has to wait for the second session.
    $secondSession->beginTransaction();
    $secondSession->select("select id from rule_sets where name = 'shared' for share");

    DB::statement("set local lock_timeout = '200ms'");

    $this->beforeApplicationDestroyed(function () use ($secondSession): void {
        $secondSession->rollBack();
        $secondSession->table('rule_sets')->where('name', 'shared')->delete();
        DB::purge('second_session');
    });
});

it('locks the rule set while publishing, so publishes of one set queue', function (): void {
    expect(fn () => publishUndated('shared', 'value'))->toThrow(QueryException::class, 'lock timeout');
});

it('locks the rule set while publishing edits', function (): void {
    expect(fn () => RulesEngine::ruleSet('shared')->addRule('value', Condition::always(), 'value')->publish())
        ->toThrow(QueryException::class, 'lock timeout');
});
