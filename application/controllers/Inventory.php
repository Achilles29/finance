<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/Purchase.php';

class Inventory extends Purchase
{
    public function index()
    {
        redirect('inventory/stock/warehouse');
    }
}