<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Coffee_packaging_label_model extends CI_Model
{
    private const TABLE = 'coffee_packaging_label';

    public function table_ready(): bool
    {
        return $this->db->table_exists(self::TABLE);
    }

    public function list_labels(array $filters = [], int $limit = 80): array
    {
        if (!$this->table_ready()) {
            return [];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));

        $this->db
            ->select('id, label_code, coffee_name, origin, weight_text, roast_level, process_method, image_path, is_active, updated_at, created_at')
            ->from(self::TABLE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('coffee_name', $q)
                ->or_like('origin', $q)
                ->or_like('label_code', $q)
                ->or_like('tasting_notes', $q)
                ->group_end();
        }

        if ($status === 'INACTIVE') {
            $this->db->where('is_active', 0);
        } elseif ($status === 'ALL') {
            // no-op
        } else {
            $this->db->where('is_active', 1);
        }

        return $this->db
            ->order_by('updated_at IS NULL', 'ASC', false)
            ->order_by('updated_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->result_array();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0 || !$this->table_ready()) {
            return null;
        }

        $row = $this->db
            ->from(self::TABLE)
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function save(array $data, int $id = 0): int
    {
        if (!$this->table_ready()) {
            return 0;
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update(self::TABLE, $data);
            return $id;
        }

        $data['label_code'] = $data['label_code'] ?? $this->next_label_code();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->insert(self::TABLE, $data);
        return (int)$this->db->insert_id();
    }

    public function set_active(int $id, bool $active, ?int $userId = null): bool
    {
        if ($id <= 0 || !$this->table_ready()) {
            return false;
        }

        $data = [
            'is_active' => $active ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($userId !== null && $this->db->field_exists('updated_by', self::TABLE)) {
            $data['updated_by'] = $userId;
        }

        return (bool)$this->db->where('id', $id)->update(self::TABLE, $data);
    }

    public function next_label_code(): string
    {
        $prefix = 'LBL-' . date('Ymd') . '-';
        $row = $this->db
            ->select('label_code')
            ->from(self::TABLE)
            ->like('label_code', $prefix, 'after')
            ->order_by('label_code', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $last = 0;
        if (!empty($row['label_code']) && preg_match('/-(\d+)$/', (string)$row['label_code'], $m)) {
            $last = (int)$m[1];
        }

        return $prefix . str_pad((string)($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
