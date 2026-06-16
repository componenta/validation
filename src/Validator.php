<?php

namespace Componenta\Validation;

use LogicException;
use Componenta\Validation\Rule\Rules;
use Componenta\Validation\Walker\Walker;
use Componenta\Validation\Walker\WalkerInterface;
use Componenta\Validation\Formatter\MessageFormatter;
use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Exception\ValidationException;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Main validator class.
 *
 * Validates input data using rules, supports nested fields, wildcards,
 * and collection rules. Generates fully qualified paths for error reporting.
 */
final readonly class Validator implements ValidatorInterface
{
    private RuleCollectorInterface $rules;
    private MessageFormatterInterface $formatter;

    public function __construct(
        iterable $rules,
        private WalkerInterface $walker = new Walker(),
        ?MessageFormatterInterface $formatter = null,
        private string $locale = ConfigKey::LOCALE_EN,
    ) {
        $this->rules = new Rules($rules);
        $this->formatter = $formatter ?? MessageFormatter::fromDefaults();
    }

    public function withRules(iterable $rules): self
    {
        return new self($rules, $this->walker, $this->formatter, $this->locale);
    }

    public function withWalker(WalkerInterface $walker): self
    {
        return new self($this->rules, $walker, $this->formatter, $this->locale);
    }

    public function withFormatter(MessageFormatterInterface $formatter): self
    {
        return new self($this->rules, $this->walker, $formatter, $this->locale);
    }

    public function withLocale(string $locale): self
    {
        return new self($this->rules, $this->walker, $this->formatter, $locale);
    }

    public function validate(iterable $data, ?ContextInterface $context = null): true|ErrorMessageCollectorInterface
    {
        $default = [
            ContextInterface::VALIDATION_RULES_ATTRIBUTE => $this->rules,
            ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE => false,
            ContextInterface::SKIP_MISSING_RULES_ATTRIBUTE => true,
            ContextInterface::THROW_ON_FAILURE_ATTRIBUTE => false,
            ContextInterface::MESSAGE_FORMATTER_ATTRIBUTE => $this->formatter,
            ContextInterface::LOCALE_ATTRIBUTE => $this->locale,
        ];

        if ($context === null) $context = new Context($default);
        else $context = new Context(array_merge($default, $context->attributes));

        $errors = null;

        $rules = $context->getAttribute(ContextInterface::VALIDATION_RULES_ATTRIBUTE);

        if (!$rules instanceof RuleCollectorInterface) {
            $rules = new Rules($rules);
            $context = $context->withAttribute(ContextInterface::VALIDATION_RULES_ATTRIBUTE, $rules);
        }

        if ($rules->count() === 0) {
            throw new LogicException('No validation rules provided.');
        }

        $skipMissing        = (bool) $context->getAttribute(ContextInterface::SKIP_MISSING_RULES_ATTRIBUTE, false);
        $throwOnFailure     = (bool) $context->getAttribute(ContextInterface::THROW_ON_FAILURE_ATTRIBUTE, false);
        $stopOnFirstFailure = (bool) $context->getAttribute(ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE, false);

        foreach ($this->walker->walk($data, $rules) as $target) {

            if ($target->rule === null) {
                if ($skipMissing) continue;
                throw new LogicException("No rule defined for field '$target->path'");
            }

            $context = $context->withAttributes([
                ContextInterface::CURRENT_PATH_ATTRIBUTE    => $target->path,
                ContextInterface::CURRENT_FIELD_ATTRIBUTE   => $target->field,
                ContextInterface::CURRENT_RULE_ATTRIBUTE    => $target->rule->name,
                ContextInterface::VALIDATION_DATA_ATTRIBUTE => $data,
            ]);

            $result = $target->rule->validate($target->value, $context);

            if ($result !== true) {
                if ($stopOnFirstFailure) {
                    if ($throwOnFailure) throw new ValidationException($result);
                    return $result;
                }

                $errors ??= new ErrorMessageCollector();
                $errors->merge($result);
            }

            $processedRules  = [...($context->getAttribute(ContextInterface::PROCESSED_RULES_ATTRIBUTE, [])), $target->rule->name];
            $processedFields = [...($context->getAttribute(ContextInterface::PROCESSED_FIELDS_ATTRIBUTE, [])), $target->path];

            $context = $context->withAttributes([
                ContextInterface::PROCESSED_RULES_ATTRIBUTE => $processedRules,
                ContextInterface::LAST_PROCESSED_RULE_ATTRIBUTE => $target->rule->name,
                ContextInterface::PROCESSED_FIELDS_ATTRIBUTE => $processedFields,
                ContextInterface::LAST_PROCESSED_FIELD_ATTRIBUTE => $target->path,
            ]);
        }

        if ($errors === null || $errors->isEmpty()) return true;

        if ($throwOnFailure) {
            throw new ValidationException($errors);
        }

        return $errors;
    }
}
