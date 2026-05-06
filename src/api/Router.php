<?php

declare(strict_types=1);

/**
 * Simple API router for dispatch.
 * Maps HTTP method + path to handler functions.
 */

class ApiRouter
{
    private array $routes = [];

    public function post(string $path, callable $handler): self
    {
        $this->routes['POST'][$path] = $handler;
        return $this;
    }

    public function get(string $path, callable $handler): self
    {
        $this->routes['GET'][$path] = $handler;
        return $this;
    }

    public function put(string $path, callable $handler): self
    {
        $this->routes['PUT'][$path] = $handler;
        return $this;
    }

    public function delete(string $path, callable $handler): self
    {
        $this->routes['DELETE'][$path] = $handler;
        return $this;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);

        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_SLASHES);
        }
    }
}

function response(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function parseJsonInput(): ?array
{
    $input = file_get_contents('php://input') ?: '';
    if ($input === '') {
        return null;
    }

    return json_decode($input, associative: true);
}
