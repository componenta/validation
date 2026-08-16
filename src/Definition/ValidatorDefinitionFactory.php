<?php

declare(strict_types=1);

namespace Componenta\Validation\Definition;

use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Rule\RuleComposer;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use InvalidArgumentException;

/** Hydrates compiled scalar descriptors into runtime rule objects. */
final readonly class ValidatorDefinitionFactory
{
    public function __construct(private RuleFactoryInterface $ruleFactory) {}

    /**
     * @param array<string, mixed> $definition
     * @return array<string, RuleInterface>
     */
    public function createRules(array $definition): array
    {
        if (($definition['kind'] ?? null) !== 'rules' || !is_array($definition['fields'] ?? null)) {
            throw new InvalidArgumentException('Compiled validator definition must contain a rules field map.');
        }

        $rules = [];

        foreach ($definition['fields'] as $field => $descriptors) {
            if (!is_string($field) || $field === '' || !is_array($descriptors) || $descriptors === []) {
                throw new InvalidArgumentException('Compiled validation fields must use non-empty string keys and non-empty descriptor lists.');
            }

            $fieldRules = [];
            foreach ($descriptors as $descriptor) {
                if (!is_array($descriptor)) {
                    throw new InvalidArgumentException(sprintf(
                        'Compiled validation descriptor for field "%s" must be an array.',
                        $field,
                    ));
                }

                $fieldRules[] = $this->createRule($descriptor);
            }

            $rules[$field] = RuleComposer::all(...$fieldRules);
        }

        return $rules;
    }

    /** @param array<string, mixed> $descriptor */
    private function createRule(array $descriptor): RuleInterface
    {
        $kind = $descriptor['kind'] ?? null;

        if ($kind === 'definition') {
            $definition = $descriptor['definition'] ?? null;
            if (!is_string($definition) || trim($definition) === '') {
                throw new InvalidArgumentException('Compiled string rule definition must be a non-empty string.');
            }

            return $this->ruleFactory->createRule($definition);
        }

        if ($kind !== 'rule' && $kind !== 'rule_attribute') {
            throw new InvalidArgumentException(sprintf(
                'Unsupported compiled rule descriptor kind "%s".',
                is_scalar($kind) ? (string) $kind : get_debug_type($kind),
            ));
        }

        $class = $descriptor['class'] ?? null;
        $arguments = $descriptor['arguments'] ?? null;
        if (!is_string($class) || $class === '' || !is_array($arguments)) {
            throw new InvalidArgumentException('Compiled rule class descriptors require a class and argument array.');
        }

        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Compiled validation rule class "%s" does not exist.', $class));
        }

        $instance = new $class(...$arguments);

        if ($kind === 'rule') {
            if (!$instance instanceof RuleInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Compiled validation rule "%s" must implement %s.',
                    $class,
                    RuleInterface::class,
                ));
            }

            return $instance;
        }

        if (!$instance instanceof RuleAttribute) {
            throw new InvalidArgumentException(sprintf(
                'Compiled validation rule attribute "%s" must extend %s.',
                $class,
                RuleAttribute::class,
            ));
        }

        return $instance->buildRule($this->ruleFactory);
    }
}
