<?php

declare(strict_types=1);

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Compile\AttributeValidationPlanCompiler;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Provider\CompiledAttributeValidationProvider;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\RuleFactory;
use Psr\Container\ContainerInterface;

final class CompiledAttributeValidationParityFixture
{
    #[Field('email_address')]
    #[Nullable]
    #[Validate('required|email')]
    public ?string $email = null;
}

final class InvalidCompiledValidationAttributeFixture
{
    #[Field(123)]
    #[Validate('required')]
    public string $name;
}

it('matches reflection validation results for compiled attribute plans', function (): void {
    $container = new class () implements ContainerInterface {
        public function get(string $id): mixed
        {
            throw new RuntimeException("Unexpected container lookup: {$id}");
        }

        public function has(string $id): bool
        {
            return false;
        }
    };
    $ruleFactory = new RuleFactory();
    $validatorFactory = new ValidatorFactory($container, $ruleFactory);
    $reflection = new AttributeValidationProvider($validatorFactory, $ruleFactory);
    $plans = (new AttributeValidationPlanCompiler())->compile([
        CompiledAttributeValidationParityFixture::class,
    ]);
    $compiled = new CompiledAttributeValidationProvider(
        $validatorFactory,
        $ruleFactory,
        $plans,
        $reflection,
    );

    foreach ([
        ['email_address' => null],
        ['email_address' => 'valid@example.com'],
        ['email_address' => 'not-an-email'],
    ] as $data) {
        $reflectionResult = $reflection
            ->provide(CompiledAttributeValidationParityFixture::class)
            ?->validate($data);
        $compiledResult = $compiled
            ->provide(CompiledAttributeValidationParityFixture::class)
            ?->validate($data);

        if ($reflectionResult === true) {
            expect($compiledResult)->toBeTrue();
            continue;
        }

        expect($reflectionResult)->toBeInstanceOf(ErrorMessageCollectorInterface::class)
            ->and($compiledResult)->toBeInstanceOf(ErrorMessageCollectorInterface::class)
            ->and(count($compiledResult))->toBe(count($reflectionResult))
            ->and($compiledResult->has('email_address'))
            ->toBe($reflectionResult->has('email_address'));
    }
});

it('preserves attribute constructor failures during compilation', function (): void {
    expect(fn(): array => (new AttributeValidationPlanCompiler())->compile([
        InvalidCompiledValidationAttributeFixture::class,
    ]))->toThrow(TypeError::class);
});
