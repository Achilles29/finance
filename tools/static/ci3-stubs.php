<?php

/**
 * Static-only CodeIgniter 3 surface. This file is parsed by PHPStan as a stub;
 * it must never be loaded by the web application.
 */

class CI_Controller
{
    public function __construct() {}

    /** @return mixed */
    public function __get(string $name) {}

    /** @param mixed $value */
    public function __set(string $name, $value): void {}
}

class CI_Model
{
    public function __construct() {}

    /** @return mixed */
    public function __get(string $name) {}

    /** @param mixed $value */
    public function __set(string $name, $value): void {}
}

class CI_DB_query_builder {}

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

function uri_string(): string {}

/** @param mixed $var */
function html_escape($var, bool $double_encode = true): string {}

function validation_errors(string $prefix = '', string $suffix = ''): string {}

/** @param mixed $attributes @param mixed $hidden */
function form_open(string $action = '', $attributes = [], $hidden = []): string {}

function form_close(string $extra = ''): string {}

/** @param mixed $default */
function set_value(string $field, $default = '', bool $html_escape = true): string {}
