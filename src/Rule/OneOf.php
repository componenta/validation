<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * OneOf: passes if at least one rule passes
 *
 * Example:
 *   new OneOf(new Email(), new Phone());
 */
final class OneOf implements RuleInterface
{
    /** @var array<int, RuleInterface> */
    private array $rules;

    public string $name {
        get => 'one_of(' . implode('|', array_map(
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
            if (($rule)($value)) {
                return true;
            }
        }
        return false;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $errors = null;
        $stopFirst = (bool) $context->getAttribute(ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE, false);

        foreach ($this->rules as $rule) {
            $result = $rule->validate($value, $context);
            if ($result === true) {
                return true; // at least one passed
            }

            $errors ??= new ErrorMessageCollector();
            $errors->merge($result);

            if ($stopFirst) {
                break;
            }
        }

        return $errors ?? true;
    }
}
