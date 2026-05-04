<?php

namespace Yii1x\ActiveRecord;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

class QueryBuilder
{
    public DbCriteria $criteria;
    protected ConditionBuilder $conditionBuilder {
        get => $this->conditionBuilder ?? new ConditionBuilder($this->model, $this->criteria);
    }

    public function __construct(protected ActiveRecord $model, ?DbCriteria $criteria = null)
    {
        $this->criteria = $criteria ?: new DbCriteria(['alias' => 't']);
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

    public function groupBy(string|array $column): static
    {
        $this->criteria->group = $column;
        return $this;
    }

    public function with(string|array $with): static
    {
        $this->criteria->mergeWith(['with' => (array)$with]);
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

    public function whereRaw(string $condition, array $params = [], string $operator = 'AND'): static
    {
        $this->conditionBuilder->whereRaw($condition, $params, $operator);
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

    public function whereRelation(string $relation, ?\Closure $callback = null, string $operator = 'AND', string $relAlias = null): static
    {
        $this->conditionBuilder->whereRelation($relation, $callback, $operator, $relAlias);
        return $this;
    }

    public function count(): int
    {
        return $this->model->count(clone $this->criteria);
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
}
