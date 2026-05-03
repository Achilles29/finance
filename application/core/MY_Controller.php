<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Base controller untuk semua halaman yang butuh login
 *
 * Semua controller kecuali Auth extend class ini.
 * Menangani: cek login, load izin ke session, helper cek izin per aksi.
 */
class MY_Controller extends CI_Controller
{
    /** Data user yang sedang login (dari session) */
    protected $current_user = [];

    /** Cache izin user: ['page_code' => ['view'=>1, 'create'=>0, ...]] */
    protected $user_perms = [];

    public function __construct()
    {
        parent::__construct();
        $this->_check_auth();
    }

    // ---------------------------------------------------------------
    // AUTH CHECK
    // ---------------------------------------------------------------

    private function _check_auth()
    {
        $user = $this->session->userdata('auth_user');

        if (empty($user)) {
            $this->session->set_flashdata('redirect_after_login', uri_string());
            redirect('login');
        }

        $this->current_user = $user;

        // Muat izin dari session (sudah di-cache saat login)
        $this->user_perms = $this->session->userdata('user_perms') ?? [];
    }

    // ---------------------------------------------------------------
    // PERMISSION HELPERS
    // ---------------------------------------------------------------

    /**
     * Apakah user superadmin? (bypass semua cek)
     */
    protected function is_superadmin(): bool
    {
        return !empty($this->current_user['is_superadmin']);
    }

    /**
     * Cek izin user untuk halaman + aksi tertentu.
     *
     * @param string $page_code  Contoh: 'auth.users.index'
     * @param string $action     Salah satu: view|create|edit|delete|export
     * @return bool
     */
    protected function can(string $page_code, string $action = 'view'): bool
    {
        if ($this->is_superadmin()) {
            return true;
        }

        return !empty($this->user_perms[$page_code]['can_' . $action]);
    }

    /**
     * Paksa cek izin — redirect ke 403 jika tidak punya akses.
     */
    protected function require_permission(string $page_code, string $action = 'view'): void
    {
        if (!$this->can($page_code, $action)) {
            show_error('Anda tidak memiliki izin untuk mengakses halaman ini.', 403, 'Akses Ditolak');
        }
    }

    // ---------------------------------------------------------------
    // VIEW LOADER HELPER
    // ---------------------------------------------------------------

    /**
     * Load view dengan layout (header + sidebar + content + footer).
     * Sidebar data (main tree, my tree, favorites) di-load otomatis di sini.
     *
     * @param string $view       Path view relatif dari application/views/
     * @param array  $data       Data yang dikirim ke view
     * @param bool   $return     Jika TRUE, kembalikan string bukan output langsung
     */
    protected function render(string $view, array $data = [], bool $return = false)
    {
        $data['current_user'] = $this->current_user;
        $data['user_perms']   = $this->user_perms;

        // Load sidebar data otomatis (kecuali sudah diset manual oleh controller)
        if (!isset($data['sidebar_main'])) {
            $this->load->model('Menu_model');
            $data['sidebar_main']      = $this->Menu_model->get_sidebar_tree(
                $this->user_perms, $this->is_superadmin(), 'MAIN'
            );
            $data['sidebar_my']        = $this->Menu_model->get_sidebar_tree(
                $this->user_perms, $this->is_superadmin(), 'MY'
            );
            $data['sidebar_favorites'] = $this->Menu_model->get_favorites(
                (int)($this->current_user['id'] ?? 0)
            );
        }

        $data['content_view'] = $view;
        $data['content_data'] = $data;

        return $this->load->view('layout/main', $data, $return);
    }
}
