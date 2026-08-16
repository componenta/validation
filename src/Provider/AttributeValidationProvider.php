<?php

declare(strict_types=1);

namespace Componenta\Validation\Provider;

use Componenta\Validation\Definition\AttributeValidatorDefinitionExtractor;
use Componenta\Validation\Definition\ValidatorDefinitionFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\ValidatorInterface;

/** Reflection-backed provider intended for development mode. */
final class AttributeValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, ValidatorInterface|null> */
    private array $cache = [];

    private readonly AttributeValidatorDefinitionExtractor $extractor;

    private readonly ValidatorDefinitionFactory $definitionFactory;

    public function __construct(
        private readonly ValidatorFactoryInterface $validatorFactory,
        RuleFactoryInterface $ruleFactory,
        ?AttributeValidatorDefinitionExtractor $extractor = null,
    ) {
        $this->extractor = $extractor ?? new AttributeValidatorDefinitionExtractor();
        $this->definitionFactory = new ValidatorDefinitionFactory($ruleFactory);
    }

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (array_key_exists($entryId, $this->cache)) {
            return $this->cache[$entryId];
        }

        if (!class_exists($entryId)) {
            return $this->cache[$entryId] = null;
        }

        $definition = $this->extractor->extract($entryId);
        if ($definition === null || ($definition['kind'] ?? null) !== 'rules') {
            return $this->cache[$entryId] = null;
        }

        return $this->cache[$entryId] = $this->validatorFactory->createFrom(
            $this->definitionFactory->createRules($definition),
        );
    }
}
