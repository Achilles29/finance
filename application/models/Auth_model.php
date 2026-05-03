<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth_model — Login, logout, dan load permission ke session
 */
class Auth_model extends CI_Model
{
    // ---------------------------------------------------------------
    // LOGIN
    // ---------------------------------------------------------------

    /**
     * Cari user aktif berdasarkan username ATAU email.
     * Verifikasi password dengan bcrypt.
     *
     * @return array|false  Data user jika cocok, false jika gagal
     */
    public function attempt_login(string $identifier, string $password)
    {
        $this->db->select('u.id, u.employee_id, u.username, u.email, u.password_hash, u.is_active');
        $this->db->from('auth_user u');
        $this->db->where('(u.username = ' . $this->db->escape($identifier)
            . ' OR u.email = ' . $this->db->escape($identifier) . ')');
        $this->db->where('u.is_active', 1);
        $row = $this->db->get()->row_array();

        if (empty($row)) {
            return false;
        }

        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }

        // Cek apakah hash perlu di-rehash (cost naik)
        if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->db->where('id', $row['id']);
            $this->db->update('auth_user', ['password_hash' => $new_hash, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        // Update last login
        $this->db->where('id', $row['id']);
        $this->db->update('auth_user', ['last_login_at' => date('Y-m-d H:i:s')]);

        unset($row['password_hash']);
        return $row;
    }

    /**
     * Simpan log sesi login ke auth_session_log.
     * Kembalikan session_log_id agar bisa dipakai saat logout.
     */
    public function log_login(int $user_id, string $ip, string $user_agent = ''): int
    {
        $this->db->insert('auth_session_log', [
            'user_id'    => $user_id,
            'ip_address' => $ip,
            'user_agent' => substr($user_agent, 0, 255),
            'login_at'   => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insert_id();
    }

    /**
     * Catat waktu logout ke auth_session_log.
     */
    public function log_logout(int $session_log_id): void
    {
        if ($session_log_id > 0) {
            $this->db->where('id', $session_log_id);
            $this->db->update('auth_session_log', ['logout_at' => date('Y-m-d H:i:s')]);
        }
    }

    // ---------------------------------------------------------------
    // LOAD PERMISSIONS
    // ---------------------------------------------------------------

    /**
     * Hitung izin final user, gabungkan semua role lalu terapkan override.
     * Hasilnya di-cache ke session agar tidak query DB tiap request.
     *
     * Return format: ['page_code' => ['can_view'=>1, 'can_create'=>0, ...], ...]
     */
    public function load_permissions(int $user_id): array
    {
        // 1. Cek apakah user SUPERADMIN
        $is_superadmin = $this->_has_superadmin_role($user_id);

        if ($is_superadmin) {
            return ['__superadmin__' => true];
        }

        // 2. Gabungkan izin dari semua role (OR)
        $perms = $this->_get_role_permissions($user_id);

        // 3. Terapkan override GRANT
        $grants = $this->_get_overrides($user_id, 'GRANT');
        foreach ($grants as $page_code => $flags) {
            if (!isset($perms[$page_code])) {
                $perms[$page_code] = ['can_view'=>0,'can_create'=>0,'can_edit'=>0,'can_delete'=>0,'can_export'=>0];
            }
            foreach ($flags as $k => $v) {
                if ($v) $perms[$page_code][$k] = 1;
            }
        }

        // 4. Terapkan override REVOKE
        $revokes = $this->_get_overrides($user_id, 'REVOKE');
        foreach ($revokes as $page_code => $flags) {
            if (isset($perms[$page_code])) {
                foreach ($flags as $k => $v) {
                    if ($v) $perms[$page_code][$k] = 0;
                }
            }
        }

        return $perms;
    }

    private function _has_superadmin_role(int $user_id): bool
    {
        $this->db->select('1');
        $this->db->from('auth_user_role ur');
        $this->db->join('auth_role r', 'r.id = ur.role_id');
        $this->db->where('ur.user_id', $user_id);
        $this->db->where('r.role_code', 'SUPERADMIN');
        $this->db->where('r.is_active', 1);
        $this->db->limit(1);
        return (bool) $this->db->get()->num_rows();
    }

    private function _get_role_permissions(int $user_id): array
    {
        $this->db->select('p.page_code, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete, rp.can_export');
        $this->db->from('auth_user_role ur');
        $this->db->join('auth_role_permission rp', 'rp.role_id = ur.role_id');
        $this->db->join('sys_page p', 'p.id = rp.page_id');
        $this->db->where('ur.user_id', $user_id);
        $this->db->where('p.is_active', 1);
        $rows = $this->db->get()->result_array();

        $perms = [];
        foreach ($rows as $row) {
            $code = $row['page_code'];
            if (!isset($perms[$code])) {
                $perms[$code] = ['can_view'=>0,'can_create'=>0,'can_edit'=>0,'can_delete'=>0,'can_export'=>0];
            }
            // OR — jika salah satu role punya izin, user punya izin
            $perms[$code]['can_view']   = max($perms[$code]['can_view'],   (int)$row['can_view']);
            $perms[$code]['can_create'] = max($perms[$code]['can_create'], (int)$row['can_create']);
            $perms[$code]['can_edit']   = max($perms[$code]['can_edit'],   (int)$row['can_edit']);
            $perms[$code]['can_delete'] = max($perms[$code]['can_delete'], (int)$row['can_delete']);
            $perms[$code]['can_export'] = max($perms[$code]['can_export'], (int)$row['can_export']);
        }
        return $perms;
    }

    private function _get_overrides(int $user_id, string $type): array
    {
        $this->db->select('p.page_code, o.can_view, o.can_create, o.can_edit, o.can_delete, o.can_export');
        $this->db->from('auth_user_permission_override o');
        $this->db->join('sys_page p', 'p.id = o.page_id');
        $this->db->where('o.user_id', $user_id);
        $this->db->where('o.override_type', $type);
        $this->db->where('p.is_active', 1);
        $rows = $this->db->get()->result_array();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['page_code']] = [
                'can_view'   => (int)$row['can_view'],
                'can_create' => (int)$row['can_create'],
                'can_edit'   => (int)$row['can_edit'],
                'can_delete' => (int)$row['can_delete'],
                'can_export' => (int)$row['can_export'],
            ];
        }
        return $result;
    }

    /**
     * Reload permission dari DB dan update session cache.
     * Dipanggil setelah perubahan role/override.
     */
    public function refresh_permissions(int $user_id): void
    {
        $perms = $this->load_permissions($user_id);
        $this->session->set_userdata('user_perms', $perms);

        // Set flag is_superadmin di session user data
        $auth_user = $this->session->userdata('auth_user') ?? [];
        $auth_user['is_superadmin'] = isset($perms['__superadmin__']);
        $this->session->set_userdata('auth_user', $auth_user);
    }
}
