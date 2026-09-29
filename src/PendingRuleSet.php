<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Str;
use LeeOvery\RulesEngine\Actions\PublishRuleSetVersionAction;
use LeeOvery\RulesEngine\Exceptions\CannotPublishRuleSetException;
use LeeOvery\RulesEngine\Models\RuleSet;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

class PendingRuleSet
{
    private readonly string $name;

    private ?string $description = null;

    private ?CarbonImmutable $effectiveFrom = null;

    private ?string $publishedBy = null;

    private ?string $note = null;

    /** @var list<RuleDefinition> */
    private array $rules = [];

    /** @var list<Closure(RuleList): RuleList> */
    private array $edits = [];

    private ?int $revertVersion = null;

    public function __construct(BackedEnum|string $name)
    {
        $this->name = Name::of($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function effectiveFrom(DateTimeInterface|string $date): self
    {
        $this->effectiveFrom = CarbonImmutable::parse($date)->startOfDay();

        return $this;
    }

    public function publishedBy(string $publisher): self
    {
        $this->publishedBy = $publisher;

        return $this;
    }

    public function note(string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>|null  $metadata
     */
    public function rule(BackedEnum|string $key, ConditionBuilder $condition, mixed $value, ?array $metadata = null): self
    {
        throw_if($this->edits !== [], CannotPublishRuleSetException::listAndEdits($this->name));

        $this->rules[] = new RuleDefinition(Name::of($key), $condition, $value, $metadata);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>|null  $metadata
     */
    public function addRule(
        BackedEnum|string $key,
        ConditionBuilder $condition,
        mixed $value,
        ?array $metadata = null,
        BackedEnum|string|null $before = null,
        BackedEnum|string|null $after = null,
    ): self {
        throw_if($before !== null && $after !== null, CannotPublishRuleSetException::beforeAndAfter($this->name, Name::of($key)));

        return $this->edit(fn (RuleList $rules): RuleList => $rules->add(
            new RuleDefinition(Name::of($key), $condition, $value, $metadata, (string) Str::uuid7()),
            $this->nameOrNull($before),
            $this->nameOrNull($after),
        ));
    }

    /**
     * @param  array<array-key, mixed>|Unchanged|null  $metadata
     */
    public function updateRule(
        BackedEnum|string $key,
        mixed $value = Unchanged::Keep,
        ConditionBuilder|Unchanged $condition = Unchanged::Keep,
        array|Unchanged|null $metadata = Unchanged::Keep,
    ): self {
        throw_if(
            $value instanceof Unchanged && $condition instanceof Unchanged && $metadata instanceof Unchanged,
            CannotPublishRuleSetException::nothingToUpdate($this->name, Name::of($key)),
        );

        return $this->edit(fn (RuleList $rules): RuleList => $rules->update(Name::of($key), $condition, $value, $metadata));
    }

    public function moveRule(BackedEnum|string $key, BackedEnum|string|null $before = null, BackedEnum|string|null $after = null): self
    {
        throw_if(($before === null) === ($after === null), CannotPublishRuleSetException::moveNeedsOnePlace($this->name, Name::of($key)));

        return $this->edit(fn (RuleList $rules): RuleList => $rules->move(Name::of($key), $this->nameOrNull($before), $this->nameOrNull($after)));
    }

    public function renameRule(BackedEnum|string $from, BackedEnum|string $to): self
    {
        return $this->edit(fn (RuleList $rules): RuleList => $rules->rename(Name::of($from), Name::of($to)));
    }

    public function removeRule(BackedEnum|string $key): self
    {
        return $this->edit(fn (RuleList $rules): RuleList => $rules->remove(Name::of($key)));
    }

    public function publish(): RuleSetVersion
    {
        return resolve(PublishRuleSetVersionAction::class)($this);
    }

    public function preview(): DraftVersion
    {
        return resolve(VersionDrafter::class)->draft($this, RuleSet::query()->whereName($this->name)->first());
    }

    public function revertTo(int $version): RuleSetVersion
    {
        throw_if($this->rules !== [] || $this->edits !== [], CannotPublishRuleSetException::revertWithChanges($this->name));

        $this->revertVersion = $version;

        return $this->publish();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getEffectiveFrom(): ?CarbonImmutable
    {
        return $this->effectiveFrom;
    }

    public function getPublishedBy(): ?string
    {
        return $this->publishedBy;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * @return list<RuleDefinition>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return list<Closure(RuleList): RuleList>
     */
    public function getEdits(): array
    {
        return $this->edits;
    }

    public function getRevertVersion(): ?int
    {
        return $this->revertVersion;
    }

    /**
     * @param  Closure(RuleList): RuleList  $edit
     */
    private function edit(Closure $edit): self
    {
        throw_if($this->rules !== [], CannotPublishRuleSetException::listAndEdits($this->name));

        $this->edits[] = $edit;

        return $this;
    }

    private function nameOrNull(BackedEnum|string|null $name): ?string
    {
        return $name === null ? null : Name::of($name);
    }
}
