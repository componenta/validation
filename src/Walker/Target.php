<?php

namespace Componenta\Validation\Walker;

use Componenta\Validation\Rule\RuleInterface;

final readonly class Target
{
    public function __construct(
        public string $field,
        public string $path,
        public ?RuleInterface $rule,
        public mixed $value
    ) {
    }
}
