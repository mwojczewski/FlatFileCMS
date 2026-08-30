<?php

declare(strict_types=1);

namespace FlatFileCms\Core;

use Closure;
use RuntimeException;

final class Container
{
    /** @var array<class-string, Closure(self): object> */
    private array $factories = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /**
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(self): T $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException(\sprintf('Service "%s" is not registered.', $id));
        }

        $service = ($this->factories[$id])($this);
        $this->instances[$id] = $service;

        /** @var T */
        return $service;
    }
}
