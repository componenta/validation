<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\When as WhenRule;

/**
 * When validation attribute.
 *
 * Conditional validation based on another field's value.
 * Resolves string rule definitions via RuleFactory in buildRule().
 *
 * Usage:
 *   #[When('status:published', then: 'required|string|length:1,200', else: 'nullable|string')]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class When extends RuleAttribute
{
    /**
     * @param string $condition Condition in "field:value" format
     * @param string $then Rule definition to apply when condition is true
     * @param string $else Rule definition to apply when condition is false
     */
    public function __construct(
        public readonly string $condition,
        public readonly string $then,
        public readonly string $else = '',
    ) {
        parent::__construct('when');
    }

    #[\Override]
    public function buildRule(RuleFactoryInterface $factory): RuleInterface
    {
        $thenRule = $factory->createRule($this->then);
        $elseRule = $this->else !== '' ? $factory->createRule($this->else) : new Nullable();

        return new WhenRule($this->condition, $thenRule, $elseRule);
    }
}
