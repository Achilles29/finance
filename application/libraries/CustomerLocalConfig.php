<?php
declare(strict_types=1);

/** The sole mutable source-tree configuration exception. Never contains agent keys. */
final class CustomerLocalConfig
{
    public const PATH = 'config/customer.json';
    public const CONTRACT = 'FINANCE_CUSTOMER_LOCAL_V1';

    public static function present(string $root): bool
    {
        return file_exists($root.'/'.self::PATH) || is_link($root.'/'.self::PATH)
            || is_link($root.'/config');
    }

    /** Detect an extracted package even when its local configuration has been removed. */
    public static function packaged(string $root): bool
    {
        return file_exists($root.'/RELEASE-MANIFEST.json') || is_link($root.'/RELEASE-MANIFEST.json');
    }

    private static function securePath(string $path, bool $secret = false): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || realpath($path) !== $path || is_link($path)) {
            throw new RuntimeException('CUSTOMER_CONFIG_PATH_UNSAFE');
        }
        for ($part = $path; ; $part = dirname($part)) {
            $stat = @stat($part);
            if (!is_array($stat) || $stat['uid'] !== 0 || ($stat['mode'] & 0022) !== 0
                || ($part === $path && $secret && ($stat['mode'] & 0027) !== 0)) {
                throw new RuntimeException('CUSTOMER_CONFIG_PERMISSION_UNSAFE');
            }
            if ($part === dirname($part)) break;
        }
    }

    private static function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed)) throw new RuntimeException('CUSTOMER_CONFIG_UNKNOWN_FIELD');
    }

    private static function text($value): string
    {
        if (!is_string($value) || trim($value) === '' || preg_match('/[\x00-\x1f\x7f]/', $value)
            || stripos($value, 'REPLACE_') !== false) throw new RuntimeException('CUSTOMER_CONFIG_INCOMPLETE');
        return $value;
    }

    /** Strict, complete snapshot; an invalid local file must NEVER fall back to legacy DB settings. */
    public static function read(string $root): array
    {
        $root = rtrim($root, '/');
        self::securePath($root);
        $path = $root.'/'.self::PATH;
        self::securePath($path, true);
        if (!is_file($path) || !is_readable($path) || filesize($path) > 16384
            || (stat($path)['nlink'] ?? 0) !== 1) throw new RuntimeException('CUSTOMER_CONFIG_FILE_UNSAFE');
        try {
            $object = json_decode((string)file_get_contents($path), false, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) { throw new RuntimeException('CUSTOMER_CONFIG_JSON_INVALID'); }
        if (!$object instanceof stdClass) throw new RuntimeException('CUSTOMER_CONFIG_JSON_INVALID');
        $c = (array)$object;
        self::keys($c, ['schema','database','base_url','encryption_key','runtime']);
        if (($c['schema'] ?? null) !== 1 || !($c['database'] ?? null) instanceof stdClass
            || !($c['runtime'] ?? null) instanceof stdClass) throw new RuntimeException('CUSTOMER_CONFIG_INCOMPLETE');
        $db = (array)$c['database']; $runtime = (array)$c['runtime'];
        self::keys($db, ['host','port','socket','name','user','password']);
        self::keys($runtime, ['directory','session_cookie']);
        $host = self::text($db['host'] ?? null);
        if (!filter_var($host, FILTER_VALIDATE_IP) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9.-]{0,252}\z/D', $host) !== 1) {
            throw new RuntimeException('CUSTOMER_CONFIG_DATABASE_INVALID');
        }
        $name = self::text($db['name'] ?? null);
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name) !== 1) throw new RuntimeException('CUSTOMER_CONFIG_DATABASE_INVALID');
        $user = self::text($db['user'] ?? null); $password = self::text($db['password'] ?? null);
        $port = $db['port'] ?? 3306;
        if (!is_int($port) || $port < 1 || $port > 65535) throw new RuntimeException('CUSTOMER_CONFIG_DATABASE_INVALID');
        $socket = $db['socket'] ?? '';
        if (!is_string($socket)) throw new RuntimeException('CUSTOMER_CONFIG_DATABASE_INVALID');
        if ($host === 'localhost' && $socket === '') throw new RuntimeException('CUSTOMER_CONFIG_EXPLICIT_SOCKET_OR_TCP_REQUIRED');
        if ($socket !== '') {
            self::securePath(dirname($socket));
            if ($host !== 'localhost' || realpath($socket) !== $socket || is_link($socket)
                || fileowner($socket) !== 0 || (fileperms($socket) & 0170000) !== 0140000) {
                throw new RuntimeException('CUSTOMER_CONFIG_DATABASE_INVALID');
            }
        }
        $url = self::text($c['base_url'] ?? null); $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment']) || ($parts['path'] ?? '/') !== '/' || preg_match('/\s/', $url)) {
            throw new RuntimeException('CUSTOMER_CONFIG_HTTPS_ROOT_URL_REQUIRED');
        }
        $key = self::text($c['encryption_key'] ?? null);
        if (strlen($key) < 32 || strlen($key) > 256) throw new RuntimeException('CUSTOMER_CONFIG_ENCRYPTION_KEY_INVALID');
        $dir = self::text($runtime['directory'] ?? null);
        if (preg_match('~\A\.\./[A-Za-z0-9_-][A-Za-z0-9_.-]*\z~D', $dir) === 1) {
            $dir = dirname($root).'/'.substr($dir, 3);
        }
        if (preg_match('~\A/[A-Za-z0-9_./-]+\z~D', $dir) !== 1 || strpos($dir, '..') !== false
            || strpos($dir, '//') !== false || $dir === '/' || $dir === dirname($root)
            || strpos($dir.'/', $root.'/') === 0 || strpos($root.'/', $dir.'/') === 0) {
            throw new RuntimeException('CUSTOMER_CONFIG_RUNTIME_UNSAFE');
        }
        // A missing leaf can be provisioned by the installer, never by an HTTP request.
        self::securePath(file_exists($dir) || is_link($dir) ? $dir : dirname($dir));
        if (file_exists($dir) && !is_dir($dir)) throw new RuntimeException('CUSTOMER_CONFIG_RUNTIME_UNSAFE');
        $cookie = $runtime['session_cookie'] ?? 'finance_session';
        if (!is_string($cookie) || preg_match('/\A[A-Za-z][A-Za-z0-9_]{5,63}\z/D', $cookie) !== 1) {
            throw new RuntimeException('CUSTOMER_CONFIG_SESSION_INVALID');
        }
        return ['FINANCE_DB_HOST'=>$host,'FINANCE_DB_PORT'=>(string)$port,'FINANCE_DB_SOCKET'=>$socket,
            'FINANCE_DB_NAME'=>$name,'FINANCE_DB_USER'=>$user,'FINANCE_DB_PASSWORD'=>$password,
            'FINANCE_BASE_URL'=>rtrim($url,'/').'/', 'FINANCE_ENCRYPTION_KEY'=>$key,
            'FINANCE_SESSION_PATH'=>$dir.'/sessions','FINANCE_LOG_PATH'=>$dir.'/logs','FINANCE_CACHE_PATH'=>$dir.'/cache',
            'FINANCE_SESSION_COOKIE'=>$cookie];
    }

    public static function runtime(array $snapshot): string
    {
        return dirname($snapshot['FINANCE_SESSION_PATH']);
    }

    /** Caller passes BOTH sources independently, so env cannot mask a conflicting external file. */
    public static function merge(array $local, array ...$sources): array
    {
        foreach ($sources as $source) foreach ($source as $key=>$value) {
            if (array_key_exists($key, $local) && $local[$key] !== $value) {
                throw new RuntimeException('CUSTOMER_CONFIG_SOURCE_CONFLICT');
            }
        }
        return $local;
    }
}
