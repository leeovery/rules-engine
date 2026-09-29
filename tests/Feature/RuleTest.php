<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Models\Rule;

describe('hashCondition', function (): void {
    it('returns a 64 character sha256 hash', function (): void {
        $hash = Rule::hashCondition(Condition::where('field', 'value'));

        expect(strlen($hash))->toBe(64);
    });

    it('produces same hash for equivalent conditions', function (): void {
        $condition1 = Condition::where('field', 'value');
        $condition2 = Condition::where('field', 'value');

        expect(Rule::hashCondition($condition1))->toBe(Rule::hashCondition($condition2));
    });

    it('produces different hash for different conditions', function (): void {
        $condition1 = Condition::where('field', 'value1');
        $condition2 = Condition::where('field', 'value2');

        expect(Rule::hashCondition($condition1))->not->toBe(Rule::hashCondition($condition2));
    });
});
