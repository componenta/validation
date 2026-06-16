<?php

namespace Componenta\Validation\Formatter;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\MessageNotFoundException;

/**
 * Message formatter interface for validation errors.
 *
 * Formats error messages by resolving message templates from dictionary
 * and replacing placeholders with actual values.
 */
interface MessageFormatterInterface
{
    /**
     * Format error message with placeholders.
     *
     * Resolves message template by ID from dictionary and replaces placeholders.
     *
     * @param string $messageId Message identifier
     * @param array $placeholders Placeholder values for message template
     * @param ContextInterface $context Validation context (provides locale)
     * @return string Formatted error message
     * @throws MessageNotFoundException When message ID not found in dictionary
     */
    public function format(string $messageId, array $placeholders, ContextInterface $context): string;
}