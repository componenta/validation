<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Cycle\Database\DatabaseInterface;

/**
 * Unique rule: value must not exist in database table.
 *
 * Examples:
 *   new Unique($db, 'users', 'email')
 *
 * String syntax:
 *   "unique(users, email)"
 *
 * Attribute usage:
 *   #[Unique(table: 'users', column: 'email')]
 *   (use Componenta\Validation\Attribute\Unique)
 */
final class Unique implements RuleInterface
{
    public const string NOT_UNIQUE_MESSAGE_ID = 'validation.unique';

    public string $name {
        get => 'unique';
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
            ->fetch() === false;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_UNIQUE_MESSAGE_ID, [
            'table' => $this->table,
            'column' => $this->column,
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_UNIQUE_MESSAGE_ID => 'The :attribute has already been taken.',
        ];
    }
}
