<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once dirname(__DIR__) . '/libraries/DeploymentConfig.php';

/*
 * Staging keeps its local connection settings in a private file outside the
 * repository. Production never reads this fallback and must use the
 * DeploymentConfig secret contract.
 */
$finance_private_database_file = '/var/lib/finance-config/database.php';
$finance_environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
if (in_array($finance_environment, array('development', 'staging'), TRUE)
	&& is_file($finance_private_database_file)
	&& !is_link($finance_private_database_file)
) {
	$finance_private_database_stat = @stat($finance_private_database_file);
	if (!is_array($finance_private_database_stat)
		|| (($finance_private_database_stat['mode'] ?? 0) & 0007) !== 0
	) {
		throw new RuntimeException('Private database configuration is unavailable.');
	}
	include $finance_private_database_file;
	if (!isset($db['default']) || !is_array($db['default'])) {
		throw new RuntimeException('Private database configuration is unavailable.');
	}
	unset($finance_private_database_stat, $finance_private_database_file, $finance_environment);
	return;
}

if (!isset($finance_deployment_config) || !($finance_deployment_config instanceof DeploymentConfig)) {
	$finance_deployment_config = new DeploymentConfig();
}

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
	'dsn' => '',
	'hostname' => $finance_deployment_config->get(DeploymentConfig::DB_HOST, ''),
	'username' => $finance_deployment_config->get(DeploymentConfig::DB_USER, ''),
	'password' => $finance_deployment_config->get(DeploymentConfig::DB_PASSWORD, ''),
	'database' => $finance_deployment_config->get(DeploymentConfig::DB_NAME, ''),
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => ($finance_environment !== 'production'),
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

unset($finance_private_database_file, $finance_environment);
