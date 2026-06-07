<?php

function &cacheStore(): array
{
    static $store = [];
    return $store;
}

function remember(string $key, callable $callback): mixed
{
    $store = &cacheStore();
    if (!array_key_exists($key, $store)) {
        $store[$key] = $callback();
    }
    return $store[$key];
}

function forgetCache(string $key): void
{
    $store = &cacheStore();
    unset($store[$key]);
}
