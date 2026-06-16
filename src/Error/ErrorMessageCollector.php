<?php

namespace Componenta\Validation\Error;

/**
 * Implementation of ErrorMessageCollectorInterface.
 *
 * Supports nested collectors, recursive counting and merging.
 * When multiple errors occur at the same path, they are collected into a nested collector.
 */
final class ErrorMessageCollector implements ErrorMessageCollectorInterface
{
    /** @var array<string|int, ErrorMessageInterface|ErrorMessageCollectorInterface> */
    private array $_messages = [];

    public iterable $messages {
        get => $this->_messages;
    }

    /**
     * Add a message or nested collector.
     *
     * If a key already exists:
     * - Existing collector + new collector -> merge into existing
     * - Existing collector + new message -> add message to existing with numeric index
     * - Existing message + new collector -> create new collector, add existing message, merge new
     * - Existing message + new message -> create new collector with both messages (numeric indices)
     *
     * @param string|int $key
     * @param ErrorMessageInterface|ErrorMessageCollectorInterface $message
     */
    public function add(string|int $key, ErrorMessageInterface|ErrorMessageCollectorInterface $message): void
    {
        if (!isset($this->_messages[$key])) {
            $this->_messages[$key] = $message;
            return;
        }

        $existing = $this->_messages[$key];

        if ($existing instanceof ErrorMessageCollectorInterface) {
            if ($message instanceof ErrorMessageCollectorInterface) {
                // Collector + Collector -> merge
                $existing->merge($message);
            } else {
                // Collector + Message -> add with numeric index
                $index = count($existing);
                $existing->add($index, $message);
            }
        } else {
            // Existing is ErrorMessageInterface
            $newCollector = new self();
            $newCollector->add(0, $existing);

            if ($message instanceof ErrorMessageCollectorInterface) {
                // Message + Collector -> merge collector contents
                $newCollector->merge($message);
            } else {
                // Message + Message -> add both with numeric indices
                $newCollector->add(1, $message);
            }

            $this->_messages[$key] = $newCollector;
        }
    }

    /**
     * Merge another collector into this one.
     */
    public function merge(ErrorMessageCollectorInterface $other): void
    {
        foreach ($other as $key => $msg) {
            $this->add($key, $msg);
        }
    }

    /**
     * Returns true if the collector has no errors (recursively).
     */
    public function isEmpty(): bool
    {
        foreach ($this->_messages as $msg) {
            if ($msg instanceof ErrorMessageCollectorInterface && !$msg->isEmpty()) {
                return false;
            }
            if ($msg instanceof ErrorMessageInterface) {
                return false;
            }
        }
        return true;
    }

    /**
     * IteratorAggregate implementation.
     */
    public function getIterator(): \Traversable
    {
        yield from $this->_messages;
    }

    /**
     * Countable implementation (recursive).
     *
     * @return int Total number of messages, including nested collectors.
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->_messages as $msg) {
            if ($msg instanceof ErrorMessageCollectorInterface) {
                $count += count($msg);
            } elseif ($msg instanceof ErrorMessageInterface) {
                $count += 1;
            }
        }
        return $count;
    }

    public function has(string|int $key): bool
    {
        return isset($this->_messages[$key]);
    }

    public function get(string|int $key): ErrorMessageInterface|ErrorMessageCollectorInterface
    {
        if (!isset($this->_messages[$key])) {
            throw new \OutOfBoundsException("Error key '$key' does not exist");
        }
        return $this->_messages[$key];
    }

    /**
     * Convert all collected errors to a recursive array.
     *
     * @return array<string|int, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->_messages as $key => $message) {
            if ($message instanceof ErrorMessageCollectorInterface) {
                $result[$key] = $message->toArray();
            } else {
                $result[$key] = $message->toString();
            }
        }

        return $result;
    }
}
