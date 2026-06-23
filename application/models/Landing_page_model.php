<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Landing_page_model extends CI_Model
{
    // ── CONFIG ────────────────────────────────────────────────────────

    public function get_config(): array
    {
        $row = $this->db->where('id', 1)->get('lp_config')->row_array();
        if (!$row) return [];

        foreach (['hero_badges', 'about_points'] as $f) {
            $decoded = !empty($row[$f]) ? json_decode($row[$f], true) : null;
            $row[$f] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    public function upsert_config(array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $exists = $this->db->where('id', 1)->count_all_results('lp_config') > 0;
        if ($exists) {
            $this->db->where('id', 1)->update('lp_config', $data);
        } else {
            $data['id'] = 1;
            $this->db->insert('lp_config', $data);
        }
    }

    // ── MENU ─────────────────────────────────────────────────────────

    public function get_menu(bool $all = false): array
    {
        $q = $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_menu')->result_array();
    }

    public function find_menu(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_menu')->row_array();
        return $row ?: null;
    }

    public function insert_menu(array $data): int
    {
        $this->db->insert('lp_menu', $data);
        return (int)$this->db->insert_id();
    }

    public function update_menu(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_menu', $data);
    }

    public function delete_menu(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_menu');
    }

    public function toggle_menu(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_menu')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_menu', ['is_active' => $new]);
        return $new;
    }

    public function reorder_menu(array $ids): void
    {
        foreach ($ids as $order => $id) {
            $this->db->where('id', (int)$id)->update('lp_menu', ['sort_order' => (int)$order]);
        }
    }

    public function next_menu_sort(): int
    {
        $row = $this->db->select_max('sort_order')->get('lp_menu')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }

    // ── GALLERY ───────────────────────────────────────────────────────

    public function get_gallery(bool $all = false): array
    {
        $q = $this->db->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_gallery')->result_array();
    }

    public function find_gallery(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_gallery')->row_array();
        return $row ?: null;
    }

    public function insert_gallery(array $data): int
    {
        $this->db->insert('lp_gallery', $data);
        return (int)$this->db->insert_id();
    }

    public function update_gallery(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_gallery', $data);
    }

    public function delete_gallery(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_gallery');
    }

    public function toggle_gallery(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_gallery')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_gallery', ['is_active' => $new]);
        return $new;
    }

    public function reorder_gallery(array $ids): void
    {
        foreach ($ids as $order => $id) {
            $this->db->where('id', (int)$id)->update('lp_gallery', ['sort_order' => (int)$order]);
        }
    }

    public function next_gallery_sort(): int
    {
        $row = $this->db->select_max('sort_order')->get('lp_gallery')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }

    // ── EMBED ─────────────────────────────────────────────────────────

    public function get_embed(bool $all = false): array
    {
        $q = $this->db->order_by('embed_type', 'ASC')->order_by('sort_order', 'ASC')->order_by('id', 'ASC');
        if (!$all) $q->where('is_active', 1);
        return $q->get('lp_embed')->result_array();
    }

    public function find_embed(int $id): ?array
    {
        $row = $this->db->where('id', $id)->get('lp_embed')->row_array();
        return $row ?: null;
    }

    public function insert_embed(array $data): int
    {
        $this->db->insert('lp_embed', $data);
        return (int)$this->db->insert_id();
    }

    public function update_embed(int $id, array $data): void
    {
        $this->db->where('id', $id)->update('lp_embed', $data);
    }

    public function delete_embed(int $id): void
    {
        $this->db->where('id', $id)->delete('lp_embed');
    }

    public function toggle_embed(int $id): int
    {
        $row = $this->db->select('is_active')->where('id', $id)->get('lp_embed')->row_array();
        if (!$row) return 0;
        $new = (int)$row['is_active'] === 1 ? 0 : 1;
        $this->db->where('id', $id)->update('lp_embed', ['is_active' => $new]);
        return $new;
    }

    public function next_embed_sort(string $type): int
    {
        $row = $this->db->select_max('sort_order')->where('embed_type', $type)->get('lp_embed')->row_array();
        return (int)($row['sort_order'] ?? -1) + 1;
    }
}
