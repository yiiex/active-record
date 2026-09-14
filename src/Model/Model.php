<?php

namespace Yii1x\ActiveRecord\Model;

use ArrayAccess;
use IteratorAggregate;
use Traversable;
use Yii1x\ActiveRecord\ORMContext;
use Yii1x\Validator\Contracts\ValidatorInterface;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
abstract class Model extends CComponent implements IteratorAggregate, ArrayAccess
{
    private ?Validator $_validator = null;
    private string $_scenario = '';

    /**
     * Returns the list of attribute names of the model.
     * @return array list of attribute names.
     */
    abstract public function attributeNames(): array;

    /**
     * Returns the validation rules for attributes.
     *
     * This method should be overridden to declare validation rules.
     * Each rule is an array with the following structure:
     * <pre>
     * array('attribute list', 'validator name', 'on'=>'scenario name', ...validation parameters...)
     * </pre>
     * where
     * <ul>
     * <li>attribute list: specifies the attributes (separated by commas) to be validated;</li>
     * <li>validator name: specifies the validator to be used. It can be the name of a model class
     *   method, the name of a built-in validator, or a validator class (or its path alias).
     *   A validation method must have the following signature:
     * <pre>
     * // $params refers to validation parameters given in the rule
     * function validatorName($attribute,$params)
     * </pre>
     *   A built-in validator refers to one of the validators declared in {@link CValidator::builtInValidators}.
     *   And a validator class is a class extending {@link CValidator}.</li>
     * <li>on: this specifies the scenarios when the validation rule should be performed.
     *   Separate different scenarios with commas. If this option is not set, the rule
     *   will be applied in any scenario that is not listed in "except". Please see {@link scenario} for more details about this option.</li>
     * <li>except: this specifies the scenarios when the validation rule should not be performed.
     *   Separate different scenarios with commas. Please see {@link scenario} for more details about this option.</li>
     * <li>additional parameters are used to initialize the corresponding validator properties.
     *   Please refer to individual validator class API for possible properties.</li>
     * </ul>
     *
     * The following are some examples:
     * <pre>
     * array(
     *     array('username', 'required'),
     *     array('username', 'length', 'min'=>3, 'max'=>12),
     *     array('password', 'compare', 'compareAttribute'=>'password2', 'on'=>'register'),
     *     array('password', 'authenticate', 'on'=>'login'),
     * );
     * </pre>
     *
     * Note, in order to inherit rules defined in the parent class, a child class needs to
     * merge the parent rules with child rules using functions like array_merge().
     *
     * @return array validation rules to be applied when {@link validate()} is called.
     * @see scenario
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Returns a list of behaviors that this model should behave as.
     * The return value should be an array of behavior configurations indexed by
     * behavior names. Each behavior configuration can be either a string specifying
     * the behavior class or an array of the following structure:
     * <pre>
     * 'behaviorName'=>array(
     *     'class'=>'path.to.BehaviorClass',
     *     'property1'=>'value1',
     *     'property2'=>'value2',
     * )
     * </pre>
     *
     * Note, the behavior classes must implement {@link IBehavior} or extend from
     * {@link Behavior}. Behaviors declared in this method will be attached
     * to the model when it is instantiated.
     *
     * For more details about behaviors, see {@link CComponent}.
     * @return array the behavior configurations (behavior name=>behavior configuration)
     */
    public function behaviors(): array
    {
        return [];
    }

    /**
     * Returns the attribute labels.
     * Attribute labels are mainly used in error messages of validation.
     * By default an attribute label is generated using {@link generateAttributeLabel}.
     * This method allows you to explicitly specify attribute labels.
     *
     * Note, in order to inherit labels defined in the parent class, a child class needs to
     * merge the parent labels with child labels using functions like array_merge().
     *
     * @return array attribute labels (name=>label)
     * @see generateAttributeLabel
     */
    public function attributeLabels(): array
    {
        return [];
    }

    protected function getValidator(): ValidatorInterface
    {
        return $this->_validator ??= new Validator($this, $this->rules());
    }

    /**
     * Performs the validation.
     *
     * This method executes the validation rules as declared in {@link rules}.
     * Only the rules applicable to the current {@link scenario} will be executed.
     * A rule is considered applicable to a scenario if its 'on' option is not set
     * or contains the scenario.
     *
     * Errors found during the validation can be retrieved via {@link getErrors}.
     *
     * @param array|null $attributes list of attributes that should be validated. Defaults to null,
     * meaning any attribute listed in the applicable validation rules should be
     * validated. If this parameter is given as a list of attributes, only
     * the listed attributes will be validated.
     * @param boolean $clearErrors whether to call {@link clearErrors} before performing validation
     * @return boolean whether the validation is successful without any error.
     * @see beforeValidate
     * @see afterValidate
     */
    public function validate(?array $attributes = null, bool $clearErrors = true): bool
    {
        if ($this->beforeValidate()) {
            $result = $this->getValidator()->validate($this->scenario, $attributes, $clearErrors);
            $this->afterValidate();
            return $result;
        }
        return false;
    }

    /**
     * This method is invoked after a model instance is created by new operator.
     * The default implementation raises the {@link onAfterConstruct} event.
     * You may override this method to do postprocessing after model creation.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterConstruct(): void
    {
        if ($this->hasEventHandler('onAfterConstruct'))
            $this->onAfterConstruct(new Event($this));
    }

    /**
     * This method is invoked before validation starts.
     * The default implementation calls {@link onBeforeValidate} to raise an event.
     * You may override this method to do preliminary checks before validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     * @return boolean whether validation should be executed. Defaults to true.
     * If false is returned, the validation will stop and the model is considered invalid.
     */
    protected function beforeValidate(): bool
    {
        $event = new ModelEvent($this);
        $this->onBeforeValidate($event);
        return $event->isValid;
    }

    /**
     * This method is invoked after validation ends.
     * The default implementation calls {@link onAfterValidate} to raise an event.
     * You may override this method to do postprocessing after validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     */
    protected function afterValidate(): void
    {
        $this->onAfterValidate(new Event($this));
    }

    /**
     * This event is raised after the model instance is created by new operator.
     * @param Event $event the event parameter
     */
    public function onAfterConstruct(Event $event): void
    {
        $this->raiseEvent('onAfterConstruct', $event);
    }

    /**
     * This event is raised before the validation is performed.
     * @param ModelEvent $event the event parameter
     */
    public function onBeforeValidate(ModelEvent $event): void
    {
        $this->raiseEvent('onBeforeValidate', $event);
    }

    /**
     * This event is raised after the validation is performed.
     * @param Event $event the event parameter
     */
    public function onAfterValidate(Event $event): void
    {
        $this->raiseEvent('onAfterValidate', $event);
    }

    /**
     * Returns a value indicating whether the attribute is required.
     * This is determined by checking if the attribute is associated with a
     * {@link CRequiredValidator} validation rule in the current {@link scenario}.
     * @param string $attribute attribute name
     * @return boolean whether the attribute is required
     */
    public function isAttributeRequired(string $attribute): bool
    {
        return in_array($attribute, $this->getValidator()->getRequiredAttributes($this->scenario));
    }

    /**
     * Returns a value indicating whether the attribute is safe for massive assignments.
     * @param string $attribute attribute name
     * @return boolean whether the attribute is safe for massive assignments
     * @since 1.1
     */
    public function isAttributeSafe(string $attribute): bool
    {
        return in_array($attribute, $this->getSafeAttributeNames());
    }

    /**
     * Returns the text label for the specified attribute.
     * @param string $attribute the attribute name
     * @return string the attribute label
     * @see generateAttributeLabel
     * @see attributeLabels
     */
    public function getAttributeLabel(string $attribute): string
    {
        $labels = $this->attributeLabels();
        return $labels[$attribute] ?? $this->generateAttributeLabel($attribute);
    }

    /**
     * Returns a value indicating whether there is any validation error.
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return boolean whether there is any error.
     */
    public function hasErrors(string|null $attribute = null): bool
    {
        return $this->getValidator()->hasErrors($attribute);
    }

    /**
     * Returns the errors for all attribute or a single attribute.
     * @param string|null $attribute attribute name. Use null to retrieve errors for all attributes.
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     */
    public function getErrors(string|null $attribute = null): array
    {
        $errors = $this->getValidator()->getErrors();
        if ($attribute === null)
            return $errors;
        else
            return $errors[$attribute] ?? [];
    }

    /**
     * Returns the first error of the specified attribute.
     * @param string $attribute attribute name.
     * @return string|null the error message. Null is returned if no error.
     */
    public function getError(string $attribute): ?string
    {
        $errors = $this->getValidator()->getErrors();
        return isset($errors[$attribute]) ? reset($errors[$attribute]) : null;
    }

    /**
     * Adds a new error to the specified attribute.
     * @param string $attribute attribute name
     * @param string $error new error message
     */
    public function addError(string $attribute, string $error): void
    {
        $this->getValidator()->addError($attribute, $error);
    }

    /**
     * Adds a list of errors.
     * @param array $errors a list of errors. The array keys must be attribute names.
     * The array values should be error messages. If an attribute has multiple errors,
     * these errors must be given in terms of an array.
     * You may use the result of {@link getErrors} as the value for this parameter.
     */
    public function addErrors(array $errors): void
    {
        foreach ($errors as $attribute => $error) {
            if (is_array($error)) {
                foreach ($error as $e)
                    $this->addError($attribute, $e);
            } else
                $this->addError($attribute, $error);
        }
    }

    /**
     * Removes errors for all attributes or a single attribute.
     * @param array|string|null $attribute attribute name. Use null to remove errors for all attribute.
     */
    public function clearErrors(array|string|null $attribute = null): void
    {
        $this->getValidator()->clearErrors($attribute);
    }

    /**
     * Generates a user friendly attribute label.
     * This is done by replacing underscores or dashes with blanks and
     * changing the first letter of each word to upper case.
     * For example, 'department_name' or 'DepartmentName' becomes 'Department Name'.
     * @param string $name the column name
     * @return string the attribute label
     */
    public function generateAttributeLabel(string $name): string
    {
        return ucwords(trim(strtolower(str_replace(['-', '_', '.'], ' ', preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $name)))));
    }

    /**
     * Returns all attribute values.
     * @param array|null $names list of attributes whose value needs to be returned.
     * Defaults to null, meaning all attributes as listed in {@link attributeNames} will be returned.
     * If it is an array, only the attributes in the array will be returned.
     * @return array attribute values (name=>value).
     */
    public function getAttributes(?array $names = null): array
    {
        $values = [];
        foreach ($this->attributeNames() as $name) {
            $values[$name] = $this->$name;
        }

        if (is_array($names)) {
            $values2 = [];
            foreach ($names as $name) {
                $values2[$name] = $values[$name] ?? null;
            }
            return $values2;
        } else {
            return $values;
        }
    }

    /**
     * Sets the attribute values in a massive way.
     * @param array $values attribute values (name=>value) to be set.
     * @param boolean $safeOnly whether the assignments should only be done to the safe attributes.
     * A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
     * @see getSafeAttributeNames
     * @see attributeNames
     */
    public function setAttributes(array $values, bool $safeOnly = true): void
    {
        $attributes = array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());
        foreach ($values as $name => $value) {
            if (isset($attributes[$name]))
                $this->$name = $value;
            elseif ($safeOnly)
                $this->onUnsafeAttribute($name, $value);
        }
    }

    /**
     * Sets the attributes to be null.
     * @param array|null $names list of attributes to be set null. If this parameter is not given,
     * all attributes as specified by {@link attributeNames} will have their values unset.
     * @since 1.1.3
     */
    public function unsetAttributes(?array $names = null): void
    {
        if ($names === null) {
            $names = $this->attributeNames();
        }
        foreach ($names as $name) {
            $this->$name = null;
        }
    }

    /**
     * This method is invoked when an unsafe attribute is being massively assigned.
     * The default implementation will log a warning message if YII_DEBUG is on.
     * It does nothing otherwise.
     * @param string $name the unsafe attribute name
     * @param mixed $value the attribute value
     * @since 1.1.1
     */
    public function onUnsafeAttribute(string $name, mixed $value): void
    {
        if (ORMContext::isDebug()) {
            ORMContext::log()?->warning('Failed to set unsafe attribute "{attribute}" of "{class}".', [
                'attribute' => $name,
                'class' => static::class,
            ]);
        }
    }

    /**
     * Returns the scenario that this model is used in.
     *
     * Scenario affects how validation is performed and which attributes can
     * be massively assigned.
     *
     * A validation rule will be performed when calling {@link validate()}
     * if its 'except' value does not contain current scenario value while
     * 'on' option is not set or contains the current scenario value.
     *
     * And an attribute can be massively assigned if it is associated with
     * a validation rule for the current scenario. Note that an exception is
     * the {@link CUnsafeValidator unsafe} validator which marks the associated
     * attributes as unsafe and not allowed to be massively assigned.
     *
     * @return string the scenario that this model is in.
     */
    public function getScenario(): string
    {
        return $this->_scenario;
    }

    /**
     * Sets the scenario for the model.
     * @param string $value the scenario that this model is in.
     * @see getScenario
     */
    public function setScenario(string $value): void
    {
        $this->_scenario = $value;
    }

    /**
     * Returns the attribute names that are safe to be massively assigned.
     * A safe attribute is one that is associated with a validation rule in the current {@link scenario}.
     * @return array safe attribute names
     */
    public function getSafeAttributeNames(): array
    {
        return $this->getValidator()->getSafeAttributes($this->scenario);
    }

    /**
     * Returns an iterator for traversing the attributes in the model.
     * This method is required by the interface IteratorAggregate.
     * @return Traversable an iterator for traversing the items in the list.
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->getAttributes());
    }

    /**
     * Returns whether there is an element at the specified offset.
     * This method is required by the interface ArrayAccess.
     * @param mixed $offset the offset to check on
     * @return boolean
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * Returns the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     * @param mixed $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     * @param mixed $offset the offset to set element
     * @param mixed $value the element value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Unsets the element at the specified offset.
     * This method is required by the interface ArrayAccess.
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->$offset);
    }
}
