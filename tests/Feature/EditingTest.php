<?php

declare(strict_types=1);

use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Facades\RulesEngine;
use LeeOvery\RulesEngine\Models\RuleSetVersion;
use LeeOvery\RulesEngine\PendingRuleSet;
use LeeOvery\RulesEngine\Tests\Fixtures\Category;
use LeeOvery\RulesEngine\Tests\Fixtures\Merchant;
use LeeOvery\RulesEngine\Tests\Fixtures\TestRuleSet;

function categories(): PendingRuleSet
{
    return RulesEngine::ruleSet(TestRuleSet::MerchantCategories);
}

/** @return array<int, mixed> */
function keysOf(RuleSetVersion $version): array
{
    return $version->rules->pluck('key')->all();
}

beforeEach(function (): void {
    $this->first = categories()
        ->rule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries, ['owner' => 'lee'])
        ->rule('shell', Condition::where('merchant', 'SHELL'), Category::Fuel)
        ->rule('pret', Condition::where('merchant', 'PRET'), Category::EatingOut)
        ->rule('fallback', Condition::always(), Category::Uncategorised)
        ->publish();

    $this->uuids = $this->first->rules->pluck('uuid', 'key')->all();
});

describe('addRule', function (): void {
    it('adds a rule at the end with a new uuid, keeping every other rule as it was', function (): void {
        $version = categories()->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries)->publish();

        expect($version->version)->toBe(2)
            ->and(keysOf($version))->toBe(['tesco', 'shell', 'pret', 'fallback', 'aldi'])
            ->and($version->rules->sole('key', 'aldi')->value)->toBe('groceries')
            ->and($version->rules->take(4)->pluck('uuid', 'key')->all())->toBe($this->uuids)
            ->and($this->uuids)->not->toContain($version->rules->sole('key', 'aldi')->uuid);
    });

    it('adds a rule before or after another', function (): void {
        $version = categories()
            ->addRule(Merchant::Aldi, Condition::where('merchant', 'ALDI'), Category::Groceries, before: Merchant::Shell)
            ->addRule('costa', Condition::where('merchant', 'COSTA'), Category::EatingOut, ['owner' => 'sam'], after: 'pret')
            ->publish();

        expect(keysOf($version))->toBe(['tesco', 'aldi', 'shell', 'pret', 'costa', 'fallback'])
            ->and($version->rules->sole('key', 'costa')->metadata)->toBe(['owner' => 'sam']);
    });

    it('refuses a key that is already there', function (): void {
        categories()->addRule('tesco', Condition::where('merchant', 'TESCO STORES'), Category::Groceries)->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' already has a rule 'tesco'.");

    it('refuses a place next to a rule that is not there', function (): void {
        categories()->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries, after: 'lidl')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");

    it('refuses both before and after', function (): void {
        categories()->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries, before: 'shell', after: 'tesco');
    })->throws(CannotPublishRuleSetException::class, "Rule 'aldi' in rule set 'merchant-categories' can be added before a rule or after one, not both.");
});

describe('updateRule', function (): void {
    it('changes any of a rule\'s value, condition and metadata, keeping its uuid, key and position', function (): void {
        $version = categories()
            ->updateRule('tesco', value: Category::Fuel)
            ->updateRule(Merchant::Shell, condition: Condition::whereStartsWith('merchant', 'SHELL'), metadata: ['owner' => 'sam'])
            ->updateRule('tesco', metadata: null)
            ->publish();

        $tesco = $version->rules->sole('key', 'tesco');
        $shell = $version->rules->sole('key', 'shell');

        expect(keysOf($version))->toBe(['tesco', 'shell', 'pret', 'fallback'])
            ->and($version->rules->pluck('uuid', 'key')->all())->toBe($this->uuids)
            ->and($tesco->value)->toBe('fuel')
            ->and($tesco->metadata)->toBeNull()
            ->and($tesco->condition->getClauses()[0]['value'])->toBe('TESCO')
            ->and($shell->value)->toBe('fuel')
            ->and($shell->metadata)->toBe(['owner' => 'sam'])
            ->and($shell->condition->getClauses()[0]['operator']->value)->toBe('starts_with');
    });

    it('can set a value to null', function (): void {
        $version = categories()->updateRule('pret', value: null)->publish();

        expect($version->rules->sole('key', 'pret')->value)->toBeNull();
    });

    it('refuses a rule that is not there', function (): void {
        categories()->updateRule('lidl', value: Category::Groceries)->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");

    it('refuses an update with nothing to change', function (): void {
        categories()->updateRule('tesco');
    })->throws(CannotPublishRuleSetException::class, "Updating rule 'tesco' in rule set 'merchant-categories' needs a value, condition or metadata.");
});

describe('moveRule', function (): void {
    it('moves a rule before or after another, keeping its uuid', function (): void {
        $version = categories()
            ->moveRule('pret', before: 'tesco')
            ->moveRule(Merchant::Tesco, after: Merchant::Shell)
            ->publish();

        expect(keysOf($version))->toBe(['pret', 'shell', 'tesco', 'fallback'])
            ->and($version->rules->pluck('uuid', 'key')->sortKeys()->all())->toBe(collect($this->uuids)->sortKeys()->all());
    });

    it('needs exactly one of before or after', function (array $place): void {
        categories()->moveRule('pret', ...$place);
    })->with([
        'neither' => [[]],
        'both' => [['before' => 'tesco', 'after' => 'shell']],
    ])->throws(CannotPublishRuleSetException::class, "Moving rule 'pret' in rule set 'merchant-categories' needs exactly one of before or after.");

    it('refuses rules that are not there', function (string $key, string $before): void {
        expect(fn () => categories()->moveRule($key, before: $before)->publish())
            ->toThrow(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");
    })->with([
        'the rule' => ['lidl', 'tesco'],
        'the place' => ['pret', 'lidl'],
    ]);

    it('refuses to move a rule next to itself', function (): void {
        categories()->moveRule('pret', after: 'pret')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule 'pret' in rule set 'merchant-categories' can't move before or after itself.");
});

describe('renameRule', function (): void {
    it('renames a rule, keeping its uuid and position', function (): void {
        $version = categories()->renameRule('tesco', Merchant::Aldi)->publish();

        expect(keysOf($version))->toBe(['aldi', 'shell', 'pret', 'fallback'])
            ->and($version->rules->firstOrFail()->uuid)->toBe($this->uuids['tesco'])
            ->and($version->rules->firstOrFail()->value)->toBe('groceries');
    });

    it('refuses a rule that is not there', function (): void {
        categories()->renameRule('lidl', 'aldi')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");

    it('refuses a name that is taken', function (): void {
        categories()->renameRule('tesco', 'shell')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' already has a rule 'shell'.");
});

describe('removeRule', function (): void {
    it('removes a rule', function (): void {
        $version = categories()->removeRule(Merchant::Shell)->publish();

        expect(keysOf($version))->toBe(['tesco', 'pret', 'fallback'])
            ->and($version->rules->pluck('uuid', 'key')->all())->toBe(collect($this->uuids)->except('shell')->all());
    });

    it('refuses a rule that is not there', function (): void {
        categories()->removeRule('lidl')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' has no rule 'lidl'.");

    it('refuses to leave the set without rules', function (): void {
        categories()->removeRule('tesco')->removeRule('shell')->removeRule('pret')->removeRule('fallback')->publish();
    })->throws(CannotPublishRuleSetException::class, "Rule set 'merchant-categories' can't be published without rules.");
});

it('applies several edits in order', function (): void {
    $version = categories()
        ->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries)
        ->moveRule('aldi', after: 'tesco')
        ->renameRule('aldi', 'aldi-stores')
        ->updateRule('aldi-stores', value: Category::Fuel)
        ->removeRule('pret')
        ->publish();

    expect($version->rules->pluck('value', 'key')->all())->toBe([
        'tesco' => 'groceries',
        'aldi-stores' => 'fuel',
        'shell' => 'fuel',
        'fallback' => 'uncategorised',
    ]);
});

it('publishes nothing when an edit fails', function (): void {
    expect(fn () => categories()->updateRule('tesco', value: Category::Fuel)->removeRule('lidl')->publish())
        ->toThrow(CannotPublishRuleSetException::class);

    expect(RulesEngine::versions(TestRuleSet::MerchantCategories))->toHaveCount(1);
});

it('applies edits to whatever version is current when publishing', function (): void {
    $samsEdit = categories()->updateRule('tesco', value: Category::EatingOut)->publishedBy('sam');

    categories()->addRule('aldi', Condition::where('merchant', 'ALDI'), Category::Groceries)->publishedBy('lee')->publish();

    $version = $samsEdit->publish();

    expect($version->version)->toBe(3)
        ->and($version->rules->pluck('value', 'key')->all())->toBe([
            'tesco' => 'eating-out',
            'shell' => 'fuel',
            'pret' => 'eating-out',
            'fallback' => 'uncategorised',
            'aldi' => 'groceries',
        ]);
});

it('keeps each rule\'s uuid through updates, moves and renames across versions', function (): void {
    categories()->updateRule('tesco', value: Category::Fuel)->publish();
    categories()->moveRule('tesco', after: 'pret')->publish();
    $version = categories()->renameRule('tesco', 'tesco-stores')->publish();

    expect($version->rules->sole('key', 'tesco-stores')->uuid)->toBe($this->uuids['tesco'])
        ->and(keysOf($version))->toBe(['shell', 'pret', 'tesco-stores', 'fallback']);
});

it('gives a key a new uuid when it is added again after being removed', function (): void {
    categories()->removeRule('tesco')->publish();

    $version = categories()->addRule('tesco', Condition::where('merchant', 'TESCO'), Category::Groceries)->publish();

    expect($version->rules->sole('key', 'tesco')->uuid)->not->toBe($this->uuids['tesco']);
});

it('adds the first rules of a new set', function (): void {
    $version = RulesEngine::ruleSet('templates')
        ->addRule('a', Condition::where('category', 'A'), 'template_a')
        ->addRule('default', Condition::always(), 'template_default')
        ->publish();

    expect($version->version)->toBe(1)
        ->and(keysOf($version))->toBe(['a', 'default']);
});

it('edits the version in force on a dated version\'s effective date', function (): void {
    RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2025-04-06')
        ->rule('basic', Condition::where('band', 'basic'), '0.0875')
        ->rule('higher', Condition::where('band', 'higher'), '0.3375')
        ->publish();

    RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2026-04-06')
        ->rule('basic', Condition::where('band', 'basic'), '0.1075')
        ->rule('higher', Condition::where('band', 'higher'), '0.3575')
        ->rule('additional', Condition::where('band', 'additional'), '0.3935')
        ->publish();

    $correction = RulesEngine::ruleSet(TestRuleSet::DividendRates)
        ->effectiveFrom('2025-10-01')
        ->updateRule('basic', value: '0.0900')
        ->publish();

    expect($correction->rules->pluck('value', 'key')->all())->toBe(['basic' => '0.0900', 'higher' => '0.3375'])
        ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2025-12-01')->with('band', 'basic')->firstValue())->toBe('0.0900')
        ->and(RulesEngine::for(TestRuleSet::DividendRates)->asOf('2026-06-01')->with('band', 'basic')->firstValue())->toBe('0.1075');
});
