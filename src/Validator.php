<?php

declare(strict_types=1);

namespace Componenta\Validation;

use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Exception\ValidationException;
use Componenta\Validation\Formatter\MessageFormatter;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Internal\ValidationData;
use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Rules;
use Componenta\Validation\Walker\Walker;
use Componenta\Validation\Walker\WalkerInterface;
use LogicException;

final readonly class Validator implements ValidatorInterface
{
    /** @var RuleCollectorInterface<RuleInterface> */
    private RuleCollectorInterface $rules;

    private MessageFormatterInterface $formatter;

    /** @param iterable<array-key, RuleInterface> $rules */
    public function __construct(
        iterable $rules,
        private WalkerInterface $walker = new Walker(),
        ?MessageFormatterInterface $formatter = null,
        private string $locale = ConfigKey::LOCALE_EN,
    ) {
        $this->rules = new Rules($rules);
        $this->formatter = $formatter ?? MessageFormatter::fromDefaults();
    }

    /** @param iterable<array-key, RuleInterface> $rules */
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
        $data = ValidationData::materialize($data);
        $default = [
            ContextInterface::VALIDATION_RULES_ATTRIBUTE => $this->rules,
            ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE => false,
            ContextInterface::SKIP_MISSING_RULES_ATTRIBUTE => true,
            ContextInterface::THROW_ON_FAILURE_ATTRIBUTE => false,
            ContextInterface::MESSAGE_FORMATTER_ATTRIBUTE => $this->formatter,
            ContextInterface::LOCALE_ATTRIBUTE => $this->locale,
        ];

        $context = $context === null
            ? new Context($default)
            : new Context(array_merge($default, $context->attributes));
        $errors = null;
        /** @var RuleCollectorInterface<RuleInterface>|iterable<array-key, RuleInterface> $rules */
        $rules = $context->getAttribute(ContextInterface::VALIDATION_RULES_ATTRIBUTE);

        if (!$rules instanceof RuleCollectorInterface) {
            $rules = new Rules($rules);
            $context = $context->withAttribute(ContextInterface::VALIDATION_RULES_ATTRIBUTE, $rules);
        }

        if ($rules->count() === 0) {
            throw new LogicException('No validation rules provided.');
        }

        $skipMissing = (bool) $context->getAttribute(ContextInterface::SKIP_MISSING_RULES_ATTRIBUTE, false);
        $throwOnFailure = (bool) $context->getAttribute(ContextInterface::THROW_ON_FAILURE_ATTRIBUTE, false);
        $stopOnFirstFailure = (bool) $context->getAttribute(ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE, false);

        $targets = $this->walker instanceof Walker
            ? $this->walker->walkMaterialized($data, $rules)
            : $this->walker->walk($data, $rules);
        $nextAttributes = [ContextInterface::VALIDATION_DATA_ATTRIBUTE => $data];
        foreach ($targets as $target) {
            if ($target->rule === null) {
                if ($skipMissing) {
                    continue;
                }

                throw new LogicException("No rule defined for field '$target->path'");
            }

            $context = $context->withAttributes([
                ContextInterface::CURRENT_PATH_ATTRIBUTE => $target->path,
                ContextInterface::CURRENT_FIELD_ATTRIBUTE => $target->field,
                ContextInterface::CURRENT_RULE_ATTRIBUTE => $target->rule->name,
            ] + $nextAttributes);
            $result = $target->rule->validate($target->value, $context);

            if ($result !== true) {
                if ($stopOnFirstFailure) {
                    if ($throwOnFailure) {
                        throw new ValidationException($result);
                    }

                    return $result;
                }

                $errors ??= new ErrorMessageCollector();
                $errors->merge($result);
            }

            /** @var iterable<array-key, string> $previousRules */
            $previousRules = $context->getAttribute(ContextInterface::PROCESSED_RULES_ATTRIBUTE, []);
            $processedRules = [...$previousRules, $target->rule->name];
            /** @var iterable<array-key, string> $previousFields */
            $previousFields = $context->getAttribute(ContextInterface::PROCESSED_FIELDS_ATTRIBUTE, []);
            $processedFields = [...$previousFields, $target->path];
            // Carry the completed field into the next snapshot with its current path.
            $nextAttributes = [
                ContextInterface::PROCESSED_RULES_ATTRIBUTE => $processedRules,
                ContextInterface::LAST_PROCESSED_RULE_ATTRIBUTE => $target->rule->name,
                ContextInterface::PROCESSED_FIELDS_ATTRIBUTE => $processedFields,
                ContextInterface::LAST_PROCESSED_FIELD_ATTRIBUTE => $target->path,
            ];
        }

        if ($errors === null) {
            return true;
        }

        if ($throwOnFailure) {
            throw new ValidationException($errors);
        }

        return $errors;
    }
}
