<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\ConditionBuilder;
use LeeOvery\RulesEngine\ConditionEvaluator;
use LeeOvery\RulesEngine\Operator;

beforeEach(function (): void {
    $this->evaluator = new ConditionEvaluator;
});

it('returns matched true with score 0 for empty condition', function (): void {
    $result = $this->evaluator->evaluate(Condition::always(), ['any' => 'data']);

    expect($result->matched)->toBeTrue()
        ->and($result->score)->toBe(0);
});

describe('comparison operators', function (): void {
    it('evaluates equals operator', function (): void {
        $condition = Condition::where('status', 'active');

        expect($this->evaluator->evaluate($condition, ['status' => 'active'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'inactive'])->matched)->toBeFalse();
    });

    it('evaluates not equals operator', function (): void {
        $condition = Condition::where('status', '!=', 'deleted');

        expect($this->evaluator->evaluate($condition, ['status' => 'active'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'deleted'])->matched)->toBeFalse();
    });

    it('evaluates numeric comparisons', function (string $operator, int $threshold, int $passing, int $failing): void {
        $condition = Condition::where('amount', $operator, $threshold);

        expect($this->evaluator->evaluate($condition, ['amount' => $passing])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['amount' => $failing])->matched)->toBeFalse();
    })->with([
        'greater than' => ['>', 100, 150, 50],
        'greater than or equals' => ['>=', 100, 100, 50],
        'less than' => ['<', 100, 50, 150],
        'less than or equals' => ['<=', 100, 100, 150],
    ]);
});

describe('array operators', function (): void {
    it('evaluates in operator', function (): void {
        $condition = Condition::where('status', 'in', ['active', 'pending']);

        expect($this->evaluator->evaluate($condition, ['status' => 'active'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'pending'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'deleted'])->matched)->toBeFalse();
    });

    it('evaluates not_in operator', function (): void {
        $condition = Condition::where('status', 'not_in', ['deleted', 'archived']);

        expect($this->evaluator->evaluate($condition, ['status' => 'active'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'deleted'])->matched)->toBeFalse();
    });
});

describe('string operators', function (): void {
    it(
        'evaluates string matching',
        function (string $operator, string $pattern, string $passing, string $failing): void {
            $condition = Condition::where('value', $operator, $pattern);

            expect($this->evaluator->evaluate($condition, ['value' => $passing])->matched)->toBeTrue()
                ->and($this->evaluator->evaluate($condition, ['value' => $failing])->matched)->toBeFalse();
        }
    )->with([
        'contains' => ['contains', '@example', 'user@example.com', 'user@other.com'],
        'starts_with' => ['starts_with', 'PRE_', 'PRE_123', 'POST_123'],
        'ends_with' => ['ends_with', '.pdf', 'document.pdf', 'document.doc'],
    ]);
});

describe('missing fields', function (): void {
    it('returns false when field is missing from data', function (): void {
        $condition = Condition::where('missing_field', 'value');

        $result = $this->evaluator->evaluate($condition, ['other_field' => 'value']);

        expect($result->matched)->toBeFalse()
            ->and($result->score)->toBe(0);
    });
});

describe('boolean logic', function (): void {
    it('evaluates AND conditions correctly', function (): void {
        $condition = Condition::where('a', 1)->where('b', 2)->where('c', 3);

        $result = $this->evaluator->evaluate($condition, ['a' => 1, 'b' => 2, 'c' => 3]);
        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(3);

        $result = $this->evaluator->evaluate($condition, ['a' => 1, 'b' => 2, 'c' => 999]);
        expect($result->matched)->toBeFalse();
    });

    it('evaluates OR conditions correctly', function (): void {
        $condition = Condition::where('status', 'approved')
            ->orWhere('status', 'completed');

        expect($this->evaluator->evaluate($condition, ['status' => 'approved'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'completed'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['status' => 'pending'])->matched)->toBeFalse();
    });
});

describe('scoring', function (): void {
    it('scores each matched condition as 1', function (): void {
        $condition = Condition::where('status', 'active');

        $result = $this->evaluator->evaluate($condition, ['status' => 'active']);

        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(1);
    });

    it('calculates score for OR as best matching path', function (): void {
        $condition = Condition::where('a', 1)
            ->orWhere('b', 2);

        $result = $this->evaluator->evaluate($condition, ['a' => 1]);
        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(1);

        $result = $this->evaluator->evaluate($condition, ['a' => 1, 'b' => 2]);
        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(1);
    });

    it('calculates score for nested conditions', function (): void {
        $condition = Condition::where('type', 'premium')
            ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'US'));

        $result = $this->evaluator->evaluate($condition, ['type' => 'premium', 'country' => 'UK']);

        expect($result->score)->toBe(2);
    });

    it('scores a failed AND group as 0, however many of its clauses matched', function (): void {
        $result = $this->evaluator->evaluate(Condition::where('a', 1)->where('b', 2), ['a' => 1, 'b' => 0]);

        expect($result->matched)->toBeFalse()
            ->and($result->score)->toBe(0);
    });

    it('scores an OR by the side that matched, not by what a failed side partly matched', function (): void {
        $condition = Condition::where('a', 1)->where('b', 2)->where('c', 3)->orWhere('d', 4);

        $result = $this->evaluator->evaluate($condition, ['a' => 1, 'b' => 2, 'c' => 0, 'd' => 4]);

        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(1);
    });

    it('scores a failed nested group as 0 on its side of an OR', function (): void {
        $condition = Condition::where(fn ($q) => $q->where('a', 1)->where('b', 2)->where('c', 3))->orWhere('d', 4);

        $result = $this->evaluator->evaluate($condition, ['a' => 1, 'b' => 2, 'c' => 0, 'd' => 4]);

        expect($result->matched)->toBeTrue()
            ->and($result->score)->toBe(1);
    });

    it('scores an OR by its better side when both sides match, whichever side that is', function (): void {
        $facts = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];

        $threeFirst = Condition::where('a', 1)->where('b', 2)->where('c', 3)->orWhere('d', 4);
        $threeLast = Condition::where('d', 4)->orWhere(fn ($q) => $q->where('a', 1)->where('b', 2)->where('c', 3));

        expect($this->evaluator->evaluate($threeFirst, $facts)->score)->toBe(3)
            ->and($this->evaluator->evaluate($threeLast, $facts)->score)->toBe(3);
    });
});

describe('nested conditions', function (): void {
    it('evaluates nested conditions', function (): void {
        $condition = Condition::where('type', 'premium')
            ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'US'));

        expect($this->evaluator->evaluate($condition, ['type' => 'premium', 'country' => 'UK'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['type' => 'premium', 'country' => 'US'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['type' => 'premium', 'country' => 'FR'])->matched)->toBeFalse(
            )
            ->and($this->evaluator->evaluate($condition, ['type' => 'basic', 'country' => 'UK'])->matched)->toBeFalse();
    });

    it('evaluates empty nested condition as matched', function (): void {
        $condition = Condition::where('type', 'premium')
            ->where(fn ($q) => $q); // Empty nested condition

        $result = $this->evaluator->evaluate($condition, ['type' => 'premium']);

        expect($result->matched)->toBeTrue()
            // Only the type clause scores
            ->and($result->score)->toBe(1);
    });
});

describe('value types', function (): void {
    it('compares float values', function (): void {
        $condition = Condition::where('price', '>', 99.99);

        expect($this->evaluator->evaluate($condition, ['price' => 100.00])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['price' => 99.99])->matched)->toBeFalse()
            ->and($this->evaluator->evaluate($condition, ['price' => 50.50])->matched)->toBeFalse();
    });

    it('compares float equality', function (): void {
        $condition = Condition::where('rate', 0.15);

        expect($this->evaluator->evaluate($condition, ['rate' => 0.15])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['rate' => 0.16])->matched)->toBeFalse();
    });

    it('compares null values', function (): void {
        $condition = Condition::where('deleted_at');

        expect($this->evaluator->evaluate($condition, ['deleted_at' => null])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['deleted_at' => '2024-01-01'])->matched)->toBeFalse();
    });

    it('compares not null values', function (): void {
        $condition = Condition::whereNotNull('verified_at');

        expect($this->evaluator->evaluate($condition, ['verified_at' => '2024-01-01'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['verified_at' => null])->matched)->toBeFalse();
    });

    it('compares boolean values', function (): void {
        $condition = Condition::where('is_active', true);

        expect($this->evaluator->evaluate($condition, ['is_active' => true])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['is_active' => false])->matched)->toBeFalse();
    });

    it('distinguishes boolean from integer', function (): void {
        $condition = Condition::where('flag', true);

        expect($this->evaluator->evaluate($condition, ['flag' => true])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['flag' => 1])->matched)->toBeFalse();
    });

    it('compares array values for equality', function (): void {
        $condition = Condition::where('tags', ['a', 'b', 'c']);

        expect($this->evaluator->evaluate($condition, ['tags' => ['a', 'b', 'c']])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['tags' => ['a', 'b']])->matched)->toBeFalse()
            ->and($this->evaluator->evaluate($condition, ['tags' => ['c', 'b', 'a']])->matched)->toBeFalse();
    });

    it('compares object instances with strict equality', function (): void {
        $object = new stdClass;
        $object->name = 'test';

        $condition = ConditionBuilder::fromClauses([
            [
                'type' => 'basic',
                'boolean' => 'and',
                'field' => 'instance',
                'operator' => Operator::Equals,
                'value' => $object,
            ],
        ]);

        expect($this->evaluator->evaluate($condition, ['instance' => $object])->matched)->toBeTrue();

        $differentObject = new stdClass;
        $differentObject->name = 'test';

        expect($this->evaluator->evaluate($condition, ['instance' => $differentObject])->matched)->toBeFalse();
    });

    it('checks if value is in array of mixed types', function (): void {
        $condition = Condition::where('value', 'in', [1, 'two', 3.0, true, null]);

        expect($this->evaluator->evaluate($condition, ['value' => 1])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => 'two'])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => 3.0])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => true])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => null])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => 'other'])->matched)->toBeFalse();
    });
});

describe('strict comparison', function (): void {
    it('compares equals strictly', function (): void {
        $condition = Condition::where('value', 1);

        expect($this->evaluator->evaluate($condition, ['value' => 1])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => '1'])->matched)->toBeFalse();
    });

    it('compares not equals strictly', function (): void {
        $condition = Condition::where('value', '!=', 1);

        expect($this->evaluator->evaluate($condition, ['value' => 1])->matched)->toBeFalse()
            ->and($this->evaluator->evaluate($condition, ['value' => '1'])->matched)->toBeTrue();
    });

    it('compares in strictly', function (): void {
        $condition = Condition::whereIn('value', [1, 2, 3]);

        expect($this->evaluator->evaluate($condition, ['value' => 1])->matched)->toBeTrue()
            ->and($this->evaluator->evaluate($condition, ['value' => '1'])->matched)->toBeFalse();
    });

    it('compares not_in strictly', function (): void {
        $condition = Condition::whereNotIn('value', [1, 2, 3]);

        expect($this->evaluator->evaluate($condition, ['value' => 1])->matched)->toBeFalse()
            ->and($this->evaluator->evaluate($condition, ['value' => '1'])->matched)->toBeTrue();
    });

    it('ignores a strict flag on a clause', function (): void {
        $condition = ConditionBuilder::fromClauses([
            [
                'type' => 'basic',
                'boolean' => 'and',
                'field' => 'value',
                'operator' => Operator::Equals,
                'value' => 1,
                'strict' => false,
            ],
        ]);

        expect($this->evaluator->evaluate($condition, ['value' => '1'])->matched)->toBeFalse();
    });
});
