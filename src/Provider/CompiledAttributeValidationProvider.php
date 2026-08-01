<?php

declare(strict_types=1);

namespace Componenta\Validation\Provider;

use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\IfThen;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;
use LogicException;

/**
 * Runtime counterpart of AttributeValidationPlanCompiler.
 *
 * A fallback keeps classes outside the build discovery scope fully
 * compatible with dynamic attribute validation.
 */
final class CompiledAttributeValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, ValidatorInterface> */
    private array $cache = [];

    /**
     * @param array<class-string, array<string, list<array<string, mixed>>>> $plans
     */
    public function __construct(
        private readonly ValidatorFactoryInterface $validatorFactory,
        private readonly RuleFactoryInterface $ruleFactory,
        private readonly array $plans,
        private readonly ?ValidationProviderInterface $fallback = null,
    ) {}

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (isset($this->cache[$entryId])) {
            return $this->cache[$entryId];
        }

        $plan = $this->plans[$entryId] ?? null;
        if (!is_array($plan)) {
            return $this->fallback?->provide($entryId);
        }

        $rules = [];
        foreach ($plan as $field => $descriptors) {
            $rules[$field] = $this->compose($descriptors);
        }

        return $this->cache[$entryId] = $this->validatorFactory->createFrom($rules);
    }

    /**
     * @param list<array<string, mixed>> $descriptors
     */
    private function compose(array $descriptors): RuleInterface
    {
        $rules = [];
        $nullable = null;

        foreach ($descriptors as $descriptor) {
            $rule = $this->makeRule($descriptor);

            if ($nullable === null && $rule instanceof Nullable) {
                $nullable = $rule;
                continue;
            }

            $rules[] = $rule;
        }

        if ($rules === []) {
            if ($nullable === null) {
                throw new LogicException('Compiled validation property plan contains no rules.');
            }

            return $nullable;
        }

        $rule = count($rules) === 1 ? $rules[0] : new AllOf(...$rules);

        return $nullable !== null
            ? new IfThen($nullable->inverse(...), $rule)
            : $rule;
    }

    /** @param array<string, mixed> $descriptor */
    private function makeRule(array $descriptor): RuleInterface
    {
        $type = $descriptor['type'] ?? null;

        if ($type === 'definition' && is_string($descriptor['rules'] ?? null)) {
            return $this->ruleFactory->createRule($descriptor['rules']);
        }

        $class = $descriptor['class'] ?? null;
        $arguments = $descriptor['arguments'] ?? [];
        if (!is_string($class) || !is_array($arguments)) {
            throw new LogicException('Invalid compiled validation rule descriptor.');
        }

        $attribute = new $class(...$arguments);

        if ($type === 'rule_attribute' && $attribute instanceof RuleAttribute) {
            return $attribute->buildRule($this->ruleFactory);
        }

        if ($type === 'rule' && $attribute instanceof RuleInterface) {
            return $attribute;
        }

        throw new LogicException(sprintf(
            'Compiled validation descriptor class "%s" does not match type "%s".',
            $class,
            is_string($type) ? $type : get_debug_type($type),
        ));
    }
}
