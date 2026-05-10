<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Users — Manajemen akun user (CRUD + role assignment + override izin)
 */
class Users extends MY_Controller
{
    const PAGE_INDEX   = 'auth.users.index';
    const PAGE_MANAGE  = 'auth.users.manage';
    const PAGE_PERMS   = 'auth.users.permissions';

    public function __construct()
    {
        parent::__construct();
        $this->load->model(['User_model', 'Role_model', 'Menu_model', 'Auth_model']);
        $this->load->library('form_validation');
    }

    // ---------------------------------------------------------------
    // INDEX
    // ---------------------------------------------------------------

    public function index()
    {
        $this->require_permission(self::PAGE_INDEX);

        $filter = [
            'search'    => $this->input->get('search', true),
            'is_active' => $this->input->get('is_active'),
        ];
        if ($filter['is_active'] === null || $filter['is_active'] === '') {
            unset($filter['is_active']);
        }

        $data = [
            'title'       => 'Manajemen User',
            'active_menu' => 'sys.users',
            'users'       => $this->User_model->get_all($filter),
            'filter'      => $filter,
        ];

        $this->render('users/index', $data);
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    public function create()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $data = [
            'title'       => 'Tambah User Baru',
            'active_menu' => 'sys.users',
            'all_roles'   => $this->Role_model->get_all(true),
            'employee_options' => $this->User_model->get_employee_options(),
            'form_action' => 'users/store',
            'edit_mode'   => false,
        ];

        $this->render('users/form', $data);
    }

    public function store()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $this->form_validation->set_rules('username', 'Username', 'required|trim|min_length[3]|max_length[60]|alpha_dash');
        $this->form_validation->set_rules('email',    'Email',    'trim|valid_email|max_length[150]');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[72]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('users/create');
        }

        $username = $this->input->post('username', true);
        $email    = trim($this->input->post('email', true));

        if ($this->User_model->is_username_taken($username)) {
            $this->session->set_flashdata('error', 'Username sudah dipakai.');
            redirect('users/create');
        }
        if ($email && $this->User_model->is_email_taken($email)) {
            $this->session->set_flashdata('error', 'Email sudah dipakai.');
            redirect('users/create');
        }

        $employeeId = (int)$this->input->post('employee_id', true);
        if ($employeeId > 0) {
            if (!$this->User_model->employee_exists($employeeId)) {
                $this->session->set_flashdata('error', 'Pegawai tidak valid.');
                redirect('users/create');
            }
            if ($this->User_model->is_employee_linked_to_other_user($employeeId)) {
                $this->session->set_flashdata('error', 'Pegawai tersebut sudah terhubung ke user lain.');
                redirect('users/create');
            }
        }

        $user_id = $this->User_model->create([
            'username' => $username,
            'email'    => $email ?: null,
            'password' => $this->input->post('password'),
            'employee_id' => $employeeId > 0 ? $employeeId : null,
        ]);

        if ($user_id) {
            $role_ids = $this->input->post('role_ids') ?: [];
            $this->User_model->sync_roles($user_id, $role_ids, $this->current_user['id']);

            $this->session->set_flashdata('success', 'User berhasil dibuat.');
        } else {
            $this->session->set_flashdata('error', 'Gagal membuat user.');
        }

        redirect('users');
    }

    // ---------------------------------------------------------------
    // EDIT / UPDATE
    // ---------------------------------------------------------------

    public function edit(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $user = $this->User_model->get_by_id($id);
        if (!$user) show_404();

        $data = [
            'title'        => 'Edit User: ' . htmlspecialchars($user['username']),
            'active_menu'  => 'sys.users',
            'all_roles'    => $this->Role_model->get_all(true),
            'employee_options' => $this->User_model->get_employee_options($id),
            'user_roles'   => array_column($this->User_model->get_user_roles($id), 'id'),
            'user'         => $user,
            'form_action'  => 'users/update/' . $id,
            'edit_mode'    => true,
        ];

        $this->render('users/form', $data);
    }

    public function update(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $user = $this->User_model->get_by_id($id);
        if (!$user) show_404();

        $this->form_validation->set_rules('email',    'Email',    'trim|valid_email|max_length[150]');
        $this->form_validation->set_rules('password', 'Password', 'min_length[8]|max_length[72]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('users/edit/' . $id);
        }

        $email = trim($this->input->post('email', true));
        if ($email && $this->User_model->is_email_taken($email, $id)) {
            $this->session->set_flashdata('error', 'Email sudah dipakai user lain.');
            redirect('users/edit/' . $id);
        }

        $employeeId = (int)$this->input->post('employee_id', true);
        if ($employeeId > 0) {
            if (!$this->User_model->employee_exists($employeeId)) {
                $this->session->set_flashdata('error', 'Pegawai tidak valid.');
                redirect('users/edit/' . $id);
            }
            if ($this->User_model->is_employee_linked_to_other_user($employeeId, $id)) {
                $this->session->set_flashdata('error', 'Pegawai tersebut sudah terhubung ke user lain.');
                redirect('users/edit/' . $id);
            }
        }

        $this->User_model->update($id, [
            'email'    => $email ?: null,
            'password' => $this->input->post('password'),
            'employee_id' => $employeeId > 0 ? $employeeId : null,
        ]);

        $role_ids = $this->input->post('role_ids') ?: [];
        $this->User_model->sync_roles($id, $role_ids, $this->current_user['id']);

        // Jika user yang diedit sedang login, refresh permissions
        if ($id === (int)$this->current_user['id']) {
            $this->Auth_model->refresh_permissions($id);
        }

        $this->session->set_flashdata('success', 'Data user berhasil diperbarui.');
        redirect('users');
    }

    // ---------------------------------------------------------------
    // TOGGLE AKTIF
    // ---------------------------------------------------------------

    public function toggle(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        // Cegah nonaktifkan diri sendiri
        if ($id === (int)$this->current_user['id']) {
            $this->session->set_flashdata('error', 'Tidak bisa menonaktifkan akun sendiri.');
            redirect('users');
        }

        $this->User_model->toggle_active($id);
        $this->session->set_flashdata('success', 'Status user berhasil diubah.');
        redirect('users');
    }

    // ---------------------------------------------------------------
    // OVERRIDE IZIN PER USER
    // ---------------------------------------------------------------

    public function permissions(int $id)
    {
        $this->require_permission(self::PAGE_PERMS, 'edit');

        $user = $this->User_model->get_by_id($id);
        if (!$user) show_404();

        // Ambil semua halaman aktif
        $this->load->model('Menu_model');
        $all_pages = $this->Menu_model->get_all_pages();

        // Kelompokkan per module
        $pages_by_module = [];
        foreach ($all_pages as $page) {
            $pages_by_module[$page['module']][] = $page;
        }

        // Ambil override yang sudah ada
        $overrides_raw = $this->User_model->get_user_overrides($id);
        $overrides = [];
        foreach ($overrides_raw as $ov) {
            $overrides[$ov['page_id']] = $ov;
        }

        $data = [
            'title'          => 'Override Izin: ' . htmlspecialchars($user['username']),
            'active_menu'    => 'sys.users',
            'user'           => $user,
            'user_roles'     => $this->User_model->get_user_roles($id),
            'pages_by_module'=> $pages_by_module,
            'overrides'      => $overrides,
        ];

        $this->render('users/permissions', $data);
    }

    public function save_override(int $id)
    {
        $this->require_permission(self::PAGE_PERMS, 'edit');

        $user = $this->User_model->get_by_id($id);
        if (!$user) show_404();

        $page_id       = (int) $this->input->post('page_id');
        $override_type = $this->input->post('override_type', true);
        $action        = $this->input->post('action', true); // 'save' atau 'delete'

        if (!in_array($override_type, ['GRANT', 'REVOKE'], true) && $action !== 'delete') {
            $this->session->set_flashdata('error', 'Tipe override tidak valid.');
            redirect('users/permissions/' . $id);
        }

        if ($action === 'delete') {
            $this->User_model->delete_override($id, $page_id);
            $this->session->set_flashdata('success', 'Override dihapus.');
        } else {
            $this->User_model->save_override($id, $page_id, [
                'override_type' => $override_type,
                'can_view'      => $this->input->post('can_view') ? 1 : 0,
                'can_create'    => $this->input->post('can_create') ? 1 : 0,
                'can_edit'      => $this->input->post('can_edit') ? 1 : 0,
                'can_delete'    => $this->input->post('can_delete') ? 1 : 0,
                'can_export'    => $this->input->post('can_export') ? 1 : 0,
                'reason'        => $this->input->post('reason', true),
            ], $this->current_user['id']);
            $this->session->set_flashdata('success', 'Override berhasil disimpan.');
        }

        // Refresh permission user yang di-override (jika dia sedang login — session lain tidak bisa direfresh real-time)
        // Untuk sekarang, refresh hanya jika itu diri sendiri
        if ($id === (int)$this->current_user['id']) {
            $this->Auth_model->refresh_permissions($id);
        }

        redirect('users/permissions/' . $id);
    }
}
