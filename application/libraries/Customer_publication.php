<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Public content policy; never reads orders, stock, cost, or customer transactions. */
class Customer_publication
{
    public const TEMPLATE_KEY = 'customer.menu_book_template';
    public const TEMPLATES = ['legacy_namua', 'customer', 'disabled'];

    public static function template($value): string
    {
        // Existing installations retain their artwork until an admin explicitly changes it.
        if ($value === null) return 'legacy_namua';
        return is_string($value) && in_array($value, self::TEMPLATES, true) ? $value : 'disabled';
    }

    public static function public_url($value): string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) return '';
        $parts = parse_url($value);
        return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !isset($parts['user']) && !isset($parts['pass']) ? $value : '';
    }
}
