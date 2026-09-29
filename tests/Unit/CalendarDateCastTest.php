<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LeeOvery\RulesEngine\Casts\CalendarDateCast;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

beforeEach(function (): void {
    $this->cast = new CalendarDateCast;
    $this->model = new RuleSetVersion;
});

it('stores only the calendar date', function (mixed $value, ?string $stored): void {
    expect($this->cast->set($this->model, 'effective_from', $value, []))->toBe($stored);
})->with([
    'date string' => ['2026-04-06', '2026-04-06'],
    'date and time string' => ['2026-04-06 23:59:59', '2026-04-06'],
    'date in its own timezone' => [new DateTimeImmutable('2026-04-06 00:30:00', new DateTimeZone('Europe/London')), '2026-04-06'],
    'null' => [null, null],
]);

it('reads a stored date as the start of that day', function (string $stored): void {
    $date = $this->cast->get($this->model, 'effective_from', $stored, []);

    expect($date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($date?->format('Y-m-d H:i:s'))->toBe('2026-04-06 00:00:00');
})->with([
    'date' => '2026-04-06',
    'date with a time' => '2026-04-06 00:00:00',
]);

it('reads a missing date as null', function (): void {
    expect($this->cast->get($this->model, 'effective_from', null, []))->toBeNull();
});
