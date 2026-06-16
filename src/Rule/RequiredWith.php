<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * RequiredWith rule: field is required if any of the specified fields are present.
 *
 * Example:
 *   new RequiredWith('phone') // required if phone is filled
 *   new RequiredWith(['first_name', 'last_name']) // required if any name is filled
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RequiredWith implements RuleInterface
{
    public const string REQUIRED_WITH_MESSAGE_ID = 'validation.required_with';

    /** @var array<int, string> */
    private readonly array $fields;

    public string $name {
        get => 'required_with';
    }

    /**
     * @param string|array<int, string> $fields Fields to check
     */
    public function __construct(string|array $fields)
    {
        $this->fields = is_array($fields) ? $fields : [$fields];
    }

    /**
     * Quick check: if any other field is present, value must not be empty.
     *
     * @param mixed $value Value to check
     * @param bool $otherPresent Whether any of the other fields is present
     */
    public function __invoke(mixed $value, bool $otherPresent = false): bool
    {
        if (!$otherPresent) {
            return true;
        }

        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);

        // Check if any of the specified fields are present
        $anyPresent = false;
        $presentFields = [];

        foreach ($this->fields as $field) {
            if ($this->isPresent($data, $field)) {
                $anyPresent = true;
                $presentFields[] = $field;
            }
        }

        // If none are present, this field is not required
        if (!$anyPresent) {
            return true;
        }

        // Check if current field is filled
        $isEmpty = $value === null
            || $value === ''
            || $value === []
            || (is_string($value) && trim($value) === '');

        if ($isEmpty) {
            $collector = new ErrorMessageCollector();
            $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
            $collector->add($path, new ErrorMessage($context, self::REQUIRED_WITH_MESSAGE_ID, [
                'fields' => implode(', ', $presentFields),
            ]));
            return $collector;
        }

        return true;
    }

    private function isPresent(array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) {
            return false;
        }

        $value = $data[$field];

        return $value !== null
            && $value !== ''
            && $value !== []
            && !(is_string($value) && trim($value) === '');
    }

    public static function getMessages(): array
    {
        return [
            self::REQUIRED_WITH_MESSAGE_ID => 'This field is required when :fields is present.',
        ];
    }
}
