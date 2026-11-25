<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;
use Closure;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Internal\CallbackRule;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;

/** Validates a field through a callback. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Callback extends RuleAttribute
{
    /** @param Closure(mixed, ContextInterface): (true|ErrorMessageCollectorInterface) $callback */
    public function __construct(private readonly Closure $callback)
    {
        parent::__construct('callback');
    }

    #[\Override]
    public function buildRule(RuleFactoryInterface $factory): RuleInterface
    {
        return new CallbackRule($this->callback);
    }
}
