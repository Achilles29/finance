<?php

/**
 * Resolves deployment configuration from the process environment.
 *
 * This class intentionally has no logging or output side effects. A snapshot
 * may be supplied by tests so they do not need to modify the process
 * environment.
 */
final class DeploymentConfig
{
    const ENCRYPTION_KEY = 'FINANCE_ENCRYPTION_KEY';
    const DB_HOST = 'FINANCE_DB_HOST';
    const DB_NAME = 'FINANCE_DB_NAME';
    const DB_USER = 'FINANCE_DB_USER';
    const DB_PASSWORD = 'FINANCE_DB_PASSWORD';

    /**
     * @var array
     */
    private $environment;

    /**
     * @param array|null $snapshot Explicit values are intended for tests.
     */
    public function __construct($snapshot = null)
    {
        if ($snapshot === null) {
            $snapshot = self::snapshotEnvironment();
        }

        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Deployment configuration snapshot must be an array.');
        }

        $this->environment = $snapshot;
    }

    /**
     * Build a resolver from a caller-owned environment snapshot.
     *
     * @param array $snapshot
     * @return self
     */
    public static function fromSnapshot($snapshot)
    {
        return new self($snapshot);
    }

    /**
     * Take a read-only snapshot of the requested process environment values.
     *
     * @param array|null $names
     * @return array
     */
    public static function snapshotEnvironment($names = null)
    {
        if ($names === null) {
            $names = self::productionRequiredNames();
        }

        $snapshot = array();
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $snapshot[$name] = $value;
            }
        }

        return $snapshot;
    }

    /**
     * Return the names covered by the production contract.
     *
     * @return array
     */
    public static function productionRequiredNames()
    {
        return array(
            self::ENCRYPTION_KEY,
            self::DB_HOST,
            self::DB_NAME,
            self::DB_USER,
            self::DB_PASSWORD,
        );
    }

    /**
     * Resolve an optional value without exposing or logging it.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->environment)
            ? $this->environment[$name]
            : $default;
    }

    /**
     * Resolve a required value.
     *
     * The exception deliberately contains no variable name or value because
     * callers may route exception text to an external error handler.
     *
     * @param string $name
     * @return mixed
     */
    public function required($name)
    {
        $value = $this->get($name);
        if (!$this->hasContent($value)) {
            throw new RuntimeException('Required deployment configuration is unavailable.');
        }

        return $value;
    }

    /**
     * Return missing or blank names without returning any associated values.
     *
     * @param array|null $names
     * @return array
     */
    public function missingRequired($names = null)
    {
        if ($names === null) {
            $names = self::productionRequiredNames();
        }

        $missing = array();
        foreach ($names as $name) {
            if (!$this->hasContent($this->get($name))) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Validate the production secret/configuration contract.
     *
     * @return bool
     */
    public function validateProductionSecretContract()
    {
        return count($this->missingRequired()) === 0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function hasContent($value)
    {
        return is_scalar($value) && trim((string)$value) !== '';
    }
}
