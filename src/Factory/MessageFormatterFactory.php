<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Validation\ConfigKey;
use Componenta\Validation\Formatter\Dictionary;
use Componenta\Validation\Formatter\MessageFormatter;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating MessageFormatter instance.
 *
 * Creates formatter with dictionary from config or default messages.
 */
final readonly class MessageFormatterFactory
{
    public function __invoke(ContainerInterface $container): MessageFormatter
    {
        $config = $container->get(ConfigKey::CONFIG);

        // Get dictionary from config
        $dictionary = $config->array(ConfigKey::DICTIONARY, []);

        if ($dictionary === []) {
            $locales = $config->array(ConfigKey::USED_LOCALES, [ConfigKey::LOCALE_EN, ConfigKey::LOCALE_RU]);
            $dictionary = Dictionary::load($locales);
        }

        return new MessageFormatter($dictionary);
    }
}
