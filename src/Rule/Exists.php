<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Cycle\Database\DatabaseInterface;

/**
 * Exists rule: value must exist in database table.
 *
 * Examples:
 *   new Exists($db, 'users')              // Check id column
 *   new Exists($db, 'users', 'email')     // Check email column
 *
 * String syntax:
 *   "exists(users)"
 *   "exists(users, email)"
 *
 * Attribute usage:
 *   #[Exists(table: 'categories')]
 *   (use Componenta\Validation\Attribute\Exists)
 */
final class Exists implements RuleInterface
{
    public const string NOT_EXISTS_MESSAGE_ID = 'validation.exists';

    public string $name {
        get => 'exists';
    }

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $table,
        private readonly string $column = 'id',
    ) {}

    public function __invoke(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $this->db
            ->select('*')
            ->from($this->table)
            ->where($this->column, $value)
            ->limit(1)
            ->run()
            ->fetch() !== false;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_EXISTS_MESSAGE_ID, [
            'table' => $this->table,
            'column' => $this->column,
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_EXISTS_MESSAGE_ID => 'The selected :attribute does not exist.',
        ];
    }
}
