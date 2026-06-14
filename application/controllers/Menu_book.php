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
            'main_character'        => 'menu_book/food/page_02_main_character',
            'nusantara_comfort'     => 'menu_book/food/page_03_nusantara_comfort',
            'flame_flavor'          => 'menu_book/food/page_04_flame_flavor',
            'asian_signatures'      => 'menu_book/food/page_05_asian_signatures',
            'bowl_spice_noodles'    => 'menu_book/food/page_06_bowl_spice_noodles',
            'bites_of_joy'          => 'menu_book/food/page_07_bites_of_joy',
            'dessert_collection'    => 'menu_book/food/page_08_dessert_collection',
            'extras_sides'          => 'menu_book/food/page_09_extras_sides',
        ];

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
