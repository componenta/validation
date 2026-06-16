<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Error\ErrorMessageCollector;

/**
 * AllOf: passes if all rules pass
 *
 * Example:
 *   new AllOf(new Email(), new MaxLength(255));
 */
final class AllOf implements RuleInterface
{
    /** @var array<int, RuleInterface> */
    private array $rules;

    public string $name {
        get => 'all_of(' . implode('|', array_map(
            static fn(RuleInterface $r): string => $r->name,
            $this->rules
        )) . ')';
    }

    public function __construct(RuleInterface $rule, RuleInterface ...$rules)
    {
        $this->rules = [$rule, ...$rules];
    }

    public function __invoke(mixed $value): bool
    {
        foreach ($this->rules as $rule) {
            if (!($rule)($value)) {
                return false;
            }
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $errors = null;
        $stopFirst = (bool) $context->getAttribute(ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE, false);

        foreach ($this->rules as $rule) {
            $result = $rule->validate($value, $context);
            if ($result !== true) {
                $errors ??= new ErrorMessageCollector();
                $errors->merge($result);

                if ($stopFirst) {
                    break;
                }
            }
        }

        return $errors ?? true;
    }
}
