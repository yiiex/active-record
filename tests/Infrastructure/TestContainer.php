<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\{LoggerInterface, NullLogger};

class TestContainer implements ContainerInterface
{
    private array $services = [];

    public function __construct()
    {
        $this->set(LoggerInterface::class, new NullLogger());
    }

    public function set($id, $value): static
    {
        $this->services[$id] = $value;
        return $this;
    }

    public function get(string $id): mixed
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        throw new class($id) extends \Exception implements NotFoundExceptionInterface {
            public function __construct(string $serviceId)
            {
                parent::__construct("Service '{$serviceId}' not found in test container");
            }
        };
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
