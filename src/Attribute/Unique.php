<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;

/**
 * Unique validation attribute.
 *
 * Validates that value does not exist in specified database table and column.
 * Actual rule instance is created via RuleFactory with DatabaseInterface dependency.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Unique extends RuleAttribute
{
    /**
     * @param string $table Database table name
     * @param string $column Database column name
     */
    public function __construct(
        public readonly string $table,
        public readonly string $column = 'id',
    ) {
        parent::__construct('unique');
    }

    #[\Override]
    protected function getParams(): array
    {
        return [$this->table, $this->column];
    }
}
