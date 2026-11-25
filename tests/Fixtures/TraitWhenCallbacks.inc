<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\WhenCallback;

trait TraitWhenCallbackField
{
    #[WhenCallback(self::needsValue(...), then: 'required')]
    public ?string $value = null;
}

final class TraitRequiredWhenCallbackDto
{
    use TraitWhenCallbackField;

    private static function needsValue(): bool
    {
        return true;
    }
}

final class TraitOptionalWhenCallbackDto
{
    use TraitWhenCallbackField;

    private static function needsValue(): bool
    {
        return false;
    }
}
