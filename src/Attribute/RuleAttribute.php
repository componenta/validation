<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;

/**
 * Base attribute for validation rules with complex dependencies.
 *
 * Rules requiring non-scalar dependencies cannot be instantiated as attributes directly.
 * This attribute stores rule name and parameters, actual rule instance is created via RuleFactory.
 * Subclasses provide specific rule parameters via getParams().
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class RuleAttribute
{
    /**
     * @param string $ruleName Name of the validation rule
     */
    public function __construct(
        public readonly string $ruleName,
    ) {}

    /**
     * Build rule instance via factory.
     *
     * Default implementation: ruleName:param1,param2,...
     * Subclasses may override for complex rule construction.
     */
    public function buildRule(RuleFactoryInterface $factory): RuleInterface
    {
        $params = $this->getParams();

        if ($params === []) {
            return $factory->createRule($this->ruleName);
        }

        return $factory->createRule($this->ruleName . ':' . implode(',', $params));
    }

    /**
     * Rule parameters for string definition.
     *
     * @return list<string>
     */
    protected function getParams(): array
    {
        return [];
    }
}
