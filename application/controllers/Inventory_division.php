<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory_division extends Purchase
{
    public function index()
    {
        parent::stock_division_index();
    }

    public function opening()
    {
        parent::stock_opening_division_index();
    }

    public function daily()
    {
        parent::stock_division_daily_index();
    }

    public function movement()
    {
        parent::stock_division_movement_index();
    }

    public function material_matrix()
    {
        parent::stock_material_daily_matrix();
    }

    public function matrix_view()
    {
        parent::inventory_material_daily_index();
    }
}