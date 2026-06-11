<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_warehouse extends Purchase
{
    public function index()
    {
        parent::stock_warehouse_index();
    }

    public function opening()
    {
        parent::stock_opening_warehouse_index();
    }

    public function adjustment()
    {
        parent::stock_adjustment_warehouse_index();
    }

    public function daily()
    {
        parent::stock_warehouse_daily_index();
    }

    public function movement()
    {
        parent::stock_warehouse_movement_index();
    }

    public function daily_matrix()
    {
        parent::stock_warehouse_daily_matrix();
    }

    public function matrix_view()
    {
        parent::inventory_warehouse_daily_index();
    }

    public function lot()
    {
        parent::warehouse_lot_audit_index();
    }

    public function opname_monthly()
    {
        parent::stock_warehouse_opname_monthly();
    }
}