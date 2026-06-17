<?php

/**
 * DBドライバーを問わず大文字小文字・全半角を無視したテキスト検索クエリを組み立てるユーティリティクラス。
 */
namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class DatabaseText
{
    public static function whereContainsInsensitive(EloquentBuilder|QueryBuilder $builder, string $column, ?string $value, string $boolean = 'and'): EloquentBuilder|QueryBuilder
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return $builder;
        }

        return $builder->whereRaw(
            self::loweredExpression($builder, $column) . ' LIKE ?',
            ['%' . $normalized . '%'],
            $boolean
        );
    }

    public static function whereEqualsInsensitive(EloquentBuilder|QueryBuilder $builder, string $column, ?string $value, string $boolean = 'and'): EloquentBuilder|QueryBuilder
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return $builder;
        }

        return $builder->whereRaw(
            self::loweredExpression($builder, $column) . ' = ?',
            [$normalized],
            $boolean
        );
    }

    public static function loweredExpression(EloquentBuilder|QueryBuilder $builder, string $column): string
    {
        $query = $builder instanceof EloquentBuilder ? $builder->getQuery() : $builder;
        $wrapped = $query->getGrammar()->wrap($column);

        return 'LOWER(' . self::castedExpression($query, $wrapped) . ')';
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strtolower($trimmed, 'UTF-8');
    }

    private static function castedExpression(QueryBuilder $query, string $wrappedColumn): string
    {
        $castType = match ($query->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => 'CHAR',
            default => 'TEXT',
        };

        return "COALESCE(CAST({$wrappedColumn} AS {$castType}), '')";
    }
}
