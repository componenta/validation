<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Rules;
use Componenta\Validation\Walker\Walker;
use PHPUnit\Framework\TestCase;
use Traversable;

final class WalkerContractTest extends TestCase
{
    public function testEquallySpecificWildcardsKeepTheirRegistrationOrder(): void
    {
        $required = new Required();
        $email = new Email();
        $rules = new Rules(['items.*.value' => $required, '*.0.value' => $email]);
        $targets = (new Walker())->walk(['items' => [['value' => 'present']]], $rules);

        $target = $targets->current();

        self::assertSame('items.0.value', $target->path);
        self::assertSame($required, $target->rule);
    }

    public function testFutureWildcardMatchesUseChangesToAnExternalCollectorDuringIteration(): void
    {
        $required = new Required();
        $email = new Email();
        $rules = new class(new Rules(['items.*.value' => $required])) implements RuleCollectorInterface {
            public function __construct(public Rules $current) {}

            public function has(string $name): bool
            {
                return $this->current->has($name);
            }

            public function get(string $name): RuleInterface
            {
                return $this->current->get($name);
            }

            public function toArray(): array
            {
                return $this->current->toArray();
            }

            public function count(): int
            {
                return $this->current->count();
            }

            public function getIterator(): Traversable
            {
                return $this->current->getIterator();
            }
        };
        $targets = (new Walker())->walk(['items' => [['value' => 'first'], ['value' => 'second']]], $rules);
        self::assertSame($required, $targets->current()->rule);

        $rules->current = new Rules(['items.*.value' => $email]);
        $targets->next();

        self::assertSame('items.1.value', $targets->current()->path);
        self::assertSame($email, $targets->current()->rule);
    }
}
