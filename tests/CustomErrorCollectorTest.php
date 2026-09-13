<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Error\ErrorMessageInterface;
use Componenta\Validation\Formatter\MessageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final readonly class ReadOnlyErrorCollector implements ErrorMessageCollectorInterface
{
    public function __construct(public array $messages) {}
    public function getIterator(): \Traversable { yield from $this->messages; }
    public function count(): int { return count($this->messages); }
    public function has(string|int $key): bool { return isset($this->messages[$key]); }
    public function get(string|int $key): ErrorMessageInterface|ErrorMessageCollectorInterface {
        return $this->messages[$key] ?? throw new \OutOfBoundsException();
    }
    public function toArray(): array {
        return array_map(static fn ($message) => $message instanceof ErrorMessageInterface
            ? $message->toString() : $message->toArray(), $this->messages);
    }
}

final class CustomErrorCollectorTest extends TestCase
{
    private function message(string $text): ErrorMessage {
        $context = new Context([ContextInterface::MESSAGE_FORMATTER_ATTRIBUTE => new MessageFormatter(['en_US' => ['required' => 'required', 'first error' => 'first error', 'second error' => 'second error']])]);
        return new ErrorMessage($context, $text);
    }

    public function testReadsNestedCollectorsThroughTheirPublicInterface(): void {
        $errors = new ErrorMessageCollector();
        $errors->add('profile', new ReadOnlyErrorCollector(['name' => $this->message('required')]));

        self::assertFalse($errors->isEmpty());
        self::assertSame(['profile' => ['name' => 'required']], $errors->toArray());
    }

    public static function additions(): iterable {
        yield 'message' => [false];
        yield 'collector' => [true];
    }

    #[DataProvider('additions')]
    public function testAppendsErrorsWithoutMutatingTheForeignCollector(bool $collector): void {
        $foreign = new ReadOnlyErrorCollector(['first' => $this->message('first error')]);
        $errors = new ErrorMessageCollector();
        $errors->add('profile', $foreign);

        $errors->add('profile', $collector
            ? new ReadOnlyErrorCollector(['second' => $this->message('second error')])
            : $this->message('second error'));

        self::assertSame(2, count($errors));
        self::assertSame(['first' => 'first error'], $foreign->toArray());
        self::assertSame(['first error', 'second error'], array_values($errors->get('profile')->toArray()));
    }

    public function testAnEmptyNestedTreeHasNoMessages(): void {
        $errors = new ErrorMessageCollector();
        $errors->add('profile', new ReadOnlyErrorCollector(['address' => new ReadOnlyErrorCollector([])]));

        self::assertTrue($errors->isEmpty());
        self::assertSame(0, count($errors));
    }
}
