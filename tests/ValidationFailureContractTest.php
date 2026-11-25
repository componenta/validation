<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Exception\ValidationException;
use Componenta\Validation\Formatter\Dictionary;
use Componenta\Validation\Formatter\MessageFormatter;
use Componenta\Validation\Rule\IsBoolean;
use Componenta\Validation\Rule\MimeType;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\UploadedFile;
use Componenta\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

final class ValidationFailureContractTest extends TestCase
{
    public function testAReportedFailureRemainsAFailureWithoutMessages(): void
    {
        $rule = new class implements RuleInterface {
            public string $name { get => 'reject'; }

            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                return new ErrorMessageCollector();
            }
        };
        $validator = new Validator(['field' => $rule]);

        $errors = $validator->validate(['field' => 'value']);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame([], $errors->toArray());
    }

    public function testThrowOnFailureDoesNotRequireFormattingTheErrors(): void
    {
        $validator = new Validator(['name' => new Required()], formatter: new MessageFormatter([]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed: name');
        $validator->validate([], Context::throwOnFailure());
    }

    public function testABuiltInRuleFormatsItsErrorsWithADefaultContext(): void
    {
        $rule = new Required();

        $errors = $rule->validate('', Context::currentPath('name'));

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame(['name' => 'This field is required.'], $errors->toArray());
    }

    public function testFormatPreservesTheLiteralTextInPlaceholderValues(): void
    {
        $formatter = new MessageFormatter(['en_US' => ['test.message' => ':min / :minimum']]);
        $context = Context::messageFormatter($formatter);
        $message = new ErrorMessage($context, 'test.message', ['min' => ':minimum', 'minimum' => '10']);

        self::assertSame(':minimum / 10', $message->toString());
    }

    #[DataProvider('bundledLocales')]
    public function testBundledDictionariesCanRenderUploadAndBooleanFailures(string $locale): void
    {
        $upload = $this->createStub(UploadedFileInterface::class);
        $upload->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);
        $validator = new Validator([
            'document' => new UploadedFile(),
            'missing' => new UploadedFile(),
            'mime' => new MimeType($this->createStub(MimeTypeDetectorInterface::class), ['text/plain']),
            'flag' => new IsBoolean(),
        ], formatter: new MessageFormatter(Dictionary::load([$locale])), locale: $locale);

        $errors = $validator->validate(['document' => 'not a file', 'missing' => $upload, 'mime' => $upload, 'flag' => 'undefined']);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        $messages = $errors->toArray();
        self::assertSame(['document', 'missing', 'mime', 'flag'], array_keys($messages));
        self::assertStringContainsString((string) UPLOAD_ERR_NO_FILE, $messages['missing']);
        self::assertStringContainsString((string) UPLOAD_ERR_NO_FILE, $messages['mime']);
    }

    public static function bundledLocales(): iterable
    {
        yield 'English' => ['en_US'];
        yield 'Russian' => ['ru_RU'];
        yield 'French' => ['fr_FR'];
        yield 'German' => ['de_DE'];
        yield 'Spanish' => ['es_US'];
    }
}
