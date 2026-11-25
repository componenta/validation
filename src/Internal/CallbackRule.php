<?php

declare(strict_types=1);

namespace Componenta\Validation\Internal;

use Closure;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;

/** @internal */
final class CallbackRule implements RuleInterface
{
    public string $name {
        get => 'callback';
    }

    /** @param Closure(mixed, ContextInterface): (true|ErrorMessageCollectorInterface) $callback */
    public function __construct(private readonly Closure $callback) {}

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return ($this->callback)($value, $context);
    }
}
