<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User_model — CRUD user, assignment role, dan override izin
 */
class User_model extends CI_Model
{
    // ---------------------------------------------------------------
    // READ
    // ---------------------------------------------------------------

    public function get_all(array $filter = []): array
    {
        $this->db->select('u.id, u.username, u.email, u.is_active, u.last_login_at, u.created_at, u.employee_id');
        $this->db->from('auth_user u');

        if (!empty($filter['search'])) {
            $s = $this->db->escape_like_str($filter['search']);
            $this->db->group_start();
            $this->db->like('u.username', $s, 'both');
            $this->db->or_like('u.email', $s, 'both');
            $this->db->group_end();
        }

        if (isset($filter['is_active'])) {
            $this->db->where('u.is_active', (int)$filter['is_active']);
        }

        $this->db->order_by('u.username', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_by_id(int $id): ?array
    {
        $row = $this->db->get_where('auth_user', ['id' => $id])->row_array();
        if ($row) unset($row['password_hash']);
        return $row ?: null;
    }

    /**
     * Ambil daftar role yang dimiliki user
     */
    public function get_user_roles(int $user_id): array
    {
        $this->db->select('r.id, r.role_code, r.role_name');
        $this->db->from('auth_user_role ur');
        $this->db->join('auth_role r', 'r.id = ur.role_id');
        $this->db->where('ur.user_id', $user_id);
        $this->db->order_by('r.role_name');
        return $this->db->get()->result_array();
    }

    /**
     * Ambil semua override izin user ini
     */
    public function get_user_overrides(int $user_id): array
    {
        $this->db->select('o.*, p.page_code, p.page_name, p.module');
        $this->db->from('auth_user_permission_override o');
        $this->db->join('sys_page p', 'p.id = o.page_id');
        $this->db->where('o.user_id', $user_id);
        $this->db->order_by('p.module, p.page_name');
        return $this->db->get()->result_array();
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    /**
     * Buat user baru. Return insert_id atau false jika username/email sudah ada.
     */
    public function create(array $data): int|false
    {
        if ($this->_username_exists($data['username'])) return false;
        if (!empty($data['email']) && $this->_email_exists($data['email'])) return false;

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $this->db->insert('auth_user', [
            'username'      => $data['username'],
            'email'         => $data['email'] ?? null,
            'password_hash' => $hash,
            'employee_id'   => $data['employee_id'] ?? null,
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------

    public function update(int $id, array $data): bool
    {
        $update = [
            'email'      => $data['email'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Update password hanya jika diisi
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (isset($data['employee_id'])) {
            $update['employee_id'] = $data['employee_id'] ?: null;
        }

        $this->db->where('id', $id);
        return $this->db->update('auth_user', $update);
    }

    public function toggle_active(int $id): bool
    {
        $this->db->where('id', $id);
        $this->db->set('is_active', 'IF(is_active=1, 0, 1)', false);
        $this->db->set('updated_at', date('Y-m-d H:i:s'));
        return $this->db->update('auth_user');
    }

    // ---------------------------------------------------------------
    // ROLE ASSIGNMENT
    // ---------------------------------------------------------------

    /**
     * Sync role user: hapus yang lama, insert yang baru (dalam 1 transaksi).
     *
     * @param int   $user_id
     * @param array $role_ids   Array of role ID integers
     * @param int   $assigned_by
     */
    public function sync_roles(int $user_id, array $role_ids, int $assigned_by): void
    {
        $this->db->trans_start();

        $this->db->where('user_id', $user_id)->delete('auth_user_role');

        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_filter($role_ids)) as $role_id) {
            $this->db->insert('auth_user_role', [
                'user_id'     => $user_id,
                'role_id'     => (int)$role_id,
                'assigned_by' => $assigned_by,
                'assigned_at' => $now,
            ]);
        }

        $this->db->trans_complete();
    }

    // ---------------------------------------------------------------
    // PERMISSION OVERRIDES
    // ---------------------------------------------------------------

    /**
     * Simpan override izin untuk user.
     * Upsert — insert atau update jika sudah ada.
     */
    public function save_override(int $user_id, int $page_id, array $data, int $by): void
    {
        $payload = [
            'user_id'       => $user_id,
            'page_id'       => $page_id,
            'override_type' => $data['override_type'],
            'can_view'      => (int)($data['can_view'] ?? 0),
            'can_create'    => (int)($data['can_create'] ?? 0),
            'can_edit'      => (int)($data['can_edit'] ?? 0),
            'can_delete'    => (int)($data['can_delete'] ?? 0),
            'can_export'    => (int)($data['can_export'] ?? 0),
            'reason'        => $data['reason'] ?? null,
            'overridden_by' => $by,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $exists = $this->db->get_where('auth_user_permission_override', [
            'user_id' => $user_id, 'page_id' => $page_id
        ])->row();

        if ($exists) {
            $this->db->where('user_id', $user_id)->where('page_id', $page_id)
                ->update('auth_user_permission_override', $payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('auth_user_permission_override', $payload);
        }
    }

    public function delete_override(int $user_id, int $page_id): void
    {
        $this->db->where('user_id', $user_id)->where('page_id', $page_id)
            ->delete('auth_user_permission_override');
    }

    // ---------------------------------------------------------------
    // VALIDATION HELPERS
    // ---------------------------------------------------------------

    private function _username_exists(string $username, int $exclude_id = 0): bool
    {
        $this->db->where('username', $username);
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_user') > 0;
    }

    private function _email_exists(string $email, int $exclude_id = 0): bool
    {
        $this->db->where('email', $email);
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_user') > 0;
    }

    public function is_username_taken(string $username, int $exclude_id = 0): bool
    {
        return $this->_username_exists($username, $exclude_id);
    }

    public function is_email_taken(string $email, int $exclude_id = 0): bool
    {
        return $this->_email_exists($email, $exclude_id);
    }
}
