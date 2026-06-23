<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Landing_page extends MY_Controller
{
    private const PAGE_CODE = 'landing_page.index';

    public function __construct()
    {
        parent::__construct();
        $this->require_permission(self::PAGE_CODE);
        $this->load->model('Landing_page_model', 'lp');
    }

    // ── HALAMAN UTAMA ────────────────────────────────────────────────

    public function index(): void
    {
        $tab = in_array($this->input->get('tab', true), ['config', 'menu', 'gallery', 'embed'], true)
            ? $this->input->get('tab', true)
            : 'config';

        $data = [
            'title'     => 'Pengaturan Landing Page',
            'tab'       => $tab,
            'cfg'       => $this->lp->get_config(),
            'menus'     => $this->lp->get_menu(true),
            'galleries' => $this->lp->get_gallery(true),
            'embeds'    => $this->lp->get_embed(true),
        ];
        $this->render('landing_page/index', $data);
    }

    // ── CONFIG ───────────────────────────────────────────────────────

    public function config_update(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');

        $text_fields = [
            'hero_title', 'hero_subtitle', 'hero_image',
            'about_title', 'about_text', 'about_image',
            'address', 'phone', 'whatsapp',
            'order_url', 'member_url', 'instagram_url', 'linktree_url', 'map_url',
            'cta_title', 'cta_text', 'footer_text',
        ];
        $data = [];
        foreach ($text_fields as $f) {
            $data[$f] = trim((string)$this->input->post($f, true));
        }

        // Hero badges — textarea, satu badge per baris
        $badges = array_values(array_filter(
            array_map('trim', explode("\n", (string)$this->input->post('hero_badges_raw', true)))
        ));
        $data['hero_badges'] = json_encode($badges, JSON_UNESCAPED_UNICODE);

        // About points — textarea, satu poin per baris
        $points = array_values(array_filter(
            array_map('trim', explode("\n", (string)$this->input->post('about_points_raw', true)))
        ));
        $data['about_points'] = json_encode($points, JSON_UNESCAPED_UNICODE);

        // Pengaturan sumber data
        $menu_source    = $this->input->post('menu_source', true);
        $gallery_source = $this->input->post('gallery_source', true);
        $data['menu_source']          = in_array($menu_source, ['manual', 'produk'], true) ? $menu_source : 'manual';
        $data['gallery_source']       = in_array($gallery_source, ['manual', 'produk'], true) ? $gallery_source : 'manual';
        $data['menu_limit']           = max(4, min(20, (int)$this->input->post('menu_limit', true)));
        $data['gallery_limit']        = max(4, min(12, (int)$this->input->post('gallery_limit', true)));
        $data['menu_best_seller_top'] = max(1, min(10, (int)$this->input->post('menu_best_seller_top', true)));
        $data['menu_kategori_ids']    = trim((string)$this->input->post('menu_kategori_ids', true));
        $data['gallery_kategori_ids'] = trim((string)$this->input->post('gallery_kategori_ids', true));

        $data['updated_by'] = (int)$this->current_user['id'];

        $this->lp->upsert_config($data);
        $this->session->set_flashdata('success', 'Pengaturan landing page berhasil disimpan.');
        redirect('landing-page?tab=config');
    }

    // ── MENU ─────────────────────────────────────────────────────────

    public function menu_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        $input = $this->_menu_input();
        if ($input['title'] === '') { $this->json_error('Nama menu tidak boleh kosong.', 422); return; }

        $input['sort_order'] = $this->lp->next_menu_sort();
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_menu($input);
        $this->json_ok(['message' => 'Menu berhasil ditambahkan.']);
    }

    public function menu_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_menu($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_menu_input();
        if ($input['title'] === '') { $this->json_error('Nama menu tidak boleh kosong.', 422); return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_menu($id, $input);
        $this->json_ok(['message' => 'Menu berhasil diperbarui.']);
    }

    public function menu_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if ($id <= 0 || !$this->lp->find_menu($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_menu($id);
        $this->json_ok(['message' => 'Menu berhasil dihapus.']);
    }

    public function menu_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_menu($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_menu($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    public function menu_reorder(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $body = json_decode(file_get_contents('php://input'), true);
        $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
        if (empty($ids)) { $this->json_error('Data urutan tidak valid.', 422); return; }
        $this->lp->reorder_menu(array_values($ids));
        $this->json_ok(['message' => 'Urutan menu berhasil disimpan.']);
    }

    private function _menu_input(): array
    {
        $price_raw = preg_replace('/[^0-9]/', '', (string)$this->input->post('price', true));
        return [
            'title'          => trim((string)$this->input->post('title', true)),
            'description'    => trim((string)$this->input->post('description', true)),
            'image'          => trim((string)$this->input->post('image', true)),
            'price'          => $price_raw !== '' ? (int)$price_raw : null,
            'is_best_seller' => $this->input->post('is_best_seller', true) ? 1 : 0,
            'is_active'      => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }

    // ── GALLERY ───────────────────────────────────────────────────────

    public function gallery_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        $input = $this->_gallery_input();
        if ($input['image'] === '') { $this->json_error('URL gambar tidak boleh kosong.', 422); return; }

        $input['sort_order'] = $this->lp->next_gallery_sort();
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_gallery($input);
        $this->json_ok(['message' => 'Foto gallery berhasil ditambahkan.']);
    }

    public function gallery_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_gallery_input();
        if ($input['image'] === '') { $this->json_error('URL gambar tidak boleh kosong.', 422); return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_gallery($id, $input);
        $this->json_ok(['message' => 'Foto gallery berhasil diperbarui.']);
    }

    public function gallery_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_gallery($id);
        $this->json_ok(['message' => 'Foto gallery berhasil dihapus.']);
    }

    public function gallery_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_gallery($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_gallery($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    public function gallery_reorder(): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        $body = json_decode(file_get_contents('php://input'), true);
        $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
        if (empty($ids)) { $this->json_error('Data urutan tidak valid.', 422); return; }
        $this->lp->reorder_gallery(array_values($ids));
        $this->json_ok(['message' => 'Urutan gallery berhasil disimpan.']);
    }

    private function _gallery_input(): array
    {
        return [
            'image'     => trim((string)$this->input->post('image', true)),
            'caption'   => trim((string)$this->input->post('caption', true)),
            'is_active' => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }

    // ── EMBED ─────────────────────────────────────────────────────────

    public function embed_store(): void
    {
        $this->require_permission(self::PAGE_CODE, 'create');
        $input = $this->_embed_input();
        if (trim($input['embed_html']) === '') { $this->json_error('Kode embed tidak boleh kosong.', 422); return; }

        $input['sort_order'] = $this->lp->next_embed_sort($input['embed_type']);
        $input['created_by'] = (int)$this->current_user['id'];
        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->insert_embed($input);
        $this->json_ok(['message' => 'Embed berhasil ditambahkan.']);
    }

    public function embed_update(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }

        $input = $this->_embed_input();
        if (trim($input['embed_html']) === '') { $this->json_error('Kode embed tidak boleh kosong.', 422); return; }

        $input['updated_by'] = (int)$this->current_user['id'];
        $this->lp->update_embed($id, $input);
        $this->json_ok(['message' => 'Embed berhasil diperbarui.']);
    }

    public function embed_delete(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'delete');
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $this->lp->delete_embed($id);
        $this->json_ok(['message' => 'Embed berhasil dihapus.']);
    }

    public function embed_toggle(int $id): void
    {
        $this->require_permission(self::PAGE_CODE, 'edit');
        if ($id <= 0 || !$this->lp->find_embed($id)) { $this->json_error('Data tidak ditemukan.', 404); return; }
        $new = $this->lp->toggle_embed($id);
        $this->json_ok(['message' => 'Status berhasil diubah.', 'is_active' => $new]);
    }

    private function _embed_input(): array
    {
        $type = $this->input->post('embed_type', true);
        return [
            'embed_type' => in_array($type, ['reel', 'photo'], true) ? $type : 'photo',
            'embed_html' => trim((string)$this->input->post('embed_html')), // sengaja tidak XSS-filter HTML embed
            'is_active'  => $this->input->post('is_active', true) !== '0' ? 1 : 0,
        ];
    }
}
