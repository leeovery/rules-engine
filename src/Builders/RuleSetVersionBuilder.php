<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Builders;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use LeeOvery\RulesEngine\Models\RuleSetVersion;

/**
 * @extends Builder<RuleSetVersion>
 */
class RuleSetVersionBuilder extends Builder
{
    public function whereVersion(int $version): self
    {
        return $this->where('version', $version);
    }

    public function knownAt(DateTimeInterface $moment): self
    {
        $moment = CarbonImmutable::instance($moment)->setTimezone(date_default_timezone_get());

        return $this->where('published_at', '<=', $this->model->fromDateTime($moment));
    }

    public function inForceOn(DateTimeInterface $date): self
    {
        return $this
            ->where(fn (self $query): self => $query
                ->whereNull('effective_from')
                ->orWhere('effective_from', '<=', $date->format('Y-m-d')))
            ->undatedLast()
            ->orderByDesc('effective_from')
            ->newestFirst();
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('version');
    }

    private function undatedLast(): self
    {
        return $this->orderByRaw('case when effective_from is null then 1 else 0 end');
    }
}
