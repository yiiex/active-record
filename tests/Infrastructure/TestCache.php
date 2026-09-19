<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * In-memory PSR-16 cache with call counters, used to assert caching behaviour.
 */
class TestCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public int $getCount = 0;
    public int $setCount = 0;
    public int $deleteCount = 0;
    public int $hits = 0;
    public int $misses = 0;

    /** @var string[] */
    public array $setKeys = [];
    /** @var string[] */
    public array $deletedKeys = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCount++;

        if (array_key_exists($key, $this->data)) {
            $this->hits++;

            return $this->data[$key];
        }

        $this->misses++;

        return $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->setCount++;
        $this->setKeys[] = $key;
        $this->data[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        $this->deleteCount++;
        $this->deletedKeys[] = $key;
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
