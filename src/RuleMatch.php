<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class RuleMatch implements Arrayable
{
    /**
     * @param  array<array-key, mixed>|null  $metadata
     */
    public function __construct(
        public string $ruleSet,
        public int $version,
        public int $versionId,
        public int $ruleId,
        public string $uuid,
        public string $key,
        public int $position,
        public mixed $value,
        public int $score,
        private ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $match
     */
    public static function fromArray(array $match): self
    {
        return new self(
            ruleSet: $match['rule_set'],
            version: $match['version'],
            versionId: $match['version_id'],
            ruleId: $match['rule_id'],
            uuid: $match['uuid'],
            key: $match['key'],
            position: $match['position'],
            value: $match['value'],
            score: $match['score'],
            metadata: $match['metadata'],
        );
    }

    public function metadata(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->metadata : data_get($this->metadata, $key, $default);
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enumClass
     * @return TEnum
     */
    public function as(string $enumClass): BackedEnum
    {
        return $enumClass::from($this->value);
    }

    public function toArray(): array
    {
        return [
            'rule_set' => $this->ruleSet,
            'version' => $this->version,
            'version_id' => $this->versionId,
            'rule_id' => $this->ruleId,
            'uuid' => $this->uuid,
            'key' => $this->key,
            'position' => $this->position,
            'value' => $this->value,
            'score' => $this->score,
            'metadata' => $this->metadata,
        ];
    }
}
