<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sidebar — AJAX endpoint untuk pin/unpin favorit
 */
class Sidebar extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Menu_model');
    }

    public function pin()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $menu_id = (int) $this->input->post('menu_id');
        if ($menu_id > 0) {
            $this->Menu_model->pin_favorite($this->current_user['id'], $menu_id);
        }
        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    public function unpin()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $menu_id = (int) $this->input->post('menu_id');
        if ($menu_id > 0) {
            $this->Menu_model->unpin_favorite($this->current_user['id'], $menu_id);
        }
        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    public function reorder()
    {
        if (!$this->input->is_ajax_request()) show_404();
        $ids = $this->input->post('ids') ?: [];
        if (!empty($ids)) {
            $this->Menu_model->reorder_favorites($this->current_user['id'], $ids);
        }
        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    public function manage()
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengatur struktur sidebar.', 403, 'Akses Ditolak');
            return;
        }

        $type = strtoupper((string)$this->input->get('type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $editId = (int)$this->input->get('edit_id', true);
        $editMenu = null;
        if ($editId > 0) {
            $candidate = $this->Menu_model->get_menu_by_id($editId);
            if ($candidate && ($candidate['sidebar_type'] ?? '') === $type) {
                $editMenu = $candidate;
            }
        }

        $data = [
            'title' => 'Manajemen Sidebar (Drag & Drop)',
            'active_menu' => 'grp.system',
            'sidebar_type_selected' => $type,
            'sidebar_tree_raw' => $this->Menu_model->get_sidebar_tree_raw($type),
            'sidebar_tree_preview' => $this->build_sidebar_preview_tree($type),
            'sidebar_flat_raw' => $this->Menu_model->get_sidebar_flat_raw($type),
            'parent_candidates' => $this->Menu_model->get_parent_candidates($type, $editId),
            'edit_menu' => $editMenu,
            'favorite_summary' => $this->Menu_model->get_favorite_summary(),
        ];

        $this->render('sidebar/manage', $data);
    }

    public function save_structure()
    {
        if (!$this->is_superadmin()) {
            $this->output->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Forbidden']));
            return;
        }

        if (!$this->input->is_ajax_request()) {
            show_404();
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $treeJson = (string)$this->input->post('tree_json', false);
        $tree = json_decode($treeJson, true);
        if (!is_array($tree)) {
            $this->output->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Payload tidak valid']));
            return;
        }

        $this->db->trans_start();
        $this->Menu_model->save_sidebar_structure($type, $tree);
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->output->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode(['ok' => false, 'message' => 'Gagal menyimpan struktur sidebar']));
            return;
        }

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['ok' => true]));
    }

    private function build_sidebar_preview_tree(string $type): array
    {
        $tree = $this->Menu_model->get_sidebar_tree_raw($type);
        if ($type !== 'MAIN') {
            return $tree;
        }
        return $this->regroup_master_preview_tree($tree);
    }

    private function regroup_master_preview_tree(array $tree): array
    {
        foreach ($tree as &$item) {
            if (($item['menu_code'] ?? '') === 'grp.master' && !empty($item['children'])) {
                $item['children'] = $this->regroup_master_children_preview((array)$item['children']);
            }
        }
        unset($item);
        return $tree;
    }

    private function regroup_master_children_preview(array $children): array
    {
        $buckets = [
            'product' => [
                'label' => 'Produk & Extra',
                'icon' => 'ri-store-2-line',
                'match' => [
                    'master.product.division', 'master.product.classification', 'master.product.category',
                    'master.product_division', 'master.product_classification', 'master.product_category',
                    'master.product', 'master.extra', 'master.extra_group', 'master.product.extra',
                    'master.product_extra_map', 'master.extra.group', 'master.extra-group',
                ],
            ],
            'inventory' => [
                'label' => 'Item & Bahan',
                'icon' => 'ri-flask-line',
                'match' => [
                    'master.uom', 'master.operational_division', 'master.item_category',
                    'master.material', 'master.item', 'master.component_category', 'master.component', 'master.vendor',
                ],
            ],
            'relation' => [
                'label' => 'Relasi & Formula',
                'icon' => 'ri-links-line',
                'match' => [
                    'master.product.recipe', 'master.component.formula', 'master.product_recipe',
                    'master.component_formula', 'master.relation',
                ],
            ],
            'config' => [
                'label' => 'Konfigurasi',
                'icon' => 'ri-settings-3-line',
                'match' => [
                    'master.variable.cost.default', 'master.variable_cost_default',
                ],
            ],
        ];

        $grouped = [
            'product' => [],
            'inventory' => [],
            'relation' => [],
            'config' => [],
            'other' => [],
        ];

        foreach ($children as $child) {
            $code = (string)($child['menu_code'] ?? '');
            $placed = false;
            foreach ($buckets as $bucketKey => $bucket) {
                foreach ($bucket['match'] as $needle) {
                    if (strpos($code, $needle) === 0) {
                        $grouped[$bucketKey][] = $child;
                        $placed = true;
                        break 2;
                    }
                }
            }
            if (!$placed) {
                $grouped['other'][] = $child;
            }
        }

        $result = [];
        $order = 1;
        foreach (['product', 'inventory', 'relation', 'config'] as $bucketKey) {
            if (empty($grouped[$bucketKey])) {
                continue;
            }
            $result[] = [
                'id' => -1000 - $order,
                'parent_id' => null,
                'menu_code' => 'master.group.' . $bucketKey,
                'menu_label' => $buckets[$bucketKey]['label'],
                'icon' => $buckets[$bucketKey]['icon'],
                'url' => null,
                'is_virtual' => 1,
                'children' => $grouped[$bucketKey],
            ];
            $order++;
        }

        foreach ($grouped['other'] as $other) {
            $result[] = $other;
        }

        return $result;
    }

    public function menu_store()
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = 'MAIN';
        }

        $menuCode = trim((string)$this->input->post('menu_code', true));
        $menuLabel = trim((string)$this->input->post('menu_label', true));
        $icon = trim((string)$this->input->post('icon', true));
        $url = trim((string)$this->input->post('url', true));
        $parentId = (int)$this->input->post('parent_id', true);
        $sortOrder = (int)$this->input->post('sort_order', true);

        if ($menuCode === '' || $menuLabel === '') {
            $this->session->set_flashdata('error', 'Kode menu dan nama menu wajib diisi.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        if ($this->Menu_model->menu_code_exists($menuCode)) {
            $this->session->set_flashdata('error', 'Kode menu sudah digunakan.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        $this->Menu_model->create_sidebar_menu([
            'parent_id' => $parentId > 0 ? $parentId : null,
            'menu_code' => $menuCode,
            'menu_label' => $menuLabel,
            'icon' => $icon !== '' ? $icon : 'ri-circle-line',
            'url' => $url !== '' ? $url : null,
            'page_id' => null,
            'sort_order' => $sortOrder > 0 ? $sortOrder : 999,
            'is_active' => 1,
            'sidebar_type' => $type,
        ]);

        $this->session->set_flashdata('success', 'Menu sidebar berhasil ditambahkan.');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }

    public function menu_update(int $id)
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }

        $row = $this->Menu_model->get_menu_by_id($id);
        if (!$row) {
            show_404();
            return;
        }

        $type = strtoupper((string)$this->input->post('sidebar_type', true));
        if (!in_array($type, ['MAIN', 'MY'], true)) {
            $type = (string)$row['sidebar_type'];
        }

        $menuCode = trim((string)$this->input->post('menu_code', true));
        $menuLabel = trim((string)$this->input->post('menu_label', true));
        $icon = trim((string)$this->input->post('icon', true));
        $url = trim((string)$this->input->post('url', true));
        $parentId = (int)$this->input->post('parent_id', true);
        $sortOrder = (int)$this->input->post('sort_order', true);
        $isActive = $this->input->post('is_active') ? 1 : 0;

        if ($menuCode === '' || $menuLabel === '') {
            $this->session->set_flashdata('error', 'Kode menu dan nama menu wajib diisi.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data&edit_id=' . $id);
            return;
        }

        if ($parentId === $id) {
            $parentId = 0;
        }

        if ($this->Menu_model->menu_code_exists($menuCode, $id)) {
            $this->session->set_flashdata('error', 'Kode menu sudah digunakan menu lain.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data&edit_id=' . $id);
            return;
        }

        $this->Menu_model->update_sidebar_menu($id, [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'menu_code' => $menuCode,
            'menu_label' => $menuLabel,
            'icon' => $icon !== '' ? $icon : 'ri-circle-line',
            'url' => $url !== '' ? $url : null,
            'sort_order' => $sortOrder > 0 ? $sortOrder : 999,
            'is_active' => $isActive,
            'sidebar_type' => $type,
        ]);

        $this->session->set_flashdata('success', 'Menu sidebar berhasil diperbarui.');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }

    public function menu_delete(int $id)
    {
        if (!$this->is_superadmin()) {
            show_error('Hanya superadmin yang dapat mengelola sidebar.', 403, 'Akses Ditolak');
            return;
        }

        $row = $this->Menu_model->get_menu_by_id($id);
        if (!$row) {
            show_404();
            return;
        }

        $type = (string)$row['sidebar_type'];

        $hasChild = $this->db->from('sys_menu')->where('parent_id', $id)->count_all_results() > 0;
        if ($hasChild) {
            $this->session->set_flashdata('error', 'Menu tidak bisa dihapus karena masih memiliki submenu.');
            redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
            return;
        }

        // Soft delete agar histori favorit dan relasi tetap aman.
        $this->Menu_model->update_sidebar_menu($id, ['is_active' => 0]);

        $this->session->set_flashdata('success', 'Menu sidebar dinonaktifkan (soft delete).');
        redirect('sidebar/manage?type=' . $type . '&tab=menu-data');
    }
}
