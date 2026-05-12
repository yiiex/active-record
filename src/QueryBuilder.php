<?php

namespace Yii1x\ActiveRecord;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

class QueryBuilder
{
    public DbCriteria $criteria;
    protected ?ConditionBuilder $conditionBuilder {
        get => $this->conditionBuilder ?? new ConditionBuilder($this->model, $this->criteria);
    }

    public function __construct(protected ActiveRecord $model, ?DbCriteria $criteria = null)
    {
        $this->criteria = $criteria ?: new DbCriteria(['alias' => 't']);
    }

    public function distinct(bool $value = true): static
    {
        $this->criteria->distinct = $value;
        return $this;
    }

    public function select(string|array $column): static
    {
        $this->criteria->select = $column;
        return $this;
    }

    public function orderBy(string|array $column): static
    {
        $this->criteria->order = $column;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->criteria->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->criteria->offset = $offset;
        return $this;
    }

    public function indexBy(string $property): static
    {
        $this->criteria->index = $property;
        return $this;
    }

    public function groupBy(string|array $column): static
    {
        $this->criteria->group = $column;
        return $this;
    }

    public function having(string $condition): static
    {
        $this->criteria->having = $condition;
        return $this;
    }

    public function with(string|array $with): static
    {
        $this->criteria->mergeWith(['with' => (array)$with]);
        return $this;
    }

    public function together(bool $value = true): static
    {
        $this->criteria->together = $value;
        return $this;
    }

    public function scopes(string|array $scopes): static
    {
        $this->conditionBuilder->scopes($scopes);
        return $this;
    }

    public function applyScopes(string|array $scopes): static
    {
        $this->conditionBuilder->applyScopes($scopes);
        return $this;
    }

    public function like(string $column, string $value, $operator = 'AND'): static
    {
        $this->conditionBuilder->like($column, $value, $operator);
        return $this;
    }

    public function where(string|\Closure $column, mixed $comparison = null, mixed $value = null, $operator = 'AND'): static
    {
        $this->conditionBuilder->where($column, $comparison, $value, $operator);
        return $this;
    }

    public function whereIn(string $column, array $value, $operator = 'AND'): static
    {
        $this->conditionBuilder->whereIn($column, $value, $operator);
        return $this;
    }

    public function whereNotIn(string $column, array $value, $operator = 'AND'): static
    {
        $this->conditionBuilder->whereNotIn($column, $value, $operator);
        return $this;
    }

    public function whereNull(string $column, string $operator = 'AND'): static
    {
        $this->conditionBuilder->whereNull($column, $operator);
        return $this;
    }

    public function whereNotNull(string $column, string $operator = 'AND'): static
    {
        $this->conditionBuilder->whereNotNull($column, $operator);
        return $this;
    }

    public function whereBetween(string $column, mixed $start, mixed $end, string $operator = 'AND'): static
    {
        $this->conditionBuilder->whereBetween($column, $start, $end, $operator);
        return $this;
    }

    public function whereRaw(string $condition, array $params = [], string $operator = 'AND'): static
    {
        $this->conditionBuilder->whereRaw($condition, $params, $operator);
        return $this;
    }

    public function whereRelation(string $relation, ?\Closure $callback = null, string $operator = 'AND', string $relAlias = null): static
    {
        $this->conditionBuilder->whereRelation($relation, $callback, $operator, $relAlias);
        return $this;
    }

    public function join(string $table, string $condition, string $type = 'INNER JOIN', ?string $tableAlias = null): static
    {
        $join = implode(' ', array_filter([
                $type,
                $this->model->dbConnection->quoteTableName($table),
                $tableAlias,
            ])) . " ON {$condition}";
        if ($this->criteria->join === '') {
            $this->criteria->join = $join;
        } else {
            $this->criteria->join .= ' ' . $join;
        }
        return $this;
    }

    public function leftJoin(string $table, string $condition, ?string $tableAlias = null): static
    {
        return $this->join($table, $condition, 'LEFT JOIN', $tableAlias);
    }

    public function rightJoin(string $table, string $condition, ?string $tableAlias = null): static
    {
        return $this->join($table, $condition, 'RIGHT JOIN', $tableAlias);
    }

    public function innerJoin(string $table, string $condition, ?string $tableAlias = null): static
    {
        return $this->join($table, $condition, 'INNER JOIN', $tableAlias);
    }

    /**
     * Conditionally applies a callback to the query builder
     *
     * If the condition evaluates to true, the callback is executed with this instance.
     * Otherwise, the default callback (if provided) is executed.
     *
     * @param mixed $condition The condition to evaluate
     * @param \Closure $callback Callback to execute when condition is true
     * @param \Closure|null $fallback Callback to execute when condition is false (optional)
     * @return static Returns the same query builder instance for chaining
     */
    public function when(mixed $condition, \Closure $callback, ?\Closure $fallback = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($fallback) {
            $fallback($this);
        }
        return $this;
    }

    /**
     * Creates a fork (copy) of the current query builder instance
     *
     * Use this when you need to create a separate branch of the query without affecting the original.
     * All criteria, conditions, scopes, and parameters are copied to the new instance.
     *
     * @return static A new independent query builder instance with the same state
     */
    public function fork(): static
    {
        return new static($this->model, clone $this->criteria);
    }

    public function count(): int
    {
        return $this->model->count(clone $this->criteria);
    }

    public function exists(): bool
    {
        return $this->model->exists(clone $this->criteria);
    }

    public function find(): ?ActiveRecord
    {
        return $this->model->find(clone $this->criteria);
    }

    public function findAll(): array
    {
        return $this->model->findAll(clone $this->criteria);
    }

    public function deleteAll(): int
    {
        return $this->model->deleteAll(clone $this->criteria);
    }

    public function __clone(): void
    {
        $this->criteria = clone $this->criteria;
        $this->conditionBuilder = null;
    }
}
