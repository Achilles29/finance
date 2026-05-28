<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_model extends CI_Model
{
    public function member_filter_options(): array
    {
        return [
            'cities' => $this->distinct_non_empty_values('crm_member', 'city'),
            'tiers' => $this->distinct_non_empty_values('crm_member', 'member_tier'),
        ];
    }

    public function member_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $memberStatus = strtoupper(trim((string)($filters['member_status'] ?? 'ALL')));
        $city = trim((string)($filters['city'] ?? ''));
        $tier = trim((string)($filters['tier'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $this->db->from('crm_member m');
        if ($q !== '') {
            $this->db->group_start()
                ->like('m.member_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->or_like('m.email', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('m.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('m.is_active', 0);
        }
        if (in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $this->db->where('m.member_status', $memberStatus);
        }
        if ($city !== '') {
            $this->db->where('m.city', $city);
        }
        if ($tier !== '') {
            $this->db->where('m.member_tier', $tier);
        }

        $total = (int)$this->db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $this->db->select('
            m.id,
            m.member_no,
            m.member_name,
            m.mobile_phone,
            m.email,
            m.birth_date,
            m.gender,
            m.address,
            m.city,
            m.postal_code,
            m.emergency_contact_name,
            m.emergency_contact_phone,
            m.member_tier,
            m.point_balance_cache,
            m.stamp_balance_cache,
            m.total_spending,
            m.member_status,
            m.is_active,
            m.joined_at,
            m.notes
        ')
            ->order_by('m.member_name', 'ASC')
            ->order_by('m.member_no', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_member(int $id): ?array
    {
        return $this->db->from('crm_member')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_member(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['member_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama member wajib diisi.'];
        }

        $memberStatus = strtoupper(trim((string)($data['member_status'] ?? 'ACTIVE')));
        if (!in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $memberStatus = 'ACTIVE';
        }
        $gender = strtoupper(trim((string)($data['gender'] ?? '')));
        if (!in_array($gender, ['L', 'P'], true)) {
            $gender = null;
        }

        $memberNo = strtoupper(trim((string)($data['member_no'] ?? '')));
        if ($memberNo === '') {
            $memberNo = $this->generate_member_no((string)($data['joined_at'] ?? ''));
        } elseif ($this->member_no_exists($memberNo, $id)) {
            return ['ok' => false, 'message' => 'Nomor member sudah dipakai.'];
        }

        $payload = [
            'member_no' => $memberNo,
            'member_name' => $name,
            'mobile_phone' => $this->nullable_text($data['mobile_phone'] ?? ''),
            'email' => $this->nullable_text($data['email'] ?? ''),
            'birth_date' => $this->nullable_date($data['birth_date'] ?? ''),
            'gender' => $gender,
            'address' => $this->nullable_text($data['address'] ?? ''),
            'city' => $this->nullable_text($data['city'] ?? ''),
            'postal_code' => $this->nullable_text($data['postal_code'] ?? ''),
            'emergency_contact_name' => $this->nullable_text($data['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => $this->nullable_text($data['emergency_contact_phone'] ?? ''),
            'member_tier' => $this->nullable_text($data['member_tier'] ?? ''),
            'joined_at' => $this->nullable_datetime($data['joined_at'] ?? '') ?: date('Y-m-d H:i:s'),
            'member_status' => $memberStatus,
            'notes' => $this->nullable_text($data['notes'] ?? ''),
        ];

        if ($id > 0) {
            $existing = $this->find_member($id);
            if (!$existing) {
                return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('crm_member', $payload);
        } else {
            $this->db->insert('crm_member', $payload);
            $id = (int)$this->db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_member(int $id): array
    {
        $row = $this->find_member($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->db->where('id', $id)->update('crm_member', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function payment_method_filter_options(): array
    {
        return [
            'accounts' => $this->active_company_accounts(),
        ];
    }

    public function payment_method_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $methodType = strtoupper(trim((string)($filters['method_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $this->db->from('pos_payment_method pm');
        $this->db->join('fin_company_account a', 'a.id = pm.company_account_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('pm.method_code', $q)
                ->or_like('pm.method_name', $q)
                ->or_like('a.account_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('pm.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('pm.is_active', 0);
        }
        if (in_array($methodType, ['CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $this->db->where('pm.method_type', $methodType);
        }

        $total = (int)$this->db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $this->db->select('
            pm.id,
            pm.method_code,
            pm.method_name,
            pm.method_type,
            pm.company_account_id,
            pm.allows_change,
            pm.requires_reference_no,
            pm.show_in_cashier,
            pm.sort_order,
            pm.notes,
            pm.is_active,
            a.account_code,
            a.account_name
        ')
            ->order_by('pm.sort_order', 'ASC')
            ->order_by('pm.method_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_payment_method(int $id): ?array
    {
        return $this->db->from('pos_payment_method')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_payment_method(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['method_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama metode pembayaran wajib diisi.'];
        }
        $type = strtoupper(trim((string)($data['method_type'] ?? 'CASH')));
        if (!in_array($type, ['CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $type = 'CASH';
        }

        $methodCode = strtoupper(trim((string)($data['method_code'] ?? '')));
        if ($methodCode === '') {
            $methodCode = $this->generate_named_code('pos_payment_method', 'method_code', $name, 'PM-', $id, 40);
        } elseif ($this->code_exists('pos_payment_method', 'method_code', $methodCode, $id)) {
            return ['ok' => false, 'message' => 'Kode metode pembayaran sudah dipakai.'];
        }

        $payload = [
            'method_code' => $methodCode,
            'method_name' => $name,
            'method_type' => $type,
            'company_account_id' => !empty($data['company_account_id']) ? (int)$data['company_account_id'] : null,
            'allows_change' => !empty($data['allows_change']) ? 1 : 0,
            'requires_reference_no' => !empty($data['requires_reference_no']) ? 1 : 0,
            'show_in_cashier' => !empty($data['show_in_cashier']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'notes' => $this->nullable_text($data['notes'] ?? ''),
        ];

        if ($payload['company_account_id'] !== null && !$this->company_account_exists((int)$payload['company_account_id'])) {
            return ['ok' => false, 'message' => 'Company account tidak ditemukan.'];
        }

        if ($id > 0) {
            if (!$this->find_payment_method($id)) {
                return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pos_payment_method', $payload);
        } else {
            $this->db->insert('pos_payment_method', $payload);
            $id = (int)$this->db->insert_id();
        }
        return ['ok' => true, 'id' => $id];
    }

    public function toggle_payment_method(int $id): array
    {
        $row = $this->find_payment_method($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->db->where('id', $id)->update('pos_payment_method', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function outlet_terminal_filter_options(): array
    {
        return [
            'product_divisions' => $this->product_divisions(),
            'operational_divisions' => $this->operational_divisions(),
            'outlets' => $this->active_outlets(),
        ];
    }

    public function outlet_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $this->db->from('pos_outlet o')
            ->join('mst_product_division pd', 'pd.id = o.product_division_id', 'left')
            ->join('mst_operational_division od', 'od.id = o.operational_division_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('o.outlet_code', $q)
                ->or_like('o.outlet_name', $q)
                ->or_like('pd.name', $q)
                ->or_like('od.name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('o.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('o.is_active', 0);
        }

        $total = (int)$this->db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $this->db->select('
            o.*,
            pd.name AS product_division_name,
            od.name AS operational_division_name
        ')
            ->order_by('o.outlet_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function terminal_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $this->db->from('pos_terminal t')
            ->join('pos_outlet o', 'o.id = t.outlet_id', 'left');
        if ($q !== '') {
            $this->db->group_start()
                ->like('t.terminal_code', $q)
                ->or_like('t.terminal_name', $q)
                ->or_like('t.device_key', $q)
                ->or_like('o.outlet_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $this->db->where('t.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $this->db->where('t.is_active', 0);
        }
        if ($outletId > 0) {
            $this->db->where('t.outlet_id', $outletId);
        }

        $total = (int)$this->db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $this->db->select('
            t.*,
            o.outlet_name
        ')
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('t.terminal_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return [
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function find_outlet(int $id): ?array
    {
        return $this->db->from('pos_outlet')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_outlet(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['outlet_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama outlet wajib diisi.'];
        }
        $scope = strtoupper(trim((string)($data['outlet_scope'] ?? 'REGULAR')));
        if (!in_array($scope, ['REGULAR', 'EVENT', 'ALL'], true)) {
            $scope = 'REGULAR';
        }
        $code = strtoupper(trim((string)($data['outlet_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code('pos_outlet', 'outlet_code', $name, 'OUT-', $id, 40);
        } elseif ($this->code_exists('pos_outlet', 'outlet_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode outlet sudah dipakai.'];
        }

        $payload = [
            'outlet_code' => $code,
            'outlet_name' => $name,
            'outlet_scope' => $scope,
            'product_division_id' => !empty($data['product_division_id']) ? (int)$data['product_division_id'] : null,
            'operational_division_id' => !empty($data['operational_division_id']) ? (int)$data['operational_division_id'] : null,
            'address' => $this->nullable_text($data['address'] ?? ''),
            'phone' => $this->nullable_text($data['phone'] ?? ''),
            'notes' => $this->nullable_text($data['notes'] ?? ''),
        ];

        if ($id > 0) {
            if (!$this->find_outlet($id)) {
                return ['ok' => false, 'message' => 'Outlet tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pos_outlet', $payload);
        } else {
            $this->db->insert('pos_outlet', $payload);
            $id = (int)$this->db->insert_id();
        }
        return ['ok' => true, 'id' => $id];
    }

    public function toggle_outlet(int $id): array
    {
        $row = $this->find_outlet($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Outlet tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->db->where('id', $id)->update('pos_outlet', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function find_terminal(int $id): ?array
    {
        return $this->db->from('pos_terminal')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_terminal(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['terminal_name'] ?? ''));
        $outletId = (int)($data['outlet_id'] ?? 0);
        if ($name === '' || $outletId <= 0) {
            return ['ok' => false, 'message' => 'Outlet dan nama terminal wajib diisi.'];
        }
        $platform = strtoupper(trim((string)($data['app_platform'] ?? 'DESKTOP')));
        if (!in_array($platform, ['DESKTOP', 'WEB', 'ANDROID', 'IOS', 'OTHER'], true)) {
            $platform = 'DESKTOP';
        }
        if (!$this->find_outlet($outletId)) {
            return ['ok' => false, 'message' => 'Outlet terminal tidak ditemukan.'];
        }

        $code = strtoupper(trim((string)($data['terminal_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code('pos_terminal', 'terminal_code', $name, 'TERM-', $id, 40);
        } elseif ($this->code_exists('pos_terminal', 'terminal_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode terminal sudah dipakai.'];
        }

        $deviceKey = trim((string)($data['device_key'] ?? ''));
        if ($deviceKey !== '' && $this->terminal_device_key_exists($deviceKey, $id)) {
            return ['ok' => false, 'message' => 'Device key terminal sudah dipakai.'];
        }

        $payload = [
            'outlet_id' => $outletId,
            'terminal_code' => $code,
            'terminal_name' => $name,
            'device_key' => $deviceKey === '' ? null : $deviceKey,
            'app_platform' => $platform,
            'notes' => $this->nullable_text($data['notes'] ?? ''),
        ];

        if ($id > 0) {
            if (!$this->find_terminal($id)) {
                return ['ok' => false, 'message' => 'Terminal tidak ditemukan.'];
            }
            $this->db->where('id', $id)->update('pos_terminal', $payload);
        } else {
            $this->db->insert('pos_terminal', $payload);
            $id = (int)$this->db->insert_id();
        }
        return ['ok' => true, 'id' => $id];
    }

    public function toggle_terminal(int $id): array
    {
        $row = $this->find_terminal($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Terminal tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->db->where('id', $id)->update('pos_terminal', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function active_company_accounts(): array
    {
        if (!$this->db->table_exists('fin_company_account')) {
            return [];
        }
        return $this->db->select('id, account_code, account_name')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('account_name', 'ASC')
            ->get()->result_array();
    }

    public function product_divisions(): array
    {
        if (!$this->db->table_exists('mst_product_division')) {
            return [];
        }
        return $this->db->select('id, code, name')
            ->from('mst_product_division')
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get()->result_array();
    }

    public function operational_divisions(): array
    {
        if (!$this->db->table_exists('mst_operational_division')) {
            return [];
        }
        return $this->db->select('id, code, name')
            ->from('mst_operational_division')
            ->where('is_active', 1)
            ->order_by('name', 'ASC')
            ->get()->result_array();
    }

    public function active_outlets(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }
        return $this->db->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()->result_array();
    }

    private function distinct_non_empty_values(string $table, string $column): array
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists($column, $table)) {
            return [];
        }
        $rows = $this->db->select($column)
            ->from($table)
            ->where($column . ' IS NOT NULL', null, false)
            ->where('TRIM(' . $column . ") <> ''", null, false)
            ->group_by($column)
            ->order_by($column, 'ASC')
            ->get()->result_array();
        return array_values(array_filter(array_map(static function (array $row) use ($column): string {
            return trim((string)($row[$column] ?? ''));
        }, $rows)));
    }

    private function member_no_exists(string $memberNo, int $excludeId = 0): bool
    {
        return $this->code_exists('crm_member', 'member_no', $memberNo, $excludeId);
    }

    private function company_account_exists(int $id): bool
    {
        if ($id <= 0 || !$this->db->table_exists('fin_company_account')) {
            return false;
        }
        return (int)$this->db->from('fin_company_account')->where('id', $id)->count_all_results() > 0;
    }

    private function terminal_device_key_exists(string $deviceKey, int $excludeId = 0): bool
    {
        $this->db->from('pos_terminal')->where('device_key', $deviceKey);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return (int)$this->db->count_all_results() > 0;
    }

    private function code_exists(string $table, string $column, string $code, int $excludeId = 0): bool
    {
        $this->db->from($table)->where($column, $code);
        if ($excludeId > 0) {
            $this->db->where('id !=', $excludeId);
        }
        return (int)$this->db->count_all_results() > 0;
    }

    private function generate_member_no(string $joinedAt = ''): string
    {
        $ts = strtotime($joinedAt);
        if ($ts === false) {
            $ts = time();
        }
        $prefix = 'MBR-' . date('Ym', $ts) . '-';
        $maxSeq = 0;

        $rows = $this->db->select('member_no')
            ->from('crm_member')
            ->like('member_no', $prefix, 'after')
            ->get()->result_array();
        foreach ($rows as $row) {
            $code = strtoupper(trim((string)($row['member_no'] ?? '')));
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d{4})$/', $code, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $next = $maxSeq + 1;
        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    private function generate_named_code(string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        $slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $base = $prefix . substr($slug, 0, max(1, $maxLen - strlen($prefix) - 5));
        $candidate = $base;
        $seq = 1;
        while ($this->code_exists($table, $column, $candidate, $excludeId)) {
            $suffix = '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            $cut = max(1, $maxLen - strlen($suffix));
            $candidate = substr($base, 0, $cut) . $suffix;
            $seq++;
        }
        return $candidate;
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        return [$page, $offset, $totalPages];
    }

    private function nullable_text($value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullable_date($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function nullable_datetime($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
