<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

use Modules\Ingestion\Enums\SourceType;

abstract class SourceSchema
{
    abstract public function source(): SourceType;

    abstract public function title(): string;

    abstract public function columns(): array;

    abstract public function recordKeyColumn(): string;

    abstract public function uniqueKeyColumn(): ?string;

    protected function rowRules(array $values, array &$errors, ValidationContext $context): void {}

    public function headers(): array
    {
        return array_map(fn (Column $c): string => $c->name, $this->columns());
    }

    public function column(string $name): ?Column
    {
        foreach ($this->columns() as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    public function validate(int $rowNumber, array $raw, ValidationContext $context): RowResult
    {
        $values = [];
        $errors = [];
        $clean = [];

        foreach ($this->columns() as $column) {
            $value = self::clean($raw[$column->name] ?? null);
            $clean[$column->name] = $value;

            if ($value === null) {
                if ($column->isRequired()) {
                    $errors[] = Reason::missing($column->name);
                }
                $values[$column->name] = null;

                continue;
            }

            $parsed = FieldParser::parse($column, $value, $context->timezone);
            if ($parsed->failed()) {
                $errors[] = (string) $parsed->error;
                $values[$column->name] = null;
            } else {
                $values[$column->name] = $parsed->value;
            }
        }

        if ($errors === []) {
            $this->rowRules($values, $errors, $context);
        }

        return new RowResult($rowNumber, $clean, $errors === [] ? $values : [], $errors, $clean[$this->recordKeyColumn()] ?? '(blank)');
    }

    public static function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
