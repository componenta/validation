<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/** Unless ExcludeIf bypasses the group, runs constraints in order until the first failure. */
final class Sequential implements RuleInterface
{
    /** @var non-empty-list<RuleInterface> */
    private array $rules;

    public string $name {
        get => 'sequential(' . implode('|', array_map(
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
        foreach ($this->rules as $rule) {
            if ($rule instanceof ExcludeIf && $rule->shouldExclude($context)) {
                return true;
            }
        }

        foreach ($this->rules as $rule) {
            if ($rule instanceof ExcludeIf) {
                continue;
            }

            $result = $rule->validate($value, $context);
            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }
}
