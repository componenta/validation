<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    public function testWithAttributeReturnsNewContext(): void
    {
        $context = new Context(['one' => 1]);
        $next = $context->withAttribute('two', 2);

        self::assertNotSame($context, $next);
        self::assertFalse($context->hasAttribute('two'));
        self::assertSame(2, $next->getAttribute('two'));
    }

    public function testStaticFactoriesSetKnownAttributes(): void
    {
        self::assertSame('ru', Context::locale('ru')->getAttribute(ContextInterface::LOCALE_ATTRIBUTE));
        self::assertSame('email', Context::currentPath('email')->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE));
    }
}
