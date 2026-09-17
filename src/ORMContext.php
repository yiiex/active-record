<?php

namespace Yii1x\ActiveRecord;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Static transport layer for ActiveRecord services.
 *
 * Provides a single entry-point to the dependency container, logger, cache
 * and debug flag without hard-wiring them into the models.
 * Must be bootstrapped once per process (usually in the application bootstrap).
 *
 * <code>
 * ORMContext::bootstrap($container, $_ENV['DEBUG'] ?? false);
 * </code>
 *
 * @package Yii1x\ActiveRecord
 */
final class ORMContext
{
    protected static ?ContainerInterface $container = null;
    protected static bool $debug = false;
    protected static bool $profile = false;

    /**
     * Initialize the global ORM context.
     *
     * @param ContainerInterface $container PSR-11 container that provides
     * CacheInterface and LoggerInterface.
     * @param bool $debug Enable/disable debug logging.
     */
    public static function bootstrap(ContainerInterface $container, bool $debug = false, bool $profile = false): void
    {
        self::$container = $container;
        self::$debug = $debug;
        self::$profile = $profile;
    }

    public static function isBootstrapped(): bool
    {
        return (bool)self::$container;
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    public static function isProfile(): bool
    {
        return self::$profile;
    }

    public static function container(): ?ContainerInterface
    {
        if (is_null(self::$container)) {
            throw new \RuntimeException('Run bootstrap before using ORMContext');
        }
        return self::$container;
    }

    public static function db(string $name)
    {
        return self::container()->get($name);
    }

    public static function cache(): ?CacheInterface
    {
        return self::container()->get(CacheInterface::class);
    }

    public static function log(): ?LoggerInterface
    {
        return self::container()->get(LoggerInterface::class);
    }

    public static function dispatch(object $event): void
    {
        $eventDispatcher = self::container()->get(EventDispatcherInterface::class);
        $eventDispatcher->dispatch($event);
    }
}
