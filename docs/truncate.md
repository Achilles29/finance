## ABAIKAN FILE INI. hanya catatan saya

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE db_finance.aud_transaction_log;
TRUNCATE TABLE db_finance.fin_account_mutation_log;
TRUNCATE TABLE db_finance.inv_division_daily_rollup;
TRUNCATE TABLE db_finance.inv_division_monthly_opname;
TRUNCATE TABLE db_finance.inv_division_stock_balance;
TRUNCATE TABLE db_finance.inv_division_stock_opening_snapshot;

TRUNCATE TABLE db_finance.inv_stock_movement_log;
TRUNCATE TABLE db_finance.inv_warehouse_daily_rollup;
TRUNCATE TABLE db_finance.inv_warehouse_monthly_opname;
TRUNCATE TABLE db_finance.inv_warehouse_stock_balance;
TRUNCATE TABLE db_finance.inv_warehouse_stock_opening_snapshot;


SET FOREIGN_KEY_CHECKS = 1;;