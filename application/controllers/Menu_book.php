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
        redirect('menu_book/food/main_character');
    }

    public function food($page = null)
    {
        if ($page === null) {
            redirect('menu_book/food/main_character');
        }

        $allowed_pages = [
            'main_character' => 'menu_book/food/page_02_main_character',
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