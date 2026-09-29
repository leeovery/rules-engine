<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Models\Concerns;

use LeeOvery\RulesEngine\Exceptions\ImmutableRecordException;

trait Immutable
{
    // Every update, increment and delete of a stored model sets its keys here, including the
    // quiet variants and those made while model events are faked, so refusing here refuses them all.
    protected function setKeysForSaveQuery($query): never
    {
        throw ImmutableRecordException::for($this);
    }
}
