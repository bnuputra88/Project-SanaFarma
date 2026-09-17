<?php
/**
 * Lightweight reflection-based DI container compatible with CI3.
 * Resolves constructor type-hints recursively; CI_DB is injected from the CI instance.
 */
final class Container
{
    private $instances = [];
    private $bindings = [];

    public function __construct()
    {
        $this->instances[self::class] = $this;
    }

    public function instance(string $id, $object): void
    {
        $this->instances[$id] = $object;
    }

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            return $this->instances[$id] = ($this->bindings[$id])($this);
        }
        if (!class_exists($id)) {
            throw new RuntimeException("Container: class '$id' not found");
        }
        $ref = new ReflectionClass($id);
        $ctor = $ref->getConstructor();
        $args = [];
        if ($ctor) {
            foreach ($ctor->getParameters() as $p) {
                $type = $p->getType();
                if ($type && !$type->isBuiltin()) {
                    $args[] = $this->get($type->getName());
                } elseif ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                } else {
                    throw new RuntimeException("Container: cannot resolve parameter \${$p->getName()} of $id");
                }
            }
        }
        return $this->instances[$id] = $ref->newInstanceArgs($args);
    }
}
