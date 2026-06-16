<?php

namespace Componenta\Validation;

/**
 * Immutable validation context.
 *
 * Stores validation options, state, processed fields/rules, and all rules.
 * Each modification returns a new instance.
 */
interface ContextInterface
{
    /**
     * The original validation input data.
     * Type: array<string, mixed>
     */
    public const string VALIDATION_DATA_ATTRIBUTE = 'VALIDATION_DATA';

    /**
     * If true, validator must throw ValidationException after validating all fields if there are errors.
     * Type: bool
     */
    public const string THROW_ON_FAILURE_ATTRIBUTE = 'THROW_ON_FAILURE';

    /**
     * If true, validation stops immediately on first failure.
     * Collected errors so far are preserved, but no further fields are validated.
     * Type: bool
     */
    public const string STOP_ON_FIRST_FAILURE_ATTRIBUTE = 'STOP_ON_FIRST_FAILURE';

    /**
     * Current field path (e.g., nested fields like 'user.address.zip').
     * Type: string
     */
    public const string CURRENT_PATH_ATTRIBUTE = 'CURRENT_PATH';

    /**
     * Current field being validated. (e.g., nested fields like 'zip')
     * Type: string
     */
    public const string CURRENT_FIELD_ATTRIBUTE = 'CURRENT_FIELD';

    /**
     * Current rule name being applied.
     * Type: string
     */
    public const string CURRENT_RULE_ATTRIBUTE = 'CURRENT_RULE';

    /**
     * List of rule names that were processed.
     * Type: array<string>
     */
    public const string PROCESSED_RULES_ATTRIBUTE = 'PROCESSED_RULES';

    /**
     * Last processed rule name.
     * Type: string
     */
    public const string LAST_PROCESSED_RULE_ATTRIBUTE = 'LAST_PROCESSED_RULE';

    /**
     * List of field names that were processed.
     * Type: array<string>
     */
    public const string PROCESSED_FIELDS_ATTRIBUTE = 'PROCESSED_FIELDS';

    /**
     * Last processed field name.
     * Type: string
     */
    public const string LAST_PROCESSED_FIELD_ATTRIBUTE = 'LAST_PROCESSED_FIELD';

    /**
     * Collection of all validation rules.
     * Type: iterable<string, RuleInterface>
     */
    public const string VALIDATION_RULES_ATTRIBUTE = 'VALIDATION_RULES';

    /**
     * Whether to skip fields without rules.
     * Type: bool
     */
    public const string SKIP_MISSING_RULES_ATTRIBUTE = 'SKIP_MISSING_RULES';

    /**
     * Locale used for message formatting.
     * Type: string
     */
    public const string LOCALE_ATTRIBUTE = 'LOCALE_ATTRIBUTE';

    /**
     * Message formatter instance.
     * Type: MessageFormatterInterface
     */
    public const string MESSAGE_FORMATTER_ATTRIBUTE = 'MESSAGE_FORMATTER_ATTRIBUTE';

    /**
     * All context attributes.
     *
     * @var array<string, mixed>
     */
    public array $attributes { get; }

    /**
     * Create new context with multiple attributes merged.
     *
     * @param array<string, mixed> $attributes Attributes to merge
     * @return ContextInterface New context instance
     */
    public function withAttributes(array $attributes): ContextInterface;

    /**
     * Create new context with single attribute set.
     *
     * @param string $name Attribute name
     * @param mixed $value Attribute value
     * @return ContextInterface New context instance
     */
    public function withAttribute(string $name, mixed $value): ContextInterface;

    /**
     * Create new context without specified attribute.
     *
     * @param string $name Attribute name to remove
     * @return ContextInterface New context instance
     */
    public function withoutAttribute(string $name): ContextInterface;

    /**
     * Check if attribute exists in context.
     *
     * @param string $name Attribute name
     * @return bool True if attribute exists
     */
    public function hasAttribute(string $name): bool;

    /**
     * Get attribute value or default if not present.
     *
     * @param string $name Attribute name
     * @param mixed $default Default value if attribute not found
     * @return mixed Attribute value or default
     */
    public function getAttribute(string $name, mixed $default = null): mixed;
}
