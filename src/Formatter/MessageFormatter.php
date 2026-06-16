<?php

namespace Componenta\Validation\Formatter;

use Componenta\Validation\ConfigKey;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\MessageNotFoundException;

/**
 * Default formatter: resolves messages by id and replaces placeholders.
 *
 * The translation dictionary is injected via the constructor.
 */
final class MessageFormatter implements MessageFormatterInterface
{
    /**
     * @param array<string, array<string, string>> $dictionary
     */
    public function __construct(
        private array $dictionary
    ) {
    }

    public static function fromDefaults(): self
    {
        $dictionary = Dictionary::fromDefaults();
        return new self($dictionary);
    }

    /**
     * Add messages for a specific locale.
     *
     * @param string $locale
     * @param array<string, string> $messages
     */
    public function addMessages(string $locale, array $messages): void
    {
        $this->dictionary[$locale] = array_merge(
            $this->dictionary[$locale] ?? [],
            $messages
        );
    }

    /**
     * Add a single message for a specific locale.
     *
     * @param string $locale
     * @param string $messageId
     * @param string $template
     */
    public function addMessage(string $locale, string $messageId, string $template): void
    {
        $this->dictionary[$locale][$messageId] = $template;
    }

    public function format(string $messageId, array $placeholders, ContextInterface $context): string
    {
        $locale = (string) $context->getAttribute(ContextInterface::LOCALE_ATTRIBUTE, ConfigKey::LOCALE_EN);

        if (!isset($this->dictionary[$locale][$messageId])) {
            throw new MessageNotFoundException(sprintf(
                'Message "%s" not found for locale "%s"',
                $messageId,
                $locale
            ));
        }

        $message = $this->dictionary[$locale][$messageId];
        $placeholders += [
            'attribute' => $this->resolveAttributeName($context),
        ];

        foreach ($placeholders as $key => $value) {
            $replacement = is_scalar($value) ? (string) $value : gettype($value);
            $message = str_replace(':' . $key, $replacement, $message);
        }

        return $message;
    }

    private function resolveAttributeName(ContextInterface $context): string
    {
        $path = $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE);

        if (is_string($path) && $path !== '') {
            return $path;
        }

        $field = $context->getAttribute(ContextInterface::CURRENT_FIELD_ATTRIBUTE);

        if (is_string($field) && $field !== '') {
            return $field;
        }

        return 'attribute';
    }
}
