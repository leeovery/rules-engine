<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Casts\JsonValueCast;
use LeeOvery\RulesEngine\Models\Rule;
use LeeOvery\RulesEngine\Tests\Fixtures\TransferPurpose;

beforeEach(function (): void {
    $this->cast = new JsonValueCast;
    $this->model = new Rule;
});

it('encodes values as JSON, keeping whole floats as floats', function (mixed $value, string $json): void {
    expect($this->cast->set($this->model, 'value', $value, []))->toBe($json);
})->with([
    'string' => ['loan', '"loan"'],
    'whole float' => [1.0, '1.0'],
    'null' => [null, 'null'],
    'backed enum' => [TransferPurpose::Loan, '"loan"'],
    'array' => [['allowance' => 50000, 'ordinary' => '0.1075'], '{"allowance":50000,"ordinary":"0.1075"}'],
]);

it('decodes JSON into arrays and scalars', function (string $json, mixed $value): void {
    expect($this->cast->get($this->model, 'value', $json, []))->toBe($value);
})->with([
    'string' => ['"loan"', 'loan'],
    'whole float' => ['1.0', 1.0],
    'JSON null' => ['null', null],
    'object' => ['{"allowance":50000}', ['allowance' => 50000]],
]);

it('reads a missing value as null', function (): void {
    expect($this->cast->get($this->model, 'value', null, []))->toBeNull();
});

it('refuses what JSON cannot hold', function (): void {
    $this->cast->set($this->model, 'value', INF, []);
})->throws(JsonException::class);
