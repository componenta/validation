<?php

declare(strict_types=1);

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidationProviderFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use Psr\Container\ContainerInterface;

final class ProductionAttributeValidationFixture
{
    public function __construct(
        #[Validate('required|string')]
        public string $name,
    ) {}
}

#[ValidatedBy(DevelopmentOnlyValidatorFixture::class)]
final class DevelopmentValidatedByFixture {}

final class DevelopmentOnlyValidatorFixture implements ValidatorInterface
{
    public function validate(
        iterable $data,
        ?ContextInterface $context = null,
    ): true|ErrorMessageCollectorInterface {
        return true;
    }
}

function validationFactoryContainer(string $environment): ContainerInterface
{
    $validator = new DevelopmentOnlyValidatorFixture();
    $validatorFactory = new class () implements ValidatorFactoryInterface {
        public function create(string $cls): ValidatorInterface
        {
            return new $cls([]);
        }

        public function createFrom(array $rules): ValidatorInterface
        {
            return new Validator($rules);
        }
    };
    $entries = [
        ConfigKey::CONFIG => new Config([], new Environment(['APP_ENV' => $environment])),
        ValidatorFactoryInterface::class => $validatorFactory,
        RuleFactoryInterface::class => new RuleFactory(),
        DevelopmentOnlyValidatorFixture::class => $validator,
    ];

    return new class ($entries) implements ContainerInterface {
        public function __construct(private readonly array $entries) {}

        public function get(string $id): mixed
        {
            return $this->entries[$id] ?? throw new RuntimeException("Missing entry: {$id}");
        }

        public function has(string $id): bool
        {
            return array_key_exists($id, $this->entries);
        }
    };
}

it('keeps attribute validation available in production', function () {
    $provider = (new ValidationProviderFactory())(validationFactoryContainer('production'));

    expect($provider->provide(ProductionAttributeValidationFixture::class))
        ->toBeInstanceOf(ValidatorInterface::class);
});

it('enables dynamic ValidatedBy lookup only in development', function () {
    $production = (new ValidationProviderFactory())(validationFactoryContainer('production'));
    $development = (new ValidationProviderFactory())(validationFactoryContainer('development'));

    expect($production->provide(DevelopmentValidatedByFixture::class))->toBeNull()
        ->and($development->provide(DevelopmentValidatedByFixture::class))
        ->toBeInstanceOf(ValidatorInterface::class);
});
