<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Security\SessionStore;

final class ArraySessionStore implements SessionStore
{
    public array $data = [];
    public int $regenerations = 0;

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->regenerations++;
    }
}
