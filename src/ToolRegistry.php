<?php

declare(strict_types=1);

namespace App;

final class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function __construct(array $config)
    {
        $map = $config['tools'] ?? [];
        foreach ($map as $id => $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }
            $tool = new $class();
            if (!$tool instanceof ToolInterface) {
                continue;
            }
            $this->tools[$tool->id()] = $tool;
        }
    }

    public function get(string $id): ?ToolInterface
    {
        return $this->tools[$id] ?? null;
    }

    /** @return list<ToolInterface> */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /** @return list<ToolInterface> */
    public function byCategory(string $category): array
    {
        return array_values(array_filter(
            $this->tools,
            static fn (ToolInterface $t) => $t->category() === $category
        ));
    }
}
