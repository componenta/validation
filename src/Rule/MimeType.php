<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Attribute;
use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * MimeType rule: uploaded file must have an allowed MIME type.
 *
 * Uses content-based detection (finfo) for security -
 * does not trust the client-reported Content-Type header.
 *
 * Example:
 *   new MimeType($detector, ['image/jpeg', 'image/png', 'image/webp'])
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class MimeType implements RuleInterface
{
    public const string NOT_UPLOADED_FILE_MESSAGE_ID = 'validation.mime_type.not_uploaded_file';
    public const string INVALID_MIME_TYPE_MESSAGE_ID = 'validation.mime_type.invalid';

    public string $name {
        get => 'mime_type';
    }

    /**
     * @param MimeTypeDetectorInterface $detector Content-based MIME detector
     * @param list<string> $allowed Allowed MIME types
     */
    public function __construct(
        private readonly MimeTypeDetectorInterface $detector,
        private readonly array $allowed,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        $mimeType = $this->detector->detectMimeType($value->getStream());

        return $mimeType !== null && in_array($mimeType, $this->allowed, true);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!$value instanceof UploadedFileInterface) {
            $collector->add($path, new ErrorMessage($context, self::NOT_UPLOADED_FILE_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));

            return $collector;
        }

        $mimeType = $this->detector->detectMimeType($value->getStream());

        if ($mimeType === null || !in_array($mimeType, $this->allowed, true)) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_MIME_TYPE_MESSAGE_ID, [
                'allowed' => implode(', ', $this->allowed),
                'actual' => $mimeType ?? 'unknown',
            ]));

            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_UPLOADED_FILE_MESSAGE_ID => 'The value must be an uploaded file, :type given.',
            self::INVALID_MIME_TYPE_MESSAGE_ID => 'File type :actual is not allowed. Allowed types: :allowed.',
        ];
    }
}
