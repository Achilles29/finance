<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Role_model — CRUD role dan matrix izin per halaman
 */
class Role_model extends CI_Model
{
    // ---------------------------------------------------------------
    // READ
    // ---------------------------------------------------------------

    public function get_all(bool $active_only = false): array
    {
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('role_name');
        return $this->db->get('auth_role')->result_array();
    }

    public function get_by_id(int $id): ?array
    {
        return $this->db->get_where('auth_role', ['id' => $id])->row_array() ?: null;
    }

    /**
     * Ambil semua halaman yang aktif, dikelompokkan per module,
     * sekaligus sertakan izin role ini untuk setiap halaman.
     *
     * Return: ['MODULE' => [['page_code'=>..., 'can_view'=>0, ...], ...], ...]
     */
    public function get_pages_with_permissions(int $role_id): array
    {
        $this->db->select('p.id as page_id, p.page_code, p.page_name, p.module, p.description,
            COALESCE(rp.can_view,0)   as can_view,
            COALESCE(rp.can_create,0) as can_create,
            COALESCE(rp.can_edit,0)   as can_edit,
            COALESCE(rp.can_delete,0) as can_delete,
            COALESCE(rp.can_export,0) as can_export');
        $this->db->from('sys_page p');
        $this->db->join('auth_role_permission rp',
            'rp.page_id = p.id AND rp.role_id = ' . (int)$role_id, 'left');
        $this->db->where('p.is_active', 1);
        $this->db->order_by('p.module, p.page_name');
        $rows = $this->db->get()->result_array();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][] = $row;
        }
        return $grouped;
    }

    /**
     * Ambil hanya izin yang sudah disimpan untuk role ini
     * (untuk display di tabel cepat)
     */
    public function get_permissions(int $role_id): array
    {
        $this->db->select('rp.*, p.page_code, p.page_name, p.module');
        $this->db->from('auth_role_permission rp');
        $this->db->join('sys_page p', 'p.id = rp.page_id');
        $this->db->where('rp.role_id', $role_id);
        $this->db->order_by('p.module, p.page_name');
        return $this->db->get()->result_array();
    }

    // ---------------------------------------------------------------
    // CREATE
    // ---------------------------------------------------------------

    public function create(array $data): int|false
    {
        if ($this->_code_exists($data['role_code'])) return false;

        $this->db->insert('auth_role', [
            'role_code'   => strtoupper(preg_replace('/\s+/', '_', trim($data['role_code']))),
            'role_name'   => $data['role_name'],
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    // ---------------------------------------------------------------
    // UPDATE
    // ---------------------------------------------------------------

    public function update(int $id, array $data): bool
    {
        $this->db->where('id', $id);
        return $this->db->update('auth_role', [
            'role_name'   => $data['role_name'],
            'description' => $data['description'] ?? null,
            'is_active'   => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): bool
    {
        // Cegah hapus jika masih dipakai user
        $count = $this->db->where('role_id', $id)->count_all_results('auth_user_role');
        if ($count > 0) return false;

        $this->db->where('id', $id)->delete('auth_role_permission');
        $this->db->where('id', $id)->delete('auth_role');
        return true;
    }

    // ---------------------------------------------------------------
    // MATRIX IZIN — SAVE
    // ---------------------------------------------------------------

    /**
     * Simpan matrix izin untuk role.
     * Menerima array: [page_id => ['can_view'=>1, 'can_create'=>0, ...], ...]
     *
     * Hapus dulu semua izin role ini, lalu insert ulang (lebih simpel).
     */
    public function save_permissions(int $role_id, array $matrix): void
    {
        $this->db->trans_start();

        // Hapus izin lama role ini
        $this->db->where('role_id', $role_id)->delete('auth_role_permission');

        $now = date('Y-m-d H:i:s');
        foreach ($matrix as $page_id => $flags) {
            $page_id = (int)$page_id;
            if ($page_id <= 0) continue;

            // Hanya insert jika minimal can_view = 1
            $can_view = (int)($flags['can_view'] ?? 0);
            if ($can_view === 0) continue;

            $this->db->insert('auth_role_permission', [
                'role_id'    => $role_id,
                'page_id'    => $page_id,
                'can_view'   => 1,
                'can_create' => (int)($flags['can_create'] ?? 0),
                'can_edit'   => (int)($flags['can_edit'] ?? 0),
                'can_delete' => (int)($flags['can_delete'] ?? 0),
                'can_export' => (int)($flags['can_export'] ?? 0),
                'created_at' => $now,
            ]);
        }

        $this->db->trans_complete();
    }

    // ---------------------------------------------------------------
    // VALIDATION
    // ---------------------------------------------------------------

    private function _code_exists(string $code, int $exclude_id = 0): bool
    {
        $this->db->where('role_code', strtoupper($code));
        if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('auth_role') > 0;
    }
}
