<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/** Passes when at least one child rule passes. */
final class OneOf implements RuleInterface
{
    /** @var non-empty-list<RuleInterface> */
    private array $rules;

    public string $name {
        get => 'one_of(' . implode('|', array_map(
            static fn (RuleInterface $rule): string => $rule->name,
            $this->rules,
        )) . ')';
    }

    public function __construct(RuleInterface $rule, RuleInterface ...$rules)
    {
        $this->rules = [$rule, ...$rules];
    }

    public function __invoke(mixed $value): bool
    {
        return $this->validate($value, new Context()) === true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $stopFirst = (bool) $context->getAttribute(
            ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE,
            false,
        );
        $firstErrors = null;
        $errors = null;
        $hasConstraint = false;

        foreach ($this->rules as $rule) {
            if ($rule instanceof Nullable) {
                if ($rule($value)) {
                    return true;
                }

                continue;
            }

            $hasConstraint = true;
            $result = $rule->validate($value, $context);
            if ($result === true) {
                return true;
            }

            $firstErrors ??= $result;
            if (!$stopFirst) {
                $errors ??= new ErrorMessageCollector();
                $errors->merge($result);
            }
        }

        if (!$hasConstraint) {
            return true;
        }

        return $stopFirst
            ? $firstErrors ?? new ErrorMessageCollector()
            : $errors ?? new ErrorMessageCollector();
    }
}
