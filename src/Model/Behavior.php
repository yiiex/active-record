<?php

namespace Yii1x\ActiveRecord\Model;

use ReflectionClass;
use Yii1x\ActiveRecord\Contracts\BehaviorInterface;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link https://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
class Behavior extends CComponent implements BehaviorInterface
{
    private bool $_enabled = false;
    private ?CComponent $_owner = null;

    /**
     * Declares events and the corresponding event handler methods.
     * The events are defined by the {@link owner} component, while the handler
     * methods by the behavior class. The handlers will be attached to the corresponding
     * events when the behavior is attached to the {@link owner} component; and they
     * will be detached from the events when the behavior is detached from the component.
     * Make sure you've declared handler method as public.
     * @return array events (array keys) and the corresponding event handler methods (array values).
     */
    public function events(): array
    {
        return [];
    }

    /**
     * Attaches the behavior object to the component.
     * The default implementation will set the {@link owner} property
     * and attach event handlers as declared in {@link events}.
     * This method will also set {@link enabled} to true.
     * Make sure you've declared handler as public and call the parent implementation if you override this method.
     * @param CComponent $owner the component that this behavior is to be attached to.
     */
    public function attach(CComponent $owner): void
    {
        $this->_enabled = true;
        $this->_owner = $owner;
        $this->_attachEventHandlers();
    }

    /**
     * Detaches the behavior object from the component.
     * The default implementation will unset the {@link owner} property
     * and detach event handlers declared in {@link events}.
     * This method will also set {@link enabled} to false.
     * Make sure you call the parent implementation if you override this method.
     * @param CComponent $owner the component that this behavior is to be detached from.
     */
    public function detach(CComponent $owner): void
    {
        foreach ($this->events() as $event => $handler) {
            $owner->detachEventHandler($event, [$this, $handler]);
        }
        $this->_owner = null;
        $this->_enabled = false;
    }

    public function getOwner(): ?CComponent
    {
        return $this->_owner;
    }

    public function getEnabled(): bool
    {
        return $this->_enabled;
    }

    /**
     * @param boolean $value whether this behavior is enabled
     */
    public function setEnabled(bool $value): static
    {
        if ($this->_enabled != $value && $this->_owner) {
            if ($value) {
                $this->_attachEventHandlers();
            } else {
                foreach ($this->events() as $event => $handler) {
                    $this->_owner->detachEventHandler($event, [$this, $handler]);
                }
            }
        }
        $this->_enabled = $value;
        return $this;
    }

    private function _attachEventHandlers(): void
    {
        $class = new ReflectionClass($this);
        foreach ($this->events() as $event => $handler) {
            if ($class->getMethod($handler)->isPublic())
                $this->_owner->attachEventHandler($event, array($this, $handler));
        }
    }
}
