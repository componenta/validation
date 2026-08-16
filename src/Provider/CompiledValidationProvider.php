<?php

declare(strict_types=1);

namespace Componenta\Validation\Provider;

use Componenta\Validation\ConfigKey;
use Componenta\Validation\Definition\ValidatorDefinitionFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;

/** Provides validators from the versioned metadata produced by validation-app. */
final class CompiledValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, array<string, mixed>> */
    private readonly array $definitions;

    /** @var array<string, ValidatorInterface|null> */
    private array $cache = [];

    /** @param array<string, mixed> $compiled */
    public function __construct(
        array $compiled,
        private readonly ValidatorFactoryInterface $validatorFactory,
        private readonly ValidatorDefinitionFactory $definitionFactory,
    ) {
        if (($compiled['version'] ?? null) !== ConfigKey::COMPILED_VALIDATORS_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Compiled validation map version must be %d.',
                ConfigKey::COMPILED_VALIDATORS_VERSION,
            ));
        }

        $definitions = $compiled['validators'] ?? null;
        if (!is_array($definitions)) {
            throw new InvalidArgumentException('Compiled validation map must contain a validators array.');
        }

        foreach ($definitions as $entry => $definition) {
            if (!is_string($entry) || $entry === '' || !is_array($definition)) {
                throw new InvalidArgumentException('Compiled validator entries must map non-empty strings to definition arrays.');
            }
        }

        /** @var array<string, array<string, mixed>> $definitions */
        $this->definitions = $definitions;
    }

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (array_key_exists($entryId, $this->cache)) {
            return $this->cache[$entryId];
        }

        $definition = $this->definitions[$entryId] ?? null;
        if ($definition === null) {
            return $this->cache[$entryId] = null;
        }

        return $this->cache[$entryId] = match ($definition['kind'] ?? null) {
            'rules' => $this->validatorFactory->createFrom(
                $this->definitionFactory->createRules($definition),
            ),
            'validator' => $this->createValidatorService($definition),
            default => throw new InvalidArgumentException(sprintf(
                'Compiled validator "%s" has an unsupported definition kind.',
                $entryId,
            )),
        };
    }

    /** @param array<string, mixed> $definition */
    private function createValidatorService(array $definition): ValidatorInterface
    {
        $class = $definition['class'] ?? null;
        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException('Compiled validator service definition must contain a class.');
        }

        return $this->validatorFactory->create($class);
    }
}
