<?php
/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yii1x\ActiveRecord\Model\Rules;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Db\Schema\DbCriteria;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\Validator\Rules\AbstractRule;

/**
 * CUniqueValidator validates that the attribute value is unique in the corresponding database table.
 *
 * When using the {@link message} property to define a custom error message, the message
 * may contain additional placeholders that will be replaced with the actual content. In addition
 * to the "{attribute}" placeholder, recognized by all validators (see {@link Validator}),
 * CUniqueValidator allows for the following placeholders to be specified:
 * <ul>
 * <li>{value}: replaced with current value of the attribute.</li>
 * </ul>
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.validators
 * @since 1.0
 */
class UniqueRule extends AbstractRule
{
    /**
     * @var boolean whether the comparison is case sensitive. Defaults to true.
     * Note, by setting it to false, you are assuming the attribute type is string.
     */
    public bool $caseSensitive = true;
    /**
     * @var boolean whether the attribute value can be null or empty. Defaults to true,
     * meaning that if the attribute is empty, it is considered valid.
     */
    public bool $allowEmpty = true;
    /**
     * @var string the ActiveRecord class name that should be used to
     * look for the attribute value being validated. Defaults to null, meaning using
     * the class of the object currently being validated.
     * You may use path alias to reference a class name here.
     * @see attributeName
     */
    public ?string $className = null;
    /**
     * @var string the ActiveRecord class attribute name that should be
     * used to look for the attribute value being validated. Defaults to null,
     * meaning using the name of the attribute being validated.
     * @see className
     */
    public ?string $attributeName = null;
    /**
     * @var mixed additional query criteria. Either an array or CDbCriteria.
     * This will be combined with the condition that checks if the attribute
     * value exists in the corresponding table column.
     * This array will be used to instantiate a {@link DbCriteria} object.
     */
    public mixed $criteria = [];


    /**
     * Validates the attribute of the object.
     * If there is any error, the error message is added to the object.
     * @param object $object the object being validated
     * @param string $attribute the attribute being validated
     */
    protected function validateAttribute(object $object, string $attribute): void
    {
        $value = $object->$attribute;
        if ($this->allowEmpty && $this->isEmpty($value))
            return;

        if (is_array($value)) {
            // https://github.com/yiisoft/yii/issues/1955
            $this->validator->addError($attribute, '{attribute} is invalid.');
            return;
        }


        $className = $this->className ?: get_class($object);
        $attributeName = $this->attributeName === null ? $attribute : $this->attributeName;
        $finder = $this->getModel($className);
        $table = $finder->getTableSchema();
        if (($column = $table->getColumn($attributeName)) === null) {
            throw new DbException(sprintf('Table "%s" does not have a column named "%s".', $table->name, $attributeName));
        }

        $columnName = $column->rawName;
        $criteria = new DbCriteria();
        if ($this->criteria !== array())
            $criteria->mergeWith($this->criteria);
        $tableAlias = empty($criteria->alias) ? $finder->getTableAlias(true) : $criteria->alias;
        $valueParamName = DbCriteria::PARAM_PREFIX . DbCriteria::$paramCount++;
        $criteria->addCondition($this->caseSensitive ? "{$tableAlias}.{$columnName}={$valueParamName}" : "LOWER({$tableAlias}.{$columnName})=LOWER({$valueParamName})");
        $criteria->params[$valueParamName] = $value;

        if (!$object instanceof ActiveRecord || $object->isNewRecord || $object->tableName() !== $finder->tableName())
            $exists = $finder->exists($criteria);
        else {
            $criteria->limit = 2;
            $objects = $finder->findAll($criteria);
            $n = count($objects);
            if ($n === 1) {
                if ($column->isPrimaryKey)  // primary key is modified and not unique
                    $exists = $object->getOldPrimaryKey() != $object->getPrimaryKey();
                else {
                    // non-primary key, need to exclude the current record based on PK
                    $exists = array_shift($objects)->getPrimaryKey() != $object->getOldPrimaryKey();
                }
            } else
                $exists = $n > 1;
        }

        if ($exists) {
            $this->validator->addError($attribute, $this->message !== null ? $this->message : '{attribute} "{value}" has already been taken.', ['{value}' => $value]);
        }
    }

    /**
     * Given active record class name returns new model instance.
     *
     * @param string $className active record class name.
     * @return ActiveRecord active record model instance.
     *
     * @since 1.1.14
     */
    protected function getModel(string $className): ActiveRecord
    {
        return ActiveRecord::model($className);
    }
}

