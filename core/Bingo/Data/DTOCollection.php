<?php

declare(strict_types = 1);

namespace Bingo\Data;

use ArrayAccess;
use Countable;
use Iterator;

class DTOCollection implements ArrayAccess, Countable, Iterator
{
    private array $items    = [];
    private int   $position = 0;

    public function __construct(array $items = [], ?string $dtoClass = null)
    {
        foreach ($items as $item) {
            if ($dtoClass && is_array($item)) {
                $this->items[] = new $dtoClass($item);
            } elseif ($item instanceof DataTransferObject) {
                $this->items[] = $item;
            } else {
                $this->items[] = $item;
            }
        }
    }

    public static function make(array $items, string $dtoClass): self
    {
        return new self($items, $dtoClass);
    }

    public function add(DataTransferObject $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->items, $callback);
        return new self(array_values($filtered));
    }

    public function first(): ?DataTransferObject
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?DataTransferObject
    {
        return end($this->items) ?: null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            return $item instanceof DataTransferObject ? $item->toArray() : $item;
        }, $this->items);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->items);
    }

    // Iterator implementation
    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }
}
