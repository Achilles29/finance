<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master_model extends CI_Model
{
    public function count_filtered(string $table, array $searchable, string $q = '', ?int $isActive = null): int
    {
        $this->db->from($table);
        if ($isActive !== null && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', $isActive);
        }
        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $q);
                } else {
                    $this->db->or_like($col, $q);
                }
            }
            $this->db->group_end();
        }
        return (int)$this->db->count_all_results();
    }

    public function get_filtered(string $table, array $searchable, string $q, int $limit, int $offset, string $orderBy = 'id', string $orderDir = 'DESC', ?int $isActive = null): array
    {
        $this->db->from($table);
        if ($isActive !== null && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', $isActive);
        }
        if ($q !== '' && !empty($searchable)) {
            $this->db->group_start();
            foreach ($searchable as $i => $col) {
                if ($i === 0) {
                    $this->db->like($col, $q);
                } else {
                    $this->db->or_like($col, $q);
                }
            }
            $this->db->group_end();
        }
        $this->db->order_by($orderBy, $orderDir);
        $this->db->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    public function get_by_id(string $table, int $id): ?array
    {
        return $this->db->get_where($table, ['id' => $id])->row_array() ?: null;
    }

    public function insert(string $table, array $data): int
    {
        $this->db->insert($table, $data);
        return (int)$this->db->insert_id();
    }

    public function update(string $table, int $id, array $data): bool
    {
        return $this->db->where('id', $id)->update($table, $data);
    }

    public function toggle_active(string $table, int $id): bool
    {
        $this->db->where('id', $id);
        $this->db->set('is_active', 'IF(is_active=1,0,1)', false);
        return $this->db->update($table);
    }

    public function get_options(string $table, string $valueCol = 'id', string $labelCol = 'name', bool $activeOnly = true): array
    {
        $this->db->select($valueCol . ' AS value, ' . $labelCol . ' AS label', false);
        if ($activeOnly && $this->db->field_exists('is_active', $table)) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by($labelCol, 'ASC');
        return $this->db->get($table)->result_array();
    }

    public function exists_by_code(string $table, string $codeColumn, string $codeValue, int $excludeId = 0): bool
    {
        $this->db->where($codeColumn, $codeValue);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results($table) > 0;
    }

    public function get_variable_cost_default_percent(string $scopeCode, float $fallback = 20.0): float
    {
        if (!$this->db->table_exists('mst_variable_cost_default')) {
            return $fallback;
        }

        $row = $this->db
            ->select('default_percent')
            ->from('mst_variable_cost_default')
            ->where('scope_code', strtoupper($scopeCode))
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row || !isset($row['default_percent'])) {
            return $fallback;
        }

        return (float)$row['default_percent'];
    }

    public function generate_unique_code(string $table, string $codeColumn, string $name, int $excludeId = 0, int $maxLength = 50): string
    {
        $maxLength = max(3, $maxLength);
        $stem = $this->build_initial_code($name);
        $candidate = substr($stem, 0, $maxLength);

        if (!$this->exists_by_code($table, $codeColumn, $candidate, $excludeId)) {
            return $candidate;
        }

        $seq = 1;
        while ($seq <= 9999) {
            $suffix = str_pad((string)$seq, 2, '0', STR_PAD_LEFT);
            $baseLen = max(1, $maxLength - strlen($suffix));
            $candidate = substr($stem, 0, $baseLen) . $suffix;
            if (!$this->exists_by_code($table, $codeColumn, $candidate, $excludeId)) {
                return $candidate;
            }
            $seq++;
        }

        return substr('AUTO' . date('His'), 0, $maxLength);
    }

    private function build_initial_code(string $name): string
    {
        $clean = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', ' ', $name)));
        if ($clean === '') {
            return 'CODE';
        }

        $parts = preg_split('/\s+/', $clean);
        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= substr($part, 0, 1);
        }

        if (strlen($initials) < 3) {
            $packed = str_replace(' ', '', $clean);
            $initials = substr($packed, 0, 3);
        }

        return $initials !== '' ? $initials : 'CODE';
    }
}
