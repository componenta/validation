<?php

namespace Componenta\Validation\Error;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Formatter\MessageFormatterInterface;

/**
 * Immutable validation error message with lazy formatting.
 */
final class ErrorMessage implements ErrorMessageInterface
{
    private ?string $message = null;

    public function __construct(
        public readonly ContextInterface $context,
        public readonly string $messageId,
        public readonly array $placeholders = [],
    ) {}

    public function toString(): string
    {
        if ($this->message === null) {
            /** @var MessageFormatterInterface $formatter */
            $formatter = $this->context->getAttribute(ContextInterface::MESSAGE_FORMATTER_ATTRIBUTE);
            $this->message = $formatter->format($this->messageId, $this->placeholders, $this->context);
        }

        return $this->message;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
