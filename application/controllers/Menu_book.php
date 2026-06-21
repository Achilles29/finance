<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Menu_book extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
    }

    public function index()
    {
        $data = [
            'title' => 'Menu Book - NAMUA Coffee & Eatery',
        ];

        $this->load->view('menu_book/index', $data);
    }

    public function food($page = null)
    {
        if ($page === null) {
            redirect('menu_book');
        }

        $allowed_pages = [
            'main_character'      => 'menu_book/food/page_02_main_character',
            'nusantara_comfort'   => 'menu_book/food/page_03_nusantara_comfort',
            'flame_flavor'        => 'menu_book/food/page_04_flame_flavor',
            'asian_signatures'    => 'menu_book/food/page_05_asian_signatures',
            'bowl_spice_noodles'  => 'menu_book/food/page_06_bowl_spice_noodles',
            'bites_of_joy'        => 'menu_book/food/page_07_bites_of_joy',
            'dessert_collection'  => 'menu_book/food/page_08_dessert_collection',
            'extras_sides'        => 'menu_book/food/page_09_extras_sides',
        ];

        $this->_load_menu_page($page, $allowed_pages);
    }

    public function beverage($page = null)
    {
        if ($page === null) {
            redirect('menu_book');
        }

        $allowed_pages = [
            'namua_signatures'            => 'menu_book/beverage/page_10_namua_signatures',
            'house_masterpieces'          => 'menu_book/beverage/page_11_house_masterpieces',
            'coffee_atelier'              => 'menu_book/beverage/page_12_coffee_atelier',
            'cold_creamy_creations'       => 'menu_book/beverage/page_13_cold_creamy_creations',
            'spark_refresh'               => 'menu_book/beverage/page_14_spark_refresh',
            'blended_delights'            => 'menu_book/beverage/page_15_blended_delights',
            'tea_tradition'               => 'menu_book/beverage/page_16_tea_tradition',
            'sweet_scoops'                => 'menu_book/beverage/page_17_sweet_scoops',
            'extras_enhancers'            => 'menu_book/beverage/page_18_extras_enhancers',
        ];

        $this->_load_menu_page($page, $allowed_pages);
    }

    private function _load_menu_page($page, $allowed_pages)
    {
        if (!array_key_exists($page, $allowed_pages)) {
            show_404();
            return;
        }

        $data = [
            'title' => 'Menu Book - NAMUA Coffee & Eatery',
            'page'  => $page,
        ];

        $this->load->view($allowed_pages[$page], $data);
    }
}