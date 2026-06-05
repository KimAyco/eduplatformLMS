<?php

function remember(string $key, callable $callback): mixed
{
    static $store = [];
    if (!array_key_exists($key, $store)) {
        $store[$key] = $callback();
    }
    return $store[$key];
}

function forgetCache(string $key): void
{
    static $store = [];
    unset($store[$key]);
}
