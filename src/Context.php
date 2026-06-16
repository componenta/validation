<?php

declare(strict_types=1);

namespace Componenta\Validation;

/**
 * Immutable key-value storage for validation context.
 */
final class Context implements ContextInterface
{
    /** @var array<string, mixed> */
    private(set) array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function withAttributes(array $attributes): ContextInterface
    {
        $new = clone $this;
        $new->attributes = array_merge($this->attributes, $attributes);

        return $new;
    }

    public function withAttribute(string $name, mixed $value): ContextInterface
    {
        return $this->withAttributes([$name => $value]);
    }

    public function withoutAttribute(string $name): ContextInterface
    {
        $new = clone $this;
        $attributes = $this->attributes;
        unset($attributes[$name]);
        $new->attributes = $attributes;

        return $new;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    // ========================================================================
    // Static factory methods - create context with specific attribute
    // ========================================================================

    public static function locale(string $locale): ContextInterface
    {
        return new self([self::LOCALE_ATTRIBUTE => $locale]);
    }

    public static function validationData(array $data): ContextInterface
    {
        return new self([self::VALIDATION_DATA_ATTRIBUTE => $data]);
    }

    public static function throwOnFailure(bool $throwOnFailure = true): ContextInterface
    {
        return new self([self::THROW_ON_FAILURE_ATTRIBUTE => $throwOnFailure]);
    }

    public static function stopOnFirstFailure(bool $stopOnFirstFailure = true): ContextInterface
    {
        return new self([self::STOP_ON_FIRST_FAILURE_ATTRIBUTE => $stopOnFirstFailure]);
    }

    public static function currentPath(string $path): ContextInterface
    {
        return new self([self::CURRENT_PATH_ATTRIBUTE => $path]);
    }

    public static function currentField(string $field): ContextInterface
    {
        return new self([self::CURRENT_FIELD_ATTRIBUTE => $field]);
    }

    public static function currentRule(string $rule): ContextInterface
    {
        return new self([self::CURRENT_RULE_ATTRIBUTE => $rule]);
    }

    public static function validationRules(iterable $rules): ContextInterface
    {
        return new self([self::VALIDATION_RULES_ATTRIBUTE => $rules]);
    }

    public static function skipMissingRules(bool $skipMissingRules = true): ContextInterface
    {
        return new self([self::SKIP_MISSING_RULES_ATTRIBUTE => $skipMissingRules]);
    }

    public static function messageFormatter($formatter): ContextInterface
    {
        return new self([self::MESSAGE_FORMATTER_ATTRIBUTE => $formatter]);
    }

    public function withLocale(string $locale): ContextInterface
    {
        return $this->withAttribute(self::LOCALE_ATTRIBUTE, $locale);
    }

    public function withValidationData(array $data): ContextInterface
    {
        return $this->withAttribute(self::VALIDATION_DATA_ATTRIBUTE, $data);
    }

    public function withThrowOnFailure(bool $throwOnFailure = true): ContextInterface
    {
        return $this->withAttribute(self::THROW_ON_FAILURE_ATTRIBUTE, $throwOnFailure);
    }

    public function withStopOnFirstFailure(bool $stopOnFirstFailure = true): ContextInterface
    {
        return $this->withAttribute(self::STOP_ON_FIRST_FAILURE_ATTRIBUTE, $stopOnFirstFailure);
    }

    public function withCurrentPath(string $path): ContextInterface
    {
        return $this->withAttribute(self::CURRENT_PATH_ATTRIBUTE, $path);
    }

    public function withCurrentField(string $field): ContextInterface
    {
        return $this->withAttribute(self::CURRENT_FIELD_ATTRIBUTE, $field);
    }

    public function withCurrentRule(string $rule): ContextInterface
    {
        return $this->withAttribute(self::CURRENT_RULE_ATTRIBUTE, $rule);
    }

    public function withProcessedRules(array $rules): ContextInterface
    {
        return $this->withAttribute(self::PROCESSED_RULES_ATTRIBUTE, $rules);
    }

    public function withLastProcessedRule(string $rule): ContextInterface
    {
        return $this->withAttribute(self::LAST_PROCESSED_RULE_ATTRIBUTE, $rule);
    }

    public function withProcessedFields(array $fields): ContextInterface
    {
        return $this->withAttribute(self::PROCESSED_FIELDS_ATTRIBUTE, $fields);
    }

    public function withLastProcessedField(string $field): ContextInterface
    {
        return $this->withAttribute(self::LAST_PROCESSED_FIELD_ATTRIBUTE, $field);
    }

    public function withValidationRules(iterable $rules): ContextInterface
    {
        return $this->withAttribute(self::VALIDATION_RULES_ATTRIBUTE, $rules);
    }

    public function withSkipMissingRules(bool $skipMissingRules = true): ContextInterface
    {
        return $this->withAttribute(self::SKIP_MISSING_RULES_ATTRIBUTE, $skipMissingRules);
    }

    public function withMessageFormatter($formatter): ContextInterface
    {
        return $this->withAttribute(self::MESSAGE_FORMATTER_ATTRIBUTE, $formatter);
    }
}
