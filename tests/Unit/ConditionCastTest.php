<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Casts\ConditionCast;
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Models\Rule;

beforeEach(function (): void {
    $this->cast = new ConditionCast;
    $this->model = new Rule;
});

it('casts null to empty condition', function (): void {
    $result = $this->cast->get($this->model, 'condition', null, []);

    expect($result->isEmpty())->toBeTrue();
});

it('casts json string to condition', function (): void {
    $json = '[{"type":"basic","boolean":"and","field":"status","operator":"=","value":"active"}]';

    $result = $this->cast->get($this->model, 'condition', $json, []);

    expect($result->getClauses())->toHaveCount(1)
        ->and($result->getClauses()[0]['field'])->toBe('status');
});

it('casts condition to json string', function (): void {
    $condition = Condition::where('status', 'active');

    $result = $this->cast->set($this->model, 'condition', $condition, []);

    expect($result)->toBe('[{"type":"basic","boolean":"and","field":"status","operator":"=","value":"active"}]');
});

it('casts array to json string', function (): void {
    $array = [['type' => 'basic', 'field' => 'test']];

    $result = $this->cast->set($this->model, 'condition', $array, []);

    expect($result)->toBe('[{"type":"basic","field":"test"}]');
});

it('round trips condition through cast', function (): void {
    $original = Condition::where('type', 'premium')
        ->where('amount', '>', 1000);

    $json = $this->cast->set($this->model, 'condition', $original, []);
    $restored = $this->cast->get($this->model, 'condition', $json, []);

    $restoredJson = $this->cast->set($this->model, 'condition', $restored, []);

    expect($restoredJson)->toBe($json);
});
