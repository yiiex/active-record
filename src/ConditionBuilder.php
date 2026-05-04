<?php

namespace Yii1x\ActiveRecord;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Relations\{ActiveRelation, BelongsToRelation};

class ConditionBuilder
{
    public function __construct(
        protected ActiveRecord $model,
        public DbCriteria      $criteria,
        protected bool         $modelContext = true,
    )
    {

    }

    public function scopes(string|array $scopes): static
    {
        $this->criteria->mergeWith(['scopes' => (array)$scopes]);
        return $this;
    }

    public function applyScopes(string|array $scopes, string $operator = 'AND'): static
    {
        $tempCriteria = new DbCriteria(['alias' => $this->criteria->alias, 'scopes' => (array)$scopes]);
        $this->model->applyScopes($tempCriteria);
        $this->criteria->mergeWith(['condition' => $tempCriteria->condition, 'params' => $tempCriteria->params], $operator);
        return $this;
    }

    public function like(string $column, string $value, $operator = 'AND'): static
    {
        $this->criteria->addCondition("$column LIKE " . $this->criteria->addParam($value), $operator);
        return $this;
    }

    public function where(string|\Closure $column, mixed $comparison = null, mixed $value = null, $operator = 'AND'): static
    {
        if ($column instanceof \Closure) {
            $column($cb = new ConditionBuilder($this->model, new DbCriteria(), $this->modelContext));
            $this->criteria->mergeWith($cb->criteria, $operator);
        } else {
            if ($value === null) {
                $value = $comparison;
                $comparison = '=';
            }
            $this->criteria->compare($column, $comparison . $value, operator: $operator);
        }
        return $this;
    }

    public function whereIn(string $column, array $value, $operator = 'AND'): static
    {
        $this->criteria->addInCondition($column, $value, $operator);
        return $this;
    }

    public function whereNotIn(string $column, array $value, $operator = 'AND'): static
    {
        $this->criteria->addNotInCondition($column, $value, $operator);
        return $this;
    }

    public function whereRaw(string $condition, array $params = [], string $operator = 'AND'): static
    {
        $this->criteria->addCondition($condition, $operator);
        foreach ($params as $name => $param) {
            $this->criteria->addParam($param, $name);
        }
        return $this;
    }

    public function whereHas(string $tableName, ?\Closure $callback = null, string $operator = 'AND', ?string $tableAlias = null): static
    {
        $tableAlias ??= $tableName;
        $conditionBuilder = new static($this->model, new DbCriteria(['select' => '1', 'alias' => $tableAlias]), false);
        if ($callback) {
            $callback($conditionBuilder);
        }
        $cmd = $this->model->commandBuilder->createFindCommand($tableName, $conditionBuilder->criteria, $tableAlias);
        $this->whereRaw('EXISTS(' . $cmd->getText() . ')', operator: $operator);
        $this->criteria->params += $conditionBuilder->criteria->params;
        return $this;
    }

    /**
     * Build where exists condition
     * @param string $relation
     * @param \Closure $callback
     * @param string $operator
     * @param string|null $relAlias
     * @return $this
     */
    public function whereRelation(string $relation, ?\Closure $callback = null, string $operator = 'AND', ?string $relAlias = null): static
    {
        if (str_contains($relation, '.')) {
            $parts = explode('.', $relation, 2);
            // Рекурсивно строим условие
            return $this->whereRelation($parts[0], fn(ConditionBuilder $builder) => $builder
                ->whereRelation($parts[1], $callback), $operator, $relAlias);
        }

        if (!$this->modelContext) {
            throw new DbException('Model context not set');
        }
        /** @var ActiveRelation $rel */
        if (!$rel = $this->model->getActiveRelation($relation)) {
            throw new DbException('Relation "' . $relation . '" does not exist.');
        }
        if ($rel->through) {
            if (!$throughRel = $this->model->getActiveRelation($rel->through)) {
                throw new DbException('Relation "' . $rel->through . '" does not exist.');
            }
            $model = ActiveRecord::model($throughRel->className);
            $modelAlias = $rel->through;
        } else {
            $model = $this->model;
            $modelAlias = $this->criteria->alias;
        }
        $relAlias ??= $relation;
        $relModel = ActiveRecord::model($rel->className);
        $conditionBuilder = new static($relModel, new DbCriteria(['select' => '1', 'alias' => $relAlias]));
        if ($callback) {
            $callback($conditionBuilder);
        }
        if ($rel instanceof BelongsToRelation) {
            $fkMap = is_string($rel->foreignKey) ? [$rel->foreignKey => $model->getTableSchema()->primaryKey] : $rel->foreignKey;
        } else {
            $fkMap = is_string($rel->foreignKey) ? [$relModel->getTableSchema()->primaryKey => $rel->foreignKey] : $rel->foreignKey;
        }

        foreach ($fkMap as $pk => $fk) {
            if (is_array($pk) || is_array($fk)) {
                throw new DbException("Relation '{$relation}' has composite key. " . "Define all fields explicitly in foreignKey array.");
            }
            $conditionBuilder->whereRaw("{$relAlias}.{$fk} = {$modelAlias}.{$pk}");
        }
        $relModel->tableAlias = $relAlias;
        $relModel->applyScopes($conditionBuilder->criteria);
        $cmd = $relModel->commandBuilder->createFindCommand($relModel->tableName(), $conditionBuilder->criteria, $relAlias);
        if ($rel->through) {
            $this->whereRelation($rel->through, fn(ConditionBuilder $builder) => $builder->whereRaw('EXISTS(' . $cmd->getText() . ')'));
        } else {
            $this->whereRaw('EXISTS(' . $cmd->getText() . ')', operator: $operator);
        }
        $this->criteria->params += $conditionBuilder->criteria->params;
        return $this;
    }

}
