<?php

namespace Componenta\Validation\Formatter;

use Componenta\Validation\ConfigKey;

final class Dictionary
{
    public static function load(array $locales): array
    {
        $dictionary = [];
        $dir = dirname(__DIR__, 2) . '/dictionary';

        foreach ($locales as $locale) {
            $dictionary[$locale] = require $dir . '/' . $locale . '.php';
        }

        return $dictionary;
    }

    public static function fromDefaults(): array
    {
        return self::load([ConfigKey::LOCALE_EN]);
    }

    public static function loadAll(): array
    {
        return self::load([
            ConfigKey::LOCALE_EN,
            ConfigKey::LOCALE_FR,
            ConfigKey::LOCALE_DE,
            ConfigKey::LOCALE_RU,
            ConfigKey::LOCALE_ES
        ]);
    }
}