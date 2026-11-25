<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;
use Closure;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Internal\CallbackRule;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\When;

/** Selects a validation branch using the current value and context. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class WhenCallback extends RuleAttribute
{
    /**
     * @param Closure(mixed, ContextInterface): bool $condition
     * @param string|(Closure(mixed, ContextInterface): (true|ErrorMessageCollectorInterface)) $then
     * @param string|(Closure(mixed, ContextInterface): (true|ErrorMessageCollectorInterface)) $else
     */
    public function __construct(
        private readonly Closure $condition,
        private readonly string|Closure $then,
        private readonly string|Closure $else = '',
    ) {
        parent::__construct('when');
    }

    #[\Override]
    public function buildRule(RuleFactoryInterface $factory): RuleInterface
    {
        return new When(
            $this->condition,
            self::buildBranch($factory, $this->then),
            $this->else === '' ? new Nullable() : self::buildBranch($factory, $this->else),
        );
    }

    /** @param string|(Closure(mixed, ContextInterface): (true|ErrorMessageCollectorInterface)) $branch */
    private static function buildBranch(RuleFactoryInterface $factory, string|Closure $branch): RuleInterface
    {
        return $branch instanceof Closure ? new CallbackRule($branch) : $factory->createRule($branch);
    }
}
