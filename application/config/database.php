<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once dirname(__DIR__) . '/libraries/DeploymentConfig.php';
if (!isset($finance_deployment_config) || !($finance_deployment_config instanceof DeploymentConfig)) {
    $finance_deployment_config = new DeploymentConfig();
}

// Local settings: config/customer.json. Legacy environment/external JSON remains supported.
// Never fall back silently to a different server's hard-coded PHP configuration.
$active_group = 'default';
$query_builder = TRUE;
$db['default'] = array(
    'dsn' => '',
    'hostname' => $finance_deployment_config->get(DeploymentConfig::DB_SOCKET, '') ?: $finance_deployment_config->get(DeploymentConfig::DB_HOST, ''),
    'port' => (int)$finance_deployment_config->get(DeploymentConfig::DB_PORT, 3306),
    'username' => $finance_deployment_config->get(DeploymentConfig::DB_USER, ''),
    'password' => $finance_deployment_config->get(DeploymentConfig::DB_PASSWORD, ''),
    'database' => $finance_deployment_config->get(DeploymentConfig::DB_NAME, ''),
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_general_ci',
    'swap_pre' => '',
    'encrypt' => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => FALSE
);
