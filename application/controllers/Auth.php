<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth — Controller login dan logout
 * Tidak extend MY_Controller karena halaman login harus bisa diakses tanpa login.
 */
class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->helper(['url', 'form']);
        $this->load->library(['session', 'form_validation']);
    }

    // ---------------------------------------------------------------
    // LOGIN
    // ---------------------------------------------------------------

    public function index()
    {
        // Jika sudah login, langsung ke dashboard
        if ($this->session->userdata('auth_user')) {
            redirect('dashboard');
        }

        $data = [
            'title'     => 'Login — Finance App',
            'error_msg' => $this->session->flashdata('login_error'),
        ];

        $this->load->view('auth/login', $data);
    }

    public function do_login()
    {
        if ($this->session->userdata('auth_user')) {
            redirect('dashboard');
        }

        // Validasi input
        $this->form_validation->set_rules('identifier', 'Username/Email', 'required|trim|min_length[3]|max_length[150]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]|max_length[72]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('login_error', validation_errors('<li>', '</li>'));
            redirect('login');
        }

        $identifier = $this->input->post('identifier', true);
        $password   = $this->input->post('password');   // tidak di-XSS untuk password

        $user = $this->Auth_model->attempt_login($identifier, $password);

        if (!$user) {
            $this->session->set_flashdata('login_error', 'Username / email atau password salah.');
            redirect('login');
        }

        // Load permissions dan cache ke session
        $perms = $this->Auth_model->load_permissions($user['id']);
        $is_superadmin = isset($perms['__superadmin__']);

        // Simpan ke session
        $this->session->set_userdata([
            'auth_user'    => array_merge($user, ['is_superadmin' => $is_superadmin]),
            'user_perms'   => $perms,
        ]);

        // Catat log
        $log_id = $this->Auth_model->log_login(
            $user['id'],
            $this->input->ip_address(),
            $this->input->user_agent()
        );
        $this->session->set_userdata('session_log_id', $log_id);

        // Redirect ke halaman sebelumnya jika ada
        $redirect_to = $this->session->flashdata('redirect_after_login');
        redirect($redirect_to ?: 'dashboard');
    }

    // ---------------------------------------------------------------
    // LOGOUT
    // ---------------------------------------------------------------

    public function logout()
    {
        $log_id = (int) $this->session->userdata('session_log_id');
        $this->Auth_model->log_logout($log_id);
        $this->session->sess_destroy();
        redirect('login');
    }
}
