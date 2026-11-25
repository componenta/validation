<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Validator;
use InvalidArgumentException;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use Traversable;

final class ValidationDataContractTest extends TestCase
{
    public function testNumericFieldNamesWorkForPresentAndMissingValues(): void
    {
        $validator = new Validator(['0' => new Required(), '*.name' => new Required()]);

        self::assertTrue($validator->validate([0 => ['name' => 'present'], 1 => ['name' => 'Ada']]));

        $errors = $validator->validate([1 => ['name' => 'Ada']]);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame([0], array_keys($errors->toArray()));
    }

    public function testMaterializingAnInputLeavesTheCallersReferenceIntact(): void
    {
        $records = (static function (): Traversable {
            yield 'name' => 'Ada';
        })();
        $original = $records;
        $data = ['user' => &$records];
        $validator = new Validator(['user.name' => new Required()]);

        self::assertTrue($validator->validate($data));
        self::assertSame($original, $records);
    }

    public function testTwoFieldsCanReadTheSameOneShotIterable(): void
    {
        $records = (static function (): Traversable {
            yield 'name' => 'Ada';
        })();
        $validator = new Validator(['user.name' => new Required(), 'alias.name' => new Required()]);

        self::assertTrue($validator->validate(['user' => $records, 'alias' => $records]));
    }

    public function testRejectsARecursiveIterableBeforeReenteringIt(): void
    {
        $records = new class implements IteratorAggregate {
            private bool $started = false;

            public function getIterator(): Traversable
            {
                if ($this->started) {
                    throw new \LogicException('The input was traversed more than once.');
                }

                $this->started = true;
                yield 'self' => $this;
            }
        };
        $validator = new Validator(['name' => new Required()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation input cannot contain recursive collections.');
        $validator->validate($records);
    }

    public function testSharedArrayReferencesDoNotCountAsRecursion(): void
    {
        $person = ['name' => 'Ada'];
        $validator = new Validator(['users.*.name' => new Required()]);

        self::assertTrue($validator->validate(['users' => [&$person, &$person]]));
    }

    public function testRejectsARecursiveArray(): void
    {
        $data = ['name' => 'Ada'];
        $data['nested'] = &$data;
        $validator = new Validator(['name' => new Required()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation input cannot contain recursive collections.');
        $validator->validate($data);
    }
}
