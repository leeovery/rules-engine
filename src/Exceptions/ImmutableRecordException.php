<?php

declare(strict_types=1);

namespace LeeOvery\RulesEngine\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ImmutableRecordException extends LogicException
{
    public static function for(Model $model): self
    {
        return new self(sprintf(
            '%s %s is immutable. Publish a new version instead.',
            class_basename($model),
            $model->getKey(),
        ));
    }
}
