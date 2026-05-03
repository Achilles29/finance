<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menu_model — Sidebar menu dinamis, sys_page registry, dan favorit
 */
class Menu_model extends CI_Model
{
    // ---------------------------------------------------------------
    // SIDEBAR
    // ---------------------------------------------------------------

    /**
     * Ambil seluruh menu sidebar, sudah difilter sesuai izin user.
     * Return struktur tree: parent → children.
     *
     * @param array  $perms       Cache izin user dari session
     * @param bool   $is_superadmin
     * @param string $type        'MAIN' atau 'MY'
     */
    public function get_sidebar_tree(array $perms, bool $is_superadmin, string $type = 'MAIN'): array
    {
        $this->db->select('m.id, m.parent_id, m.menu_code, m.menu_label, m.icon, m.url, m.page_id, m.sort_order, p.page_code');
        $this->db->from('sys_menu m');
        $this->db->join('sys_page p', 'p.id = m.page_id', 'left');
        $this->db->where('m.is_active', 1);
        $this->db->where('m.sidebar_type', $type);
        $this->db->order_by('m.sort_order', 'ASC');
        $rows = $this->db->get()->result_array();

        // Filter berdasarkan izin
        $allowed = [];
        foreach ($rows as $row) {
            // Tidak ada page_id = selalu tampil (heading/grup)
            if (empty($row['page_id'])) {
                $allowed[$row['id']] = $row;
                continue;
            }
            // Superadmin bypass
            if ($is_superadmin) {
                $allowed[$row['id']] = $row;
                continue;
            }
            // Cek can_view
            $code = $row['page_code'] ?? '';
            if (!empty($perms[$code]['can_view'])) {
                $allowed[$row['id']] = $row;
            }
        }

        return $this->_build_tree($allowed);
    }

    private function _build_tree(array $items, ?int $parent_id = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            $item_parent = $item['parent_id'] ? (int)$item['parent_id'] : null;
            if ($item_parent === $parent_id) {
                $item['children'] = $this->_build_tree($items, (int)$item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    // ---------------------------------------------------------------
    // FAVORITES
    // ---------------------------------------------------------------

    public function get_favorites(int $user_id): array
    {
        $this->db->select('f.id, f.menu_id, f.sort_order, m.menu_label, m.icon, m.url');
        $this->db->from('sys_sidebar_favorite f');
        $this->db->join('sys_menu m', 'm.id = f.menu_id');
        $this->db->where('f.user_id', $user_id);
        $this->db->where('m.is_active', 1);
        $this->db->order_by('f.sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    public function pin_favorite(int $user_id, int $menu_id): void
    {
        $max = $this->db->select_max('sort_order')->where('user_id', $user_id)
            ->get('sys_sidebar_favorite')->row();
        $next_order = (int)($max->sort_order ?? 0) + 1;

        // Insert or ignore jika sudah ada
        $exists = $this->db->get_where('sys_sidebar_favorite', [
            'user_id' => $user_id, 'menu_id' => $menu_id
        ])->num_rows();

        if (!$exists) {
            $this->db->insert('sys_sidebar_favorite', [
                'user_id'    => $user_id,
                'menu_id'    => $menu_id,
                'sort_order' => $next_order,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function unpin_favorite(int $user_id, int $menu_id): void
    {
        $this->db->where('user_id', $user_id)->where('menu_id', $menu_id)
            ->delete('sys_sidebar_favorite');
    }

    public function reorder_favorites(int $user_id, array $menu_ids): void
    {
        $order = 1;
        foreach ($menu_ids as $menu_id) {
            $this->db->where('user_id', $user_id)->where('menu_id', (int)$menu_id)
                ->update('sys_sidebar_favorite', ['sort_order' => $order++]);
        }
    }

    public function get_favorite_summary(): array
    {
        $total_favorite_rows = (int)$this->db->count_all('sys_sidebar_favorite');

        $active_users = (int)$this->db->distinct()
            ->select('user_id')
            ->from('sys_sidebar_favorite')
            ->count_all_results();

        $top_menus = $this->db
            ->select('m.menu_label, COUNT(f.id) AS total_pin', false)
            ->from('sys_sidebar_favorite f')
            ->join('sys_menu m', 'm.id = f.menu_id')
            ->group_by('m.id, m.menu_label')
            ->order_by('total_pin', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        $top_users = $this->db
            ->select('u.username, COUNT(f.id) AS total_pin', false)
            ->from('sys_sidebar_favorite f')
            ->join('auth_user u', 'u.id = f.user_id', 'left')
            ->group_by('f.user_id, u.username')
            ->order_by('total_pin', 'DESC')
            ->limit(5)
            ->get()
            ->result_array();

        return [
            'total_rows' => $total_favorite_rows,
            'active_users' => $active_users,
            'top_menus' => $top_menus,
            'top_users' => $top_users,
        ];
    }

    public function get_sidebar_tree_raw(string $type = 'MAIN'): array
    {
        $this->db->select('id, parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->order_by('is_active', 'DESC');
        $this->db->order_by('sort_order', 'ASC');
        $rows = $this->db->get()->result_array();

        $indexed = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $indexed[(int)$row['id']] = $row;
        }

        $tree = [];
        foreach ($indexed as $id => $item) {
            $parentId = !empty($item['parent_id']) ? (int)$item['parent_id'] : 0;
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
            } else {
                $tree[] = &$indexed[$id];
            }
        }

        return $tree;
    }

    public function get_sidebar_flat_raw(string $type = 'MAIN'): array
    {
        $this->db->select('id, parent_id, menu_code, menu_label, icon, url, page_id, sort_order, is_active, sidebar_type');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->order_by('parent_id IS NULL', 'DESC', false);
        $this->db->order_by('parent_id', 'ASC');
        $this->db->order_by('sort_order', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_menu_by_id(int $id): ?array
    {
        return $this->db->get_where('sys_menu', ['id' => $id])->row_array() ?: null;
    }

    public function get_parent_candidates(string $type, int $excludeId = 0): array
    {
        $this->db->select('id, menu_label, menu_code');
        $this->db->from('sys_menu');
        $this->db->where('sidebar_type', $type);
        $this->db->where('is_active', 1);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        $this->db->order_by('menu_label', 'ASC');
        return $this->db->get()->result_array();
    }

    public function create_sidebar_menu(array $data): int
    {
        $this->db->insert('sys_menu', $data);
        return (int)$this->db->insert_id();
    }

    public function update_sidebar_menu(int $id, array $data): bool
    {
        return $this->db->where('id', $id)->update('sys_menu', $data);
    }

    public function menu_code_exists(string $menuCode, int $excludeId = 0): bool
    {
        $this->db->from('sys_menu');
        $this->db->where('menu_code', $menuCode);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    public function save_sidebar_structure(string $type, array $tree): void
    {
        $order = 1;
        foreach ($tree as $node) {
            $this->persist_sidebar_node($type, $node, null, $order++);
        }
    }

    private function persist_sidebar_node(string $type, array $node, ?int $parentId, int $sortOrder): void
    {
        $menuId = (int)($node['id'] ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $this->db->where('id', $menuId)
            ->where('sidebar_type', $type)
            ->update('sys_menu', [
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
            ]);

        $childOrder = 1;
        $children = $node['children'] ?? [];
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($children as $child) {
            $this->persist_sidebar_node($type, (array)$child, $menuId, $childOrder++);
        }
    }

    // ---------------------------------------------------------------
    // SYS_PAGE REGISTRY
    // ---------------------------------------------------------------

    public function get_all_pages(): array
    {
        $this->db->where('is_active', 1);
        $this->db->order_by('module, page_name');
        return $this->db->get('sys_page')->result_array();
    }

    /**
     * Daftarkan halaman baru ke sys_page jika belum ada.
     * Dipanggil dari controller saat pertama kali diakses superadmin.
     */
    public function register_page(string $page_code, string $page_name, string $module, string $description = ''): void
    {
        $exists = $this->db->get_where('sys_page', ['page_code' => $page_code])->num_rows();
        if (!$exists) {
            $this->db->insert('sys_page', [
                'page_code'   => $page_code,
                'page_name'   => $page_name,
                'module'      => $module,
                'description' => $description,
                'is_active'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
