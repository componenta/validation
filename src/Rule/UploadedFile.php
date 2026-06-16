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
 * UploadedFile rule: value must be a valid uploaded file with no errors.
 *
 * Validates that the value is an instance of UploadedFileInterface
 * and has UPLOAD_ERR_OK status.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class UploadedFile implements RuleInterface
{
    public const string NOT_UPLOADED_FILE_MESSAGE_ID = 'validation.uploaded_file.not_uploaded_file';
    public const string UPLOAD_ERROR_MESSAGE_ID = 'validation.uploaded_file.upload_error';

    public string $name {
        get => 'uploaded_file';
    }

    public function __invoke(mixed $value): bool
    {
        return $value instanceof UploadedFileInterface
            && $value->getError() === UPLOAD_ERR_OK;
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

        if ($value->getError() !== UPLOAD_ERR_OK) {
            $collector->add($path, new ErrorMessage($context, self::UPLOAD_ERROR_MESSAGE_ID, [
                'code' => $value->getError(),
            ]));

            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_UPLOADED_FILE_MESSAGE_ID => 'The value must be an uploaded file, :type given.',
            self::UPLOAD_ERROR_MESSAGE_ID => 'File upload failed with error code :code.',
        ];
    }
}
