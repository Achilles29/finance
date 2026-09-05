<?php

/**
 * Static-only CodeIgniter 3 surface. This file is parsed by PHPStan as a stub;
 * it must never be loaded by the web application.
 */

class CI_Controller
{
    /** @return mixed */
    public function __get(string $name) {}

    /** @param mixed $value */
    public function __set(string $name, $value): void {}
}

class CI_Model
{
    /** @return mixed */
    public function __get(string $name) {}

    /** @param mixed $value */
    public function __set(string $name, $value): void {}
}

/** @return mixed */
function get_instance() {}

/** @return mixed */
function config_item(string $item) {}

/** @return mixed */
function show_error($message, int $status_code = 500, string $heading = 'An Error Was Encountered') {}

function log_message(string $level, string $message): void {}

function show_404(string $page = '', bool $log_error = true): void {}

function redirect(string $uri = '', string $method = 'auto', ?int $code = null): void {}

function site_url($uri = '', ?string $protocol = null): string {}

function base_url($uri = '', ?string $protocol = null): string {}

function current_url(): string {}
