<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Required;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    public function testRequiredRuleAcceptsNonEmptyValues(): void
    {
        $rule = new Required();

        self::assertTrue($rule('value'));
        self::assertFalse($rule('   '));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('', Context::currentPath('name')));
    }

    public function testEmailRuleValidatesEmailValues(): void
    {
        $rule = new Email();

        self::assertTrue($rule('user@example.com'));
        self::assertFalse($rule('not-an-email'));
        self::assertTrue($rule->validate('user@example.com', Context::currentPath('email')));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('bad', Context::currentPath('email')));
    }
}
