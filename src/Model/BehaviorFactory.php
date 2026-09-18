<?php

namespace Yii1x\ActiveRecord\Model;

use InvalidArgumentException;
use ReflectionClass;

class BehaviorFactory
{
    public static function make(string $class, array $config = [], mixed ...$args): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class {$class} does not exist");
        }
        $reflection = new ReflectionClass($class);
        $constructorArgs = [...$args];
        $object = $constructorArgs ? $reflection->newInstanceArgs($constructorArgs) : $reflection->newInstance();
        foreach ($config as $property => $value) {
            if (!property_exists($object, $property)) {
                throw new InvalidArgumentException(sprintf('Property %s does not exist in class %s', $property, $class));
            }
            $object->$property = $value;
        }
        return $object;
    }

    /**
     * Метод для создания только из конфигурации
     */
    public static function fromConfig(array|string $config): object
    {
        if (is_string($config)) {
            if (!class_exists($config)) {
                throw new InvalidArgumentException("Class '{$config}' does not exist");
            }
            $config = ['class' => $config];
        }
        if (!isset($config['class'])) {
            throw new InvalidArgumentException('Configuration must contain "class" key');
        }
        $class = $config['class'];
        unset($config['class']);
        return self::make($class, $config);
    }
}
