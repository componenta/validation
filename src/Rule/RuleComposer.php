<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use InvalidArgumentException;

final class RuleComposer
{
    public static function all(RuleInterface ...$rules): RuleInterface
    {
        return match (count($rules)) {
            0 => throw new InvalidArgumentException('At least one validation rule is required.'),
            1 => $rules[0],
            default => new AllOf(...$rules),
        };
    }

    private function __construct() {}
}
