<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Detector\MimeType as DetectedMimeType;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Definition\AttributeValidatorDefinitionExtractor;
use Componenta\Validation\Definition\ValidatorDefinitionFactory;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\RuleFactoryFactory;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\CompiledValidationProvider;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\ArrayOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\MimeType;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Url;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class RegressionTest extends TestCase
{
    public function testOneOfKeepsSearchingUnderStopOnFirstFailure(): void
    {
        $rule = new OneOf(new Email(), new Url());

        self::assertTrue($rule->validate(
            'https://example.com',
            Context::stopOnFirstFailure(),
        ));
    }

    public function testCompositesSupportValidateOnlyCustomRules(): void
    {
        $rule = new class implements RuleInterface {
            public string $name { get => 'validate_only'; }

            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                return $value === 'ok' ? true : new ErrorMessageCollector();
            }
        };

        self::assertTrue((new AllOf($rule))('ok'));
        self::assertTrue((new OneOf($rule))('ok'));
        self::assertTrue((new ArrayOf($rule))(['ok']));
    }

    public function testGeneratorInputIsMaterializedOnce(): void
    {
        $data = (static function (): \Generator {
            yield 'name' => 'Ada';
        })();

        self::assertTrue((new Validator(['name' => new Required()]))->validate($data));
    }

    public function testMissingNestedAndWildcardFieldsAreValidated(): void
    {
        $validator = new Validator([
            'profile.email' => new Required(),
            'items.*.sku' => new Required(),
        ]);

        $result = $validator->validate([
            'items' => [
                ['name' => 'First'],
                ['sku' => 'ok'],
            ],
        ]);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        self::assertArrayHasKey('profile.email', $result->toArray());
        self::assertArrayHasKey('items.0.sku', $result->toArray());
    }

    public function testProgrammaticNullableAllOfAcceptsNull(): void
    {
        self::assertTrue((new AllOf(new Nullable(), new Email()))->validate(null, new Context()));
    }

    public function testIfThenStringAliasIsNotExposed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RuleFactory())->createRule('ifthen:required,email');
    }

    public function testRuleFactoryDependenciesRemainOptional(): void
    {
        $factory = (new RuleFactoryFactory())(new TestContainer());

        self::assertInstanceOf(Email::class, $factory->createRule('email'));
    }

    public function testMimeRuleDoesNotReadStreamAfterUploadFailure(): void
    {
        $detector = new class implements MimeTypeDetectorInterface {
            public function detectMimeType(string|StreamInterface $content, bool $asObject = false): string|DetectedMimeType|null
            {
                return 'text/plain';
            }
        };
        $upload = new FailingUploadedFile();

        $result = (new MimeType($detector, ['text/plain']))->validate($upload, new Context());

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        self::assertFalse($upload->streamRequested);
    }

    public function testCompiledDefinitionsReuseTheDefaultValidator(): void
    {
        $extractor = new AttributeValidatorDefinitionExtractor();
        $definition = $extractor->extract(CompiledDto::class);
        self::assertIsArray($definition);

        $container = new TestContainer();
        $ruleFactory = new RuleFactory();
        $validatorFactory = new ValidatorFactory($container, $ruleFactory);
        $provider = new CompiledValidationProvider([
            'version' => 1,
            'validators' => [CompiledDto::class => $definition],
        ], $validatorFactory, new ValidatorDefinitionFactory($ruleFactory));

        self::assertTrue($provider->provide(CompiledDto::class)?->validate([
            'email' => 'user@example.com',
        ]));
    }

    public function testValidatedByIsRepresentedAsAValidatorService(): void
    {
        $definition = (new AttributeValidatorDefinitionExtractor())->extract(DelegatedDto::class);

        self::assertSame([
            'kind' => 'validator',
            'class' => DelegatedValidator::class,
        ], $definition);
    }

    public function testValidatedByCannotBeCombinedWithPropertyRules(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AttributeValidatorDefinitionExtractor())->extract(ConflictingDto::class);
    }
}

final class TestContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(private array $entries = []) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("Missing $id") extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class FailingUploadedFile implements UploadedFileInterface
{
    public bool $streamRequested = false;

    public function getStream(): StreamInterface
    {
        $this->streamRequested = true;
        throw new \RuntimeException('Stream must not be requested.');
    }

    public function moveTo(string $targetPath): void {}
    public function getSize(): ?int { return null; }
    public function getError(): int { return UPLOAD_ERR_NO_FILE; }
    public function getClientFilename(): ?string { return null; }
    public function getClientMediaType(): ?string { return null; }
}

final class CompiledDto
{
    #[Required]
    #[Validate('email')]
    public string $email;
}

#[ValidatedBy(DelegatedValidator::class)]
final class DelegatedDto {}

final class DelegatedValidator implements ValidatorInterface
{
    public function validate(iterable $data, ?ContextInterface $context = null): true|ErrorMessageCollectorInterface
    {
        return true;
    }
}

#[ValidatedBy(DelegatedValidator::class)]
final class ConflictingDto
{
    #[Required]
    public string $name;
}
