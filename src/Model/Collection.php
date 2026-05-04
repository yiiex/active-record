<?php

namespace Yii1x\ActiveRecord\Model;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;
use ArrayIterator;

class Collection implements IteratorAggregate, ArrayAccess, Countable
{
    private $_d = array();
    private $_c = 0;
    private $_readOnly = false;

    public function __construct($data = null, $readOnly = false)
    {
        if ($data !== null)
            $this->copyFrom($data);
        $this->setReadOnly($readOnly);
    }

    public function getReadOnly(): bool
    {
        return $this->_readOnly;
    }

    protected function setReadOnly($value)
    {
        $this->_readOnly = $value;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->_d);
    }

    public function count(): int
    {
        return $this->getCount();
    }

    public function getCount(): int
    {
        return $this->_c;
    }

    public function itemAt($index)
    {
        if (isset($this->_d[$index]))
            return $this->_d[$index];
        elseif ($index >= 0 && $index < $this->_c)
            return $this->_d[$index];
        else
            throw new \InvalidArgumentException(sprintf('Index %d does not exist', $index));
    }

    public function add($item): int
    {
        $this->insertAt($this->_c, $item);
        return $this->_c - 1;
    }

    public function insertAt($index, $item)
    {
        if (!$this->_readOnly) {
            if ($index === $this->_c)
                $this->_d[$this->_c++] = $item;
            elseif ($index >= 0 && $index < $this->_c) {
                array_splice($this->_d, $index, 0, array($item));
                $this->_c++;
            } else {
                throw new \InvalidArgumentException(sprintf('List index %d is out of bound.', $index));
            }
        } else {
            throw new \RuntimeException('The list is read only.');
        }
    }

    public function remove($item): false|int|string
    {
        if (($index = $this->indexOf($item)) >= 0) {
            $this->removeAt($index);
            return $index;
        } else
            return false;
    }

    public function removeAt($index)
    {
        if (!$this->_readOnly) {
            if ($index >= 0 && $index < $this->_c) {
                $this->_c--;
                if ($index === $this->_c)
                    return array_pop($this->_d);
                else {
                    $item = $this->_d[$index];
                    array_splice($this->_d, $index, 1);
                    return $item;
                }
            } else {
                throw new \InvalidArgumentException(sprintf('List index %d is out of bound.', $index));
            }
        }
        throw new \RuntimeException('The list is read only.');
    }

    public function clear(): void
    {
        for ($i = $this->_c - 1; $i >= 0; --$i)
            $this->removeAt($i);
    }

    public function contains($item): bool
    {
        return $this->indexOf($item) >= 0;
    }

    public function indexOf($item): false|int|string
    {
        if (($index = array_search($item, $this->_d, true)) !== false)
            return $index;
        else
            return -1;
    }

    public function toArray(): array
    {
        return $this->_d;
    }

    public function copyFrom(iterable $data): void
    {
        if ($this->_c > 0) {
            $this->clear();
        }
        if ($data instanceof self) {
            $data = $data->_d;
        }
        foreach ($data as $item) {
            $this->add($item);
        }
    }


    public function offsetExists($offset): bool
    {
        return ($offset >= 0 && $offset < $this->_c);
    }

    public function offsetGet($offset): mixed
    {
        return $this->itemAt($offset);
    }

    public function offsetSet($offset, $item): void
    {
        if ($offset === null || $offset === $this->_c)
            $this->insertAt($this->_c, $item);
        else {
            $this->removeAt($offset);
            $this->insertAt($offset, $item);
        }
    }

    public function offsetUnset($offset): void
    {
        $this->removeAt($offset);
    }
}
