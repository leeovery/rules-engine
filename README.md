<div align="center">

# Rules Engine

**Versioned business rules for Laravel**

Resolve values from named rule sets against facts, with immutable, dated versions,
<br>rules that keep their identity through every edit, and a record of what each change did.

[![Tests](https://img.shields.io/github/actions/workflow/status/leeovery/rules-engine/tests.yml?branch=main&label=tests)](https://github.com/leeovery/rules-engine/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/leeovery/rules-engine.svg)](https://packagist.org/packages/leeovery/rules-engine)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20.svg)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

[Install](#install) · [Quick Start](#quick-start) · [Core Concepts](#core-concepts) · [Reference](#reference) · [Testing](#testing)

</div>

---

A rule set is a named, ordered list of rules, and each rule has a key, a condition and a value. You resolve a rule set against facts, and the best-matching rule answers.

Rule sets are stored as immutable versions. Every change publishes the next version, and nothing is ever edited or deleted. Each rule keeps a permanent uuid across versions, and every match says which version and rule answered, so a decision can be explained and reproduced later.

## Why Rules Engine?

Business rules change more often than code: which category a merchant belongs to, which rate applies from April, which queue a request goes to. Hard-code them and every change needs a deploy. Keep them in a plain table and every change overwrites the old answer, so nobody can say later why a decision came out the way it did.

- **Every answer can be explained.** A match names the version and the rule that answered, by a uuid that survives updates, moves and renames.
- **Nothing is overwritten.** Every publish writes a new, immutable version, so a past lookup can be run again exactly as it was.
- **Dates are built in.** A version can take effect on a date, so next April's rates can be published today, and a correction to last year changes last year only.
- **Changes are safe and visible.** Edits name rules by key and apply under a row lock, `preview()` shows a change before it's made, and every publish fires an event listing what changed, rule by rule.

## Install

```bash
composer require leeovery/rules-engine
```

The service provider and the `RulesEngine` facade register themselves. The migrations run from the package, so there's nothing to publish. They create three tables, `rule_sets`, `rule_set_versions` and `rules`:

```bash
php artisan migrate
```

Publish the config file if you want to change the cache settings:

```bash
php artisan vendor:publish --tag="rules-engine-config"
```

### Requirements

- **PHP 8.4+**
- **Laravel 13**

## Quick Start

```php
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;

// Publish version 1 of a rule set.
RulesEngine::ruleSet('merchant-categories')
    ->rule('tesco', Condition::whereStartsWith('merchant', 'TESCO'), 'groceries')
    ->rule('shell', Condition::whereStartsWith('merchant', 'SHELL'), 'fuel')
    ->rule('fallback', Condition::always(), 'uncategorised')
    ->publish();

// Resolve it against facts.
RulesEngine::for('merchant-categories')
    ->with(['merchant' => 'TESCO STORES 3021'])
    ->firstValue(); // 'groceries'

// Add a rule. This publishes version 2, and version 1 stays as it was.
RulesEngine::ruleSet('merchant-categories')
    ->addRule('pret', Condition::whereContains('merchant', 'PRET'), 'eating-out', before: 'fallback')
    ->publish();
```

## Core Concepts

```
RulesEngine::for('merchant-categories')->with($facts)->first()
  │
  ├─ picks a version: the one in force on asOf(), among those published by knownAt()
  ├─ scores each of its rules' conditions against the facts
  └─ returns the best match, naming its value, key, uuid, position, score and version
```

- **Rule sets** are named by a string or a backed enum, and hold an ordered list of rules. A set is created the first time it's published.
- **Versions are immutable, and can be dated.** Each publish writes the next version, numbered 1, 2, 3…, holding the complete rule list, and nothing is ever updated or deleted. A version can carry an effective date. Every lookup is as of a date and sees only the versions published by a moment, so a past lookup can be reproduced. See [as of a date, as known at a moment](#as-of-a-date-as-known-at-a-moment) and [effective dates](#effective-dates).
- **Rules have a key and a uuid.** The key names a rule within a version, and it's how you edit the rule. The uuid is its permanent identity: it survives updates, moves and renames, and every match carries it.
- **Conditions are tested against facts.** Conditions are built like a query builder's `where()` clauses and stored as JSON. Resolving scores each rule's condition against the facts you pass: the most specific match wins, and position breaks ties. See [building conditions](#building-conditions) and [scoring and ties](#scoring-and-ties).
- **Publish a list, or edit by key.** Publish a complete list of rules, or edits to the version in force: `addRule()`, `updateRule()`, `moveRule()`, `renameRule()` and `removeRule()`. Publishing refuses anything ambiguous, such as two rules sharing a key or a condition. See [changing rules](#changing-rules).
- **Preview and revert.** `preview()` works out what a publish would do without doing it, and `revertTo()` publishes an earlier version's rules again as the next version.
- **Every publish announces its changes.** `RuleSetVersionPublished` fires inside the publishing transaction, carrying the rules added, removed, renamed and moved, and those whose condition, value or metadata changed. See [events](#events).
- **Matches are cached.** They're keyed by version and facts, and versions never change, so a cached match never goes stale. Choosing the version is never cached, so a new version takes effect at once.
- **Publishing locks the rule set.** Concurrent publishes of one set queue on its row, number their versions in turn, and apply their edits on top of each other rather than overwriting them. See [changing rules](#changing-rules) for how this works on SQLite.

## Reference

### Publishing a Rule Set

The examples below follow one rule set, which sorts card transactions into spending categories by merchant. Name rule sets with a backed enum, or plain strings:

```php
enum RuleSet: string
{
    case MerchantCategories = 'merchant-categories';
}

enum Category: string
{
    case Groceries = 'groceries';
    case Fuel = 'fuel';
    case EatingOut = 'eating-out';
    case Uncategorised = 'uncategorised';
}
```

Publish the first version as a complete list of rules:

```php
use LeeOvery\RulesEngine\Condition;
use LeeOvery\RulesEngine\Facades\RulesEngine;

RulesEngine::ruleSet(RuleSet::MerchantCategories)
    ->description('Spending categories by merchant')
    ->rule('tesco', Condition::whereStartsWith('merchant', 'TESCO'), Category::Groceries)
    ->rule('shell', Condition::whereStartsWith('merchant', 'SHELL'), Category::Fuel)
    ->rule('pret', Condition::whereContains('merchant', 'PRET'), Category::EatingOut)
    ->rule('fallback', Condition::always(), Category::Uncategorised)
    ->publishedBy('alex')
    ->note('Starting categories')
    ->publish();
```

`publish()` stores version 1 and returns it as a `RuleSetVersion` with its rules loaded.

- **Keys** name rules within a version, and must be unique in it. Every key argument takes a backed enum as well as a string, and an enum is stored as its value.
- **Positions** follow the order of the list, starting at 1, and break ties between equally good matches.
- **Values** are stored as JSON. Arrays, scalars and null are fine, a backed enum is stored as its value, and closures and other objects are refused. Metadata, the optional fourth argument, is JSON too.
- **Uuids** are UUIDv7s, given to each rule when it first appears.
- **The description** is used when the set is first created.

Publishing a complete list again creates the next version, holding exactly the rules listed. A rule keeps the uuid of the previous version's rule with the same key, and a new key gets a new uuid.

The facade stands for `LeeOvery\RulesEngine\RulesEngine`, which you can inject instead.

### Resolving

```php
$match = RulesEngine::for(RuleSet::MerchantCategories)
    ->with(['merchant' => 'TESCO STORES 3021', 'amount' => 4250])
    ->first();

$match->as(Category::class); // Category::Groceries
$match->key;                 // 'tesco'
$match->version;             // 1
```

| Method | Returns |
|---|---|
| `first()` | the best `RuleMatch`, or null |
| `firstValue()` | the best match's value, or null |
| `sole()` | the only `RuleMatch`, throwing `NoMatchingRuleException` or `MultipleRulesMatchedException` otherwise |
| `soleValue()` | the only match's value, throwing as `sole()` does |
| `all()` | every `RuleMatch`, best first |
| `resolve()` | a `RuleMatchCollection` of every match, best first |

A `RuleMatch` is a readonly value that says what answered:

```php
$match->ruleSet;    // 'merchant-categories'
$match->version;    // 1
$match->versionId;  // the rule_set_versions id
$match->ruleId;     // the rules id
$match->uuid;       // the rule's permanent uuid
$match->key;        // 'tesco'
$match->position;   // 1
$match->value;      // 'groceries', decoded from JSON
$match->score;      // 1

$match->metadata();                    // all metadata, or null
$match->metadata('source.answer');     // a key, with dot notation
$match->metadata('owner', 'unknown');  // with a default
$match->as(Category::class);           // the enum case for the value
```

Add facts as an array or a key and value. Calling `with()` again adds to them, and a later fact replaces an earlier one with the same key. Backed enums in facts are compared by their values, the same way they're stored in conditions.

```php
RulesEngine::for(RuleSet::MerchantCategories)
    ->with('merchant', 'SHELL 0141')
    ->with(['amount' => 6500])
    ->firstValue(); // 'fuel'
```

### Changing Rules

Instead of listing every rule again, publish edits to the version in force. Edits name rules by key:

```php
RulesEngine::ruleSet(RuleSet::MerchantCategories)
    ->updateRule('pret', value: Category::Groceries)
    ->publishedBy('sam')
    ->note('Pret is lunch shopping')
    ->publish();
```

| Edit | Does | Refuses |
|---|---|---|
| `addRule($key, $condition, $value, $metadata, before: $key, after: $key)` | adds a rule with a new uuid, at the end unless `before` or `after` places it | a key that's already there, a place next to a missing rule, both `before` and `after` |
| `updateRule($key, value: …, condition: …, metadata: …)` | changes any of them, keeping the rule's uuid, key and position; `metadata: null` clears it | a missing key, or nothing to change |
| `moveRule($key, before: $key)` or `moveRule($key, after: $key)` | moves the rule, keeping its uuid | a missing key, anything but exactly one of `before` and `after`, a rule placed next to itself |
| `renameRule($from, $to)` | renames the rule, keeping its uuid and position | a missing `$from`, a `$to` that's already there |
| `removeRule($key)` | leaves the rule out of the next version | a missing key |

One publish can carry several edits, applied in order:

```php
RulesEngine::ruleSet(RuleSet::MerchantCategories)
    ->addRule('aldi', Condition::whereStartsWith('merchant', 'ALDI'), Category::Groceries, after: 'tesco')
    ->moveRule('pret', before: 'tesco')
    ->renameRule('shell', 'shell-garages')
    ->removeRule('fallback')
    ->publish();
```

Edits are applied when you publish, under a lock on the rule set, to whatever version is current then. If someone else publishes in between, both changes land: yours are applied on top of theirs. Publishes of one set queue on that lock and number their versions in turn.

The lock is a `SELECT … FOR UPDATE` on the rule set's row. SQLite has no row locks, so Laravel leaves the lock out there. SQLite lets only one transaction write at a time, so there a publish that races another fails instead of queueing, and never overwrites it.

A rule's uuid never changes while the rule exists, through any number of updates, moves and renames. A key that's removed and later added again is a new rule with a new uuid.

A builder publishes either a complete list or edits, not both.

### Previewing a Change

`preview()` works out what a publish would do, without publishing it or firing anything:

```php
$preview = RulesEngine::ruleSet(RuleSet::MerchantCategories)
    ->addRule('costa', Condition::whereContains('merchant', 'COSTA'), Category::EatingOut)
    ->preview();

$preview->version;          // the number it would get
$preview->previousVersion;  // the version it's compared with
$preview->rules;            // the complete rule list, as RuleDefinition values with keys and uuids
$preview->changes;          // the RuleSetChanges it would make
```

It refuses whatever publishing would refuse.

### Reverting

`revertTo()` publishes a copy of an earlier version's rules, with the same keys and uuids, as the next version:

```php
RulesEngine::ruleSet(RuleSet::MerchantCategories)
    ->publishedBy('alex')
    ->note('Back to the starting categories')
    ->revertTo(1);
```

It can't be combined with a list or edits. A version that doesn't exist throws `NoApplicableVersionException`. In a dated set, the copy needs an effective date of its own, as any new version does: `->effectiveFrom('2027-04-06')->revertTo(1)`.

### Seeing What Changed

`RuleSetChanges` compares two versions rule by rule, matching rules by uuid:

```php
$changes = RulesEngine::changes(RuleSet::MerchantCategories, from: 1, to: 3);

$changes->added;             // RuleReference values, each with a uuid and key
$changes->removed;
$changes->renamed;           // RenamedRule values, each with a uuid, from and to
$changes->conditionChanged;
$changes->valueChanged;
$changes->metadataChanged;
$changes->moved;
$changes->isEmpty();
```

`moved` lists the fewest rules whose moves give the new order, so adding or removing a rule doesn't count the rules around it as moved. Values and metadata are compared as they're stored.

Every published version carries its changes from the version it follows, in its event. For version 1, every rule is added.

### As of a Date, as Known at a Moment

Every lookup is as of a date, today unless you pass `asOf()`, and considers only versions published at or before a moment, now unless you pass `knownAt()`:

```php
RulesEngine::for(RuleSet::MerchantCategories)
    ->asOf($transaction->booked_on)         // the version in force on that date
    ->knownAt($transaction->categorised_at) // only versions published by then
    ->with(['merchant' => $transaction->merchant])
    ->first();
```

- **The version in force on a date** is the one with the greatest effective date on or before it, and among those, the highest version number. A version without an effective date is in force from the start, below any dated version. Merchant categories have no effective dates, so their newest version is always in force, whatever the date.
- **`asOf()`** takes a `DateTimeInterface` or a string, and reads the date in the value's own timezone.
- **`knownAt()`** reproduces a lookup as it was at a moment, to the microsecond, in any timezone. It defaults to now, so versions published after the current time are invisible: a test that travels back in time needs its versions published by then, or an explicit `knownAt()`.
- **`atVersion(3)`** pins an exact version, whatever `asOf` and `knownAt` say, for re-scoring history.

Choosing the version is a small query that's never cached, so a new version takes effect at once. If no version applies, the lookup throws `NoApplicableVersionException`, naming the date and moment it looked for. An unknown rule set throws `RuleSetNotFoundException`.

### Effective Dates

Reference data such as tax rates changes on known dates. Give its versions effective dates. With a `UkDividendRates = 'uk.dividend-rates'` case on the enum:

```php
RulesEngine::ruleSet(RuleSet::UkDividendRates)
    ->effectiveFrom('2026-04-06')
    ->rule('rates', Condition::always(), [
        'allowance' => 50000,
        'ordinary' => '0.1075',
        'upper' => '0.3575',
        'additional' => '0.3935',
    ])
    ->publish();

$rates = RulesEngine::for(RuleSet::UkDividendRates)->asOf($dividend->paid_on)->firstValue();
```

- **A set's first version decides.** If it has an effective date, every later version needs one. If it doesn't, none may have one.
- **Effective dates are calendar dates**, given as a `DateTimeInterface` or a string. A `DateTimeInterface` keeps the date in its own timezone.
- **No version can take effect before the first version's date.** A later version can take effect on an earlier date than the newest, which corrects that period: it replaces what was in force from its date until the next dated version.
- **A new version follows the version in force on its effective date.** Edits apply to that version, and changes are measured from it, so a correction for last year edits last year's rates, not this year's.

### What Publishing Refuses

Publishing throws `CannotPublishRuleSetException`, and stores nothing, when:

- there are no rules;
- two rules share a key;
- two rules share a condition, since the second could never be chosen first;
- a value, metadata or condition holds something JSON can't store;
- an effective date breaks the first version's choice, or comes before its date;
- an edit refers to a missing rule, or would reuse a taken key.

Mixing a list and edits, or a revert with either, throws straight away, and so do an `addRule()` with both `before` and `after`, a `moveRule()` without exactly one of them, and an `updateRule()` with nothing to change.

### Events

Publishing runs in one database transaction and dispatches `LeeOvery\RulesEngine\Events\RuleSetVersionPublished` inside it, after the version is written and before the transaction commits. The event carries `ruleSet` (the name), `version`, `effectiveFrom`, `publishedBy`, `note` and `changes`, the `RuleSetChanges` from the version it follows.

```php
use Illuminate\Support\Facades\Event;
use LeeOvery\RulesEngine\Events\RuleSetVersionPublished;

Event::listen(function (RuleSetVersionPublished $event): void {
    foreach ($event->changes->valueChanged as $rule) {
        // re-categorise the transactions that $rule->uuid answered
    }
});
```

The event doesn't wait for the commit: listeners run inside the transaction, and if one throws, the version is rolled back. A queued listener's job is pushed at once too, unless the listener or its queue connection waits for commits (`after_commit`). On a `database` queue on the same connection, a job pushed at once is written in the same transaction as the version, so neither lands without the other.

### Immutability

`RuleSetVersion` and `Rule` models throw `ImmutableRecordException` when you update, increment or delete them, including the quiet variants and while model events are faked. A rule set that has versions can't be deleted either: the foreign keys restrict it.

### Building Conditions

Conditions use a fluent API modelled on Laravel's query builder.

```php
use LeeOvery\RulesEngine\Operator;

// Equals (default operator)
Condition::where('merchant', 'TESCO')

// With an explicit operator, as a string or an Operator case
Condition::where('amount', '>', 10000)
Condition::where('amount', Operator::GreaterThan, 10000)

// AND and OR
Condition::whereStartsWith('merchant', 'SHELL')->where('amount', '<', 2000)
Condition::whereContains('merchant', 'PRET')->orWhereContains('merchant', 'COSTA')

// Nested: type = 'card' AND (country = 'UK' OR country = 'IE')
Condition::where('type', 'card')
    ->where(fn ($q) => $q->where('country', 'UK')->orWhere('country', 'IE'))
```

Clauses combine from left to right. Without nesting, `->orWhere()` applies to the whole condition chain before it, and a `->where()` after it applies to the whole result, so `where('a', 1)->orWhere('b', 2)->where('c', 3)` means (a or b) and c. Nest a closure to group clauses any other way.

```php
Condition::whereIn('status', ['pending', 'booked'])
Condition::whereNotIn('type', ['refund', 'fee'])
Condition::whereNot('archived', true)
Condition::whereNull('merchant')
Condition::whereNotNull('merchant')
Condition::whereContains('merchant', 'PRET')
Condition::whereStartsWith('merchant', 'TESCO')
Condition::whereEndsWith('merchant', 'LTD')

// Every method has an 'or' variant
Condition::where('a', 1)->orWhereIn('b', [2, 3])

Condition::always() // matches any facts
Condition::else()   // the same, reads well as a last rule
```

| Operator | String | Example |
|----------|--------|---------|
| Equals | `=`, `==` | `where('status', 'booked')` |
| NotEquals | `!=`, `<>` | `where('status', '!=', 'pending')` |
| GreaterThan | `>` | `where('amount', '>', 1000)` |
| GreaterThanOrEquals | `>=` | `where('amount', '>=', 1000)` |
| LessThan | `<` | `where('amount', '<', 1000)` |
| LessThanOrEquals | `<=` | `where('amount', '<=', 1000)` |
| In | `in` | `whereIn('status', ['a', 'b'])` |
| NotIn | `not_in` | `whereNotIn('type', ['x'])` |
| Contains | `contains` | `whereContains('merchant', 'PRET')` |
| StartsWith | `starts_with` | `whereStartsWith('merchant', 'TESCO')` |
| EndsWith | `ends_with` | `whereEndsWith('merchant', 'LTD')` |

Equality and `in` are strict, so `1` doesn't equal `'1'`, and `true` doesn't equal `1`. `>`, `>=`, `<` and `<=` use PHP's comparison operators, and `contains`, `starts_with` and `ends_with` compare case-sensitive strings. A condition on a fact that isn't there doesn't match, not even `whereNull()` or `whereNotIn()`.

### Scoring and Ties

The engine scores matches with MAX-CSP (maximum constraint satisfaction): a match scores the number of clauses satisfied along the most specific way its condition matched. Clauses combine from left to right, as in [building conditions](#building-conditions):

- a clause scores 1 when it matches, and 0 when it doesn't;
- an AND scores the sum of its two sides when both match; when either side fails, the AND fails and scores 0, however many of its clauses matched;
- an OR scores the higher of the sides that matched, so a side that failed adds nothing;
- a nested group is scored the same way, as one side;
- `Condition::always()` matches with a score of 0.

So `where('a', 1)->where('b', 2)->where('c', 3)->orWhere('d', 4)` scores 3 when a, b and c match, whether or not d does, and 1 when d matches but c doesn't, even if a and b do.

Matches are ordered by score, highest first, so the most specific matching rule wins. Equal scores are ordered by position, lowest first, so ties never depend on database order.

### Caching

Matches are cached for the configured time, keyed by the version's id and the facts. Versions never change, so a cached result never goes stale, and a new version is used as soon as it's published.

```php
RulesEngine::for(RuleSet::MerchantCategories)->with($facts)->cache(3600)->first();   // seconds
RulesEngine::for(RuleSet::MerchantCategories)->with($facts)->withoutCache()->first();
```

`cache()` takes a number of seconds greater than zero. The defaults live in the config file:

```php
// config/rules-engine.php
return [
    'cache' => [
        'ttl' => 3600, // seconds; null turns caching off by default
        'prefix' => 'rules-engine',
    ],
];
```

Matches are cached as plain arrays, so they work with Laravel 13's default `cache.serializable_classes => false`.

### Reading History

```php
RulesEngine::versions(RuleSet::MerchantCategories);                  // every RuleSetVersion, oldest first
RulesEngine::versions(RuleSet::MerchantCategories)->load('rules');
RulesEngine::version(RuleSet::MerchantCategories, 2);                // one version; its rules load on access
RulesEngine::newestVersion(RuleSet::MerchantCategories);             // the version published last, whatever its effective date
RulesEngine::changes(RuleSet::MerchantCategories, from: 1, to: 2);   // what changed between them
```

A `RuleSetVersion` has `version`, `effective_from` (a `CarbonImmutable` date or null), `published_at` (a `CarbonImmutable` with microseconds), `published_by`, `note`, and its `ruleSet` and `rules`, ordered by position.

### Database Schema

#### rule_sets

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Unique name |
| description | text | Optional description |
| created_at, updated_at | timestamp | |

#### rule_set_versions

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| rule_set_id | bigint | The rule set; unique with `version` |
| version | int | 1, 2, 3… within the set |
| effective_from | date | When a dated version takes effect |
| published_at | timestamp(6) | When it was published |
| published_by | string | Who published it |
| note | text | Why |

#### rules

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| rule_set_version_id | bigint | The version |
| uuid | uuid | The rule's permanent identity, the same in every version; unique within the version |
| key | string | The rule's name, unique within the version |
| position | int | Order within the version, unique |
| condition | json | The condition |
| condition_hash | string | SHA-256 of the condition, unique within the version |
| value | json | The value |
| metadata | json | Optional metadata |

## Testing

```bash
composer test          # Pest
composer analyse       # PHPStan
composer format:check  # Pint, checking only
composer format        # Pint, fixing the style
composer ci            # the style check, PHPStan and the tests
```

The suite runs on SQLite by default. Postgres is the only database that lets the row-locking tests hold a lock from a second session, so they're skipped elsewhere. Point the suite at a Postgres database to run them:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=rules_engine_testing DB_USERNAME=postgres DB_PASSWORD=secret composer test
```

CI runs the suite on SQLite and on Postgres 17, with PHP 8.4 and 8.5, against the lowest and the newest dependencies.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
