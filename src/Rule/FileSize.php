<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Attribute;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * FileSize rule: uploaded file size must be within bounds.
 *
 * Examples:
 *   new FileSize(max: 2 * 1024 * 1024)             // max 2 MB
 *   new FileSize(max: 5242880, min: 1024)           // 1 KB - 5 MB
 *   FileSize::parseSize('2mb')                      // 2097152
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class FileSize implements RuleInterface
{
    public const string NOT_UPLOADED_FILE_MESSAGE_ID = 'validation.file_size.not_uploaded_file';
    public const string TOO_LARGE_MESSAGE_ID = 'validation.file_size.too_large';
    public const string TOO_SMALL_MESSAGE_ID = 'validation.file_size.too_small';

    public string $name {
        get => 'file_size';
    }

    /**
     * @param int $max Maximum file size in bytes
     * @param int $min Minimum file size in bytes
     */
    public function __construct(
        private readonly int $max,
        private readonly int $min = 0,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!$value instanceof UploadedFileInterface) {
            return false;
        }

        $size = $value->getSize() ?? 0;

        return $size >= $this->min && $size <= $this->max;
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

        $size = $value->getSize() ?? 0;

        if ($size > $this->max) {
            $collector->add($path, new ErrorMessage($context, self::TOO_LARGE_MESSAGE_ID, [
                'max' => self::formatSize($this->max),
                'actual' => self::formatSize($size),
            ]));

            return $collector;
        }

        if ($size < $this->min) {
            $collector->add($path, new ErrorMessage($context, self::TOO_SMALL_MESSAGE_ID, [
                'min' => self::formatSize($this->min),
                'actual' => self::formatSize($size),
            ]));

            return $collector;
        }

        return true;
    }

    /**
     * Parse human-readable size string to bytes.
     *
     * Supported formats: '2mb', '500kb', '1gb', '1024' (bytes).
     */
    public static function parseSize(string $size): int
    {
        $size = strtolower(trim($size));

        if (is_numeric($size)) {
            return (int) $size;
        }

        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(kb|mb|gb|tb|b)?$/', $size, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid file size format: "%s"', $size));
        }

        $value = (float) $matches[1];
        $unit = $matches[2] ?? 'b';

        return (int) match ($unit) {
            'b' => $value,
            'kb' => $value * 1024,
            'mb' => $value * 1024 * 1024,
            'gb' => $value * 1024 * 1024 * 1024,
            'tb' => $value * 1024 * 1024 * 1024 * 1024,
        };
    }

    /**
     * Format bytes to human-readable string.
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_UPLOADED_FILE_MESSAGE_ID => 'The value must be an uploaded file, :type given.',
            self::TOO_LARGE_MESSAGE_ID => 'File size must not exceed :max, :actual given.',
            self::TOO_SMALL_MESSAGE_ID => 'File size must be at least :min, :actual given.',
        ];
    }
}
