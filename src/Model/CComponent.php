<?php

namespace Yii1x\ActiveRecord\Model;

use Yii1x\ActiveRecord\Contracts\BehaviorInterface;
use Yii1x\ActiveRecord\ORMContext;

/**
 * This file contains the foundation classes for component-based and event-driven programming.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

/**
 * CComponent is the base class for all components.
 *
 * CComponent implements the protocol of defining, using properties and events.
 *
 * A property is defined by a getter method, and/or a setter method.
 * Properties can be accessed in the way like accessing normal object members.
 * Reading or writing a property will cause the invocation of the corresponding
 * getter or setter method, e.g
 * <pre>
 * $a=$component->text;     // equivalent to $a=$component->getText();
 * $component->text='abc';  // equivalent to $component->setText('abc');
 * </pre>
 * The signatures of getter and setter methods are as follows,
 * <pre>
 * // getter, defines a readable property 'text'
 * public function getText() { ... }
 * // setter, defines a writable property 'text' with $value to be set to the property
 * public function setText($value) { ... }
 * </pre>
 *
 * An event is defined by the presence of a method whose name starts with 'on'.
 * The event name is the method name. When an event is raised, functions
 * (called event handlers) attached to the event will be invoked automatically.
 *
 * An event can be raised by calling {@link raiseEvent} method, upon which
 * the attached event handlers will be invoked automatically in the order they
 * are attached to the event. Event handlers must have the following signature,
 * <pre>
 * function eventHandler($event) { ... }
 * </pre>
 * where $event includes parameters associated with the event.
 *
 * To attach an event handler to an event, see {@link attachEventHandler}.
 * You can also use the following syntax:
 * <pre>
 * $component->onClick=$callback;    // or $component->onClick->add($callback);
 * </pre>
 * where $callback refers to a valid PHP callback. Below we show some callback examples:
 * <pre>
 * 'handleOnClick'                   // handleOnClick() is a global function
 * array($object,'handleOnClick')    // using $object->handleOnClick()
 * array('Page','handleOnClick')     // using Page::handleOnClick()
 * </pre>
 *
 * To raise an event, use {@link raiseEvent}. The on-method defining an event is
 * commonly written like the following:
 * <pre>
 * public function onClick($event)
 * {
 *     $this->raiseEvent('onClick',$event);
 * }
 * </pre>
 * where <code>$event</code> is an instance of {@link CEvent} or its child class.
 * One can then raise the event by calling the on-method instead of {@link raiseEvent} directly.
 *
 * Both property names and event names are case-insensitive.
 *
 * CComponent supports behaviors. A behavior is an
 * instance of {@link IBehavior} which is attached to a component. The methods of
 * the behavior can be invoked as if they belong to the component. Multiple behaviors
 * can be attached to the same component.
 *
 * To attach a behavior to a component, call {@link attachBehavior}; and to detach the behavior
 * from the component, call {@link detachBehavior}.
 *
 * A behavior can be temporarily enabled or disabled by calling {@link enableBehavior}
 * or {@link disableBehavior}, respectively. When disabled, the behavior methods cannot
 * be invoked via the component.
 *
 * Starting from version 1.1.0, a behavior's properties (either its public member variables or
 * its properties defined via getters and/or setters) can be accessed through the component it
 * is attached to.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.base
 * @since 1.0
 */
class CComponent
{
    private $_e;
    private $_m;

    /**
     * Returns a property value, an event handler list or a behavior based on its name.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using the following syntax to read a property or obtain event handlers:
     * <pre>
     * $value=$component->propertyName;
     * $handlers=$component->eventName;
     * </pre>
     * @param string $name the property name or event name
     * @return mixed the property value, event handlers attached to the event, or the named behavior
     * @see __set
     */
    public function __get(string $name): mixed
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter))
            return $this->$getter();
        elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            // duplicating getEventHandlers() here for performance
            $name = strtolower($name);
            if (!isset($this->_e[$name]))
                $this->_e[$name] = new Collection();
            return $this->_e[$name];
        } elseif (isset($this->_m[$name]))
            return $this->_m[$name];
        elseif (is_array($this->_m)) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canGetProperty($name)))
                    return $object->$name;
            }
        }
        throw new \LogicException(sprintf('Property "%s::$%s" is not defined.', static::class, $name));
    }

    /**
     * Sets value of a component property.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using the following syntax to set a property or attach an event handler
     * <pre>
     * $this->propertyName=$value;
     * $this->eventName=$callback;
     * </pre>
     * @param string $name the property name or the event name
     * @param mixed $value the property value or callback
     * @see __get
     */
    public function __set(string $name, mixed $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter))
            return $this->$setter($value);
        elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            // duplicating getEventHandlers() here for performance
            $name = strtolower($name);
            if (!isset($this->_e[$name]))
                $this->_e[$name] = new Collection;
            return $this->_e[$name]->add($value);
        } elseif (is_array($this->_m)) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canSetProperty($name)))
                    return $object->$name = $value;
            }
        }

        throw match (true) {
            method_exists($this, 'get' . $name) => new \LogicException(
                sprintf('Property "%s::$%s" is read-only.', static::class, $name)
            ),
            default => new \LogicException(
                sprintf('Property "%s::$%s" is not defined.', static::class, $name)
            ),
        };
    }

    /**
     * Checks if a property value is null.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using isset() to detect if a component property is set or not.
     * @param string $name the property name or the event name
     * @return boolean
     */
    public function __isset(string $name): bool
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter))
            return $this->$getter() !== null;
        elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name)) {
            $name = strtolower($name);
            return isset($this->_e[$name]) && $this->_e[$name]->getCount();
        } elseif (is_array($this->_m)) {
            if (isset($this->_m[$name]))
                return true;
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && (property_exists($object, $name) || $object->canGetProperty($name)))
                    return $object->$name !== null;
            }
        }
        return false;
    }

    /**
     * Sets a component property to be null.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using unset() to set a component property to be null.
     * @param string $name the property name or the event name
     */
    public function __unset(string $name)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter))
            $this->$setter(null);
        elseif (strncasecmp($name, 'on', 2) === 0 && method_exists($this, $name))
            unset($this->_e[strtolower($name)]);
        elseif (is_array($this->_m)) {
            if (isset($this->_m[$name]))
                $this->detachBehavior($name);
            else {
                foreach ($this->_m as $object) {
                    if ($object->getEnabled()) {
                        if (property_exists($object, $name))
                            return $object->$name = null;
                        elseif ($object->canSetProperty($name))
                            return $object->$setter(null);
                    }
                }
            }
        } elseif (method_exists($this, 'get' . $name)) {
            throw new \RuntimeException('Property "%s::%s" is read-only.', static::class, $name);
        }
    }

    /**
     * Calls the named method which is not a class method.
     * Do not call this method. This is a PHP magic method that we override
     * to implement the behavior feature.
     * @param string $name the method name
     * @param array $parameters method parameters
     * @return mixed the method return value
     */
    public function __call(string $name, array $parameters = [])
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $object) {
                if ($object->getEnabled() && method_exists($object, $name))
                    return call_user_func_array(array($object, $name), $parameters);
            }
        }
        if (class_exists('Closure', false) && ($this->canGetProperty($name) || property_exists($this, $name)) && $this->$name instanceof \Closure)
            return call_user_func_array($this->$name, $parameters);
        throw new \RuntimeException(sprintf('%s and its behaviors do not have a method or closure named "%s".', static::class, $name));
    }

    /**
     * Returns the named behavior object.
     * The name 'asa' stands for 'as a'.
     * @param string $behavior the behavior name
     * @return BehaviorInterface|null the behavior object, or null if the behavior does not exist
     */
    public function asa(string $behavior): ?BehaviorInterface
    {
        return $this->_m[$behavior] ?? null;
    }

    /**
     * Attaches a list of behaviors to the component.
     * Each behavior is indexed by its name and should be an instance of
     * {@link IBehavior}, a string specifying the behavior class, or an
     * array of the following structure:
     * <pre>
     * array(
     *     'class'=>'path.to.BehaviorClass',
     *     'property1'=>'value1',
     *     'property2'=>'value2',
     * )
     * </pre>
     * @param array $behaviors list of behaviors to be attached to the component
     */
    public function attachBehaviors(array $behaviors): void
    {
        foreach ($behaviors as $name => $behavior)
            $this->attachBehavior($name, $behavior);
    }

    /**
     * Detaches all behaviors from the component.
     */
    public function detachBehaviors(): void
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $name => $behavior)
                $this->detachBehavior($name);
            $this->_m = null;
        }
    }

    /**
     * Attaches a behavior to this component.
     * This method will create the behavior object based on the given
     * configuration. After that, the behavior object will be initialized
     * by calling its {@link IBehavior::attach} method.
     * @param string $name the behavior's name. It should uniquely identify this behavior.
     * @param mixed $behavior the behavior configuration. This is passed as the first
     * parameter to {@link YiiBase::createComponent} to create the behavior object.
     * You can also pass an already created behavior instance (the new behavior will replace an already created
     * behavior with the same name, if it exists).
     * @return BehaviorInterface the behavior object
     */
    public function attachBehavior(string $name, mixed $behavior): BehaviorInterface
    {
        if (!($behavior instanceof BehaviorInterface)) {
            $behavior = BehaviorFactory::fromConfig($behavior);
        }
        $behavior->setEnabled(true);
        $behavior->attach($this);
        return $this->_m[$name] = $behavior;
    }

    /**
     * Detaches a behavior from the component.
     * The behavior's {@link IBehavior::detach} method will be invoked.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     * @return BehaviorInterface|null the detached behavior. Null if the behavior does not exist.
     */
    public function detachBehavior(string $name): ?BehaviorInterface
    {
        if (isset($this->_m[$name])) {
            $this->_m[$name]->detach($this);
            $behavior = $this->_m[$name];
            unset($this->_m[$name]);
            return $behavior;
        }
        return null;
    }

    /**
     * Enables all behaviors attached to this component.
     */
    public function enableBehaviors(): void
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $behavior)
                $behavior->setEnabled(true);
        }
    }

    /**
     * Disables all behaviors attached to this component.
     */
    public function disableBehaviors(): void
    {
        if ($this->_m !== null) {
            foreach ($this->_m as $behavior)
                $behavior->setEnabled(false);
        }
    }

    /**
     * Enables an attached behavior.
     * A behavior is only effective when it is enabled.
     * A behavior is enabled when first attached.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     */
    public function enableBehavior($name): void
    {
        if (isset($this->_m[$name]))
            $this->_m[$name]->setEnabled(true);
    }

    /**
     * Disables an attached behavior.
     * A behavior is only effective when it is enabled.
     * @param string $name the behavior's name. It uniquely identifies the behavior.
     */
    public function disableBehavior($name): void
    {
        if (isset($this->_m[$name]))
            $this->_m[$name]->setEnabled(false);
    }

    /**
     * Determines whether a property is defined.
     * A property is defined if there is a getter or setter method
     * defined in the class. Note, property names are case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property is defined
     * @see canGetProperty
     * @see canSetProperty
     */
    public function hasProperty(string $name): bool
    {
        return method_exists($this, 'get' . $name) || method_exists($this, 'set' . $name);
    }

    /**
     * Determines whether a property can be read.
     * A property can be read if the class has a getter method
     * for the property name. Note, property name is case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property can be read
     * @see canSetProperty
     */
    public function canGetProperty(string $name): bool
    {
        return method_exists($this, 'get' . $name);
    }

    /**
     * Determines whether a property can be set.
     * A property can be written if the class has a setter method
     * for the property name. Note, property name is case-insensitive.
     * @param string $name the property name
     * @return boolean whether the property can be written
     * @see canGetProperty
     */
    public function canSetProperty(string $name): bool
    {
        return method_exists($this, 'set' . $name);
    }

    /**
     * Determines whether an event is defined.
     * An event is defined if the class has a method named like 'onXXX'.
     * Note, event name is case-insensitive.
     * @param string $name the event name
     * @return boolean whether an event is defined
     */
    public function hasEvent(string $name): bool
    {
        return !strncasecmp($name, 'on', 2) && method_exists($this, $name);
    }

    /**
     * Checks whether the named event has attached handlers.
     * @param string $name the event name
     * @return boolean whether an event has been attached one or several handlers
     */
    public function hasEventHandler(string $name): bool
    {
        $name = strtolower($name);
        return isset($this->_e[$name]) && $this->_e[$name]->getCount() > 0;
    }

    /**
     * Returns the list of attached event handlers for an event.
     * @param string $name the event name
     * @return Collection list of attached event handlers for the event
     */
    public function getEventHandlers(string $name): Collection
    {
        if ($this->hasEvent($name)) {
            $name = strtolower($name);
            if (!isset($this->_e[$name]))
                $this->_e[$name] = new Collection();
            return $this->_e[$name];
        } else {
            throw new \InvalidArgumentException(sprintf('Event "%s::%s" is not defined.', static::class, $name));
        }
    }

    /**
     * Attaches an event handler to an event.
     *
     * An event handler must be a valid PHP callback, i.e., a string referring to
     * a global function name, or an array containing two elements with
     * the first element being an object and the second element a method name
     * of the object.
     *
     * An event handler must be defined with the following signature,
     * <pre>
     * function handlerName($event) {}
     * </pre>
     * where $event includes parameters associated with the event.
     *
     * This is a convenient method of attaching a handler to an event.
     * It is equivalent to the following code:
     * <pre>
     * $component->getEventHandlers($eventName)->add($eventHandler);
     * </pre>
     *
     * Using {@link getEventHandlers}, one can also specify the execution order
     * of multiple handlers attaching to the same event. For example:
     * <pre>
     * $component->getEventHandlers($eventName)->insertAt(0,$eventHandler);
     * </pre>
     * makes the handler to be invoked first.
     *
     * @param string $name the event name
     * @param callable $handler the event handler
     * @see detachEventHandler
     */
    public function attachEventHandler(string $name, callable $handler): void
    {
        $this->getEventHandlers($name)->add($handler);
    }

    /**
     * Detaches an existing event handler.
     * This method is the opposite of {@link attachEventHandler}.
     * @param string $name event name
     * @param callable $handler the event handler to be removed
     * @return boolean if the detachment process is successful
     * @see attachEventHandler
     */
    public function detachEventHandler(string $name, callable $handler): bool
    {
        if ($this->hasEventHandler($name)) {
            return $this->getEventHandlers($name)->remove($handler) !== false;
        } else {
            return false;
        }
    }

    /**
     * Raises an event.
     * This method represents the happening of an event. It invokes
     * all attached handlers for the event.
     * @param string $name the event name
     * @param Event $event the event parameter
     */
    public function raiseEvent(string $name, Event $event): void
    {
        $name = strtolower($name);
        if (isset($this->_e[$name])) {
            foreach ($this->_e[$name] as $handler) {
                if (is_string($handler))
                    call_user_func($handler, $event);
                elseif (is_callable($handler, true)) {
                    if (is_array($handler)) {
                        // an array: 0 - object, 1 - method name
                        list($object, $method) = $handler;
                        if (is_string($object))    // static method call
                            call_user_func($handler, $event);
                        elseif (method_exists($object, $method))
                            $object->$method($event);
                        else {
                            throw new \LogicException('Event "%s::%s" is attached with an invalid handler "%s".', static::class, $name, $handler[1]);
                        }
                    } else // PHP 5.3: anonymous function
                        call_user_func($handler, $event);
                } else {
                    throw new \LogicException('Event "%s::%s" is attached with an invalid handler "%s".', static::class, $name, gettype($handler));
                }
                // stop further handling if param.handled is set true
                if (($event instanceof Event) && $event->handled)
                    return;
            }
        } elseif (ORMContext::isDebug() && !$this->hasEvent($name)) {
            throw new \LogicException(
                sprintf('Event "%s::%s" is not defined.', static::class, $name)
            );
        }
    }
}
