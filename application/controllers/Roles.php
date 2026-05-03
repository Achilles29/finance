<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Roles — Manajemen role dan matrix izin CRUD per halaman
 */
class Roles extends MY_Controller
{
    const PAGE_INDEX  = 'auth.roles.index';
    const PAGE_MANAGE = 'auth.roles.manage';
    const PAGE_MATRIX = 'auth.roles.matrix';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Role_model');
        $this->load->library('form_validation');
    }

    // ---------------------------------------------------------------
    // INDEX
    // ---------------------------------------------------------------

    public function index()
    {
        $this->require_permission(self::PAGE_INDEX);

        $data = [
            'title'       => 'Manajemen Role',
            'active_menu' => 'sys.roles',
            'roles'       => $this->Role_model->get_all(),
        ];

        $this->render('roles/index', $data);
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    public function create()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $data = [
            'title'       => 'Buat Role Baru',
            'active_menu' => 'sys.roles',
            'form_action' => 'roles/store',
            'edit_mode'   => false,
            'role'        => null,
        ];

        $this->render('roles/form', $data);
    }

    public function store()
    {
        $this->require_permission(self::PAGE_MANAGE, 'create');

        $this->form_validation->set_rules('role_code', 'Kode Role', 'required|trim|min_length[2]|max_length[50]|alpha_dash');
        $this->form_validation->set_rules('role_name', 'Nama Role', 'required|trim|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('roles/create');
        }

        $role_id = $this->Role_model->create([
            'role_code'   => $this->input->post('role_code', true),
            'role_name'   => $this->input->post('role_name', true),
            'description' => $this->input->post('description', true),
        ]);

        if ($role_id === false) {
            $this->session->set_flashdata('error', 'Kode role sudah dipakai.');
            redirect('roles/create');
        }

        $this->session->set_flashdata('success', 'Role berhasil dibuat. Sekarang atur matrix izinnya.');
        redirect('roles/matrix/' . $role_id);
    }

    // ---------------------------------------------------------------
    // EDIT / UPDATE
    // ---------------------------------------------------------------

    public function edit(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $data = [
            'title'       => 'Edit Role: ' . htmlspecialchars($role['role_name']),
            'active_menu' => 'sys.roles',
            'form_action' => 'roles/update/' . $id,
            'edit_mode'   => true,
            'role'        => $role,
        ];

        $this->render('roles/form', $data);
    }

    public function update(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $this->form_validation->set_rules('role_name',   'Nama Role',  'required|trim|min_length[2]|max_length[100]');
        $this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[255]');

        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('error', validation_errors('<li>', '</li>'));
            redirect('roles/edit/' . $id);
        }

        $this->Role_model->update($id, [
            'role_name'   => $this->input->post('role_name', true),
            'description' => $this->input->post('description', true),
            'is_active'   => $this->input->post('is_active') ? 1 : 0,
        ]);

        $this->session->set_flashdata('success', 'Role berhasil diperbarui.');
        redirect('roles');
    }

    // ---------------------------------------------------------------
    // DELETE
    // ---------------------------------------------------------------

    public function delete(int $id)
    {
        $this->require_permission(self::PAGE_MANAGE, 'delete');

        // Cegah hapus role system default
        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();
        if ($role['role_code'] === 'SUPERADMIN') {
            $this->session->set_flashdata('error', 'Role SUPERADMIN tidak bisa dihapus.');
            redirect('roles');
        }

        $result = $this->Role_model->delete($id);
        if (!$result) {
            $this->session->set_flashdata('error', 'Role tidak bisa dihapus karena masih dipakai user.');
        } else {
            $this->session->set_flashdata('success', 'Role berhasil dihapus.');
        }
        redirect('roles');
    }

    // ---------------------------------------------------------------
    // MATRIX IZIN
    // ---------------------------------------------------------------

    public function matrix(int $id)
    {
        $this->require_permission(self::PAGE_MATRIX, 'view');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        $data = [
            'title'            => 'Matrix Izin: ' . htmlspecialchars($role['role_name']),
            'active_menu'      => 'sys.roles',
            'role'             => $role,
            'pages_by_module'  => $this->Role_model->get_pages_with_permissions($id),
        ];

        $this->render('roles/matrix', $data);
    }

    public function save_matrix(int $id)
    {
        $this->require_permission(self::PAGE_MATRIX, 'edit');

        $role = $this->Role_model->get_by_id($id);
        if (!$role) show_404();

        // Input format: perms[page_id][can_view] = 1, perms[page_id][can_create] = 1, dll
        $matrix_raw = $this->input->post('perms') ?: [];

        // Sanitasi
        $matrix = [];
        foreach ($matrix_raw as $page_id => $flags) {
            $page_id = (int)$page_id;
            if ($page_id <= 0) continue;
            $matrix[$page_id] = [
                'can_view'   => isset($flags['can_view'])   ? 1 : 0,
                'can_create' => isset($flags['can_create']) ? 1 : 0,
                'can_edit'   => isset($flags['can_edit'])   ? 1 : 0,
                'can_delete' => isset($flags['can_delete']) ? 1 : 0,
                'can_export' => isset($flags['can_export']) ? 1 : 0,
            ];
        }

        $this->Role_model->save_permissions($id, $matrix);

        $this->session->set_flashdata('success', 'Matrix izin berhasil disimpan.');
        redirect('roles/matrix/' . $id);
    }
}
