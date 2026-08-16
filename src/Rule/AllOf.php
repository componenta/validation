<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/** Passes when every child rule passes, with Nullable acting as a null bypass marker. */
final class AllOf implements RuleInterface
{
    /** @var list<RuleInterface> */
    private array $rules = [];

    private bool $nullable = false;

    public string $name {
        get {
            $names = array_map(
                static fn (RuleInterface $rule): string => $rule->name,
                $this->rules,
            );

            if ($this->nullable) {
                array_unshift($names, 'nullable');
            }

            return 'all_of(' . implode('|', $names) . ')';
        }
    }

    public function __construct(RuleInterface $rule, RuleInterface ...$rules)
    {
        foreach ([$rule, ...$rules] as $candidate) {
            if ($candidate instanceof Nullable) {
                $this->nullable = true;
                continue;
            }

            if ($candidate instanceof self) {
                $this->nullable = $this->nullable || $candidate->nullable;
                array_push($this->rules, ...$candidate->rules);
                continue;
            }

            $this->rules[] = $candidate;
        }
    }

    public function __invoke(mixed $value): bool
    {
        return $this->validate($value, new Context()) === true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($value === null && $this->nullable) {
            return true;
        }

        $errors = null;
        $stopFirst = (bool) $context->getAttribute(
            ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE,
            false,
        );

        foreach ($this->rules as $rule) {
            $result = $rule->validate($value, $context);
            if ($result === true) {
                continue;
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
