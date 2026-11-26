<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Detector\FinfoDetector;
use Componenta\Detector\MimeType as DetectedMimeType;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Context;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\MimeType;
use Componenta\Validation\Rule\RuleFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class MimeRuleContractTest extends TestCase
{
    #[DataProvider('definitions')]
    public function testFactoryRulesValidateActualContentRatherThanClientMetadata(string $definition, bool $expected): void
    {
        $rule = (new RuleFactory(detector: new FinfoDetector()))->createRule($definition);

        // A seekable upload is not required by the detector. Use a fresh stream
        // for each call because the detector consumes non-seekable streams.
        $result = $rule->validate($this->plainTextUpload(), Context::currentPath('attachment'));
        if ($expected) {
            self::assertTrue($result);
        } else {
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
            self::assertArrayHasKey('attachment', $result->toArray());
        }
    }

    public static function definitions(): iterable
    {
        yield 'direct matching type' => ['mime_type:text/plain', true];
        yield 'direct nonmatching type' => ['mime_type:image/png', false];
        yield 'direct wildcard denied' => ['mime_type:text/*', false];
        yield 'direct universal wildcard denied' => ['mime_type:*/*', false];
        yield 'file matching type' => ['file:0,text/plain', true];
        yield 'file nonmatching type' => ['file:0,image/png', false];
        yield 'file wildcard denied' => ['file:0,text/*', false];
        yield 'file universal wildcard denied' => ['file:0,*/*', false];
        yield 'file size and type' => ['file:1KB,text/plain', true];
    }

    public function testUndetectedContentIsRejectedWithUnknownActualType(): void
    {
        $detector = $this->createStub(MimeTypeDetectorInterface::class);
        $detector->method('detectMimeType')->willReturn(null);
        $rule = new MimeType($detector, ['text/plain']);

        self::assertFalse($rule($this->plainTextUpload()));
        $result = $rule->validate($this->plainTextUpload(), Context::currentPath('attachment'));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        $message = $result->get('attachment');
        self::assertInstanceOf(ErrorMessage::class, $message);
        self::assertSame(MimeType::INVALID_MIME_TYPE_MESSAGE_ID, $message->messageId);
        self::assertSame(['allowed' => 'text/plain', 'actual' => 'unknown'], $message->placeholders);
    }

    public function testDetectedTypeIsFormattedAsAStringAndNotAnObject(): void
    {
        $detector = $this->createStub(MimeTypeDetectorInterface::class);
        $detector->method('detectMimeType')->willReturn(new DetectedMimeType('text/plain'));
        $rule = new MimeType($detector, ['image/png']);

        self::assertFalse($rule($this->plainTextUpload()));
        $result = $rule->validate($this->plainTextUpload(), Context::currentPath('attachment'));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        $message = $result->get('attachment');
        self::assertInstanceOf(ErrorMessage::class, $message);
        self::assertSame(['allowed' => 'image/png', 'actual' => 'text/plain'], $message->placeholders);
        self::assertStringContainsString('text/plain', $message->toString());
    }

    private function plainTextUpload(): UploadedFileInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('eof')->willReturnOnConsecutiveCalls(false, true);
        $stream->method('read')->willReturn('plain text');

        $file = $this->createStub(UploadedFileInterface::class);
        $file->method('getError')->willReturn(UPLOAD_ERR_OK);
        $file->method('getSize')->willReturn(10);
        $file->method('getStream')->willReturn($stream);
        $file->method('getClientFilename')->willReturn('spoofed.png');
        $file->method('getClientMediaType')->willReturn('image/png');

        return $file;
    }
}
