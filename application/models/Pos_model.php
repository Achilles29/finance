<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_model extends CI_Model
{
    /** @var CI_DB_query_builder */
    protected $coredb;

    public function __construct()
    {
        parent::__construct();
        $this->coredb = $this->resolveCoreDatabase();
    }

    private function resolveCoreDatabase()
    {
        if (isset($this->db) && $this->db instanceof CI_DB_query_builder) {
            return $this->db;
        }

        return $this->load->database('default', true);
    }

    public function member_filter_options(): array
    {
        return [
            'tiers' => $this->distinct_non_empty_values($this->coredb, 'crm_member', 'member_tier'),
        ];
    }

    public function member_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $memberStatus = strtoupper(trim((string)($filters['member_status'] ?? 'ALL')));
        $tier = trim((string)($filters['tier'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('crm_member m');

        if ($q !== '') {
            $db->group_start()
                ->like('m.member_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->or_like('m.email', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('m.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('m.is_active', 0);
        }
        if (in_array($memberStatus, ['ACTIVE', 'SUSPENDED', 'CLOSED'], true)) {
            $db->where('m.member_status', $memberStatus);
        }
        if ($tier !== '') {
            $db->where('m.member_tier', $tier);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
            m.member_no,
            m.id,
            m.member_name,
            m.mobile_phone,
            m.email,
            m.birth_date,
            m.gender,
            m.address,
            m.city,
            m.postal_code,
            m.notes,
            m.is_active,
            m.member_tier,
            m.joined_at,
            m.expired_at,
            m.member_status
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
        return $this->coredb->from('crm_member m')
            ->select('
                m.member_no,
                m.id,
                m.member_name,
                m.mobile_phone,
                m.email,
                m.birth_date,
                m.gender,
                m.address,
                m.city,
                m.postal_code,
                m.notes,
                m.is_active,
                m.member_tier,
                m.joined_at,
                m.expired_at,
                m.member_status
            ')
            ->where('m.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_member(array $data): array
    {
        $db = $this->coredb;
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

        $db->trans_begin();
        try {
            $memberNo = strtoupper(trim((string)($data['member_no'] ?? '')));
            $joinedAt = $this->nullable_datetime($data['joined_at'] ?? '') ?: date('Y-m-d H:i:s');
            $payload = [
                'member_name' => $name,
                'mobile_phone' => $this->nullable_text($data['mobile_phone'] ?? ''),
                'email' => $this->nullable_text($data['email'] ?? ''),
                'birth_date' => $this->nullable_date($data['birth_date'] ?? ''),
                'gender' => $gender,
                'address' => $this->nullable_text($data['address'] ?? ''),
                'city' => $this->nullable_text($data['city'] ?? ''),
                'postal_code' => $this->nullable_text($data['postal_code'] ?? ''),
                'member_tier' => $this->nullable_text($data['member_tier'] ?? ''),
                'joined_at' => $joinedAt,
                'expired_at' => $this->nullable_datetime($data['expired_at'] ?? ''),
                'member_status' => $memberStatus,
                'notes' => $this->nullable_text($data['notes'] ?? ''),
                'is_active' => 1,
            ];

            if ($id > 0) {
                $existing = $this->find_member($id);
                if (!$existing) {
                    throw new RuntimeException('Member tidak ditemukan.');
                }
                if ($memberNo === '') {
                    $memberNo = (string)$existing['member_no'];
                }
                if ($this->code_exists($db, 'crm_member', 'member_no', $memberNo, $id)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                $db->where('id', $id)->update('crm_member', $payload);
            } else {
                if ($memberNo === '') {
                    $memberNo = $this->generate_member_no_core($joinedAt);
                } elseif ($this->code_exists($db, 'crm_member', 'member_no', $memberNo, 0)) {
                    throw new RuntimeException('Nomor member sudah dipakai.');
                }
                $payload['member_no'] = $memberNo;
                $db->insert('crm_member', $payload);
                $id = (int)$db->insert_id();
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan member.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_member(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_member($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->trans_begin();
        try {
            $memberStatus = strtoupper((string)($row['member_status'] ?? 'ACTIVE'));
            if ($newValue === 0 && $memberStatus === 'ACTIVE') {
                $memberStatus = 'CLOSED';
            } elseif ($newValue === 1 && $memberStatus === 'CLOSED') {
                $memberStatus = 'ACTIVE';
            }
            $db->where('id', $id)->update('crm_member', ['is_active' => $newValue, 'member_status' => $memberStatus]);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status member.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function payment_method_filter_options(): array
    {
        return [
            'accounts' => $this->active_company_accounts(),
        ];
    }

    public function sales_channel_filter_options(): array
    {
        return [
            'service_types' => $this->sales_channel_service_type_options(),
        ];
    }

    public function sales_channel_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $serviceType = strtoupper(trim((string)($filters['service_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        if (!$this->db->table_exists('pos_sales_channel')) {
            return [
                'rows' => [],
                'meta' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1],
            ];
        }

        $db = $this->coredb;
        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $hasSortOrder = $this->db->field_exists('sort_order', 'pos_sales_channel');
        $selectAllowedTypes = $hasAllowedTypes ? 'sc.allowed_service_types' : "'' AS allowed_service_types";
        $selectSortOrder = $hasSortOrder ? 'sc.sort_order' : '0 AS sort_order';
        $db->from('pos_sales_channel sc');
        if ($q !== '') {
            $db->group_start()
                ->like('sc.channel_code', $q)
                ->or_like('sc.channel_name', $q)
                ->or_like('sc.notes', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('sc.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('sc.is_active', 0);
        }
        if (in_array($serviceType, $this->sales_channel_service_type_options(), true)) {
            $db->group_start()
                ->where('sc.service_type_default', $serviceType);
            if ($hasAllowedTypes) {
                $db->or_like('sc.allowed_service_types', $serviceType);
            }
            $db
                ->group_end();
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
            sc.id,
            sc.channel_code,
            sc.channel_name,
            sc.service_type_default,
            ' . $selectAllowedTypes . ',
            sc.marketplace_fee_percent,
            sc.is_default,
            sc.is_active,
            ' . $selectSortOrder . ',
            sc.notes
        ')
            ->order_by('sc.is_default', 'DESC')
            ->order_by($hasSortOrder ? 'sc.sort_order' : 'sc.id', 'ASC')
            ->order_by('sc.channel_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
            $row['allowed_service_type_list'] = $allowedRaw !== ''
                ? $this->explode_service_types($allowedRaw)
                : [$row['service_type_default'] ?? 'DINE_IN'];
        }
        unset($row);

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

    public function find_sales_channel(int $id): ?array
    {
        if ($id <= 0 || !$this->db->table_exists('pos_sales_channel')) {
            return null;
        }

        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $hasSortOrder = $this->db->field_exists('sort_order', 'pos_sales_channel');
        $row = $this->coredb
            ->from('pos_sales_channel sc')
            ->select('
                sc.id,
                sc.channel_code,
                sc.channel_name,
                sc.service_type_default,
                ' . ($hasAllowedTypes ? 'sc.allowed_service_types' : "'' AS allowed_service_types") . ',
                sc.marketplace_fee_percent,
                sc.is_default,
                sc.is_active,
                ' . ($hasSortOrder ? 'sc.sort_order' : '0 AS sort_order') . ',
                sc.notes
            ')
            ->where('sc.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }
        $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
        $row['allowed_service_type_list'] = $allowedRaw !== ''
            ? $this->explode_service_types($allowedRaw)
            : [$row['service_type_default'] ?? 'DINE_IN'];
        return $row;
    }

    public function save_sales_channel(array $data): array
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return ['ok' => false, 'message' => 'Schema sales channel POS belum tersedia. Jalankan migration sales channel terlebih dulu.'];
        }

        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['channel_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama sales channel wajib diisi.'];
        }

        $serviceTypeDefault = strtoupper(trim((string)($data['service_type_default'] ?? 'DINE_IN')));
        $allowedTypes = $this->normalize_allowed_service_types($data['allowed_service_types'] ?? []);
        if (empty($allowedTypes)) {
            return ['ok' => false, 'message' => 'Minimal pilih 1 service type yang diizinkan untuk channel ini.'];
        }
        if (!in_array($serviceTypeDefault, $allowedTypes, true)) {
            return ['ok' => false, 'message' => 'Service type default harus termasuk ke daftar service type yang diizinkan.'];
        }

        $db->trans_begin();
        try {
            $channelCode = strtoupper(trim((string)($data['channel_code'] ?? '')));
            $isDefault = !empty($data['is_default']) ? 1 : 0;
            $payload = [
                'channel_name' => $name,
                'service_type_default' => $serviceTypeDefault,
                'marketplace_fee_percent' => round(max(0, (float)($data['marketplace_fee_percent'] ?? 0)), 4),
                'is_default' => $isDefault,
                'is_active' => !isset($data['is_active']) || (int)$data['is_active'] === 1 ? 1 : 0,
                'notes' => $this->nullable_text($data['notes'] ?? ''),
            ];
            if ($this->db->field_exists('allowed_service_types', 'pos_sales_channel')) {
                $payload['allowed_service_types'] = implode(',', $allowedTypes);
            }
            if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
                $payload['sort_order'] = max(0, (int)($data['sort_order'] ?? 0));
            }

            if ($id > 0) {
                $existing = $this->find_sales_channel($id);
                if (!$existing) {
                    throw new RuntimeException('Sales channel tidak ditemukan.');
                }
                if ($channelCode === '') {
                    $channelCode = (string)$existing['channel_code'];
                }
                if ($this->code_exists($db, 'pos_sales_channel', 'channel_code', $channelCode, $id)) {
                    throw new RuntimeException('Kode sales channel sudah dipakai.');
                }
                $payload['channel_code'] = $channelCode;
                $db->where('id', $id)->update('pos_sales_channel', $payload);
            } else {
                if ($channelCode === '') {
                    $channelCode = $this->generate_pos_sales_channel_code($name);
                } elseif ($this->code_exists($db, 'pos_sales_channel', 'channel_code', $channelCode, 0)) {
                    throw new RuntimeException('Kode sales channel sudah dipakai.');
                }
                $payload['channel_code'] = $channelCode;
                $db->insert('pos_sales_channel', $payload);
                $id = (int)$db->insert_id();
            }

            if ($isDefault === 1) {
                $db->where('id !=', $id)->update('pos_sales_channel', ['is_default' => 0]);
            } else {
                $defaultCount = (int)$db->where('is_default', 1)->count_all_results('pos_sales_channel');
                if ($defaultCount === 0) {
                    $db->where('id', $id)->update('pos_sales_channel', ['is_default' => 1]);
                }
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_sales_channel(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_sales_channel($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Sales channel tidak ditemukan.'];
        }

        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->trans_begin();
        try {
            if ((int)($row['is_default'] ?? 0) === 1 && $newValue === 0) {
                throw new RuntimeException('Sales channel default tidak boleh dinonaktifkan sebelum ada default lain yang aktif.');
            }
            $db->where('id', $id)->update('pos_sales_channel', ['is_active' => $newValue]);
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete_sales_channel(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_sales_channel($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Sales channel tidak ditemukan.'];
        }
        if ((int)($row['is_default'] ?? 0) === 1) {
            return ['ok' => false, 'message' => 'Sales channel default tidak boleh dihapus. Pindahkan default ke channel lain dulu.'];
        }
        if ($this->db->table_exists('pos_order') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $used = (int)$db->where('sales_channel_id', $id)->count_all_results('pos_order');
            if ($used > 0) {
                return ['ok' => false, 'message' => 'Sales channel sudah dipakai di transaksi POS. Nonaktifkan saja bila tidak ingin dipakai lagi.'];
            }
        }

        $db->trans_begin();
        try {
            $db->where('id', $id)->delete('pos_sales_channel');
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menghapus sales channel.');
            }
            $db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function payment_method_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $methodType = strtoupper(trim((string)($filters['method_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('pos_payment_method pm')
            ->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('pm.method_code', $q)
                ->or_like('pm.method_name', $q)
                ->or_like('pm.method_type', $q)
                ->or_like('acc.bank_name', $q)
                ->or_like('acc.account_name', $q)
                ->or_like('acc.account_no', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('pm.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('pm.is_active', 0);
        }
        if (in_array($methodType, ['CASH', 'BANK', 'EWALLET', 'QRIS', 'COMPLIMENT', 'DEPOSIT', 'OTHER'], true)) {
            $db->where('pm.method_type', $methodType);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('pm.*, acc.bank_name, acc.account_name, acc.account_no, acc.account_type')
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

    public function deposit_rows(array $filters): array
    {
        if (!$this->db->table_exists('pos_payment')) {
            return [
                'rows' => [],
                'summary' => [
                    'deposit_count' => 0,
                    'total_deposit_amount' => 0,
                    'total_applied_amount' => 0,
                    'total_remaining_amount' => 0,
                    'open_deposit_count' => 0,
                ],
                'meta' => ['total' => 0, 'page' => 1, 'limit' => 50, 'total_pages' => 1],
            ];
        }

        $q = trim((string)($filters['q'] ?? ''));
        $paymentStatus = strtoupper(trim((string)($filters['payment_status'] ?? 'PAID')));
        $settlementStatus = strtoupper(trim((string)($filters['settlement_status'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $hasApplyTable = $this->db->table_exists('pos_payment_deposit_apply');
        $hasPaymentLine = $this->db->table_exists('pos_payment_line');

        $db = $this->coredb;
        $db->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left');

        if ($hasApplyTable) {
            $db->join('pos_payment_deposit_apply dpa', "dpa.deposit_payment_id = p.id AND dpa.apply_status = 'APPLIED'", 'left');
        }
        if ($hasPaymentLine) {
            $db->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left');
            $db->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');
        }

        $db->where('p.payment_type', 'DEPOSIT');
        if ($q !== '') {
            $db->group_start()
                ->like('p.payment_no', $q)
                ->or_like('o.order_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.mobile_phone', $q)
                ->group_end();
        }
        if (in_array($paymentStatus, ['PAID', 'PENDING', 'VOID', 'FAILED'], true)) {
            $db->where('p.payment_status', $paymentStatus);
        }

        $remainingExpr = '(COALESCE(p.net_amount,0) - COALESCE(p.deposit_applied_amount,0))';
        if ($settlementStatus === 'OPEN') {
            $db->where($remainingExpr . ' >', 0, false);
            $db->where('COALESCE(p.deposit_applied_amount,0) =', 0, false);
        } elseif ($settlementStatus === 'PARTIAL') {
            $db->where('COALESCE(p.deposit_applied_amount,0) >', 0, false);
            $db->where($remainingExpr . ' >', 0, false);
        } elseif ($settlementStatus === 'FULL') {
            $db->where($remainingExpr . ' <=', 0, false);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $applyCountSelect = $hasApplyTable ? 'COUNT(DISTINCT dpa.id)' : '0';
        $methodSelect = $hasPaymentLine ? "GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name SEPARATOR ', ')" : "''";

        $rows = $db->select("
                p.id,
                p.payment_no,
                p.order_id,
                p.member_id,
                p.payment_status,
                p.net_amount,
                p.deposit_applied_amount,
                p.paid_at,
                  p.created_at,
                  o.order_no,
                  m.member_name,
                  m.mobile_phone,
                  GREATEST(COALESCE(p.net_amount,0) - COALESCE(p.deposit_applied_amount,0), 0) AS remaining_amount,
                {$applyCountSelect} AS apply_count,
                {$methodSelect} AS payment_method_names
            ", false)
            ->group_by('p.id')
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'DESC', false)
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        $summaryDb = $this->coredb
            ->select("
                COUNT(*) AS deposit_count,
                COALESCE(SUM(COALESCE(net_amount,0)),0) AS total_deposit_amount,
                COALESCE(SUM(COALESCE(deposit_applied_amount,0)),0) AS total_applied_amount,
                COALESCE(SUM(GREATEST(COALESCE(net_amount,0) - COALESCE(deposit_applied_amount,0), 0)),0) AS total_remaining_amount,
                COALESCE(SUM(CASE WHEN GREATEST(COALESCE(net_amount,0) - COALESCE(deposit_applied_amount,0), 0) > 0 THEN 1 ELSE 0 END),0) AS open_deposit_count
            ", false)
            ->from('pos_payment')
            ->where('payment_type', 'DEPOSIT');
        if (in_array($paymentStatus, ['PAID', 'PENDING', 'VOID', 'FAILED'], true)) {
            $summaryDb->where('payment_status', $paymentStatus);
        }
        $summary = $summaryDb->get()->row_array() ?: [];

        return [
            'rows' => $rows,
            'summary' => [
                'deposit_count' => (int)($summary['deposit_count'] ?? 0),
                'total_deposit_amount' => round((float)($summary['total_deposit_amount'] ?? 0), 2),
                'total_applied_amount' => round((float)($summary['total_applied_amount'] ?? 0), 2),
                'total_remaining_amount' => round((float)($summary['total_remaining_amount'] ?? 0), 2),
                'open_deposit_count' => (int)($summary['open_deposit_count'] ?? 0),
            ],
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function save_deposit(array $payload, int $actorEmployeeId): array
    {
        if (!$this->db->table_exists('pos_payment') || !$this->db->table_exists('pos_payment_line')) {
            return ['ok' => false, 'message' => 'Schema payment POS belum tersedia. Jalankan migration payment terlebih dulu.'];
        }
        if (!$this->db->field_exists('order_id', 'pos_payment')) {
            return ['ok' => false, 'message' => 'Schema payment POS belum lengkap.'];
        }
        $orderIdNullable = $this->column_is_nullable('pos_payment', 'order_id');
        if (!$orderIdNullable) {
            return ['ok' => false, 'message' => 'Schema DP belum siap. Jalankan patch agar pos_payment.order_id boleh NULL untuk deposit mandiri.'];
        }

        $memberId = !empty($payload['member_id']) ? (int)$payload['member_id'] : null;
        $memberName = trim((string)($payload['member_name'] ?? ''));
        $mobilePhone = trim((string)($payload['mobile_phone'] ?? ''));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $paymentMethodId = !empty($payload['payment_method_id']) ? (int)$payload['payment_method_id'] : 0;
        $notes = $this->nullable_text($payload['notes'] ?? '');

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal DP wajib lebih besar dari nol.'];
        }
        if ($paymentMethodId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP wajib dipilih.'];
        }
        $method = $this->find_payment_method($paymentMethodId);
        if (!$method || (int)($method['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP tidak valid atau tidak aktif.'];
        }
        $companyAccountId = (int)($method['company_account_id'] ?? 0);
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Metode pembayaran DP belum terhubung ke rekening perusahaan.'];
        }
        if (!$this->db->table_exists('fin_company_account') || !$this->db->table_exists('fin_account_mutation_log')) {
            return ['ok' => false, 'message' => 'Schema keuangan belum siap. DP harus terhubung ke rekening perusahaan terlebih dulu.'];
        }
        $companyAccount = $this->db->query(
            'SELECT * FROM fin_company_account WHERE id = ? AND is_active = 1 LIMIT 1',
            [$companyAccountId]
        )->row_array();
        if (!$companyAccount) {
            return ['ok' => false, 'message' => 'Rekening perusahaan untuk metode pembayaran ini tidak ditemukan atau tidak aktif.'];
        }
        if ($memberId === null && $memberName === '') {
            return ['ok' => false, 'message' => 'Pilih member atau isi nama customer untuk DP.'];
        }
        if ($memberId !== null && !$this->local_record_exists('crm_member', $memberId)) {
            return ['ok' => false, 'message' => 'Member tidak ditemukan.'];
        }

        $activeSession = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        $this->db->trans_begin();
        try {
            if ($memberId === null) {
                $memberSave = $this->save_member([
                    'member_name' => $memberName,
                    'mobile_phone' => $mobilePhone,
                    'member_status' => 'ACTIVE',
                    'joined_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Auto dibuat dari input DP POS',
                ]);
                if (!($memberSave['ok'] ?? false)) {
                    throw new RuntimeException((string)($memberSave['message'] ?? 'Gagal membuat member dari input DP.'));
                }
                $memberId = (int)($memberSave['id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Member DP gagal dibuat.');
                }
            }

            $now = date('Y-m-d H:i:s');
            $paymentNo = $this->generate_pos_payment_no('DEPOSIT', $now);
            $paymentPayload = [
                'payment_no' => $paymentNo,
                'order_id' => null,
                'shift_id' => !empty($activeSession['shift_id']) ? (int)$activeSession['shift_id'] : null,
                'cashier_session_id' => !empty($activeSession['id']) ? (int)$activeSession['id'] : null,
                'cashier_employee_id' => $actorEmployeeId,
                'member_id' => $memberId,
                'payment_type' => 'DEPOSIT',
                'payment_status' => 'PAID',
                'paid_at' => $now,
                'gross_amount' => $amount,
                'discount_amount' => 0,
                'promo_amount' => 0,
                'voucher_amount' => 0,
                'point_redeem_amount' => 0,
                'compliment_amount' => 0,
                'deposit_applied_amount' => 0,
                'net_amount' => $amount,
                'change_amount' => 0,
                'notes' => $notes,
                'created_at' => $now,
            ];
            $this->db->insert('pos_payment', $paymentPayload);
            $paymentId = (int)$this->db->insert_id();
            if ($paymentId <= 0) {
                throw new RuntimeException('Gagal membuat header DP.');
            }

            $paymentLinePayload = [
                'payment_id' => $paymentId,
                'line_no' => 1,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'reference_no' => $this->nullable_text($payload['reference_no'] ?? ''),
                'gateway_txn_id' => null,
                'received_at' => $now,
                'status' => 'PAID',
                'created_at' => $now,
            ];
            if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
                $paymentLinePayload['company_account_id'] = $companyAccountId;
            }
            $this->db->insert('pos_payment_line', $paymentLinePayload);

            $financeResult = $this->post_company_account_mutation([
                'account_id' => $companyAccountId,
                'mutation_type' => 'IN',
                'amount' => $amount,
                'mutation_date' => $now,
                'ref_module' => 'POS',
                'ref_table' => 'pos_payment',
                'ref_id' => $paymentId,
                'ref_no' => $paymentNo,
                'notes' => 'DP POS diterima',
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
            if (!($financeResult['ok'] ?? false)) {
                throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal posting DP ke rekening perusahaan.'));
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan DP.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'id' => $paymentId,
                'payment_no' => $paymentNo,
                'member_id' => $memberId,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function void_deposit(int $id, int $actorEmployeeId): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Deposit tidak valid.'];
        }
        $row = $this->db->from('pos_payment')
            ->where('id', $id)
            ->where('payment_type', 'DEPOSIT')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Deposit tidak ditemukan.'];
        }
        if (round((float)($row['deposit_applied_amount'] ?? 0), 2) > 0) {
            return ['ok' => false, 'message' => 'Deposit sudah dipakai sebagian/seluruhnya dan tidak bisa di-void langsung.'];
        }
        if (strtoupper((string)($row['payment_status'] ?? '')) === 'VOID') {
            return ['ok' => true, 'id' => $id];
        }

        $line = $this->db->from('pos_payment_line')
            ->where('payment_id', $id)
            ->where('status', 'PAID')
            ->order_by('line_no', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();
        if (!$line) {
            return ['ok' => false, 'message' => 'Line pembayaran DP tidak ditemukan.'];
        }
        $companyAccountId = 0;
        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $companyAccountId = (int)($line['company_account_id'] ?? 0);
        }
        if ($companyAccountId <= 0) {
            $method = $this->find_payment_method((int)($line['payment_method_id'] ?? 0));
            $companyAccountId = (int)($method['company_account_id'] ?? 0);
        }
        if ($companyAccountId <= 0) {
            return ['ok' => false, 'message' => 'Rekening perusahaan untuk reversal DP tidak ditemukan.'];
        }

        $this->db->trans_begin();
        try {
            $this->db->where('id', $id)->update('pos_payment', [
                'payment_status' => 'VOID',
                'notes' => trim((string)($row['notes'] ?? '') . ' | VOID by employee ' . $actorEmployeeId),
            ]);
            $this->db->where('payment_id', $id)->update('pos_payment_line', ['status' => 'VOID']);

            $financeResult = $this->post_company_account_mutation([
                'account_id' => $companyAccountId,
                'mutation_type' => 'OUT',
                'amount' => round((float)($row['net_amount'] ?? 0), 2),
                'mutation_date' => date('Y-m-d H:i:s'),
                'ref_module' => 'POS',
                'ref_table' => 'pos_payment',
                'ref_id' => $id,
                'ref_no' => (string)($row['payment_no'] ?? null),
                'notes' => 'VOID DP POS',
                'created_by' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            ]);
            if (!($financeResult['ok'] ?? false)) {
                throw new RuntimeException((string)($financeResult['message'] ?? 'Gagal reversal DP dari rekening perusahaan.'));
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal void DP.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function find_payment_method(int $id): ?array
    {
        return $this->coredb->from('pos_payment_method')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    private function post_company_account_mutation(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        $mutationType = strtoupper(trim((string)($payload['mutation_type'] ?? 'IN')));
        $amount = round((float)($payload['amount'] ?? 0), 2);
        $mutationDate = (string)($payload['mutation_date'] ?? date('Y-m-d H:i:s'));
        if ($accountId <= 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'Payload mutasi rekening POS tidak valid.'];
        }
        if (!in_array($mutationType, ['IN', 'OUT'], true)) {
            return ['ok' => false, 'message' => 'Jenis mutasi rekening POS tidak valid.'];
        }

        $account = $this->db->query(
            'SELECT * FROM fin_company_account WHERE id = ? LIMIT 1 FOR UPDATE',
            [$accountId]
        )->row_array();
        if (!$account) {
            return ['ok' => false, 'message' => 'Rekening perusahaan tidak ditemukan saat posting mutasi POS.'];
        }

        $balanceBefore = round((float)($account['current_balance'] ?? 0), 2);
        $balanceAfter = $mutationType === 'IN'
            ? round($balanceBefore + $amount, 2)
            : round($balanceBefore - $amount, 2);

        $this->db->where('id', $accountId)->update('fin_company_account', [
            'current_balance' => $balanceAfter,
        ]);
        $this->db->insert('fin_account_mutation_log', [
            'mutation_no' => $this->generate_account_mutation_no($mutationDate),
            'mutation_date' => date('Y-m-d', strtotime($mutationDate)),
            'account_id' => $accountId,
            'mutation_type' => $mutationType,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'ref_module' => (string)($payload['ref_module'] ?? 'POS'),
            'ref_table' => (string)($payload['ref_table'] ?? 'pos_payment'),
            'ref_id' => !empty($payload['ref_id']) ? (int)$payload['ref_id'] : null,
            'ref_no' => $this->nullable_text($payload['ref_no'] ?? null),
            'notes' => $this->nullable_text($payload['notes'] ?? null),
            'created_by' => !empty($payload['created_by']) ? (int)$payload['created_by'] : null,
        ]);

        if ($this->db->trans_status() === false) {
            return ['ok' => false, 'message' => 'Gagal menyimpan mutasi rekening POS.'];
        }

        return [
            'ok' => true,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    private function column_is_nullable(string $table, string $column): bool
    {
        $row = $this->db
            ->select('IS_NULLABLE')
            ->from('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $this->db->database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->limit(1)
            ->get()
            ->row_array();

        return strtoupper((string)($row['IS_NULLABLE'] ?? 'NO')) === 'YES';
    }

    public function deposit_payment_method_options(): array
    {
        if (!$this->db->table_exists('pos_payment_method')) {
            return [];
        }

        return $this->coredb
            ->select('id, method_code, method_name, method_type')
            ->from('pos_payment_method')
            ->where('is_active', 1)
            ->where_not_in('method_type', ['COMPLIMENT'])
            ->order_by('method_type', 'ASC')
            ->order_by('method_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function save_payment_method(array $data): array
    {
        $db = $this->coredb;
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
            $methodCode = $this->generate_named_code($db, 'pos_payment_method', 'method_code', $name, 'PM-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_payment_method', 'method_code', $methodCode, $id)) {
            return ['ok' => false, 'message' => 'Kode metode pembayaran sudah dipakai.'];
        }

        $companyAccountId = !empty($data['company_account_id']) ? (int)$data['company_account_id'] : null;
        if ($companyAccountId !== null && !$this->company_account_exists($companyAccountId)) {
            return ['ok' => false, 'message' => 'Rekening perusahaan tidak ditemukan.'];
        }

        $payload = [
            'method_code' => $methodCode,
            'method_name' => $name,
            'method_type' => $type,
            'company_account_id' => $companyAccountId,
        ];

        if ($id > 0) {
            if (!$this->find_payment_method($id)) {
                return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
            }
            $db->where('id', $id)->update('pos_payment_method', $payload);
        } else {
            $db->insert('pos_payment_method', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_payment_method(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_payment_method($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_payment_method', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function outlet_terminal_filter_options(): array
    {
        return [
            'outlets' => $this->active_outlets(),
        ];
    }

    public function outlet_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));

        $db = $this->coredb;
        $db->from('pos_outlet o');
        if ($q !== '') {
            $db->group_start()
                ->like('o.outlet_code', $q)
                ->or_like('o.outlet_name', $q)
                ->or_like('o.address', $q)
                ->or_like('o.phone', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('o.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('o.is_active', 0);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('o.*')
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

        $db = $this->coredb;
        $db->from('pos_terminal t')
            ->join('pos_outlet o', 'o.id = t.outlet_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('t.terminal_code', $q)
                ->or_like('t.terminal_name', $q)
                ->or_like('t.device_key', $q)
                ->or_like('o.outlet_name', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('t.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('t.is_active', 0);
        }
        if ($outletId > 0) {
            $db->where('t.outlet_id', $outletId);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('t.*, o.outlet_name')
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
        return $this->coredb->from('pos_outlet')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_outlet(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['outlet_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama outlet wajib diisi.'];
        }

        $scope = strtoupper(trim((string)($data['outlet_scope'] ?? 'REGULAR')));
        if (!in_array($scope, ['REGULAR', 'EVENT', 'MIXED'], true)) {
            $scope = 'REGULAR';
        }

        $code = strtoupper(trim((string)($data['outlet_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($db, 'pos_outlet', 'outlet_code', $name, 'OUT-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_outlet', 'outlet_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode outlet sudah dipakai di core.'];
        }

        $payload = [
            'outlet_code' => $code,
            'outlet_name' => $name,
            'outlet_scope' => $scope,
            'address' => $this->nullable_text($data['address'] ?? ''),
            'phone' => $this->nullable_text($data['phone'] ?? ''),
        ];

        if ($id > 0) {
            if (!$this->find_outlet($id)) {
                return ['ok' => false, 'message' => 'Outlet tidak ditemukan di core.'];
            }
            $db->where('id', $id)->update('pos_outlet', $payload);
        } else {
            $db->insert('pos_outlet', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_outlet(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_outlet($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Outlet tidak ditemukan di core.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_outlet', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function find_terminal(int $id): ?array
    {
        return $this->coredb->from('pos_terminal')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_terminal(array $data): array
    {
        $db = $this->coredb;
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['terminal_name'] ?? ''));
        $outletId = (int)($data['outlet_id'] ?? 0);
        if ($name === '' || $outletId <= 0) {
            return ['ok' => false, 'message' => 'Outlet dan nama terminal wajib diisi.'];
        }
        if (!$this->find_outlet($outletId)) {
            return ['ok' => false, 'message' => 'Outlet terminal tidak ditemukan di core.'];
        }

        $osType = strtoupper(trim((string)($data['os_type'] ?? 'WEB')));
        if (!in_array($osType, ['WINDOWS', 'UBUNTU', 'ANDROID', 'IOS', 'WEB', 'OTHER'], true)) {
            $osType = 'WEB';
        }

        $code = strtoupper(trim((string)($data['terminal_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($db, 'pos_terminal', 'terminal_code', $name, 'TERM-', $id, 30);
        } elseif ($this->code_exists($db, 'pos_terminal', 'terminal_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode terminal sudah dipakai di core.'];
        }

        $deviceKey = trim((string)($data['device_key'] ?? ''));
        if ($deviceKey !== '' && $this->terminal_device_key_exists($deviceKey, $id)) {
            return ['ok' => false, 'message' => 'Device key terminal sudah dipakai di core.'];
        }

        $payload = [
            'outlet_id' => $outletId,
            'terminal_code' => $code,
            'terminal_name' => $name,
            'device_key' => $deviceKey === '' ? null : $deviceKey,
            'os_type' => $osType,
        ];

        if ($id > 0) {
            if (!$this->find_terminal($id)) {
                return ['ok' => false, 'message' => 'Terminal tidak ditemukan di core.'];
            }
            $db->where('id', $id)->update('pos_terminal', $payload);
        } else {
            $db->insert('pos_terminal', $payload);
            $id = (int)$db->insert_id();
        }

        return ['ok' => true, 'id' => $id];
    }

    public function toggle_terminal(int $id): array
    {
        $db = $this->coredb;
        $row = $this->find_terminal($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Terminal tidak ditemukan di core.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->where('id', $id)->update('pos_terminal', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function active_company_accounts(): array
    {
        return $this->coredb->select('id, account_name, account_type, bank_name, account_no, account_holder')
            ->from('fin_company_account')
            ->where('is_active', 1)
            ->order_by('account_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function active_outlets(): array
    {
        return $this->coredb->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function printer_filter_options(): array
    {
        return [
            'template_masters' => $this->core_printer_template_master_options(),
            'templates' => $this->core_printer_template_options(),
            'outlets' => $this->active_outlet_options(),
            'terminals' => $this->active_terminal_options(),
            'printers' => $this->core_printer_options(),
        ];
    }

    public function printer_general_settings(): array
    {
        $defaults = [
            'title' => 'NAMUA COFFEE N EATERY',
            'subtitle' => 'Jl. Magnolia, Desa Kabongan Kidul, Rembang',
            'logo_url' => base_url('assets/img/logo.png'),
            'wifi_name' => '',
            'wifi_password' => '',
            'show_customer_point_info' => 0,
            'show_customer_stamp_info' => 0,
            'show_customer_voucher' => 0,
            'customer_voucher_limit' => 1,
            'customer_voucher_message_template' => 'Selamat, Anda mendapat voucher {voucher_benefit}. Gunakan sebelum {voucher_expiry}.',
            'customer_voucher_align' => 'CENTER',
            'header_lines' => ['ORDER CEPAT, SAJI HANGAT.'],
            'footer_lines' => ['TERIMA KASIH SUDAH BERKUNJUNG'],
        ];

        if (!$this->coredb->table_exists('pos_printer_template_master')) {
            return ['row' => null, 'payload' => $defaults];
        }

        $row = $this->coredb
            ->from('pos_printer_template_master')
            ->where('master_code', 'POS-GLOBAL')
            ->limit(1)
            ->get()
            ->row_array();

        $payload = [];
        if ($row && !empty($row['master_payload'])) {
            $decoded = json_decode((string)$row['master_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $merged = array_merge($defaults, $payload);
        if (!empty($merged['logo_url']) && stripos((string)$merged['logo_url'], 'core.namuacoffee.com/assets/img/logo') !== false) {
            $merged['logo_url'] = base_url('assets/img/logo.png');
        }

        return ['row' => $row ?: null, 'payload' => $merged];
    }

    public function save_printer_general_settings(array $data): array
    {
        $payload = [
            'title' => trim((string)($data['title'] ?? 'NAMUA COFFEE N EATERY')),
            'subtitle' => trim((string)($data['subtitle'] ?? '')),
            'logo_url' => trim((string)($data['logo_url'] ?? base_url('assets/img/logo.png'))),
            'wifi_name' => trim((string)($data['wifi_name'] ?? '')),
            'wifi_password' => trim((string)($data['wifi_password'] ?? '')),
            'show_customer_point_info' => !empty($data['show_customer_point_info']) ? 1 : 0,
            'show_customer_stamp_info' => !empty($data['show_customer_stamp_info']) ? 1 : 0,
            'show_customer_voucher' => !empty($data['show_customer_voucher']) ? 1 : 0,
            'customer_voucher_limit' => max(1, min(5, (int)($data['customer_voucher_limit'] ?? 1))),
            'customer_voucher_message_template' => trim((string)($data['customer_voucher_message_template'] ?? '')),
            'customer_voucher_align' => strtoupper(trim((string)($data['customer_voucher_align'] ?? 'CENTER'))),
            'header_lines' => $data['header_lines'] ?? [],
            'footer_lines' => $data['footer_lines'] ?? [],
        ];

        if ($payload['logo_url'] === '' || stripos($payload['logo_url'], 'core.namuacoffee.com/assets/img/logo') !== false) {
            $payload['logo_url'] = base_url('assets/img/logo.png');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['ok' => false, 'message' => 'Payload pengaturan umum printer tidak valid.'];
        }

        $row = $this->coredb
            ->from('pos_printer_template_master')
            ->where('master_code', 'POS-GLOBAL')
            ->limit(1)
            ->get()
            ->row_array();

        $record = [
            'master_code' => 'POS-GLOBAL',
            'master_name' => 'Pengaturan Umum POS',
            'master_payload' => $json,
            'document_type' => 'OTHER',
            'description' => 'Pengaturan global printer POS: branding, Wi-Fi, dan info loyalty.',
            'is_default' => 0,
            'is_active' => 1,
        ];

        $this->coredb->trans_begin();
        try {
            if ($row) {
                $this->coredb->where('id', (int)$row['id'])->update('pos_printer_template_master', $record);
                $id = (int)$row['id'];
            } else {
                $this->coredb->insert('pos_printer_template_master', $record);
                $id = (int)$this->coredb->insert_id();
            }
            if ($this->coredb->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan pengaturan umum printer.');
            }
            $this->coredb->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->coredb->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function printer_template_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $documentType = strtoupper(trim((string)($filters['document_type'] ?? 'ALL')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 10)));

        $db = $this->coredb;
        $db->from('pos_printer_template t');
        if ($q !== '') {
            $db->group_start()
                ->like('t.template_code', $q)
                ->or_like('t.template_name', $q)
                ->or_like('t.document_type', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('t.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('t.is_active', 0);
        }
        if (in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $db->where('t.document_type', $documentType);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $rows = $db->select('t.*, NULL AS master_name', false)
            ->order_by('t.document_type', 'ASC')
            ->order_by('t.template_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_printer_template(int $id): ?array
    {
        return $this->coredb->from('pos_printer_template')->where('id', $id)->limit(1)->get()->row_array() ?: null;
    }

    public function active_printer_template_options(): array
    {
        return $this->core_printer_template_options();
    }

    public function save_printer_template(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['template_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama template printer wajib diisi.'];
        }

        $documentType = strtoupper(trim((string)($data['document_type'] ?? 'RECEIPT')));
        if (!in_array($documentType, ['RECEIPT', 'KITCHEN_TICKET', 'VOID_SLIP', 'REFUND_SLIP', 'DEPOSIT_RECEIPT'], true)) {
            $documentType = 'RECEIPT';
        }

        $code = strtoupper(trim((string)($data['template_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($this->coredb, 'pos_printer_template', 'template_code', $name, 'TPL-', $id, 40);
        } elseif ($this->code_exists($this->coredb, 'pos_printer_template', 'template_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode template printer sudah dipakai.'];
        }

        $payloadText = trim((string)($data['template_payload'] ?? ''));
        if ($payloadText === '') {
            $payloadText = '{}';
        } else {
            $decoded = json_decode($payloadText, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'message' => 'Template payload harus berupa JSON yang valid.'];
            }
            $payloadText = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $payload = [
            'template_code' => $code,
            'template_name' => $name,
            'document_type' => $documentType,
            'template_payload' => $payloadText,
            'is_default' => (int)($data['is_default'] ?? 0) === 1 ? 1 : 0,
            'is_active' => (int)($data['is_active'] ?? 1) === 1 ? 1 : 0,
        ];

        $this->coredb->trans_begin();
        try {
            if ($payload['is_default'] === 1) {
                $this->coredb->set('is_default', 0);
                $this->coredb->where('document_type', $documentType);
                if ($id > 0) {
                    $this->coredb->where('id !=', $id);
                }
                $this->coredb->update('pos_printer_template');
            }

            if ($id > 0) {
                if (!$this->find_printer_template($id)) {
                    throw new RuntimeException('Template printer tidak ditemukan.');
                }
                $this->coredb->where('id', $id)->update('pos_printer_template', $payload);
            } else {
                $this->coredb->insert('pos_printer_template', $payload);
                $id = (int)$this->coredb->insert_id();
            }
            if ($this->coredb->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan template printer.');
            }
            $this->coredb->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->coredb->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_printer_template(int $id): array
    {
        $row = $this->find_printer_template($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Template printer tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->coredb->where('id', $id)->update('pos_printer_template', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function printer_profile_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 10)));

        $db = $this->coredb;
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $db->from('pos_printer p')
            ->join('pos_outlet o', 'o.id = p.outlet_id', 'left')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left');
        $hasContentSetting = $this->coredb->table_exists('pos_printer_content_setting');
        if ($hasContentSetting) {
            $db->join('pos_printer_content_setting cs', 'cs.printer_id = p.id', 'left');
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.printer_code', $q)
                ->or_like('p.printer_name', $q)
                ->or_like('o.outlet_name', $q);
            if ($hasPrinterRole) {
                $db->or_like('p.printer_role', $q);
            }
            $db->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('p.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('p.is_active', 0);
        }
        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $contentSelect = $hasContentSetting
            ? "COALESCE(cs.show_logo, 1) AS show_logo,\n            COALESCE(cs.price_visibility, 'always') AS price_visibility,\n            COALESCE(cs.show_footer, 1) AS show_footer,"
            : "1 AS show_logo,\n            'always' AS price_visibility,\n            1 AS show_footer,";
        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        $rows = $db->select(" 
            p.id, 
            p.printer_code AS profile_code, 
            p.printer_name AS profile_name, 
            {$roleSelect} AS printer_role, 
            {$scopeSelect} AS print_scope, 
            p.outlet_id, 
            o.outlet_name, 
            COALESCE(pf.paper_width_mm, 80) AS paper_width_mm,
            COALESCE(pf.chars_per_line, 48) AS chars_per_line,
            COALESCE(pf.copies, 1) AS copy_count,
            COALESCE(pf.cut_mode, 'PARTIAL') AS cut_mode,
            COALESCE(pf.open_drawer, 0) AS open_drawer,
            {$contentSelect}
            p.is_active
        ", false)
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('p.printer_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_printer_profile(int $id): ?array 
    { 
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $hasContentSetting = $this->coredb->table_exists('pos_printer_content_setting'); 
        $contentSelect = $hasContentSetting 
            ? "COALESCE(cs.show_logo, 1) AS show_logo,\n                COALESCE(cs.price_visibility, 'always') AS price_visibility,\n                COALESCE(cs.show_footer, 1) AS show_footer," 
            : "1 AS show_logo,\n                'always' AS price_visibility,\n                1 AS show_footer,"; 
        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        $db = $this->coredb->select(" 
                p.id, 
                p.printer_code AS profile_code, 
                p.printer_name AS profile_name, 
                {$roleSelect} AS printer_role, 
                {$scopeSelect} AS print_scope, 
                p.outlet_id, 
                COALESCE(pf.paper_width_mm, 80) AS paper_width_mm,
                COALESCE(pf.chars_per_line, 48) AS chars_per_line,
                COALESCE(pf.copies, 1) AS copy_count,
                COALESCE(pf.cut_mode, 'PARTIAL') AS cut_mode,
                COALESCE(pf.open_drawer, 0) AS open_drawer,
                {$contentSelect}
                p.is_active
            ", false)
            ->from('pos_printer p')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left');
        if ($hasContentSetting) {
            $db->join('pos_printer_content_setting cs', 'cs.printer_id = p.id', 'left');
        }
        return $db->where('p.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function save_printer_profile(array $data): array
    {
        $printerId = (int)($data['printer_id'] ?? $data['id'] ?? 0);
        if ($printerId <= 0 || !$this->core_record_exists('pos_printer', $printerId)) {
            return ['ok' => false, 'message' => 'Printer untuk pengaturan output tidak ditemukan.'];
        }

        $profilePayload = [
            'paper_width_mm' => ((int)($data['paper_width_mm'] ?? 80) === 58) ? 58 : 80,
            'chars_per_line' => max(24, min(64, (int)($data['chars_per_line'] ?? 48))),
            'copies' => max(1, min(10, (int)($data['copy_count'] ?? 1))),
            'encoding' => 'UTF-8',
            'cut_mode' => in_array(strtoupper(trim((string)($data['cut_mode'] ?? 'PARTIAL'))), ['NONE', 'PARTIAL', 'FULL'], true) ? strtoupper(trim((string)($data['cut_mode'] ?? 'PARTIAL'))) : 'PARTIAL',
            'open_drawer' => (int)($data['open_drawer'] ?? 0) === 1 ? 1 : 0,
        ];
        $contentPayload = [
            'show_logo' => (int)($data['show_logo'] ?? 1) === 1 ? 1 : 0,
            'price_visibility' => (int)($data['show_price'] ?? 1) === 1 ? 'always' : 'never',
            'show_footer' => (int)($data['show_footer'] ?? 1) === 1 ? 1 : 0,
        ];
        $printerPayload = [
            'is_active' => (int)($data['is_active'] ?? 1) === 1 ? 1 : 0,
        ];

        $this->coredb->trans_begin();
        try {
            $profileExists = (int)$this->coredb->from('pos_printer_profile')->where('printer_id', $printerId)->count_all_results() > 0;
            if ($profileExists) {
                $this->coredb->where('printer_id', $printerId)->update('pos_printer_profile', $profilePayload);
            } else {
                $profilePayload['printer_id'] = $printerId;
                $this->coredb->insert('pos_printer_profile', $profilePayload);
            }

            if ($this->coredb->table_exists('pos_printer_content_setting')) {
                $contentExists = (int)$this->coredb->from('pos_printer_content_setting')->where('printer_id', $printerId)->count_all_results() > 0;
                if ($contentExists) {
                    $this->coredb->where('printer_id', $printerId)->update('pos_printer_content_setting', $contentPayload);
                } else {
                    $contentPayload['printer_id'] = $printerId;
                    $this->coredb->insert('pos_printer_content_setting', $contentPayload);
                }
            }

            $this->coredb->where('id', $printerId)->update('pos_printer', $printerPayload);
            if ($this->coredb->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan pengaturan printer.');
            }
            $this->coredb->trans_commit();
            return ['ok' => true, 'id' => $printerId];
        } catch (Throwable $e) {
            $this->coredb->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_printer_profile(int $id): array
    {
        $row = $this->find_printer_profile($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Profile printer tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->coredb->where('id', $id)->update('pos_printer', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function printer_device_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ACTIVE')));
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 10)));

        $db = $this->coredb; 
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $hasMacAddress = $this->core_field_exists('pos_printer', 'mac_address');
        $hasPythonPort = $this->core_field_exists('pos_printer', 'python_port');
        $db->from('pos_printer p') 
            ->join('pos_outlet o', 'o.id = p.outlet_id', 'left') 
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left'); 
        if ($q !== '') { 
            $db->group_start() 
                ->like('p.printer_code', $q) 
                ->or_like('p.printer_name', $q) 
                ->or_like('p.agent_host', $q) 
                ->or_like('p.device_name', $q) 
                ->or_like('p.ip_address', $q) 
                ->or_like('o.outlet_name', $q);
            if ($hasPrinterRole) {
                $db->or_like('p.printer_role', $q);
            }
            if ($hasMacAddress) {
                $db->or_like('p.mac_address', $q);
            }
            if ($hasPythonPort) {
                $db->or_like('p.python_port', $q);
            }
            $db->group_end(); 
        } 
        if ($status === 'ACTIVE') {
            $db->where('p.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('p.is_active', 0);
        }
        if ($outletId > 0) {
            $db->where('p.outlet_id', $outletId);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        $macSelect = $hasMacAddress ? 'p.mac_address' : "NULL";
        $pythonSelect = $hasPythonPort ? 'p.python_port' : "NULL";
        $rows = $db->select(" 
              p.id, 
              p.printer_code AS device_code, 
              p.printer_name AS device_name, 
              p.outlet_id, 
              o.outlet_name, 
              {$roleSelect} AS printer_role, 
              {$scopeSelect} AS print_scope, 
              p.connection_type, 
              p.agent_os,
              p.agent_os AS os_name,
              p.agent_host, 
              p.device_name AS system_device_name,
              p.device_name AS os_device_name,
              p.ip_address, 
              p.port, 
              {$macSelect} AS mac_address, 
              {$pythonSelect} AS python_port, 
              COALESCE(pf.paper_width_mm, 80) AS paper_width_mm, 
            p.is_active 
        ", false) 
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('p.printer_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_printer_device(int $id): ?array 
    { 
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $hasMacAddress = $this->core_field_exists('pos_printer', 'mac_address');
        $hasPythonPort = $this->core_field_exists('pos_printer', 'python_port');
        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        $macSelect = $hasMacAddress ? 'p.mac_address' : "NULL";
        $pythonSelect = $hasPythonPort ? 'p.python_port' : "NULL";
        return $this->coredb->select(" 
                  p.id, 
                  p.printer_code AS device_code, 
                  p.printer_name AS device_name, 
                  p.outlet_id, 
                  {$roleSelect} AS printer_role, 
                  {$scopeSelect} AS print_scope, 
                  p.connection_type, 
                  p.agent_os,
                  p.agent_os AS os_name,
                  p.agent_host, 
                  p.device_name AS system_device_name,
                  p.device_name AS os_device_name,
                  p.ip_address, 
                  p.port, 
                  {$macSelect} AS mac_address, 
                  {$pythonSelect} AS python_port, 
                  COALESCE(pf.paper_width_mm, 80) AS paper_width_mm, 
                p.is_active 
            ", false) 
            ->from('pos_printer p')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left')
            ->where('p.id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function active_printer_preview_options(): array
    {
        $db = $this->coredb;
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $hasMacAddress = $this->core_field_exists('pos_printer', 'mac_address');
        $hasPythonPort = $this->core_field_exists('pos_printer', 'python_port');
        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        $macSelect = $hasMacAddress ? 'p.mac_address' : "NULL";
        $pythonSelect = $hasPythonPort ? 'p.python_port' : "NULL";

        return $db->select("
                p.id,
                p.printer_code,
                p.printer_name,
                {$roleSelect} AS printer_role,
                {$scopeSelect} AS print_scope,
                p.connection_type,
                p.agent_host,
                p.device_name AS system_device_name,
                {$macSelect} AS mac_address,
                {$pythonSelect} AS python_port,
                o.outlet_name,
                COALESCE(pf.paper_width_mm, 80) AS paper_width_mm,
                COALESCE(pf.chars_per_line, CASE WHEN COALESCE(pf.paper_width_mm, 80) = 58 THEN 32 ELSE 48 END) AS chars_per_line,
                COALESCE(pf.copies, 1) AS copies
            ", false)
            ->from('pos_printer p')
            ->join('pos_outlet o', 'o.id = p.outlet_id', 'left')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left')
            ->where('p.is_active', 1)
            ->order_by('o.outlet_name', 'ASC')
            ->order_by('p.printer_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function save_printer_device(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['device_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama device printer wajib diisi.'];
        }
        $outletId = !empty($data['outlet_id']) ? (int)$data['outlet_id'] : null;
        if ($outletId !== null && !$this->core_record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet printer tidak ditemukan di database core.'];
        }

        $printerRole = strtoupper(trim((string)($data['printer_role'] ?? 'CUSTOM')));
        if (!in_array($printerRole, ['KASIR', 'BAR', 'KITCHEN', 'CHECKER', 'CUSTOM'], true)) {
            $printerRole = 'CUSTOM';
        }
        $printScope = strtoupper(trim((string)($data['print_scope'] ?? 'DIVISION')));
        if (!in_array($printScope, ['ALL', 'DIVISION'], true)) {
            $printScope = 'DIVISION';
        }
        $connectionType = strtoupper(trim((string)($data['connection_type'] ?? 'USB')));
        if (!in_array($connectionType, ['LOCAL_AGENT', 'LAN', 'USB'], true)) {
            $connectionType = 'USB';
        }
        $agentOs = strtoupper(trim((string)($data['agent_os'] ?? ($data['os_name'] ?? 'WINDOWS'))));
        if (!in_array($agentOs, ['WINDOWS', 'UBUNTU', 'OTHER'], true)) {
            $agentOs = 'WINDOWS';
        }
        $code = strtoupper(trim((string)($data['device_code'] ?? '')));
        if ($code === '') {
            $code = $this->generate_named_code($this->coredb, 'pos_printer', 'printer_code', $name, 'PRN-', $id, 40);
        } elseif ($this->code_exists($this->coredb, 'pos_printer', 'printer_code', $code, $id)) {
            return ['ok' => false, 'message' => 'Kode device printer sudah dipakai.'];
        }
        $agentHost = strtoupper(trim((string)($data['agent_host'] ?? '')));
        $macAddress = $this->normalize_printer_mac_address((string)($data['mac_address'] ?? ''));
        $pythonPort = !empty($data['python_port']) ? (int)$data['python_port'] : null;
        if ($pythonPort !== null && ($pythonPort < 1 || $pythonPort > 65535)) {
            return ['ok' => false, 'message' => 'Python port harus di antara 1 sampai 65535.'];
        }
        $hasMacAddress = $this->core_field_exists('pos_printer', 'mac_address');
        $hasPythonPort = $this->core_field_exists('pos_printer', 'python_port');
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        if ($connectionType === 'LOCAL_AGENT' && $hasMacAddress && $macAddress === '') { 
            return ['ok' => false, 'message' => 'MAC Address wajib diisi untuk printer LOCAL_AGENT Bluetooth.']; 
        } 
        if ($connectionType === 'LOCAL_AGENT' && $hasPythonPort && $pythonPort === null) { 
            return ['ok' => false, 'message' => 'Python port wajib diisi untuk printer LOCAL_AGENT Bluetooth.']; 
        } 
        if ($connectionType === 'LOCAL_AGENT' && $agentHost === '') {
            return ['ok' => false, 'message' => 'Agent host wajib diisi untuk printer LOCAL_AGENT Bluetooth.'];
        }
        if ($hasPythonPort && $pythonPort !== null && $this->printer_python_port_exists($agentHost, $pythonPort, $id)) { 
            return ['ok' => false, 'message' => 'Python port untuk agent host ini sudah dipakai device lain.']; 
        } 
 
        $payload = [ 
            'printer_code' => $code, 
            'printer_name' => $name, 
            'outlet_id' => $outletId, 
            'agent_os' => $agentOs, 
            'agent_host' => $this->nullable_text($agentHost), 
            'connection_type' => $connectionType, 
            'device_name' => $this->nullable_text($data['system_device_name'] ?? ''), 
            'ip_address' => $this->nullable_text($data['ip_address'] ?? ''), 
            'port' => !empty($data['port']) ? (int)$data['port'] : null, 
            'is_active' => (int)($data['is_active'] ?? 1) === 1 ? 1 : 0, 
        ]; 
        if ($hasPrinterRole) {
            $payload['printer_role'] = $printerRole;
        }
        if ($hasPrintScope) {
            $payload['print_scope'] = $printScope;
        }
        if ($hasMacAddress) {
            $payload['mac_address'] = $this->nullable_text($macAddress);
        }
        if ($hasPythonPort) {
            $payload['python_port'] = $pythonPort;
        }
        $profilePayload = [ 
            'paper_width_mm' => ((int)($data['paper_width_mm'] ?? 80) === 58) ? 58 : 80,
            'chars_per_line' => ((int)($data['paper_width_mm'] ?? 80) === 58) ? 32 : 48,
            'copies' => 1,
            'encoding' => 'UTF-8',
            'cut_mode' => 'PARTIAL',
            'open_drawer' => 0,
        ];

        $this->coredb->trans_begin();
        try {
            if ($id > 0) {
                if (!$this->find_printer_device($id)) {
                    throw new RuntimeException('Device printer tidak ditemukan.');
                }
                $this->coredb->where('id', $id)->update('pos_printer', $payload);
            } else {
                $this->coredb->insert('pos_printer', $payload);
                $id = (int)$this->coredb->insert_id();
            }

            $profileExists = (int)$this->coredb->from('pos_printer_profile')->where('printer_id', $id)->count_all_results() > 0;
            if ($profileExists) {
                $this->coredb->where('printer_id', $id)->update('pos_printer_profile', $profilePayload);
            } else {
                $profilePayload['printer_id'] = $id;
                $this->coredb->insert('pos_printer_profile', $profilePayload);
            }

            if ($this->coredb->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan device printer.');
            }
            $this->coredb->trans_commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->coredb->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggle_printer_device(int $id): array
    {
        $row = $this->find_printer_device($id);
        if (!$row) {
            return ['ok' => false, 'message' => 'Device printer tidak ditemukan.'];
        }
        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $this->coredb->where('id', $id)->update('pos_printer', ['is_active' => $newValue]);
        return ['ok' => true, 'id' => $id, 'is_active' => $newValue];
    }

    public function active_printer_devices_for_agent_config(string $agentHost = ''): array 
    { 
        $agentHost = strtoupper(trim($agentHost)); 
        $hasPrinterRole = $this->core_field_exists('pos_printer', 'printer_role');
        $hasPrintScope = $this->core_field_exists('pos_printer', 'print_scope');
        $hasMacAddress = $this->core_field_exists('pos_printer', 'mac_address');
        $hasPythonPort = $this->core_field_exists('pos_printer', 'python_port');
        if (!$hasMacAddress || !$hasPythonPort) {
            return [];
        }
        $db = $this->coredb 
            ->from('pos_printer p') 
            ->join('pos_outlet o', 'o.id = p.outlet_id', 'left')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left')
            ->where('p.is_active', 1)
            ->where('p.connection_type', 'LOCAL_AGENT')
            ->where('p.mac_address IS NOT NULL', null, false)
            ->where("TRIM(COALESCE(p.mac_address, '')) <> ''", null, false)
            ->where('p.python_port IS NOT NULL', null, false);

        if ($agentHost !== '') {
            $agentHostEscaped = $this->coredb->escape($agentHost);
            $db->group_start()
                ->where("UPPER(COALESCE(p.agent_host, '')) = {$agentHostEscaped}", null, false)
                ->or_where("TRIM(COALESCE(p.agent_host, '')) = ''", null, false)
                ->group_end();
        }

        $roleSelect = $hasPrinterRole ? 'p.printer_role' : "'CUSTOM'";
        $scopeSelect = $hasPrintScope ? 'p.print_scope' : "'DIVISION'";
        return $db->select(" 
            p.id, 
            p.printer_code, 
            p.printer_name, 
            {$roleSelect} AS printer_role, 
            {$scopeSelect} AS print_scope, 
            p.agent_host, 
            p.mac_address, 
            p.python_port, 
            COALESCE(pf.paper_width_mm, 80) AS paper_width_mm, 
            COALESCE(pf.chars_per_line, CASE WHEN COALESCE(pf.paper_width_mm, 80) = 58 THEN 32 ELSE 48 END) AS chars_per_line, 
            COALESCE(pf.copies, 1) AS copies, 
            COALESCE(pf.open_drawer, 0) AS open_drawer, 
            o.outlet_name 
        ", false) 
            ->order_by('o.outlet_name', 'ASC') 
            ->order_by('p.python_port', 'ASC') 
            ->order_by('p.printer_name', 'ASC') 
            ->get() 
            ->result_array(); 
    } 

    public function order_draft_filter_options(): array
    {
        return [
            'outlets' => $this->local_outlet_options(),
            'terminals' => $this->local_terminal_options(),
        ];
    }

    public function cashier_bootstrap_options(int $employeeId = 0): array
    {
        $outlets = $this->local_outlet_options();
        $terminals = $this->local_terminal_options();
        $activeSession = $employeeId > 0 ? $this->find_active_cashier_session($employeeId) : null;
        $salesChannels = $this->sales_channel_options();
        $defaultSalesChannelId = $this->default_sales_channel_id();

        return [
            'outlets' => $outlets,
            'terminals' => $terminals,
            'sales_channels' => $salesChannels,
            'default_sales_channel_id' => $defaultSalesChannelId,
            'active_session' => $activeSession,
        ];
    }

    public function sales_channel_options(): array
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return [];
        }

        $hasAllowedTypes = $this->db->field_exists('allowed_service_types', 'pos_sales_channel');
        $db = $this->db
            ->select('id, channel_code, channel_name, service_type_default, ' . ($hasAllowedTypes ? 'allowed_service_types' : "'' AS allowed_service_types") . ', marketplace_fee_percent, is_default')
            ->from('pos_sales_channel')
            ->where('is_active', 1);
        if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
            $db->order_by('sort_order', 'ASC');
        }

        $rows = $db
            ->order_by('channel_name', 'ASC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $allowedRaw = trim((string)($row['allowed_service_types'] ?? ''));
            $row['allowed_service_type_list'] = $allowedRaw !== ''
                ? $this->explode_service_types($allowedRaw)
                : [$row['service_type_default'] ?? 'DINE_IN'];
        }
        unset($row);

        return $rows;
    }

    public function default_sales_channel_id(): ?int
    {
        if (!$this->db->table_exists('pos_sales_channel')) {
            return null;
        }

        $db = $this->db
            ->select('id')
            ->from('pos_sales_channel')
            ->where('is_active', 1);
        if ($this->db->field_exists('is_default', 'pos_sales_channel')) {
            $db->order_by('is_default', 'DESC');
        }
        if ($this->db->field_exists('sort_order', 'pos_sales_channel')) {
            $db->order_by('sort_order', 'ASC');
        }

        $row = $db
            ->order_by('id', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        $id = (int)($row['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function cashier_catalog_filter_options(): array
    {
        $divisions = $this->db
            ->select('pd.id, pd.code, pd.name, COUNT(p.id) AS product_count', false)
            ->from('mst_product_division pd')
            ->join('mst_product p', 'p.product_division_id = pd.id', 'inner')
            ->where('pd.is_active', 1)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $divisions->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $divisions->where('p.show_in_cashier', 1);
        }
        $divisionRows = $divisions
            ->group_by(['pd.id', 'pd.code', 'pd.name'])
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('pd.name', 'ASC')
            ->get()
            ->result_array();

        $categories = $this->db
            ->select('pc.id, pc.code, pc.name, pc.product_division_id, COUNT(p.id) AS product_count', false)
            ->from('mst_product_category pc')
            ->join('mst_product p', 'p.product_category_id = pc.id', 'inner')
            ->where('pc.is_active', 1)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $categories->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $categories->where('p.show_in_cashier', 1);
        }
        $categoryRows = $categories
            ->group_by(['pc.id', 'pc.code', 'pc.name', 'pc.product_division_id'])
            ->order_by('pc.sort_order', 'ASC')
            ->order_by('pc.name', 'ASC')
            ->get()
            ->result_array();

        return [
            'divisions' => $divisionRows,
            'categories' => $categoryRows,
        ];
    }

    public function stock_live_filter_options(): array
    {
        $divisions = [];
        if ($this->db->table_exists('mst_product_division')) {
            $db = $this->db
                ->select('id, code, name')
                ->from('mst_product_division')
                ->where('is_active', 1);
            if ($this->db->field_exists('sort_order', 'mst_product_division')) {
                $db->order_by('sort_order', 'ASC');
            }
            $divisions = $db
                ->order_by('name', 'ASC')
                ->get()
                ->result_array();
        }

        return [
            'outlets' => $this->local_outlet_options(),
            'divisions' => $divisions,
        ];
    }

    public function stock_live_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if (!in_array($status, ['ALL', 'AVAILABLE', 'LIMITED', 'OUT', 'HIDDEN'], true)) {
            $status = 'ALL';
        }
        $dirtyOnly = !empty($filters['dirty_only']);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 25)));

        $db = $this->db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_classification pc', 'pc.id = p.classification_id', 'left')
            ->join('mst_product_category cat', 'cat.id = p.product_category_id', 'left')
            ->where('p.is_active', 1);

        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.product_name', $q)
                ->or_like('p.product_code', $q)
                ->group_end();
        }
        if ($divisionId > 0) {
            $db->where('p.product_division_id', $divisionId);
        }
        if ($outletId > 0 && $this->db->table_exists('pos_product_availability_cache')) {
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($outletId), 'left', false);
            if ($status !== 'ALL') {
                $db->where('pac.availability_status', $status);
            }
            if ($dirtyOnly && $this->db->field_exists('is_dirty', 'pos_product_availability_cache')) {
                $db->where('pac.is_dirty', 1);
            }
        }

        $total = (int)$db->count_all_results('', false);
        $totalPages = max(1, (int)ceil($total / max(1, $limit)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.product_division_id',
            'p.default_operational_division_id',
            'p.uom_id',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'p.stock_mode',
            'pd.code AS product_division_code',
            'pd.name AS product_division_name',
            'pc.id AS classification_id',
            'pc.code AS classification_code',
            'pc.name AS classification_name',
            'cat.id AS product_category_id',
            'cat.code AS product_category_code',
            'cat.name AS product_category_name',
        ];
        if ($outletId > 0 && $this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.id AS cache_id';
            $select[] = 'pac.availability_status AS cache_availability_status';
            $select[] = 'pac.source_mode AS cache_source_mode';
            $select[] = 'pac.estimated_available_qty AS cache_estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot AS cache_bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed AS cache_override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS cache_hpp_live_snapshot';
            $select[] = 'pac.computed_at AS cache_computed_at';
            if ($this->db->field_exists('last_commit_event', 'pos_product_availability_cache')) {
                $select[] = 'pac.last_commit_event AS cache_last_commit_event';
            }
            if ($this->db->field_exists('is_dirty', 'pos_product_availability_cache')) {
                $select[] = 'pac.is_dirty AS cache_is_dirty';
            }
        }

        $db->select(implode(",\n", $select));
        if ($this->db->field_exists('sort_order', 'mst_product_division')) {
            $db->order_by('COALESCE(pd.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('pd.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_classification')) {
            $db->order_by('COALESCE(pc.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('pc.id', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product_category')) {
            $db->order_by('COALESCE(cat.sort_order, 999999)', 'ASC', false);
        }
        $db->order_by('cat.name', 'ASC');
        if ($this->db->field_exists('sort_order', 'mst_product')) {
            $db->order_by('COALESCE(p.sort_order, 999999)', 'ASC', false);
        }
        $rows = $db->order_by('p.product_name', 'ASC')
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

    public function stock_live_latest_log_map(int $outletId, array $productIds): array
    {
        $outletId = max(0, $outletId);
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($outletId <= 0 || empty($productIds) || !$this->db->table_exists('pos_product_availability_rebuild_log')) {
            return [];
        }

        $sub = $this->db
            ->select('product_id, MAX(id) AS max_id', false)
            ->from('pos_product_availability_rebuild_log')
            ->where('outlet_id', $outletId)
            ->where_in('product_id', $productIds)
            ->group_by('product_id')
            ->get_compiled_select();

        $rows = $this->db
            ->select('l.product_id, l.event_source, l.rebuilt_at, l.mismatch_flag, l.mismatch_note')
            ->from('pos_product_availability_rebuild_log l')
            ->join('(' . $sub . ') x', 'x.max_id = l.id', 'inner', false)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['product_id'] ?? 0)] = $row;
        }
        return $map;
    }

    public function find_active_cashier_session(int $employeeId): ?array
    {
        if ($employeeId <= 0 || !$this->db->table_exists('pos_cashier_session')) {
            return null;
        }

        return $this->db->select('
                s.*,
                o.outlet_name,
                t.terminal_name,
                sh.shift_no,
                sh.opening_cash,
                sh.opened_at
            ')
            ->from('pos_cashier_session s')
            ->join('pos_outlet o', 'o.id = s.outlet_id', 'left')
            ->join('pos_terminal t', 't.id = s.terminal_id', 'left')
            ->join('pos_shift sh', 'sh.id = s.shift_id', 'left')
            ->where('s.employee_id', $employeeId)
            ->where('s.session_status', 'OPEN')
            ->order_by('s.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array() ?: null;
    }

    public function open_cashier_session(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee. Kasir belum bisa dibuka.'];
        }
        if (!$this->db->table_exists('pos_shift') || !$this->db->table_exists('pos_cashier_session')) {
            return ['ok' => false, 'message' => 'Schema shift/session POS belum siap.'];
        }

        $existing = $this->find_active_cashier_session($actorEmployeeId);
        if ($existing) {
            return ['ok' => true, 'session' => $existing, 'already_open' => true];
        }

        $outletId = (int)($payload['outlet_id'] ?? 0);
        $terminalId = (int)($payload['terminal_id'] ?? 0);
        $openingCash = round((float)($payload['opening_cash'] ?? 0), 2);
        if ($outletId <= 0 || !$this->local_record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet kasir wajib dipilih.'];
        }
        if ($terminalId <= 0 || !$this->local_record_exists('pos_terminal', $terminalId)) {
            return ['ok' => false, 'message' => 'Device/terminal kasir wajib dipilih.'];
        }
        if ($openingCash < 0) {
            return ['ok' => false, 'message' => 'Modal awal tidak boleh minus.'];
        }

        $terminalBusy = $this->db->from('pos_cashier_session')
            ->where('terminal_id', $terminalId)
            ->where('session_status', 'OPEN')
            ->count_all_results();
        if ($terminalBusy > 0) {
            return ['ok' => false, 'message' => 'Terminal ini masih dipakai sesi kasir lain. Tutup dulu sesi yang aktif atau pilih device lain.'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        try {
            $shiftNo = $this->generate_pos_shift_no($outletId, $now);
            $this->db->insert('pos_shift', [
                'shift_no' => $shiftNo,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'cashier_open_employee_id' => $actorEmployeeId,
                'status' => 'OPEN',
                'opened_at' => $now,
                'opening_cash' => $openingCash,
                'expected_cash' => $openingCash,
                'actual_cash' => 0,
                'variance_cash' => 0,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);
            $shiftId = (int)$this->db->insert_id();
            if ($shiftId <= 0) {
                throw new RuntimeException('Gagal membuat shift kasir POS.');
            }

            $this->db->insert('pos_shift_summary', $this->filter_table_payload('pos_shift_summary', [
                'shift_id' => $shiftId,
                'total_order_count' => 0,
                'total_gross_sales' => 0,
                'total_discount' => 0,
                'total_promo' => 0,
                'total_net_sales' => 0,
                'total_cash_sales' => 0,
                'total_non_cash_sales' => 0,
                'total_refund' => 0,
                'total_void' => 0,
                'total_deposit_receipts' => 0,
                'total_cash_deposit_receipts' => 0,
            ]));

            $sessionKey = $this->generate_pos_session_key($outletId, $terminalId, $actorEmployeeId, $now);
            $this->db->insert('pos_cashier_session', [
                'session_key' => $sessionKey,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'shift_id' => $shiftId,
                'employee_id' => $actorEmployeeId,
                'session_status' => 'OPEN',
                'login_at' => $now,
                'last_ping_at' => $now,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);
            $sessionId = (int)$this->db->insert_id();
            if ($sessionId <= 0) {
                throw new RuntimeException('Gagal membuat cashier session POS.');
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal membuka kasir POS.');
            }
            $this->db->trans_commit();

            $session = $this->find_active_cashier_session($actorEmployeeId);
            return ['ok' => true, 'session' => $session, 'session_id' => $sessionId, 'shift_id' => $shiftId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function close_cashier_session(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke employee.'];
        }
        $session = $this->find_active_cashier_session($actorEmployeeId);
        if (!$session) {
            return ['ok' => false, 'message' => 'Tidak ada sesi kasir aktif yang bisa ditutup.'];
        }

        $actualCash = round((float)($payload['actual_cash'] ?? 0), 2);
        if ($actualCash < 0) {
            return ['ok' => false, 'message' => 'Kas aktual tidak boleh minus.'];
        }

        $shiftId = (int)($session['shift_id'] ?? 0);
        $summary = $this->calculate_shift_summary($shiftId);
        $expectedCash = round(
            (float)($session['opening_cash'] ?? 0)
            + (float)($summary['total_cash_sales'] ?? 0)
            + (float)($summary['total_cash_deposit_receipts'] ?? 0)
            - (float)($summary['total_refund'] ?? 0),
            2
        );
        $varianceCash = round($actualCash - $expectedCash, 2);
        $now = date('Y-m-d H:i:s');

        $this->db->trans_begin();
        try {
            $this->db->where('shift_id', $shiftId)->update('pos_shift_summary', $this->filter_table_payload('pos_shift_summary', $summary));
            $this->db->where('id', $shiftId)->update('pos_shift', [
                'cashier_close_employee_id' => $actorEmployeeId,
                'status' => 'CLOSED',
                'closed_at' => $now,
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'variance_cash' => $varianceCash,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);
            $this->db->where('id', (int)$session['id'])->update('pos_cashier_session', [
                'session_status' => 'CLOSED',
                'logout_at' => $now,
                'last_ping_at' => $now,
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
            ]);

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menutup sesi kasir.');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'summary' => $summary + [
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $actualCash,
                    'variance_cash' => $varianceCash,
                ],
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function order_draft_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $db = $this->db;
        $hasTableNo = $this->db->field_exists('table_no', 'pos_order');
        $db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('pos_terminal pt', 'pt.id = o.terminal_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left');
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $db->join('pos_sales_channel sc', 'sc.id = o.sales_channel_id', 'left');
        }
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like('m.member_name', $q)
                ->or_like('m.member_no', $q);
            if ($hasTableNo) {
                $db->or_like('o.table_no', $q);
            }
            if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
                $db->or_like('sc.channel_name', $q);
            }
            $db->group_end();
        }
        if ($status === 'DRAFT') {
            $db->where_in('o.status', ['DRAFT', 'PENDING']);
        } elseif ($status === 'CONFIRMED') {
            $db->where('o.status', 'CONFIRMED');
        }
        if ($outletId > 0) {
            $db->where('o.outlet_id', $outletId);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);
        $select = '
            o.id, o.order_no, o.service_type, o.status, o.stock_commit_status, o.ordered_at, o.confirmed_at,
            o.guest_count, ' . ($hasTableNo ? 'o.table_no' : 'NULL AS table_no') . ', o.grand_total, o.notes,
            po.outlet_name, pt.terminal_name, e.employee_name,
            m.member_no, m.member_name';
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $select .= ',
            o.sales_channel_id, sc.channel_code AS sales_channel_code, sc.channel_name AS sales_channel_name';
        }

        $rows = $db->select($select)
            ->order_by('o.ordered_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_order_draft(int $id): ?array
    {
        $headerDb = $this->db
            ->from('pos_order o')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join('auth_user au', 'au.employee_id = o.cashier_employee_id', 'left');
        $headerSelect = '
                o.*,
                m.member_no,
                m.member_name,
                m.mobile_phone AS member_mobile_phone,
                e.employee_name AS cashier_employee_name,
                UPPER(COALESCE(au.username, \'\')) AS cashier_username';
        if ($this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order')) {
            $headerDb->join('pos_sales_channel sc', 'sc.id = o.sales_channel_id', 'left');
            $headerSelect .= ',
                sc.channel_code AS sales_channel_code,
                sc.channel_name AS sales_channel_name';
        }

        $header = $headerDb
            ->select($headerSelect)
            ->where('o.id', $id)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$header) {
            return null;
        }

        $lines = $this->db->select('
                l.*,
                p.product_code,
                p.product_name,
                b.bundle_code,
                b.bundle_name,
                pd.name AS product_division_name,
                u.code AS uom_code
            ')
            ->from('pos_order_line l')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('pos_product_bundle b', 'b.id = l.bundle_id', 'left')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.order_id', $id)
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

        $extras = $this->db->select('
                x.*,
                e.extra_code,
                e.extra_name,
                e.extra_type,
                e.source_kind,
                e.source_product_id,
                e.source_component_id,
                e.source_material_id,
                e.source_qty,
                e.replacement_kind,
                e.replacement_product_id,
                e.replacement_component_id,
                e.replacement_material_id,
                e.replacement_qty
            ')
            ->from('pos_order_line_extra x')
            ->join('mst_extra e', 'e.id = x.extra_id', 'left')
            ->where('x.order_id', $id)
            ->order_by('x.order_line_id', 'ASC')
            ->order_by('x.line_no', 'ASC')
            ->get()
            ->result_array();
        $extraMap = [];
        foreach ($extras as $extra) {
            $lineId = (int)($extra['order_line_id'] ?? 0);
            if (!isset($extraMap[$lineId])) {
                $extraMap[$lineId] = [];
            }
            $extraMap[$lineId][] = $extra;
        }
        foreach ($lines as &$line) {
            $line['extras'] = $extraMap[(int)($line['id'] ?? 0)] ?? [];
        }
        unset($line);

        return ['header' => $header, 'lines' => $lines];
    }

    public function order_product_search(string $q, int $outletId = 0, int $limit = 12): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'pd.name AS product_division_name',
            'u.code AS uom_code',
        ];
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.availability_status';
            $select[] = 'pac.source_mode';
            $select[] = 'pac.estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS availability_hpp_live_snapshot';
            $select[] = 'pac.last_commit_event';
        }

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_uom u', 'u.id = p.uom_id', 'left');
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $joinOutletId = $outletId > 0 ? $outletId : -1;
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($joinOutletId), 'left', false);
        }
        $db->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        $db->group_start()
            ->like('p.product_code', $q)
            ->or_like('p.product_name', $q)
            ->group_end();

        return $db->select(implode(",\n", $select))
            ->order_by('pd.name', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_product_catalog(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $categoryId = max(0, (int)($filters['category_id'] ?? 0));
        $limit = max(1, min(120, (int)($filters['limit'] ?? 32)));

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
            'p.photo_path',
            'pd.id AS product_division_id',
            'pd.code AS product_division_code',
            'pd.name AS product_division_name',
            'pc.id AS product_category_id',
            'pc.code AS product_category_code',
            'pc.name AS product_category_name',
            'u.code AS uom_code',
        ];
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $select[] = 'pac.availability_status';
            $select[] = 'pac.source_mode';
            $select[] = 'pac.estimated_available_qty';
            $select[] = 'pac.bottleneck_name_snapshot';
            $select[] = 'pac.override_allowed';
            $select[] = 'pac.hpp_live_snapshot AS availability_hpp_live_snapshot';
        }

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->join('mst_uom u', 'u.id = p.uom_id', 'left');
        if ($this->db->table_exists('pos_product_availability_cache')) {
            $joinOutletId = $outletId > 0 ? $outletId : -1;
            $db->join('pos_product_availability_cache pac', 'pac.product_id = p.id AND pac.outlet_id = ' . $this->db->escape($joinOutletId), 'left', false);
        }
        $db->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }
        if ($divisionId > 0) {
            $db->where('p.product_division_id', $divisionId);
        }
        if ($categoryId > 0) {
            $db->where('p.product_category_id', $categoryId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('p.product_code', $q)
                ->or_like('p.product_name', $q)
                ->group_end();
        }

        return $db->select(implode(",\n", $select))
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('pc.sort_order', 'ASC')
            ->order_by('p.product_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_member_search(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        return $this->db
            ->select('id, member_no, member_name, mobile_phone, member_tier, member_status, point_balance_cache, stamp_balance_cache, total_spending')
            ->from('crm_member')
            ->where('is_active', 1)
            ->where('member_status !=', 'CLOSED')
            ->group_start()
                ->like('member_no', $q)
                ->or_like('member_name', $q)
                ->or_like('mobile_phone', $q)
            ->group_end()
            ->order_by('member_name', 'ASC')
            ->order_by('member_no', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
    }

    public function order_bundle_search(string $q, int $outletId = 0, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '' || !$this->db->table_exists('pos_product_bundle') || !$this->db->table_exists('pos_product_bundle_line')) {
            return [];
        }

        $bundleRows = $this->db
            ->select('b.id, b.bundle_code, b.bundle_name, b.selling_price, pd.name AS product_division_name')
            ->from('pos_product_bundle b')
            ->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left')
            ->where('b.is_active', 1)
            ->group_start()
                ->like('b.bundle_code', $q)
                ->or_like('b.bundle_name', $q)
            ->group_end()
            ->order_by('b.bundle_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();
        if (empty($bundleRows)) {
            return [];
        }

        $this->load->library('PosBundlePricingService');
        $results = [];
        foreach ($bundleRows as $bundle) {
            $bundleId = (int)($bundle['id'] ?? 0);
            $bundleLines = $this->db
                ->select('
                    bl.product_id,
                    bl.qty,
                    bl.unit_price_override,
                    p.product_code,
                    p.product_name,
                    p.selling_price,
                    p.hpp_standard,
                    p.hpp_live_cache,
                    pd.name AS product_division_name,
                    u.code AS uom_code
                ')
                ->from('pos_product_bundle_line bl')
                ->join('mst_product p', 'p.id = bl.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->join('mst_uom u', 'u.id = p.uom_id', 'left')
                ->where('bl.bundle_id', $bundleId)
                ->order_by('bl.sort_order', 'ASC')
                ->order_by('bl.id', 'ASC')
                ->get()
                ->result_array();
            if (empty($bundleLines)) {
                continue;
            }

            $availabilityMap = $this->load_product_availability_map(array_column($bundleLines, 'product_id'), $outletId);
            $pricingLines = [];
            $availabilityStatus = 'AVAILABLE';
            $bottlenecks = [];
            $bundleEstimatedQty = null;

            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $availability = $availabilityMap[$productId] ?? [];
                $lineStatus = strtoupper((string)($availability['availability_status'] ?? 'CHECK'));
                if (in_array($lineStatus, ['OUT', 'EMPTY'], true)) {
                    $availabilityStatus = 'OUT';
                } elseif ($availabilityStatus !== 'OUT' && !in_array($lineStatus, ['AVAILABLE', 'OK'], true)) {
                    $availabilityStatus = 'CHECK';
                }

                $bottleneck = trim((string)($availability['bottleneck_name_snapshot'] ?? ''));
                if ($bottleneck !== '') {
                    $bottlenecks[$bottleneck] = $bottleneck;
                }

                $pricingLines[] = [
                    'product_id' => $productId,
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'qty' => (float)($line['qty'] ?? 0),
                    'base_unit_price' => (float)($line['selling_price'] ?? 0),
                    'override_unit_price' => $line['unit_price_override'] !== null ? (float)$line['unit_price_override'] : null,
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'division_name' => (string)($line['product_division_name'] ?? ''),
                    'hpp_live_unit' => (float)($availability['hpp_live_snapshot'] ?? $line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0),
                    'hpp_standard_unit' => (float)($line['hpp_standard'] ?? 0),
                    'cost_source_label' => 'Bundle POS',
                ];
            }

            $pricing = $this->posbundlepricingservice->allocate((float)($bundle['selling_price'] ?? 0), $pricingLines);
            $items = [];
            foreach ((array)($pricing['lines'] ?? []) as $pricingLine) {
                $productId = (int)($pricingLine['product_id'] ?? 0);
                $sourceRow = null;
                foreach ($bundleLines as $line) {
                    if ((int)$line['product_id'] === $productId) {
                        $sourceRow = $line;
                        break;
                    }
                }
                if ($sourceRow === null) {
                    continue;
                }
                $availability = $availabilityMap[$productId] ?? [];
                $items[] = [
                    'product_id' => $productId,
                    'product_code' => (string)($sourceRow['product_code'] ?? ''),
                    'product_name' => (string)($sourceRow['product_name'] ?? ''),
                    'product_division_name' => (string)($sourceRow['product_division_name'] ?? ''),
                    'uom_code' => (string)($sourceRow['uom_code'] ?? ''),
                    'qty' => (float)($pricingLine['qty'] ?? 0),
                    'unit_price' => (float)($pricingLine['allocated_unit_price'] ?? 0),
                    'allocated_total' => (float)($pricingLine['allocated_total'] ?? 0),
                    'hpp_live_snapshot' => (float)($pricingLine['hpp_live_unit'] ?? 0),
                    'hpp_standard' => (float)($pricingLine['hpp_standard_unit'] ?? 0),
                    'availability_status' => (string)($availability['availability_status'] ?? 'CHECK'),
                    'estimated_available_qty' => (float)($availability['estimated_available_qty'] ?? 0),
                    'bottleneck_name_snapshot' => (string)($availability['bottleneck_name_snapshot'] ?? ''),
                ];
            }

            $results[] = [
                'id' => $bundleId,
                'bundle_code' => (string)($bundle['bundle_code'] ?? ''),
                'bundle_name' => (string)($bundle['bundle_name'] ?? ''),
                'selling_price' => (float)($bundle['selling_price'] ?? 0),
                'product_division_name' => (string)($bundle['product_division_name'] ?? 'Campuran Divisi'),
                'availability_status' => $availabilityStatus,
                'bottleneck_name_snapshot' => implode(', ', array_values($bottlenecks)),
                'line_count' => count($items),
                'items' => $items,
                'pricing_summary' => (array)($pricing['summary'] ?? []),
            ];
        }

        return $results;
    }

    public function order_bundle_catalog(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $outletId = max(0, (int)($filters['outlet_id'] ?? 0));
        $divisionId = max(0, (int)($filters['division_id'] ?? 0));
        $limit = max(1, min(60, (int)($filters['limit'] ?? 24)));

        if (!$this->db->table_exists('pos_product_bundle') || !$this->db->table_exists('pos_product_bundle_line')) {
            return [];
        }

        $db = $this->db
            ->select("
                b.id,
                b.bundle_code,
                b.bundle_name,
                b.selling_price,
                b.product_division_id,
                pd.code AS product_division_code,
                pd.name AS product_division_name,
                (
                    SELECT COUNT(*)
                    FROM pos_product_bundle_line blc
                    WHERE blc.bundle_id = b.id
                ) AS line_count,
                (
                    SELECT p2.photo_path
                    FROM pos_product_bundle_line bl2
                    INNER JOIN mst_product p2 ON p2.id = bl2.product_id
                    WHERE bl2.bundle_id = b.id
                    ORDER BY bl2.sort_order ASC, bl2.id ASC
                    LIMIT 1
                ) AS photo_path
            ", false)
            ->from('pos_product_bundle b')
            ->join('mst_product_division pd', 'pd.id = b.product_division_id', 'left')
            ->where('b.is_active', 1);

        if ($divisionId > 0) {
            $db->where('b.product_division_id', $divisionId);
        }
        if ($q !== '') {
            $db->group_start()
                ->like('b.bundle_code', $q)
                ->or_like('b.bundle_name', $q)
                ->group_end();
        }

        $bundleRows = $db
            ->order_by('pd.sort_order', 'ASC')
            ->order_by('b.bundle_name', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        if (empty($bundleRows)) {
            return [];
        }

        $this->load->library('PosBundlePricingService');
        $results = [];
        foreach ($bundleRows as $bundle) {
            $bundleId = (int)($bundle['id'] ?? 0);
            $bundleLines = $this->db
                ->select('
                    bl.product_id,
                    bl.qty,
                    bl.unit_price_override,
                    p.product_code,
                    p.product_name,
                    p.selling_price,
                    p.hpp_standard,
                    p.hpp_live_cache,
                    pd.name AS product_division_name,
                    u.code AS uom_code
                ')
                ->from('pos_product_bundle_line bl')
                ->join('mst_product p', 'p.id = bl.product_id', 'inner')
                ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
                ->join('mst_uom u', 'u.id = p.uom_id', 'left')
                ->where('bl.bundle_id', $bundleId)
                ->order_by('bl.sort_order', 'ASC')
                ->order_by('bl.id', 'ASC')
                ->get()
                ->result_array();
            if (empty($bundleLines)) {
                continue;
            }

            $availabilityMap = $this->load_product_availability_map(array_column($bundleLines, 'product_id'), $outletId);
            $pricingLines = [];
            $availabilityStatus = 'AVAILABLE';
            $bottlenecks = [];

            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $availability = $availabilityMap[$productId] ?? [];
                $lineStatus = strtoupper((string)($availability['availability_status'] ?? 'CHECK'));
                if (in_array($lineStatus, ['OUT', 'EMPTY'], true)) {
                    $availabilityStatus = 'OUT';
                } elseif ($availabilityStatus !== 'OUT' && !in_array($lineStatus, ['AVAILABLE', 'OK'], true)) {
                    $availabilityStatus = 'CHECK';
                }

                $bottleneck = trim((string)($availability['bottleneck_name_snapshot'] ?? ''));
                if ($bottleneck !== '') {
                    $bottlenecks[$bottleneck] = $bottleneck;
                }

                $availableQty = (float)($availability['estimated_available_qty'] ?? 0);
                $bundleLineQty = max((float)($line['qty'] ?? 0), 0.000001);
                $bundleAvailableByLine = $availableQty > 0 ? floor($availableQty / $bundleLineQty) : 0;
                if (!isset($bundleEstimatedQty)) {
                    $bundleEstimatedQty = $bundleAvailableByLine;
                } else {
                    $bundleEstimatedQty = min($bundleEstimatedQty, $bundleAvailableByLine);
                }

                $pricingLines[] = [
                    'product_id' => $productId,
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'reference_unit_price' => (float)($line['selling_price'] ?? 0),
                    'override_unit_price' => isset($line['unit_price_override']) ? (float)$line['unit_price_override'] : null,
                    'qty' => (float)($line['qty'] ?? 0),
                    'hpp_live_snapshot' => (float)($line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0),
                ];
            }

            $allocation = $this->posbundlepricingservice->allocate((float)($bundle['selling_price'] ?? 0), $pricingLines);
            $allocatedMap = [];
            foreach ((array)($allocation['lines'] ?? []) as $allocatedLine) {
                $allocatedMap[(int)($allocatedLine['product_id'] ?? 0)] = $allocatedLine;
            }

            $items = [];
            foreach ($bundleLines as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $allocated = $allocatedMap[$productId] ?? [];
                $availability = $availabilityMap[$productId] ?? [];
                $items[] = [
                    'product_id' => $productId,
                    'product_code' => (string)($line['product_code'] ?? ''),
                    'product_name' => (string)($line['product_name'] ?? ''),
                    'product_division_name' => (string)($line['product_division_name'] ?? ''),
                    'uom_code' => (string)($line['uom_code'] ?? ''),
                    'qty' => round((float)($line['qty'] ?? 0), 4),
                    'unit_price' => round((float)($allocated['allocated_unit_price'] ?? 0), 2),
                    'line_total' => round((float)($allocated['allocated_line_total'] ?? 0), 2),
                    'hpp_live_snapshot' => round((float)($line['hpp_live_cache'] ?? $line['hpp_standard'] ?? 0), 6),
                    'availability_status' => (string)($availability['availability_status'] ?? 'CHECK'),
                    'estimated_available_qty' => round((float)($availability['estimated_available_qty'] ?? 0), 4),
                ];
            }

            $bundle['availability_status'] = $availabilityStatus;
            $bundle['bottleneck_name_snapshot'] = implode(', ', array_values($bottlenecks));
            $bundle['estimated_available_qty'] = max(0, (int)round((float)($bundleEstimatedQty ?? 0), 0));
            $bundle['items'] = $items;
            $results[] = $bundle;
        }

        return $results;
    }

    public function order_extra_options(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }
        $map = $this->load_product_extra_group_map([$productId]);
        return $map[$productId] ?? [];
    }

    public function save_order_draft(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee. Order draft POS belum bisa dibuat.'];
        }

        $id = (int)($payload['id'] ?? 0);
        $outletId = (int)($payload['outlet_id'] ?? 0);
        $terminalId = !empty($payload['terminal_id']) ? (int)$payload['terminal_id'] : null;
        if ($outletId <= 0 || !$this->local_record_exists('pos_outlet', $outletId)) {
            return ['ok' => false, 'message' => 'Outlet POS wajib dipilih dan harus tersedia di database lokal POS.'];
        }
        if ($terminalId !== null && !$this->local_record_exists('pos_terminal', $terminalId)) {
            return ['ok' => false, 'message' => 'Terminal POS tidak ditemukan di database lokal POS.'];
        }
        $memberId = !empty($payload['member_id']) ? (int)$payload['member_id'] : null;
        if ($memberId !== null && !$this->local_record_exists('crm_member', $memberId)) {
            return ['ok' => false, 'message' => 'Member POS tidak ditemukan. Pilih member yang valid atau kosongkan transaksi untuk walk in.'];
        }

        $serviceType = strtoupper(trim((string)($payload['service_type'] ?? 'DINE_IN')));
        if (!in_array($serviceType, ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'DINE_IN';
        }
        $hasSalesChannelSchema = $this->db->table_exists('pos_sales_channel') && $this->db->field_exists('sales_channel_id', 'pos_order');
        $salesChannelId = !empty($payload['sales_channel_id']) ? (int)$payload['sales_channel_id'] : 0;
        $salesChannel = null;
        if ($hasSalesChannelSchema) {
            if ($salesChannelId <= 0) {
                $salesChannelId = (int)($this->default_sales_channel_id() ?? 0);
            }
            if ($salesChannelId > 0 && !$this->local_record_exists('pos_sales_channel', $salesChannelId)) {
                return ['ok' => false, 'message' => 'Sales channel POS tidak ditemukan. Pilih channel penjualan yang valid.'];
            }
            if ($salesChannelId > 0) {
                $salesChannel = $this->find_sales_channel($salesChannelId);
                if (!$salesChannel) {
                    return ['ok' => false, 'message' => 'Sales channel POS tidak ditemukan.'];
                }
                $allowedTypes = (array)($salesChannel['allowed_service_type_list'] ?? []);
                if (!empty($allowedTypes) && !in_array($serviceType, $allowedTypes, true)) {
                    return ['ok' => false, 'message' => 'Service type tidak diizinkan untuk sales channel yang dipilih.'];
                }
            }
        } else {
            $salesChannelId = 0;
        }
        $requireActiveSession = !empty($payload['require_active_session']);
        $activeSession = $actorEmployeeId > 0 ? $this->find_active_cashier_session($actorEmployeeId) : null;
        if ($requireActiveSession && !$activeSession) {
            return ['ok' => false, 'message' => 'Kasir belum dibuka. Pilih outlet, device, dan modal awal dulu sebelum input transaksi.'];
        }
        if ($activeSession) {
            if ((int)($activeSession['outlet_id'] ?? 0) !== $outletId) {
                return ['ok' => false, 'message' => 'Outlet transaksi harus sama dengan outlet sesi kasir yang sedang aktif.'];
            }
            if ($terminalId !== null && (int)($activeSession['terminal_id'] ?? 0) !== $terminalId) {
                return ['ok' => false, 'message' => 'Device transaksi harus sama dengan device sesi kasir yang sedang aktif.'];
            }
            $terminalId = (int)($activeSession['terminal_id'] ?? $terminalId);
        }

        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $normalized = $this->normalize_order_draft_lines($lines);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }

        $appendedLineIds = [];
        $isConfirmedAppend = false;
        $headerOnlyUpdate = false;

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $existing = null;
            $existingOrder = null;
            if ($id > 0) {
                $existing = $this->db->query('SELECT * FROM pos_order WHERE id = ? LIMIT 1 FOR UPDATE', [$id])->row_array();
                if (!$existing) {
                    throw new RuntimeException('Draft order POS tidak ditemukan.');
                }
                if (!in_array((string)($existing['status'] ?? ''), ['DRAFT', 'PENDING', 'CONFIRMED'], true)) {
                    throw new RuntimeException('Hanya order POS berstatus DRAFT/PENDING/CONFIRMED yang bisa dibuka dari kasir.');
                }
                $isConfirmedAppend = strtoupper((string)($existing['status'] ?? '')) === 'CONFIRMED';
                if ($isConfirmedAppend) {
                    $existingOrder = $this->find_order_draft($id);
                    if (!$existingOrder) {
                        throw new RuntimeException('Order POS tersimpan tidak ditemukan saat append transaksi.');
                    }
                }
            }

            $headerTotals = $this->calculate_order_totals((array)($normalized['rows'] ?? []));
            $headerPayload = [
                'order_channel' => 'CASHIER',
                'order_scope' => 'REGULAR',
                'service_type' => $serviceType,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'shift_id' => !empty($activeSession['shift_id']) ? (int)$activeSession['shift_id'] : null,
                'cashier_session_id' => !empty($activeSession['id']) ? (int)$activeSession['id'] : null,
                'cashier_employee_id' => $actorEmployeeId,
                'member_id' => $memberId,
                'guest_count' => max(1, (int)($payload['guest_count'] ?? 1)),
                'table_no' => $this->nullable_text($payload['table_no'] ?? ''),
                'subtotal_amount' => $headerTotals['subtotal_amount'],
                'grand_total' => $headerTotals['grand_total'],
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
                'status' => $isConfirmedAppend ? 'CONFIRMED' : 'DRAFT',
                'kitchen_status' => $isConfirmedAppend ? (string)($existing['kitchen_status'] ?? 'PENDING') : 'PENDING',
                'stock_commit_status' => $isConfirmedAppend ? (string)($existing['stock_commit_status'] ?? 'POSTED') : 'PENDING',
            ];
            if ($hasSalesChannelSchema) {
                $headerPayload['sales_channel_id'] = $salesChannelId > 0 ? $salesChannelId : null;
            }

            if ($id > 0) {
                $this->db->where('id', $id)->update('pos_order', $this->filter_table_payload('pos_order', $headerPayload));
                if (!$isConfirmedAppend) {
                    $this->db->where('order_id', $id)->delete('pos_order_line_extra');
                    $this->db->where('order_id', $id)->delete('pos_order_line');
                }
            } else {
                $headerPayload['order_no'] = $this->generate_pos_order_no();
                $headerPayload['ordered_at'] = date('Y-m-d H:i:s');
                $this->db->insert('pos_order', $this->filter_table_payload('pos_order', $headerPayload));
                $id = (int)$this->db->insert_id();
                if ($id <= 0) {
                    throw new RuntimeException($this->db_error_message('Gagal membuat header draft order POS.'));
                }
            }

            $rowsToPersist = (array)($normalized['rows'] ?? []);
            $lineNo = 1;
            if ($isConfirmedAppend) {
                $appendPlan = $this->prepare_confirmed_order_append((array)$existingOrder, $rowsToPersist);
                if (!($appendPlan['ok'] ?? false)) {
                    throw new RuntimeException((string)($appendPlan['message'] ?? 'Order confirmed tidak bisa diappend dari kasir.'));
                }
                $rowsToPersist = (array)($appendPlan['new_rows'] ?? []);
                $headerOnlyUpdate = empty($rowsToPersist);
                $lineNoRow = $this->db->select('COALESCE(MAX(line_no), 0) AS max_line_no', false)
                    ->from('pos_order_line')
                    ->where('order_id', $id)
                    ->get()
                    ->row_array();
                $lineNo = (int)($lineNoRow['max_line_no'] ?? 0) + 1;
            }

            foreach ($rowsToPersist as $row) {
                $linePayload = [
                    'order_id' => $id,
                    'line_no' => $lineNo++,
                    'product_id' => (int)$row['product_id'],
                    'bundle_id' => !empty($row['bundle_id']) ? (int)$row['bundle_id'] : null,
                    'line_type' => 'PRODUCT',
                    'product_division_id_snapshot' => $row['product_division_id_snapshot'],
                    'operational_division_id' => $row['operational_division_id'],
                    'uom_id' => $row['uom_id'],
                    'qty' => $row['qty'],
                    'unit_price' => $row['unit_price'],
                    'discount_amount' => 0,
                    'net_amount' => $row['net_amount'],
                    'hpp_standard_snapshot' => $row['hpp_standard_snapshot'],
                    'hpp_live_snapshot' => $row['hpp_live_snapshot'],
                    'cogs_amount' => $row['cogs_amount'],
                    'availability_mode_snapshot' => $row['availability_mode_snapshot'],
                    'line_status' => 'OPEN',
                    'process_status' => 'NOT_PROCESSED',
                    'notes' => $row['notes'],
                ];
                $this->db->insert('pos_order_line', $this->filter_table_payload('pos_order_line', $linePayload));
                $orderLineId = (int)$this->db->insert_id();
                if ($orderLineId <= 0) {
                    throw new RuntimeException($this->db_error_message('Gagal membuat line draft order POS.'));
                }
                $appendedLineIds[] = $orderLineId;
                $extraLineNo = 1;
                foreach ((array)($row['extras'] ?? []) as $extra) {
                    $this->db->insert('pos_order_line_extra', $this->filter_table_payload('pos_order_line_extra', [
                        'order_id' => $id,
                        'order_line_id' => $orderLineId,
                        'line_no' => $extraLineNo++,
                        'extra_id' => (int)$extra['extra_id'],
                        'qty' => $extra['qty'],
                        'unit_price' => $extra['unit_price'],
                        'net_amount' => $extra['net_amount'],
                        'cost_amount_snapshot' => $extra['cost_amount_snapshot'],
                        'notes' => $extra['notes'],
                    ]));
                    if ((int)$this->db->insert_id() <= 0) {
                        throw new RuntimeException($this->db_error_message('Gagal membuat extra draft order POS.'));
                    }
                }
            }

            if ($id > 0) {
                if ($isConfirmedAppend) {
                    $appendCount = count($appendedLineIds);
                    $this->insert_order_state_log(
                        $id,
                        (string)($existing['status'] ?? 'CONFIRMED'),
                        'CONFIRMED',
                        'ORDER_APPEND_UPDATE',
                        $actorEmployeeId,
                        $headerOnlyUpdate
                            ? 'Header order confirmed diperbarui tanpa item baru.'
                            : ('Order confirmed diperbarui dengan ' . $appendCount . ' line baru dari kasir.')
                    );
                } else {
                    $this->insert_order_state_log($id, (string)($existing['status'] ?? 'DRAFT'), 'DRAFT', 'ORDER_DRAFT_UPDATE', $actorEmployeeId, 'Draft order diperbarui.');
                }
            } else {
                $this->insert_order_state_log($id, null, 'DRAFT', 'ORDER_DRAFT_CREATE', $actorEmployeeId, 'Draft order dibuat.');
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException($this->db_error_message('Gagal menyimpan draft order POS.'));
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return [
                'ok' => true,
                'id' => $id,
                'order_no' => (string)($headerPayload['order_no'] ?? ($existing['order_no'] ?? '')),
                'append_mode' => $isConfirmedAppend,
                'header_only_update' => $isConfirmedAppend && $headerOnlyUpdate,
                'appended_line_ids' => $isConfirmedAppend ? $appendedLineIds : [],
                'appended_line_count' => $isConfirmedAppend ? count($appendedLineIds) : 0,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolve_order_stock_commit_payload(int $orderId, int $actorEmployeeId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order draft POS tidak valid.'];
        }
        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order draft POS tidak ditemukan.'];
        }
        $header = (array)($order['header'] ?? []);
        $lines = (array)($order['lines'] ?? []);
        $targetLineIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['line_ids'] ?? [])))));
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Order draft belum memiliki produk.'];
        }
        $allowedStatuses = empty($targetLineIds) ? ['DRAFT', 'PENDING'] : ['DRAFT', 'PENDING', 'CONFIRMED'];
        if (!in_array((string)($header['status'] ?? ''), $allowedStatuses, true)) {
            return ['ok' => false, 'message' => 'Status order POS tidak valid untuk konfirmasi transaksi dari kasir.'];
        }
        if (empty($targetLineIds) && (string)($header['stock_commit_status'] ?? '') === 'POSTED') {
            return ['ok' => false, 'message' => 'Order ini sudah pernah melakukan stock commit.'];
        }
        if (!empty($targetLineIds)) {
            $lineIdMap = array_fill_keys($targetLineIds, true);
            $lines = array_values(array_filter($lines, static function ($line) use ($lineIdMap) {
                $lineId = (int)($line['id'] ?? 0);
                return $lineId > 0 && isset($lineIdMap[$lineId]);
            }));
            if (empty($lines)) {
                return ['ok' => false, 'message' => 'Line transaksi baru tidak ditemukan untuk stock commit tambahan POS.'];
            }
        }

        $resolvedLines = [];
        $resolvedLineNo = 1;
        foreach ($lines as $line) {
            $recipeRows = $this->load_order_product_recipe_rows((int)($line['product_id'] ?? 0));
            if (empty($recipeRows)) {
                return ['ok' => false, 'message' => 'Produk ' . (string)($line['product_name'] ?? '-') . ' belum memiliki recipe product, jadi belum bisa stock commit.'];
            }
            $productResolvedLines = [];
            foreach ($recipeRows as $recipeRow) {
                $resolved = $this->resolve_order_recipe_consumption_line($header, $line, $recipeRow);
                if (!($resolved['ok'] ?? false)) {
                    return $resolved;
                }
                $productResolvedLines[] = (array)($resolved['line'] ?? []);
            }

            $extraResolvedLines = [];
            foreach ((array)($line['extras'] ?? []) as $extraLine) {
                $extraType = strtoupper(trim((string)($extraLine['extra_type'] ?? 'ADD')));
                if (in_array($extraType, ['REMOVE', 'CHOICE'], true)) {
                    $adjusted = $this->apply_order_extra_removal_to_resolved_lines($header, $line, $productResolvedLines, $extraLine);
                    if (!($adjusted['ok'] ?? false)) {
                        return $adjusted;
                    }
                    $productResolvedLines = (array)($adjusted['lines'] ?? []);
                }

                if ($extraType === 'ADD') {
                    $resolvedExtra = $this->resolve_order_extra_consumption_line($header, $line, $extraLine);
                    if (!($resolvedExtra['ok'] ?? false)) {
                        return $resolvedExtra;
                    }
                    foreach ((array)($resolvedExtra['lines'] ?? []) as $resolvedExtraLine) {
                        $extraResolvedLines[] = $resolvedExtraLine;
                    }
                }

                if (in_array($extraType, ['REMOVE', 'CHOICE'], true)) {
                    $replacement = $this->resolve_order_extra_replacement_consumption_line($header, $line, $extraLine);
                    if (!($replacement['ok'] ?? false)) {
                        return $replacement;
                    }
                    foreach ((array)($replacement['lines'] ?? []) as $replacementLine) {
                        $extraResolvedLines[] = $replacementLine;
                    }
                }
            }

            foreach ($productResolvedLines as $resolvedProductLine) {
                $resolvedLines[] = [
                    'line_no' => $resolvedLineNo++,
                ] + $resolvedProductLine;
            }
            foreach ($extraResolvedLines as $resolvedExtraLine) {
                $resolvedLines[] = [
                    'line_no' => $resolvedLineNo++,
                ] + $resolvedExtraLine;
            }
        }

        if (empty($resolvedLines)) {
            return ['ok' => false, 'message' => 'Tidak ada konsumsi stok yang berhasil di-resolve dari recipe produk.'];
        }

        return [
            'ok' => true,
            'header' => [
                'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : (!empty($header['cashier_employee_id']) ? (int)$header['cashier_employee_id'] : null),
                'commit_status' => 'DRAFT',
                'commit_reason' => 'ORDER_CONFIRM',
                'process_state_snapshot' => 'NONE',
                'notes' => empty($targetLineIds)
                    ? ('Snapshot commit dari confirm draft order POS ' . (string)($header['order_no'] ?? ''))
                    : ('Snapshot commit append transaksi POS ' . (string)($header['order_no'] ?? '')),
            ],
            'lines' => $resolvedLines,
            'resolved_line_count' => count($resolvedLines),
        ];
    }

    public function finalize_order_confirmation(int $orderId, int $snapshotId, int $actorEmployeeId, string $stockCommitStatus = 'POSTED'): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan saat finalisasi confirm.'];
        }

        $stockCommitStatus = strtoupper(trim($stockCommitStatus));
        if (!in_array($stockCommitStatus, ['PENDING', 'QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED'], true)) {
            $stockCommitStatus = 'POSTED';
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $isAppendConfirm = strtoupper((string)($row['status'] ?? 'DRAFT')) === 'CONFIRMED';
            $orderPayload = [
                'status' => 'CONFIRMED',
                'stock_commit_status' => $stockCommitStatus,
            ];
            if (!$isAppendConfirm || empty($row['confirmed_at'])) {
                $orderPayload['confirmed_at'] = date('Y-m-d H:i:s');
            }
            if ($stockCommitStatus === 'POSTED') {
                $orderPayload['stock_committed_at'] = date('Y-m-d H:i:s');
            }
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $orderPayload));

            $note = $isAppendConfirm
                ? ('Order confirmed diappend dari kasir dan snapshot stock commit #' . $snapshotId . ' dibuat.')
                : ('Draft order dikonfirmasi dan snapshot stock commit #' . $snapshotId . ' dibuat.');
            if ($stockCommitStatus === 'QUEUED') {
                $note .= ' Posting stok dipindah ke queue runtime POS.';
            }
            $this->insert_order_state_log(
                $orderId,
                (string)($row['status'] ?? 'DRAFT'),
                'CONFIRMED',
                $isAppendConfirm ? 'ORDER_APPEND_CONFIRM' : 'ORDER_CONFIRM',
                $actorEmployeeId,
                $note
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memfinalkan status confirm order POS.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => true, 'id' => $orderId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function update_order_stock_commit_state(int $orderId, string $stockCommitStatus, array $meta = []): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan saat update stock commit state.'];
        }

        $stockCommitStatus = strtoupper(trim($stockCommitStatus));
        if (!in_array($stockCommitStatus, ['PENDING', 'QUEUED', 'PROCESSING', 'POSTED', 'FAILED', 'REVERSED'], true)) {
            return ['ok' => false, 'message' => 'Status stock commit POS tidak valid.'];
        }

        $eventCode = strtoupper(trim((string)($meta['event_code'] ?? 'ORDER_STOCK_COMMIT_STATUS')));
        $actorEmployeeId = max(0, (int)($meta['actor_employee_id'] ?? 0));
        $note = trim((string)($meta['note'] ?? ''));
        $orderStatus = strtoupper(trim((string)($meta['status'] ?? ($row['status'] ?? 'CONFIRMED'))));

        $payload = [
            'stock_commit_status' => $stockCommitStatus,
        ];
        if ($orderStatus !== '') {
            $payload['status'] = $orderStatus;
        }
        if ($stockCommitStatus === 'POSTED') {
            $payload['stock_committed_at'] = (string)($meta['stock_committed_at'] ?? date('Y-m-d H:i:s'));
        }
        if ($stockCommitStatus === 'REVERSED') {
            $payload['stock_reversed_at'] = (string)($meta['stock_reversed_at'] ?? date('Y-m-d H:i:s'));
        }

        $previousDbDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();
        try {
            $this->db->where('id', $orderId)->update('pos_order', $this->filter_table_payload('pos_order', $payload));
            $this->insert_order_state_log(
                $orderId,
                (string)($row['status'] ?? 'CONFIRMED'),
                (string)($payload['status'] ?? $row['status'] ?? 'CONFIRMED'),
                $eventCode,
                $actorEmployeeId,
                $note !== '' ? $note : ('Status stock commit POS berubah ke ' . $stockCommitStatus . '.')
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan perubahan stock commit state POS.');
            }
            $this->db->trans_commit();
            $this->db->db_debug = $previousDbDebug;
            return [
                'ok' => true,
                'id' => $orderId,
                'status' => (string)($payload['status'] ?? $row['status'] ?? 'CONFIRMED'),
                'stock_commit_status' => $stockCommitStatus,
            ];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            $this->db->db_debug = $previousDbDebug;
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function direct_print_targets_for_order_confirm(int $orderId, int $snapshotId = 0): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid untuk direct print.'];
        }
        if (
            !$this->db->table_exists('pos_printer')
            || !$this->db->table_exists('pos_printer_profile')
            || !$this->db->table_exists('pos_printer_event_setting')
        ) {
            return ['ok' => true, 'targets' => []];
        }

        $eventRow = $this->db->from('pos_printer_event_setting')
            ->where('event_code', 'ORDER_CONFIRM_KOT')
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$eventRow || (int)($eventRow['auto_print'] ?? 0) !== 1) {
            return ['ok' => true, 'targets' => []];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan untuk direct print.'];
        }

        $snapshotLineIds = $this->resolve_direct_print_line_ids_for_snapshot($orderId, $snapshotId);
        $lines = (array)($order['lines'] ?? []);
        if (is_array($snapshotLineIds)) {
            if (empty($snapshotLineIds)) {
                return ['ok' => true, 'targets' => []];
            }
            $lineIdMap = array_fill_keys($snapshotLineIds, true);
            $lines = array_values(array_filter($lines, static function ($line) use ($lineIdMap) {
                $lineId = (int)($line['id'] ?? 0);
                return $lineId > 0 && isset($lineIdMap[$lineId]);
            }));
        }
        if (empty($lines)) {
            return ['ok' => true, 'targets' => []];
        }

        $outletId = (int)(($order['header']['outlet_id'] ?? 0));
        $printers = $this->coredb
            ->select("
                p.id AS printer_id,
                p.printer_code,
                p.printer_name,
                COALESCE(p.printer_role, 'CUSTOM') AS printer_role,
                COALESCE(p.print_scope, 'DIVISION') AS print_scope,
                p.outlet_id,
                p.agent_host,
                p.python_port,
                pf.template_id,
                COALESCE(pf.paper_width_mm, 80) AS paper_width_mm,
                COALESCE(pf.chars_per_line, CASE WHEN COALESCE(pf.paper_width_mm, 80) = 58 THEN 32 ELSE 48 END) AS chars_per_line,
                COALESCE(pf.copies, pf.copy_count, 1) AS copies
            ", false)
            ->from('pos_printer p')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'left')
            ->where('p.is_active', 1)
            ->where('p.connection_type', 'LOCAL_AGENT')
            ->where('p.python_port IS NOT NULL', null, false)
            ->group_start()
                ->where('p.outlet_id', $outletId)
                ->or_where('p.outlet_id IS NULL', null, false)
            ->group_end()
            ->order_by('p.outlet_id', 'DESC')
            ->order_by('p.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($printers)) {
            return ['ok' => true, 'targets' => []];
        }

        $lineBuckets = [
            'BAR' => [],
            'KITCHEN' => [],
            'ALL' => [],
        ];
        foreach ($lines as $line) {
            $linePayload = $this->build_direct_print_order_line($line);
            $lineBuckets['ALL'][] = $linePayload;
            $role = $this->preferred_print_role_for_order_line($line);
            if (!isset($lineBuckets[$role])) {
                $role = 'KITCHEN';
            }
            $lineBuckets[$role][] = $linePayload;
        }

        $targets = [];
        foreach ($printers as $printer) {
            $role = strtoupper(trim((string)($printer['printer_role'] ?? 'CUSTOM')));
            if ($role === 'KASIR') {
                continue;
            }
            $scope = strtoupper(trim((string)($printer['print_scope'] ?? 'DIVISION')));
            $selectedLines = $scope === 'ALL'
                ? $lineBuckets['ALL']
                : ($role === 'BAR'
                    ? $lineBuckets['BAR']
                    : ($role === 'KITCHEN' ? $lineBuckets['KITCHEN'] : $lineBuckets['ALL']));
            if (empty($selectedLines)) {
                continue;
            }
            $template = $this->resolve_direct_print_template($printer, 'ORDER_CONFIRM_KOT');
            $printWidth = $this->normalize_printer_chars_per_line(
                (int)($printer['paper_width_mm'] ?? 80),
                (int)($printer['chars_per_line'] ?? 48)
            );
            $targets[] = [
                'printer_id' => (int)($printer['printer_id'] ?? 0),
                'printer_code' => (string)($printer['printer_code'] ?? ''),
                'printer_name' => (string)($printer['printer_name'] ?? ''),
                'printer_role' => $role,
                'print_scope' => $scope,
                'agent_host' => (string)($printer['agent_host'] ?? ''),
                'python_port' => (int)($printer['python_port'] ?? 0),
                'paper_width_mm' => (int)($printer['paper_width_mm'] ?? 80),
                'chars_per_line' => $printWidth,
                'copies' => max(1, (int)($printer['copies'] ?? 1)),
                'text' => $this->build_direct_kot_text((array)($order['header'] ?? []), $selectedLines, $printer, $template),
            ];
        }

        return ['ok' => true, 'targets' => $targets];
    }

    public function order_reversal_preview(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Order POS tidak valid.'];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan.'];
        }

        $this->load->library('PosStockCommitService');
        $snapshot = $this->posstockcommitservice->snapshot_for_order($orderId);
        if (!($snapshot['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($snapshot['message'] ?? 'Snapshot stock commit belum tersedia.')];
        }

        $processByOrderLine = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $processByOrderLine[(int)($line['id'] ?? 0)] = strtoupper((string)($line['process_status'] ?? 'NOT_PROCESSED'));
        }

        $processedMap = [];
        foreach ((array)($snapshot['lines'] ?? []) as $line) {
            $key = $this->snapshot_line_key($line);
            $orderLineId = (int)($line['order_line_id'] ?? 0);
            $state = $processByOrderLine[$orderLineId] ?? 'NOT_PROCESSED';
            $processedMap[$key] = $state === 'NOT_PROCESSED' ? 'NONE' : 'FULL';
        }

        $plan = $this->posstockcommitservice->build_reversal_plan((int)($snapshot['header']['id'] ?? 0), $processedMap);
        if (!($plan['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string)($plan['message'] ?? 'Gagal menyiapkan reversal plan.')];
        }

        return [
            'ok' => true,
            'order' => $order,
            'snapshot' => $snapshot,
            'plan' => $plan,
        ];
    }

    public function save_order_void(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee untuk void POS.'];
        }

        $orderId = (int)($payload['order_id'] ?? 0);
        $preview = $this->order_reversal_preview($orderId);
        if (!($preview['ok'] ?? false)) {
            return $preview;
        }

        $order = (array)($preview['order'] ?? []);
        $header = (array)($order['header'] ?? []);
        if (in_array((string)($header['status'] ?? ''), ['VOID', 'REFUND_FULL'], true)) {
            return ['ok' => false, 'message' => 'Order ini sudah tidak bisa di-void lagi.'];
        }
        if (in_array((string)($header['status'] ?? ''), ['PAID', 'PAID_PARTIAL', 'REFUND_PARTIAL', 'SERVED'], true)) {
            return ['ok' => false, 'message' => 'Order yang sudah dibayar/disajikan tidak boleh di-void. Gunakan refund agar jejak pembayarannya tetap rapi.'];
        }

        $selection = $this->build_reversal_selection($order, (array)($preview['plan']['lines'] ?? []), (array)($payload['lines'] ?? []), [
            'default_return_to_stock' => !empty($payload['return_to_stock']),
            'default_processed_state' => strtoupper(trim((string)($payload['processed_state'] ?? 'NOT_PROCESSED'))),
        ]);
        if (empty($selection['decisions'])) {
            return ['ok' => false, 'message' => 'Tidak ada line void yang valid.'];
        }

        $this->load->library('PosOrderStockService');

        $this->db->trans_begin();
        try {
            $voidPayload = [
                'void_no' => $this->generate_pos_void_no(),
                'order_id' => $orderId,
                'payment_id' => null,
                'cashier_session_id' => !empty($header['cashier_session_id']) ? (int)$header['cashier_session_id'] : null,
                'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
                'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
                'shift_id' => !empty($header['shift_id']) ? (int)$header['shift_id'] : null,
                'member_id' => !empty($header['member_id']) ? (int)$header['member_id'] : null,
                'actor_employee_id' => $actorEmployeeId,
                'approved_by' => null,
                'void_scope' => !empty($selection['is_full']) ? 'FULL' : 'PARTIAL',
                'processed_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                'return_to_stock' => !empty($selection['return_to_stock']) ? 1 : 0,
                'adjustment_mode' => $this->normalize_pos_adjustment_mode((string)($payload['adjustment_mode'] ?? 'NONE')),
                'order_status_before' => (string)($header['status'] ?? 'CONFIRMED'),
                'order_status_after' => !empty($selection['is_full']) ? 'VOID' : (string)($header['status'] ?? 'CONFIRMED'),
                'order_no_snapshot' => (string)($header['order_no'] ?? ''),
                'member_name_snapshot' => (string)($header['member_name'] ?? ''),
                'service_type_snapshot' => (string)($header['service_type'] ?? ''),
                'reason' => $this->nullable_text($payload['reason'] ?? ''),
                'line_count' => count((array)($selection['product_lines'] ?? [])),
                'extra_count' => count((array)($selection['extra_lines'] ?? [])),
                'total_qty_void' => round((float)($selection['total_qty'] ?? 0), 4),
                'amount_void' => round((float)($selection['total_amount'] ?? 0), 2),
            ];
            $this->db->insert('pos_void', $voidPayload);
            $voidId = (int)$this->db->insert_id();
            if ($voidId <= 0) {
                throw new RuntimeException('Gagal membuat dokumen void POS.');
            }

            $voidLineMap = [];
            foreach ((array)($selection['product_lines'] ?? []) as $idx => $line) {
                $this->db->insert('pos_void_line', [
                    'void_id' => $voidId,
                    'order_id' => $orderId,
                    'order_line_id' => (int)$line['order_line_id'],
                    'product_id' => !empty($line['product_id']) ? (int)$line['product_id'] : null,
                    'line_no_snapshot' => (int)($line['line_no_snapshot'] ?? ($idx + 1)),
                    'item_name_snapshot' => (string)$line['item_name_snapshot'],
                    'qty_before' => round((float)$line['qty_before'], 4),
                    'qty_void' => round((float)$line['qty_void'], 4),
                    'qty_after' => round((float)$line['qty_after'], 4),
                    'unit_price' => round((float)$line['unit_price'], 2),
                    'subtotal_void' => round((float)$line['subtotal_void'], 2),
                    'hpp_live_snapshot' => round((float)$line['hpp_live_snapshot'], 6),
                    'line_process_state' => (string)$line['line_process_state'],
                    'line_status_after' => (string)$line['line_status_after'],
                    'notes' => $this->nullable_text($line['notes'] ?? ''),
                ]);
                $voidLineMap[(int)$line['order_line_id']] = (int)$this->db->insert_id();
            }

            foreach ((array)($selection['extra_lines'] ?? []) as $extra) {
                $this->db->insert('pos_void_line_extra', [
                    'void_id' => $voidId,
                    'void_line_id' => $voidLineMap[(int)$extra['order_line_id']] ?? null,
                    'order_id' => $orderId,
                    'order_line_id' => (int)$extra['order_line_id'],
                    'order_line_extra_id' => (int)$extra['order_line_extra_id'],
                    'extra_id' => !empty($extra['extra_id']) ? (int)$extra['extra_id'] : null,
                    'extra_name_snapshot' => (string)$extra['extra_name_snapshot'],
                    'qty_per_unit' => round((float)$extra['qty_per_unit'], 4),
                    'line_qty_affected' => round((float)$extra['line_qty_affected'], 4),
                    'unit_price' => round((float)$extra['unit_price'], 2),
                    'subtotal_void' => round((float)$extra['subtotal_void'], 2),
                    'status_after' => (string)$extra['status_after'],
                ]);
            }

            $reverse = $this->posorderstockservice->reverse_commit_snapshot((int)($preview['snapshot']['header']['id'] ?? 0), (array)$selection['decisions'], [
                'actor_employee_id' => $actorEmployeeId,
                'notes' => 'Void POS #' . $voidId,
            ]);
            if (!($reverse['ok'] ?? false)) {
                throw new RuntimeException((string)($reverse['message'] ?? 'Gagal reversal stok untuk void POS.'));
            }

            $this->apply_order_line_reversal_updates((array)($selection['product_lines'] ?? []), 'VOID');
            $newOrderStatus = !empty($selection['is_full']) ? 'VOID' : (string)($header['status'] ?? 'CONFIRMED');
            $orderUpdate = ['status' => $newOrderStatus];
            if ($newOrderStatus === 'VOID') {
                $orderUpdate['kitchen_status'] = 'VOID';
            }
            $this->db->where('id', $orderId)->update('pos_order', $orderUpdate);
            $this->insert_order_state_log($orderId, (string)($header['status'] ?? 'CONFIRMED'), $newOrderStatus, 'ORDER_VOID', $actorEmployeeId, 'Void POS #' . $voidId);

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan void POS.');
            }
            $this->db->trans_commit();

            return ['ok' => true, 'id' => $voidId, 'void_no' => (string)$voidPayload['void_no']];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function save_order_refund(array $payload, int $actorEmployeeId): array
    {
        if ($actorEmployeeId <= 0) {
            return ['ok' => false, 'message' => 'User login belum terhubung ke data employee untuk refund POS.'];
        }

        $orderId = (int)($payload['order_id'] ?? 0);
        $preview = $this->order_reversal_preview($orderId);
        if (!($preview['ok'] ?? false)) {
            return $preview;
        }

        $order = (array)($preview['order'] ?? []);
        $header = (array)($order['header'] ?? []);
        if (!in_array((string)($header['status'] ?? ''), ['PAID', 'PAID_PARTIAL', 'READY', 'SERVED', 'REFUND_PARTIAL'], true)) {
            return ['ok' => false, 'message' => 'Refund hanya boleh dipakai untuk order yang sudah masuk tahap bayar / siap saji / served.'];
        }
        if ((float)($header['paid_total'] ?? 0) <= 0 && empty($header['paid_at'])) {
            return ['ok' => false, 'message' => 'Order ini belum memiliki pembayaran tercatat, jadi refund belum bisa dibuat.'];
        }

        $selection = $this->build_reversal_selection($order, (array)($preview['plan']['lines'] ?? []), (array)($payload['lines'] ?? []), [
            'default_return_to_stock' => !empty($payload['return_to_stock']),
            'default_processed_state' => strtoupper(trim((string)($payload['processed_state'] ?? 'NOT_PROCESSED'))),
        ]);
        if (empty($selection['decisions'])) {
            return ['ok' => false, 'message' => 'Tidak ada line refund yang valid.'];
        }

        $this->load->library('PosOrderStockService');

        $this->db->trans_begin();
        try {
            $refundPayload = [
                'refund_no' => $this->generate_pos_refund_no(),
                'order_id' => $orderId,
                'payment_id' => null,
                'member_id' => !empty($header['member_id']) ? (int)$header['member_id'] : null,
                'payment_method_id' => null,
                'company_account_id' => null,
                'reference_no' => $this->nullable_text($payload['reference_no'] ?? ''),
                'refund_status' => 'POSTED',
                'processed_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                'return_to_stock' => !empty($selection['return_to_stock']) ? 1 : 0,
                'adjustment_mode' => $this->normalize_pos_adjustment_mode((string)($payload['adjustment_mode'] ?? 'NONE')),
                'refund_amount' => round((float)($selection['total_amount'] ?? 0), 2),
                'reason' => $this->nullable_text($payload['reason'] ?? ''),
                'refunded_by' => $actorEmployeeId,
                'refunded_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert('pos_refund', $refundPayload);
            $refundId = (int)$this->db->insert_id();
            if ($refundId <= 0) {
                throw new RuntimeException('Gagal membuat dokumen refund POS.');
            }

            $lineNo = 1;
            foreach ((array)($selection['product_lines'] ?? []) as $line) {
                $this->db->insert('pos_refund_line', [
                    'refund_id' => $refundId,
                    'line_no' => $lineNo++,
                    'line_type' => 'PRODUCT',
                    'order_line_id' => (int)$line['order_line_id'],
                    'order_extra_line_id' => null,
                    'product_id' => !empty($line['product_id']) ? (int)$line['product_id'] : null,
                    'extra_id' => null,
                    'qty_refunded' => round((float)$line['qty_void'], 4),
                    'amount_refunded' => round((float)$line['subtotal_void'], 2),
                    'cost_reversed' => round((float)$line['qty_void'] * (float)$line['hpp_live_snapshot'], 2),
                    'line_process_state' => (string)$line['line_process_state'],
                    'notes' => $this->nullable_text($line['notes'] ?? ''),
                ]);
            }
            foreach ((array)($selection['extra_lines'] ?? []) as $extra) {
                $this->db->insert('pos_refund_line', [
                    'refund_id' => $refundId,
                    'line_no' => $lineNo++,
                    'line_type' => 'EXTRA',
                    'order_line_id' => (int)$extra['order_line_id'],
                    'order_extra_line_id' => (int)$extra['order_line_extra_id'],
                    'product_id' => null,
                    'extra_id' => !empty($extra['extra_id']) ? (int)$extra['extra_id'] : null,
                    'qty_refunded' => round((float)$extra['line_qty_affected'], 4),
                    'amount_refunded' => round((float)$extra['subtotal_void'], 2),
                    'cost_reversed' => round((float)$extra['line_qty_affected'] * (float)($extra['cost_amount_snapshot'] ?? 0), 2),
                    'line_process_state' => !empty($selection['has_processed']) ? 'PROCESSED' : 'NOT_PROCESSED',
                    'notes' => null,
                ]);
            }

            $reverse = $this->posorderstockservice->reverse_commit_snapshot((int)($preview['snapshot']['header']['id'] ?? 0), (array)$selection['decisions'], [
                'actor_employee_id' => $actorEmployeeId,
                'notes' => 'Refund POS #' . $refundId,
            ]);
            if (!($reverse['ok'] ?? false)) {
                throw new RuntimeException((string)($reverse['message'] ?? 'Gagal reversal stok untuk refund POS.'));
            }

            $this->apply_order_line_reversal_updates((array)($selection['product_lines'] ?? []), 'REFUND');
            $newOrderStatus = !empty($selection['is_full']) ? 'REFUND_FULL' : 'REFUND_PARTIAL';
            $this->db->where('id', $orderId)->update('pos_order', ['status' => $newOrderStatus]);
            $this->insert_order_state_log($orderId, (string)($header['status'] ?? 'PAID'), $newOrderStatus, 'ORDER_REFUND', $actorEmployeeId, 'Refund POS #' . $refundId);

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan refund POS.');
            }
            $this->db->trans_commit();

            return ['ok' => true, 'id' => $refundId, 'refund_no' => (string)$refundPayload['refund_no']];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function queue_order_confirm_print_jobs(int $orderId): array
    {
        if ($orderId <= 0 || !$this->db->table_exists('pos_printer_job') || !$this->db->table_exists('pos_printer') || !$this->db->table_exists('pos_printer_profile')) {
            return ['ok' => true, 'job_count' => 0];
        }

        $eventRow = $this->db->from('pos_printer_event_setting')
            ->where('event_code', 'ORDER_CONFIRM_KOT')
            ->where('is_active', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (!$eventRow || (int)($eventRow['auto_print'] ?? 0) !== 1) {
            return ['ok' => true, 'job_count' => 0];
        }

        $order = $this->find_order_draft($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan untuk membuat job printer.'];
        }

        $printers = $this->db
            ->select("
                p.id AS printer_id,
                p.printer_code,
                p.printer_name,
                COALESCE(p.printer_role, 'CUSTOM') AS printer_role,
                pf.id AS profile_id,
                COALESCE(pf.copies, pf.copy_count, 1) AS copies
            ", false)
            ->from('pos_printer p')
            ->join('pos_printer_profile pf', 'pf.printer_id = p.id', 'inner')
            ->where('p.is_active', 1)
            ->get()
            ->result_array();
        if (empty($printers)) {
            return ['ok' => true, 'job_count' => 0];
        }

        $payload = $this->build_order_kot_print_payload($order);
        $jobCount = 0;

        $this->db->trans_begin();
        try {
            foreach ($printers as $printer) {
                $role = strtoupper(trim((string)($printer['printer_role'] ?? 'CUSTOM')));
                if (!in_array($role, ['KITCHEN', 'BAR', 'CHECKER', 'CUSTOM'], true)) {
                    continue;
                }

                $jobNo = $this->generate_job_no();
                $this->db->insert('pos_printer_job', [
                    'job_no' => $jobNo,
                    'route_rule_id' => null,
                    'profile_id' => (int)$printer['profile_id'],
                    'desktop_device_id' => null,
                    'order_id' => $orderId,
                    'payment_id' => null,
                    'refund_id' => null,
                    'void_id' => null,
                    'document_type' => 'KOT',
                    'event_code' => 'ORDER_CONFIRM_KOT',
                    'print_payload' => json_encode($payload + [
                        'printer_id' => (int)$printer['printer_id'],
                        'printer_code' => (string)($printer['printer_code'] ?? ''),
                        'printer_name' => (string)($printer['printer_name'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'copy_count' => max(1, (int)($printer['copies'] ?? 1)),
                    'status' => 'PENDING',
                    'requested_at' => date('Y-m-d H:i:s'),
                ]);
                $jobId = (int)$this->db->insert_id();
                if ($this->db->table_exists('pos_printer_job_log')) {
                    $this->db->insert('pos_printer_job_log', [
                        'job_id' => $jobId,
                        'log_level' => 'INFO',
                        'message' => 'Job KOT dibuat otomatis dari confirm order POS.',
                    ]);
                }
                $jobCount++;
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal membuat antrean print job order confirm.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'job_count' => $jobCount];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage(), 'job_count' => 0];
        }
    }

    private function local_template_master_options(): array
    {
        if (!$this->db->table_exists('pos_printer_template_master')) {
            return [];
        }
        return $this->db->select('id, master_code, master_name, document_type')
            ->from('pos_printer_template_master')
            ->where('is_active', 1)
            ->order_by('document_type', 'ASC')
            ->order_by('master_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function local_template_options(): array
    {
        if (!$this->db->table_exists('pos_printer_template')) {
            return [];
        }
        return $this->db->select('t.id, t.template_code, t.template_name, tm.document_type')
            ->from('pos_printer_template t')
            ->join('pos_printer_template_master tm', 'tm.id = t.template_master_id', 'left')
            ->where('t.is_active', 1)
            ->order_by('tm.document_type', 'ASC')
            ->order_by('t.template_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function local_record_exists(string $table, int $id): bool
    {
        if ($id <= 0 || !$this->db->table_exists($table)) {
            return false;
        }
        return (int)$this->db->from($table)->where('id', $id)->count_all_results() > 0;
    }

    private function local_code_exists(string $table, string $column, string $code, int $excludeId = 0): bool
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists($column, $table)) {
            return false;
        }
        return $this->code_exists($this->db, $table, $column, $code, $excludeId);
    }

    private function generate_local_named_code(string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        return $this->generate_named_code($this->db, $table, $column, $name, $prefix, $excludeId, $maxLen);
    }

    private function normalize_order_draft_lines(array $lines): array
    {
        $normalized = [];
        $productIds = [];
        $extraIds = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($productId > 0 && $qty > 0) {
                $productIds[$productId] = $productId;
                foreach ((array)($line['extras'] ?? []) as $extraRow) {
                    $extraId = (int)($extraRow['extra_id'] ?? 0);
                    if ($extraId > 0) {
                        $extraIds[$extraId] = $extraId;
                    }
                }
            }
        }

        if (empty($productIds)) {
            return ['ok' => false, 'message' => 'Minimal harus ada 1 produk dengan qty valid di draft order POS.'];
        }

        $productRows = $this->load_order_products(array_values($productIds));
        if (empty($productRows)) {
            return ['ok' => false, 'message' => 'Produk draft POS tidak ditemukan.'];
        }

        $productMap = [];
        foreach ($productRows as $row) {
            $productMap[(int)$row['id']] = $row;
        }
        $extraOptionMap = $this->load_product_extra_group_map(array_values($productIds));
        $extraMasterMap = $this->load_extra_master_map(array_values($extraIds));

        $normalized = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if (empty($productMap[$productId])) {
                return ['ok' => false, 'message' => 'Baris #' . ($index + 1) . ': produk tidak ditemukan atau tidak aktif di POS.'];
            }

            $product = $productMap[$productId];
            $unitPrice = round((float)($line['unit_price'] ?? $product['selling_price'] ?? 0), 2);
            $hppStandard = round((float)($product['hpp_standard'] ?? 0), 6);
            $hppLive = round((float)($line['hpp_live_snapshot'] ?? $product['hpp_live_cache'] ?? 0), 6);
            if ($hppLive <= 0) {
                $hppLive = $hppStandard;
            }
            $bundleId = !empty($line['bundle_id']) ? (int)$line['bundle_id'] : null;
            $normalizedExtras = [];
            $allowedExtraIds = [];
            foreach ((array)($extraOptionMap[$productId] ?? []) as $group) {
                foreach ((array)($group['items'] ?? []) as $item) {
                    $allowedExtraIds[(int)$item['extra_id']] = true;
                }
            }
            foreach ((array)($line['extras'] ?? []) as $extraIndex => $extraLine) {
                if (!is_array($extraLine)) {
                    continue;
                }
                $extraId = (int)($extraLine['extra_id'] ?? 0);
                $extraQty = round((float)($extraLine['qty'] ?? 0), 4);
                if ($extraId <= 0 || $extraQty <= 0) {
                    continue;
                }
                if (!isset($allowedExtraIds[$extraId]) || empty($extraMasterMap[$extraId])) {
                    return ['ok' => false, 'message' => 'Baris #' . ($index + 1) . ' memiliki extra yang tidak valid untuk produk ini.'];
                }
                $extraMaster = $extraMasterMap[$extraId];
                $extraUnitPrice = round((float)($extraLine['unit_price'] ?? $extraMaster['selling_price'] ?? 0), 2);
                $extraCost = round((float)($extraMaster['cost_amount'] ?? 0), 6);
                $normalizedExtras[] = [
                    'extra_id' => $extraId,
                    'extra_code' => (string)($extraMaster['extra_code'] ?? ''),
                    'extra_name' => (string)($extraMaster['extra_name'] ?? ''),
                    'extra_type' => (string)($extraMaster['extra_type'] ?? ''),
                    'qty' => $extraQty,
                    'unit_price' => $extraUnitPrice,
                    'net_amount' => round($extraQty * $extraUnitPrice, 2),
                    'cost_amount_snapshot' => $extraCost,
                    'source_kind' => (string)($extraMaster['source_kind'] ?? 'NONE'),
                    'source_product_id' => !empty($extraMaster['source_product_id']) ? (int)$extraMaster['source_product_id'] : null,
                    'source_component_id' => !empty($extraMaster['source_component_id']) ? (int)$extraMaster['source_component_id'] : null,
                    'source_material_id' => !empty($extraMaster['source_material_id']) ? (int)$extraMaster['source_material_id'] : null,
                    'source_qty' => round((float)($extraMaster['source_qty'] ?? 0), 4),
                    'replacement_kind' => (string)($extraMaster['replacement_kind'] ?? 'NONE'),
                    'replacement_product_id' => !empty($extraMaster['replacement_product_id']) ? (int)$extraMaster['replacement_product_id'] : null,
                    'replacement_component_id' => !empty($extraMaster['replacement_component_id']) ? (int)$extraMaster['replacement_component_id'] : null,
                    'replacement_material_id' => !empty($extraMaster['replacement_material_id']) ? (int)$extraMaster['replacement_material_id'] : null,
                    'replacement_qty' => round((float)($extraMaster['replacement_qty'] ?? 0), 4),
                    'notes' => $this->nullable_text($extraLine['notes'] ?? ''),
                ];
            }
            $extraTotal = 0.0;
            foreach ($normalizedExtras as $extraRow) {
                $extraTotal += (float)($extraRow['net_amount'] ?? 0);
            }
            $extraTotal = round($extraTotal, 2);

            $normalized[] = [
                'order_line_id' => !empty($line['id']) ? (int)$line['id'] : 0,
                'product_id' => $productId,
                'bundle_id' => $bundleId,
                'product_code' => (string)($product['product_code'] ?? ''),
                'product_name' => (string)($product['product_name'] ?? ''),
                'product_division_id_snapshot' => !empty($product['product_division_id']) ? (int)$product['product_division_id'] : null,
                'operational_division_id' => $this->resolve_product_default_operational_division($product),
                'uom_id' => !empty($product['uom_id']) ? (int)$product['uom_id'] : null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'net_amount' => round($qty * $unitPrice, 2),
                'extra_total' => $extraTotal,
                'hpp_standard_snapshot' => $hppStandard,
                'hpp_live_snapshot' => $hppLive,
                'cogs_amount' => round($qty * $hppLive, 2),
                'availability_mode_snapshot' => 'AUTO',
                'extras' => $normalizedExtras,
                'notes' => $this->nullable_text($line['notes'] ?? ''),
            ];
        }

        if (empty($normalized)) {
            return ['ok' => false, 'message' => 'Draft order belum punya line produk yang valid.'];
        }

        return ['ok' => true, 'rows' => $normalized];
    }

    private function prepare_confirmed_order_append(array $existingOrder, array $incomingRows): array
    {
        $existingLines = [];
        foreach ((array)($existingOrder['lines'] ?? []) as $line) {
            $lineId = (int)($line['id'] ?? 0);
            if ($lineId > 0) {
                $existingLines[$lineId] = $line;
            }
        }

        if (empty($existingLines)) {
            return ['ok' => false, 'message' => 'Order confirmed belum memiliki line tersimpan untuk diappend.'];
        }

        $seenExisting = [];
        $newRows = [];
        foreach ($incomingRows as $row) {
            $orderLineId = (int)($row['order_line_id'] ?? 0);
            if ($orderLineId > 0) {
                if (empty($existingLines[$orderLineId])) {
                    return ['ok' => false, 'message' => 'Line transaksi lama tidak cocok dengan data order POS tersimpan. Muat ulang order lalu coba lagi.'];
                }
                if (!$this->confirmed_order_existing_line_unchanged((array)$existingLines[$orderLineId], $row)) {
                    return ['ok' => false, 'message' => 'Line transaksi lama tidak boleh diubah dari kasir. Pengurangan atau hapus item harus melalui void POS.'];
                }
                $seenExisting[$orderLineId] = true;
                continue;
            }
            $newRows[] = $row;
        }

        foreach (array_keys($existingLines) as $orderLineId) {
            if (!isset($seenExisting[$orderLineId])) {
                return ['ok' => false, 'message' => 'Line transaksi lama tidak boleh dihapus dari kasir. Gunakan void POS untuk pengurangan atau pembatalan item.'];
            }
        }

        return [
            'ok' => true,
            'new_rows' => array_values($newRows),
        ];
    }

    private function confirmed_order_existing_line_unchanged(array $existingLine, array $incomingLine): bool
    {
        if ((int)($existingLine['product_id'] ?? 0) !== (int)($incomingLine['product_id'] ?? 0)) {
            return false;
        }
        if ((int)($existingLine['bundle_id'] ?? 0) !== (int)($incomingLine['bundle_id'] ?? 0)) {
            return false;
        }
        if (round((float)($existingLine['qty'] ?? 0), 4) !== round((float)($incomingLine['qty'] ?? 0), 4)) {
            return false;
        }
        if (round((float)($existingLine['unit_price'] ?? 0), 2) !== round((float)($incomingLine['unit_price'] ?? 0), 2)) {
            return false;
        }
        if (trim((string)($existingLine['notes'] ?? '')) !== trim((string)($incomingLine['notes'] ?? ''))) {
            return false;
        }

        $existingExtras = array_values((array)($existingLine['extras'] ?? []));
        $incomingExtras = array_values((array)($incomingLine['extras'] ?? []));
        if (count($existingExtras) !== count($incomingExtras)) {
            return false;
        }

        foreach ($existingExtras as $index => $existingExtra) {
            $incomingExtra = (array)($incomingExtras[$index] ?? []);
            if ((int)($existingExtra['extra_id'] ?? 0) !== (int)($incomingExtra['extra_id'] ?? 0)) {
                return false;
            }
            if (round((float)($existingExtra['qty'] ?? 0), 4) !== round((float)($incomingExtra['qty'] ?? 0), 4)) {
                return false;
            }
            if (round((float)($existingExtra['unit_price'] ?? 0), 2) !== round((float)($incomingExtra['unit_price'] ?? 0), 2)) {
                return false;
            }
            if (trim((string)($existingExtra['notes'] ?? '')) !== trim((string)($incomingExtra['notes'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private function load_order_products(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return [];
        }

        $select = [
            'p.id',
            'p.product_code',
            'p.product_name',
            'p.product_division_id',
            'p.uom_id',
            'p.selling_price',
            'p.hpp_standard',
            'p.hpp_live_cache',
        ];
        if ($this->db->field_exists('default_operational_division_id', 'mst_product')) {
            $select[] = 'p.default_operational_division_id';
        }
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $select[] = 'p.show_pos';
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $select[] = 'p.show_in_cashier';
        }
        $select[] = 'pd.name AS product_division_name';
        $select[] = 'pd.code AS product_division_code';

        $db = $this->db;
        $db->from('mst_product p')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->where_in('p.id', $productIds)
            ->where('p.is_active', 1);
        if ($this->db->field_exists('show_pos', 'mst_product')) {
            $db->where('p.show_pos', 1);
        }
        if ($this->db->field_exists('show_in_cashier', 'mst_product')) {
            $db->where('p.show_in_cashier', 1);
        }

        return $db->select(implode(",\n", $select))->get()->result_array();
    }

    private function load_product_availability_map(array $productIds, int $outletId = 0): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if ($outletId <= 0 || empty($productIds) || !$this->db->table_exists('pos_product_availability_cache')) {
            return [];
        }

        $rows = $this->db
            ->select('product_id, availability_status, estimated_available_qty, bottleneck_name_snapshot, hpp_live_snapshot')
            ->from('pos_product_availability_cache')
            ->where('outlet_id', $outletId)
            ->where_in('product_id', $productIds)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['product_id']] = $row;
        }
        return $map;
    }

    private function load_extra_master_map(array $extraIds): array
    {
        $extraIds = array_values(array_filter(array_map('intval', $extraIds)));
        if (empty($extraIds)) {
            return [];
        }

        $rows = $this->db
            ->select('id, extra_code, extra_name, extra_type, selling_price, cost_amount, source_kind, source_product_id, source_component_id, source_material_id, source_qty, replacement_kind, replacement_product_id, replacement_component_id, replacement_material_id, replacement_qty')
            ->from('mst_extra')
            ->where_in('id', $extraIds)
            ->where('is_active', 1)
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }

    private function load_product_extra_group_map(array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds)));
        if (empty($productIds) || !$this->db->table_exists('mst_product_extra_map')) {
            return [];
        }

        $rows = $this->db
            ->select('
                m.product_id,
                g.id AS extra_group_id,
                g.group_code,
                g.group_name,
                g.is_required,
                g.min_select,
                g.max_select,
                gi.sort_order AS item_sort_order,
                e.id AS extra_id,
                e.extra_code,
                e.extra_name,
                e.extra_type,
                e.selling_price,
                e.cost_amount
            ')
            ->from('mst_product_extra_map m')
            ->join('mst_extra_group g', 'g.id = m.extra_group_id AND g.is_active = 1', 'inner')
            ->join('mst_extra_group_item gi', 'gi.extra_group_id = g.id', 'inner')
            ->join('mst_extra e', 'e.id = gi.extra_id AND e.is_active = 1', 'inner')
            ->where_in('m.product_id', $productIds)
            ->order_by('m.sort_order', 'ASC')
            ->order_by('g.sort_order', 'ASC')
            ->order_by('gi.sort_order', 'ASC')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            $groupId = (int)$row['extra_group_id'];
            if (!isset($map[$productId])) {
                $map[$productId] = [];
            }
            if (!isset($map[$productId][$groupId])) {
                $map[$productId][$groupId] = [
                    'extra_group_id' => $groupId,
                    'group_code' => (string)($row['group_code'] ?? ''),
                    'group_name' => (string)($row['group_name'] ?? ''),
                    'is_required' => (int)($row['is_required'] ?? 0),
                    'min_select' => (int)($row['min_select'] ?? 0),
                    'max_select' => (int)($row['max_select'] ?? 1),
                    'items' => [],
                ];
            }
            $map[$productId][$groupId]['items'][] = [
                'extra_id' => (int)$row['extra_id'],
                'extra_code' => (string)($row['extra_code'] ?? ''),
                'extra_name' => (string)($row['extra_name'] ?? ''),
                'extra_type' => (string)($row['extra_type'] ?? ''),
                'selling_price' => (float)($row['selling_price'] ?? 0),
                'cost_amount' => (float)($row['cost_amount'] ?? 0),
            ];
        }

        $normalized = [];
        foreach ($map as $productId => $groups) {
            $normalized[$productId] = array_values($groups);
        }
        return $normalized;
    }

    private function calculate_order_totals(array $rows): array
    {
        $subtotal = 0.0;
        foreach ($rows as $row) {
            $subtotal += (float)($row['net_amount'] ?? 0);
            $subtotal += (float)($row['extra_total'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        return [
            'subtotal_amount' => $subtotal,
            'grand_total' => $subtotal,
        ];
    }

    private function build_order_kot_print_payload(array $order): array
    {
        $header = (array)($order['header'] ?? []);
        $lines = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $lines[] = [
                'product_name' => (string)($line['product_name'] ?? ''),
                'qty' => (float)($line['qty'] ?? 0),
                'uom_code' => (string)($line['uom_code'] ?? ''),
                'bundle_name' => (string)($line['bundle_name'] ?? ''),
                'notes' => (string)($line['notes'] ?? ''),
            ];
        }

        return [
            'document_type' => 'KOT',
            'event_code' => 'ORDER_CONFIRM_KOT',
            'order_id' => (int)($header['id'] ?? 0),
            'order_no' => (string)($header['order_no'] ?? ''),
            'service_type' => (string)($header['service_type'] ?? ''),
            'guest_count' => (int)($header['guest_count'] ?? 1),
            'ordered_at' => (string)($header['ordered_at'] ?? ''),
            'outlet_id' => !empty($header['outlet_id']) ? (int)$header['outlet_id'] : null,
            'terminal_id' => !empty($header['terminal_id']) ? (int)$header['terminal_id'] : null,
            'member_name' => (string)($header['member_name'] ?? ''),
            'lines' => $lines,
        ];
    }

    private function build_direct_print_order_line(array $line): array
    {
        $extras = [];
        foreach ((array)($line['extras'] ?? []) as $extra) {
            $extraName = trim((string)($extra['extra_name'] ?? ''));
            if ($extraName === '') {
                continue;
            }
            $extraQty = max(0, (float)($extra['qty'] ?? 0));
            $extras[] = [
                'name' => $extraName,
                'qty' => $extraQty,
            ];
        }

        return [
            'product_name' => (string)($line['product_name'] ?? ''),
            'qty' => (float)($line['qty'] ?? 0),
            'notes' => trim((string)($line['notes'] ?? '')),
            'extras' => $extras,
        ];
    }

    private function preferred_print_role_for_order_line(array $line): string
    {
        $division = strtoupper(trim((string)($line['product_division_name'] ?? '')));
        if ($division === 'BEVERAGE') {
            return 'BAR';
        }
        if ($division === 'FOOD' || $division === 'EVENT') {
            return 'KITCHEN';
        }
        return 'KITCHEN';
    }

    private function resolve_direct_print_template(array $printer, string $eventCode): array
    {
        $generalSettings = (array)($this->printer_general_settings()['payload'] ?? []);
        $desiredDocumentType = strtoupper($eventCode) === 'ORDER_CONFIRM_KOT' ? 'KITCHEN_TICKET' : 'RECEIPT';
        $role = strtoupper(trim((string)($printer['printer_role'] ?? 'CUSTOM')));
        $templateRow = null;
        if (!$this->coredb->table_exists('pos_printer_template')) {
            $templateRow = null;
        }

        $profileTemplateId = (int)($printer['template_id'] ?? 0);
        if ($profileTemplateId > 0 && $this->coredb->table_exists('pos_printer_template')) {
            $candidate = $this->coredb
                ->from('pos_printer_template')
                ->where('id', $profileTemplateId)
                ->where('is_active', 1)
                ->limit(1)
                ->get()
                ->row_array();
            if ($candidate && strtoupper((string)($candidate['document_type'] ?? '')) === $desiredDocumentType) {
                $templateRow = $candidate;
            }
        }

        if (!$templateRow && $this->coredb->table_exists('pos_printer_template')) {
            $preferredCode = 'TPL-' . $role;
            $templateRow = $this->coredb
                ->group_start()
                    ->where('template_code', $preferredCode)
                    ->or_where('template_name', $role)
                ->group_end()
                ->where('document_type', $desiredDocumentType)
                ->where('is_active', 1)
                ->order_by('is_default', 'DESC')
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get('pos_printer_template')
                ->row_array();
        }

        if (!$templateRow && $this->coredb->table_exists('pos_printer_template')) {
            $templateRow = $this->coredb
                ->from('pos_printer_template')
                ->where('document_type', $desiredDocumentType)
                ->where('is_active', 1)
                ->order_by('is_default', 'DESC')
                ->order_by('id', 'ASC')
                ->limit(1)
                ->get()
                ->row_array();
        }

        $payload = [];
        if (!empty($templateRow['template_payload'])) {
            $decoded = json_decode((string)$templateRow['template_payload'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $ci = &get_instance();
        if (!isset($ci->posprinterpreviewservice)) {
            $ci->load->library('PosPrinterPreviewService');
        }
        $payload = $ci->posprinterpreviewservice->decodePayload($payload, $desiredDocumentType, $generalSettings);

        return [
            'document_type' => $desiredDocumentType,
            'row' => $templateRow ?: null,
            'payload' => $payload,
        ];
    }

    private function build_direct_kot_text(array $header, array $lines, array $printer, array $template = []): string
    {
        $width = $this->normalize_printer_chars_per_line(
            (int)($printer['paper_width_mm'] ?? 80),
            (int)($printer['chars_per_line'] ?? 48)
        );
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $payload = (array)($template['payload'] ?? []);
        $headerAlign = strtoupper((string)($payload['header_align'] ?? 'CENTER'));
        $footerAlign = strtoupper((string)($payload['footer_align'] ?? 'CENTER'));
        $showHeader = !empty($payload['show_header']);
        $showFooter = !empty($payload['show_footer']);
        $showCustomer = !empty($payload['show_customer']);
        $showTableNo = !empty($payload['show_table_no']);
        $showOrderTime = !empty($payload['show_order_time']);
        $showCashier = !empty($payload['show_cashier_order']);
        $showQty = !empty($payload['show_qty']);
        $showExtra = !empty($payload['show_extra']);
        $showNotes = !empty($payload['show_notes']);
        $chunks = [];
        $chunks[] = $divider;
        if ($showHeader) {
            $title = trim((string)($payload['title'] ?? ''));
            $subtitle = trim((string)($payload['subtitle'] ?? ''));
            if ($title !== '') {
                $chunks[] = $this->align_text_line($title, $width, $headerAlign);
            }
            if ($subtitle !== '') {
                $chunks[] = $this->align_text_line($subtitle, $width, $headerAlign);
            }
            foreach ((array)($payload['header_lines'] ?? []) as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $chunks[] = $this->align_text_line($line, $width, $headerAlign);
                }
            }
            $chunks[] = $dash;
        }
        $chunks[] = 'ORDER  ' . (string)($header['order_no'] ?? '-');
        if ($showTableNo && !empty($header['table_no'])) {
            $chunks[] = 'MEJA   ' . (string)$header['table_no'];
        }
        $chunks[] = 'LAYANAN ' . (string)($header['service_type'] ?? '-');
        $chunks[] = 'GUEST   ' . (int)($header['guest_count'] ?? 1);
        if ($showCustomer && !empty($header['member_name'])) {
            $chunks[] = 'MEMBER  ' . (string)$header['member_name'];
        }
        $cashierLabel = trim((string)($header['cashier_username'] ?? ''));
        if ($cashierLabel === '') {
            $cashierLabel = trim((string)($header['cashier_employee_name'] ?? ''));
        }
        if ($showCashier && $cashierLabel !== '') {
            $chunks[] = 'KASIR   ' . strtoupper($cashierLabel);
        }
        if ($showOrderTime && !empty($header['ordered_at'])) {
            $chunks[] = 'WAKTU   ' . date('d-m-Y H:i', strtotime((string)$header['ordered_at']));
        }
        $chunks[] = $dash;

        foreach ($lines as $line) {
            $qtyLabel = rtrim(rtrim(number_format((float)($line['qty'] ?? 0), 2, '.', ''), '0'), '.');
            $prefix = $showQty ? ($qtyLabel . ' x ') : '';
            $chunks[] = $prefix . (string)($line['product_name'] ?? '-');
            if ($showExtra) {
                foreach ((array)($line['extras'] ?? []) as $extra) {
                    $extraQty = rtrim(rtrim(number_format((float)($extra['qty'] ?? 0), 2, '.', ''), '0'), '.');
                    $extraPrefix = $showQty && $extraQty !== '' ? ' x' . $extraQty : '';
                    $chunks[] = '  + ' . (string)($extra['name'] ?? '-') . $extraPrefix;
                }
            }
            if ($showNotes && !empty($line['notes'])) {
                $chunks[] = '  NOTE: ' . (string)$line['notes'];
            }
            $chunks[] = $dash;
        }

        if ($showFooter) {
            foreach ((array)($payload['footer_lines'] ?? []) as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    $chunks[] = $this->align_text_line($line, $width, $footerAlign);
                }
            }
        }
        $chunks[] = "\n\n";
        return implode("\n", $chunks);
    }

    private function normalize_printer_chars_per_line(int $paperWidthMm, int $charsPerLine): int
    {
        $paperWidthMm = $paperWidthMm === 58 ? 58 : 80;
        $recommended = $paperWidthMm === 58 ? 32 : 48;
        $max = $paperWidthMm === 58 ? 32 : 48;
        if ($charsPerLine <= 0) {
            $charsPerLine = $recommended;
        }
        return max(24, min($max, $charsPerLine));
    }

    private function center_text_line(string $text, int $width): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($len >= $width) {
            return $text;
        }
        $left = (int)floor(($width - $len) / 2);
        return str_repeat(' ', max(0, $left)) . $text;
    }

    private function align_text_line(string $text, int $width, string $align = 'CENTER'): string
    {
        $text = trim($text);
        $align = strtoupper(trim($align));
        if ($text === '') {
            return '';
        }
        if ($align === 'LEFT' || $align === 'JUSTIFY') {
            return $text;
        }
        if ($align === 'RIGHT') {
            $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
            if ($len >= $width) {
                return $text;
            }
            return str_repeat(' ', max(0, $width - $len)) . $text;
        }
        return $this->center_text_line($text, $width);
    }

    private function load_order_product_recipe_rows(int $productId): array
    {
        if ($productId <= 0 || !$this->db->table_exists('mst_product_recipe')) {
            return [];
        }

        $select = [
            'r.id',
            'r.product_id',
            'r.line_type',
            'r.qty',
            'r.source_division_id',
            'r.material_item_id',
            'r.component_id',
            'i.material_id',
            'i.item_name',
            'i.content_uom_id AS item_uom_id',
            'm.material_name',
            'm.content_uom_id AS material_uom_id',
            'c.component_name',
            'c.operational_division_id AS component_operational_division_id',
            'c.uom_id AS component_uom_id',
        ];
        if ($this->db->field_exists('ingredient_role', 'mst_product_recipe')) {
            $select[] = 'r.ingredient_role';
        }
        if ($this->db->field_exists('uom_id', 'mst_product_recipe')) {
            $select[] = 'r.uom_id AS recipe_uom_id';
        }

        return $this->db->select(implode(",\n", $select))
            ->from('mst_product_recipe r')
            ->join('mst_item i', 'i.id = r.material_item_id', 'left')
            ->join('mst_material m', 'm.id = i.material_id', 'left')
            ->join('mst_component c', 'c.id = r.component_id', 'left')
            ->where('r.product_id', $productId)
            ->order_by('r.sort_order', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function resolve_order_recipe_consumption_line(array $header, array $orderLine, array $recipeRow): array
    {
        $lineType = strtoupper(trim((string)($recipeRow['line_type'] ?? 'MATERIAL')));
        $orderedQty = (float)($orderLine['qty'] ?? 0);
        $recipeQty = (float)($recipeRow['qty'] ?? 0);
        if ($orderedQty <= 0 || $recipeQty <= 0) {
            return ['ok' => false, 'message' => 'Qty recipe/order tidak valid untuk produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
        }

        $requiredQty = round($orderedQty * $recipeQty, 4);
        $sourceRole = $this->normalize_stock_source_role((string)($recipeRow['ingredient_role'] ?? 'MAIN'));
        $resolvedDivisionId = 0;
        $requiredUomId = 0;
        $sourceName = '';
        $unitCost = 0.0;
        $costSource = 'STANDARD_FALLBACK';
        $materialId = null;
        $componentId = null;

        if ($lineType === 'COMPONENT') {
            $componentId = (int)($recipeRow['component_id'] ?? 0);
            if ($componentId <= 0) {
                return ['ok' => false, 'message' => 'Recipe produk memakai component yang tidak valid pada produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
            }
            $resolvedDivisionId = (int)($recipeRow['source_division_id'] ?? 0);
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($recipeRow['component_operational_division_id'] ?? 0);
            }
            $requiredUomId = (int)($recipeRow['recipe_uom_id'] ?? 0);
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['component_uom_id'] ?? 0);
            }
            $sourceName = trim((string)($recipeRow['component_name'] ?? ''));
            $cost = $this->resolve_order_component_live_cost($componentId, $resolvedDivisionId);
            $unitCost = (float)($cost['unit_cost'] ?? 0);
            $costSource = (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK');
        } else {
            $materialId = (int)($recipeRow['material_id'] ?? 0);
            if ($materialId <= 0) {
                return ['ok' => false, 'message' => 'Recipe produk memakai bahan baku yang belum tersambung material pada produk ' . (string)($orderLine['product_name'] ?? '-') . '.'];
            }
            $resolvedDivisionId = (int)($recipeRow['source_division_id'] ?? 0);
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
            }
            $requiredUomId = (int)($recipeRow['recipe_uom_id'] ?? 0);
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['item_uom_id'] ?? 0);
            }
            if ($requiredUomId <= 0) {
                $requiredUomId = (int)($recipeRow['material_uom_id'] ?? 0);
            }
            $sourceName = trim((string)($recipeRow['material_name'] ?? ($recipeRow['item_name'] ?? '')));
            $cost = $this->resolve_order_material_live_cost($materialId, $resolvedDivisionId, $requiredUomId);
            $unitCost = (float)($cost['unit_cost'] ?? 0);
            $costSource = (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK');
        }

        if ($resolvedDivisionId <= 0) {
            $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
        }

        return [
            'ok' => true,
            'line' => [
                'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
                'order_line_id' => !empty($orderLine['id']) ? (int)$orderLine['id'] : null,
                'order_line_extra_id' => null,
                'line_type' => 'PRODUCT',
                'product_id' => (int)($orderLine['product_id'] ?? 0),
                'extra_id' => null,
                'source_kind' => $lineType === 'COMPONENT' ? 'COMPONENT' : 'MATERIAL',
                'source_role' => $sourceRole,
                'material_id' => $materialId ?: null,
                'component_id' => $componentId ?: null,
                'source_name_snapshot' => $sourceName !== '' ? $sourceName : null,
                'required_qty' => $requiredQty,
                'required_uom_id' => $requiredUomId > 0 ? $requiredUomId : null,
                'committed_qty' => $requiredQty,
                'reversed_qty' => 0,
                'unit_cost_live' => round($unitCost, 6),
                'total_cost_live' => round($requiredQty * $unitCost, 6),
                'cost_source' => $costSource,
                'movement_ref_type' => 'NONE',
                'movement_ref_id' => null,
                'return_policy' => 'RETURN_TO_STOCK',
                'reversal_status' => 'NONE',
                'notes' => 'Resolved dari recipe produk line #' . (int)($orderLine['line_no'] ?? 0),
            ],
        ];
    }

    private function resolve_order_extra_consumption_line(array $header, array $orderLine, array $extraLine): array
    {
        $sourceKind = strtoupper(trim((string)($extraLine['source_kind'] ?? 'NONE')));
        if ($sourceKind === 'NONE') {
            return ['ok' => true, 'lines' => []];
        }

        $extraQty = (float)($extraLine['qty'] ?? 0);
        $sourceQty = (float)($extraLine['source_qty'] ?? 0);
        if ($extraQty <= 0 || $sourceQty <= 0) {
            return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source qty yang valid untuk stock commit.'];
        }

        $requiredQty = round($extraQty * $sourceQty, 4);
        $resolvedDivisionId = (int)($orderLine['operational_division_id'] ?? 0);
        $line = [
            'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
            'order_line_id' => !empty($orderLine['id']) ? (int)$orderLine['id'] : null,
            'order_line_extra_id' => !empty($extraLine['id']) ? (int)$extraLine['id'] : null,
            'line_type' => 'EXTRA',
            'product_id' => (int)($orderLine['product_id'] ?? 0),
            'extra_id' => (int)($extraLine['extra_id'] ?? 0),
            'source_role' => 'OPTIONAL',
            'movement_ref_type' => 'NONE',
            'movement_ref_id' => null,
            'return_policy' => 'RETURN_TO_STOCK',
            'reversal_status' => 'NONE',
            'notes' => 'Resolved dari extra order line #' . (int)($orderLine['line_no'] ?? 0),
        ];

        if ($sourceKind === 'COMPONENT') {
            $componentId = (int)($extraLine['source_component_id'] ?? 0);
            if ($componentId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source component yang valid.'];
            }
            $component = $this->db->select('component_name, uom_id, operational_division_id')
                ->from('mst_component')
                ->where('id', $componentId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if ($resolvedDivisionId <= 0) {
                $resolvedDivisionId = (int)($component['operational_division_id'] ?? 0);
            }
            $cost = $this->resolve_order_component_live_cost($componentId, $resolvedDivisionId);
            return [
                'ok' => true,
                'lines' => [[
                    'source_kind' => 'COMPONENT',
                    'material_id' => null,
                    'component_id' => $componentId,
                    'source_name_snapshot' => trim((string)($component['component_name'] ?? ($extraLine['extra_name'] ?? ''))) ?: null,
                    'required_qty' => $requiredQty,
                    'required_uom_id' => !empty($component['uom_id']) ? (int)$component['uom_id'] : null,
                    'committed_qty' => $requiredQty,
                    'reversed_qty' => 0,
                    'unit_cost_live' => round((float)($cost['unit_cost'] ?? 0), 6),
                    'total_cost_live' => round($requiredQty * (float)($cost['unit_cost'] ?? 0), 6),
                    'cost_source' => (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK'),
                ] + $line],
            ];
        }

        if ($sourceKind === 'MATERIAL') {
            $materialId = (int)($extraLine['source_material_id'] ?? 0);
            if ($materialId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source material yang valid.'];
            }
            $material = $this->db->select('material_name, content_uom_id')
                ->from('mst_material')
                ->where('id', $materialId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            $requiredUomId = !empty($material['content_uom_id']) ? (int)$material['content_uom_id'] : null;
            $cost = $this->resolve_order_material_live_cost($materialId, $resolvedDivisionId, (int)$requiredUomId);
            return [
                'ok' => true,
                'lines' => [[
                    'source_kind' => 'MATERIAL',
                    'material_id' => $materialId,
                    'component_id' => null,
                    'source_name_snapshot' => trim((string)($material['material_name'] ?? ($extraLine['extra_name'] ?? ''))) ?: null,
                    'required_qty' => $requiredQty,
                    'required_uom_id' => $requiredUomId,
                    'committed_qty' => $requiredQty,
                    'reversed_qty' => 0,
                    'unit_cost_live' => round((float)($cost['unit_cost'] ?? 0), 6),
                    'total_cost_live' => round($requiredQty * (float)($cost['unit_cost'] ?? 0), 6),
                    'cost_source' => (string)($cost['cost_source'] ?? 'STANDARD_FALLBACK'),
                ] + $line],
            ];
        }

        if ($sourceKind === 'PRODUCT') {
            $sourceProductId = (int)($extraLine['source_product_id'] ?? 0);
            if ($sourceProductId <= 0) {
                return ['ok' => false, 'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya source produk yang valid.'];
            }

            $sourceProduct = $this->db->select('id, product_name, product_division_id')
                ->from('mst_product')
                ->where('id', $sourceProductId)
                ->limit(1)
                ->get()
                ->row_array() ?: [];
            if (empty($sourceProduct['id'])) {
                return ['ok' => false, 'message' => 'Produk sumber untuk extra ' . (string)($extraLine['extra_name'] ?? '-') . ' tidak ditemukan.'];
            }

            $sourceRecipeRows = $this->load_order_product_recipe_rows($sourceProductId);
            if (empty($sourceRecipeRows)) {
                return ['ok' => false, 'message' => 'Produk sumber untuk extra ' . (string)($extraLine['extra_name'] ?? '-') . ' belum punya recipe aktif.'];
            }

            $pseudoLine = [
                'id' => (int)($orderLine['id'] ?? 0),
                'order_id' => (int)($header['id'] ?? ($orderLine['order_id'] ?? 0)),
                'line_no' => (int)($orderLine['line_no'] ?? 0),
                'product_id' => $sourceProductId,
                'product_name' => (string)($sourceProduct['product_name'] ?? ($extraLine['extra_name'] ?? '')),
                'qty' => $requiredQty,
                'operational_division_id' => $resolvedDivisionId > 0 ? $resolvedDivisionId : (int)($orderLine['operational_division_id'] ?? 0),
            ];

            $resolvedLines = [];
            foreach ($sourceRecipeRows as $recipeRow) {
                $recipeResult = $this->resolve_order_recipe_consumption_line($header, $pseudoLine, $recipeRow);
                if (!($recipeResult['ok'] ?? false)) {
                    return $recipeResult;
                }
                $recipeLine = (array)($recipeResult['line'] ?? []);
                $recipeLine['order_line_id'] = !empty($orderLine['id']) ? (int)$orderLine['id'] : null;
                $recipeLine['order_line_extra_id'] = !empty($extraLine['id']) ? (int)$extraLine['id'] : null;
                $recipeLine['line_type'] = 'EXTRA';
                $recipeLine['product_id'] = (int)($orderLine['product_id'] ?? 0);
                $recipeLine['extra_id'] = (int)($extraLine['extra_id'] ?? 0);
                $recipeLine['source_role'] = 'OPTIONAL';
                $recipeLine['notes'] = 'Resolved dari extra produk "' . (string)($sourceProduct['product_name'] ?? '-') . '" pada order line #' . (int)($orderLine['line_no'] ?? 0);
                $resolvedLines[] = $recipeLine;
            }

            return ['ok' => true, 'lines' => $resolvedLines];
        }

        return ['ok' => true, 'lines' => []];
    }

    private function resolve_order_extra_replacement_consumption_line(array $header, array $orderLine, array $extraLine): array
    {
        $replacementKind = strtoupper(trim((string)($extraLine['replacement_kind'] ?? 'NONE')));
        if ($replacementKind === 'NONE') {
            return ['ok' => true, 'lines' => []];
        }

        $mapped = $extraLine;
        $mapped['source_kind'] = $replacementKind;
        $mapped['source_product_id'] = !empty($extraLine['replacement_product_id']) ? (int)$extraLine['replacement_product_id'] : null;
        $mapped['source_component_id'] = !empty($extraLine['replacement_component_id']) ? (int)$extraLine['replacement_component_id'] : null;
        $mapped['source_material_id'] = !empty($extraLine['replacement_material_id']) ? (int)$extraLine['replacement_material_id'] : null;
        $mapped['source_qty'] = round((float)($extraLine['replacement_qty'] ?? 0), 4);

        $resolved = $this->resolve_order_extra_consumption_line($header, $orderLine, $mapped);
        if (!($resolved['ok'] ?? false)) {
            $message = (string)($resolved['message'] ?? 'Pengganti extra gagal di-resolve untuk stock commit.');
            return ['ok' => false, 'message' => str_replace('source', 'replacement', $message)];
        }

        $lines = [];
        foreach ((array)($resolved['lines'] ?? []) as $line) {
            $line['notes'] = 'Resolved dari pengganti extra order line #' . (int)($orderLine['line_no'] ?? 0);
            $lines[] = $line;
        }

        return ['ok' => true, 'lines' => $lines];
    }

    private function apply_order_extra_removal_to_resolved_lines(array $header, array $orderLine, array $resolvedLines, array $extraLine): array
    {
        $targets = $this->resolve_order_extra_consumption_line($header, $orderLine, $extraLine);
        if (!($targets['ok'] ?? false)) {
            return $targets;
        }

        $targetLines = (array)($targets['lines'] ?? []);
        if (empty($targetLines)) {
            return ['ok' => true, 'lines' => $resolvedLines];
        }

        foreach ($targetLines as $targetLine) {
            $remainingQty = round((float)($targetLine['required_qty'] ?? 0), 4);
            if ($remainingQty <= 0) {
                continue;
            }

            foreach ($resolvedLines as $index => $resolvedLine) {
                if ($remainingQty <= 0) {
                    break;
                }
                if (!$this->stock_commit_lines_match($resolvedLine, $targetLine)) {
                    continue;
                }

                $availableQty = round((float)($resolvedLine['required_qty'] ?? 0), 4);
                if ($availableQty <= 0) {
                    continue;
                }

                $deductQty = min($availableQty, $remainingQty);
                $nextQty = round($availableQty - $deductQty, 4);
                $remainingQty = round($remainingQty - $deductQty, 4);
                $resolvedLines[$index]['required_qty'] = $nextQty;
                $resolvedLines[$index]['committed_qty'] = $nextQty;
                $resolvedLines[$index]['total_cost_live'] = round($nextQty * (float)($resolvedLines[$index]['unit_cost_live'] ?? 0), 6);
                $resolvedLines[$index]['notes'] = $this->merge_note(
                    (string)($resolvedLines[$index]['notes'] ?? ''),
                    'Dikurangi extra ' . (string)($extraLine['extra_name'] ?? '-')
                );
            }

            if ($remainingQty > 0.0001) {
                return [
                    'ok' => false,
                    'message' => 'Extra ' . (string)($extraLine['extra_name'] ?? '-') . ' tidak sinkron dengan recipe produk ' . (string)($orderLine['product_name'] ?? '-') . '. Source ' . (string)($targetLine['source_name_snapshot'] ?? '-') . ' tidak cukup untuk dikurangi.',
                ];
            }
        }

        $resolvedLines = array_values(array_filter($resolvedLines, static function ($line): bool {
            return round((float)($line['required_qty'] ?? 0), 4) > 0;
        }));

        return ['ok' => true, 'lines' => $resolvedLines];
    }

    private function stock_commit_lines_match(array $left, array $right): bool
    {
        $leftKind = strtoupper(trim((string)($left['source_kind'] ?? 'NONE')));
        $rightKind = strtoupper(trim((string)($right['source_kind'] ?? 'NONE')));
        if ($leftKind === '' || $rightKind === '' || $leftKind !== $rightKind) {
            return false;
        }

        if ($leftKind === 'COMPONENT') {
            return (int)($left['component_id'] ?? 0) > 0
                && (int)($left['component_id'] ?? 0) === (int)($right['component_id'] ?? 0);
        }

        if ($leftKind === 'MATERIAL') {
            return (int)($left['material_id'] ?? 0) > 0
                && (int)($left['material_id'] ?? 0) === (int)($right['material_id'] ?? 0);
        }

        return false;
    }

    private function resolve_direct_print_line_ids_for_snapshot(int $orderId, int $snapshotId = 0): ?array
    {
        if (
            $orderId <= 0
            || !$this->db->table_exists('pos_stock_commit')
            || !$this->db->table_exists('pos_stock_commit_line')
        ) {
            return null;
        }

        $commitDb = $this->db->from('pos_stock_commit')->where('order_id', $orderId);
        if ($snapshotId > 0) {
            $commitDb->where('id', $snapshotId);
        } else {
            $commitDb->order_by('id', 'DESC');
        }
        $commit = $commitDb->limit(1)->get()->row_array();
        if (!$commit) {
            return $snapshotId > 0 ? [] : null;
        }

        $rows = $this->db->select('order_line_id')
            ->from('pos_stock_commit_line')
            ->where('commit_id', (int)($commit['id'] ?? 0))
            ->where('order_line_id IS NOT NULL', null, false)
            ->get()
            ->result_array();

        $lineIds = [];
        foreach ($rows as $row) {
            $lineId = (int)($row['order_line_id'] ?? 0);
            if ($lineId > 0) {
                $lineIds[$lineId] = $lineId;
            }
        }

        return array_values($lineIds);
    }

    private function merge_note(string $base, string $append): ?string
    {
        $base = trim($base);
        $append = trim($append);
        if ($append === '') {
            return $base !== '' ? $base : null;
        }
        if ($base === '') {
            return $append;
        }
        return $base . ' | ' . $append;
    }

    private function resolve_order_material_live_cost(int $materialId, int $divisionId, int $uomId = 0): array
    {
        if ($materialId <= 0) {
            return ['unit_cost' => 0.0, 'cost_source' => 'STANDARD_FALLBACK'];
        }

        $material = $this->db->select('id, hpp_standard')
            ->from('mst_material')
            ->where('id', $materialId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $fallback = round((float)($material['hpp_standard'] ?? 0), 6);
        if ($divisionId > 0 && $this->db->table_exists('inv_division_stock_balance')) {
            $this->db->select('avg_cost_per_content')
                ->from('inv_division_stock_balance')
                ->where('division_id', $divisionId)
                ->where('material_id', $materialId);
            if ($uomId > 0 && $this->db->field_exists('content_uom_id', 'inv_division_stock_balance')) {
                $this->db->where('content_uom_id', $uomId);
            }
            $liveRow = $this->db->order_by('updated_at', 'DESC')->limit(1)->get()->row_array() ?: [];
            $live = round((float)($liveRow['avg_cost_per_content'] ?? 0), 6);
            if ($live > 0) {
                return ['unit_cost' => $live, 'cost_source' => 'LAST_LIVE'];
            }
        }

        return ['unit_cost' => $fallback, 'cost_source' => 'STANDARD_FALLBACK'];
    }

    private function resolve_order_component_live_cost(int $componentId, int $divisionId = 0): array
    {
        if ($componentId <= 0) {
            return ['unit_cost' => 0.0, 'cost_source' => 'STANDARD_FALLBACK'];
        }

        $component = $this->db->select('id, hpp_standard')
            ->from('mst_component')
            ->where('id', $componentId)
            ->limit(1)
            ->get()
            ->row_array() ?: [];

        $fallback = round((float)($component['hpp_standard'] ?? 0), 6);
        if ($this->db->table_exists('inv_component_stock_balance')) {
            $this->db->select('avg_cost')
                ->from('inv_component_stock_balance')
                ->where('component_id', $componentId);
            if ($divisionId > 0 && $this->db->field_exists('division_id', 'inv_component_stock_balance')) {
                $this->db->where('division_id', $divisionId);
            }
            $liveRow = $this->db->order_by('updated_at', 'DESC')->limit(1)->get()->row_array() ?: [];
            $live = round((float)($liveRow['avg_cost'] ?? 0), 6);
            if ($live > 0) {
                return ['unit_cost' => $live, 'cost_source' => 'LAST_LIVE'];
            }
        }

        return ['unit_cost' => $fallback, 'cost_source' => 'STANDARD_FALLBACK'];
    }

    private function normalize_stock_source_role(string $role): string
    {
        $role = strtoupper(trim($role));
        if (in_array($role, ['GARNISH', 'SAUCE', 'TOPPING'], true)) {
            return 'COMPLEMENT';
        }
        if (in_array($role, ['SUPPORT', 'OTHER'], true)) {
            return 'SUPPORT';
        }
        if ($role === 'OPTIONAL') {
            return 'OPTIONAL';
        }
        return 'MAIN';
    }

    private function resolve_product_default_operational_division(array $product): ?int
    {
        $divisionId = (int)($product['default_operational_division_id'] ?? 0);
        if ($divisionId > 0) {
            return $divisionId;
        }

        $divisionKey = strtoupper(trim((string)($product['product_division_code'] ?? ($product['product_division_name'] ?? ''))));
        $preferred = '';
        if ($divisionKey === 'BEVERAGE') {
            $preferred = 'BAR';
        } elseif ($divisionKey === 'FOOD') {
            $preferred = 'KITCHEN';
        }
        if ($preferred === '') {
            return null;
        }

        $row = $this->db->select('id')
            ->from('mst_operational_division')
            ->where('UPPER(name) =', $preferred)
            ->limit(1)
            ->get()
            ->row_array();

        return !empty($row['id']) ? (int)$row['id'] : null;
    }

    private function insert_order_state_log(int $orderId, ?string $fromStatus, string $toStatus, string $eventCode, int $actorEmployeeId, ?string $notes = null): void
    {
        $this->db->insert('pos_order_state_log', [
            'order_id' => $orderId,
            'from_status' => $fromStatus !== '' ? $fromStatus : null,
            'to_status' => $toStatus,
            'event_code' => $eventCode,
            'actor_employee_id' => $actorEmployeeId > 0 ? $actorEmployeeId : null,
            'notes' => $notes,
        ]);
    }

    private function snapshot_line_key(array $line): string
    {
        return 'commit_line:' . (int)($line['id'] ?? 0);
    }

    private function build_reversal_selection(array $order, array $planLines, array $requestedLines, array $options = []): array
    {
        $orderLines = [];
        foreach ((array)($order['lines'] ?? []) as $line) {
            $orderLines[(int)($line['id'] ?? 0)] = $line;
        }

        $requestedMap = [];
        foreach ($requestedLines as $requestedLine) {
            if (!is_array($requestedLine)) {
                continue;
            }
            $orderLineId = (int)($requestedLine['order_line_id'] ?? 0);
            if ($orderLineId > 0) {
                $requestedMap[$orderLineId] = $requestedLine;
            }
        }
        if (empty($requestedMap)) {
            foreach ($orderLines as $orderLineId => $line) {
                $requestedMap[$orderLineId] = ['order_line_id' => $orderLineId, 'qty' => (float)($line['qty'] ?? 0)];
            }
        }
        $planByOrderLine = [];
        foreach ($planLines as $planLine) {
            $orderLineId = (int)($planLine['order_line_id'] ?? 0);
            if ($orderLineId <= 0) {
                continue;
            }
            if (!isset($planByOrderLine[$orderLineId])) {
                $planByOrderLine[$orderLineId] = [];
            }
            $planByOrderLine[$orderLineId][] = $planLine;
        }

        $productLines = [];
        $extraLines = [];
        $decisions = [];
        $totalQty = 0.0;
        $totalAmount = 0.0;
        $returnToStock = false;
        $hasProcessed = false;
        $isFull = true;
        if (count($requestedMap) < count($orderLines)) {
            $isFull = false;
        }

        foreach ($requestedMap as $orderLineId => $requestedLine) {
            if (empty($orderLines[$orderLineId])) {
                continue;
            }
            $line = $orderLines[$orderLineId];
            $qtyBefore = round((float)($line['qty'] ?? 0), 4);
            $qtySelected = round((float)($requestedLine['qty'] ?? $qtyBefore), 4);
            if ($qtyBefore <= 0 || $qtySelected <= 0) {
                continue;
            }
            $qtySelected = min($qtyBefore, $qtySelected);
            $ratio = $qtyBefore > 0 ? round($qtySelected / $qtyBefore, 8) : 0.0;
            $processedState = strtoupper(trim((string)($requestedLine['processed_state'] ?? ($options['default_processed_state'] ?? ($line['process_status'] ?? 'NOT_PROCESSED')))));
            $isProcessed = in_array($processedState, ['PROCESSED', 'SERVED'], true);
            $lineReturnToStock = !$isProcessed && (!empty($requestedLine['return_to_stock']) || !empty($options['default_return_to_stock']));
            $lineStatusAfter = $qtySelected >= $qtyBefore ? 'VOID' : 'OPEN';

            $productLines[] = [
                'order_line_id' => $orderLineId,
                'product_id' => (int)($line['product_id'] ?? 0),
                'line_no_snapshot' => (int)($line['line_no'] ?? 0),
                'item_name_snapshot' => (string)($line['product_name'] ?? ''),
                'qty_before' => $qtyBefore,
                'qty_void' => $qtySelected,
                'qty_after' => round($qtyBefore - $qtySelected, 4),
                'unit_price' => round((float)($line['unit_price'] ?? 0), 2),
                'subtotal_void' => round($qtySelected * (float)($line['unit_price'] ?? 0), 2),
                'hpp_live_snapshot' => round((float)($line['hpp_live_snapshot'] ?? 0), 6),
                'line_process_state' => $isProcessed ? 'PROCESSED' : 'NOT_PROCESSED',
                'line_status_after' => $lineStatusAfter,
                'notes' => $this->nullable_text($requestedLine['notes'] ?? ''),
            ];
            $totalQty += $qtySelected;
            $totalAmount += round($qtySelected * (float)($line['unit_price'] ?? 0), 2);
            $returnToStock = $returnToStock || $lineReturnToStock;
            $hasProcessed = $hasProcessed || $isProcessed;
            if ($qtySelected + 0.0001 < $qtyBefore) {
                $isFull = false;
            }

            foreach ((array)($line['extras'] ?? []) as $extra) {
                $extraQty = round((float)($extra['qty'] ?? 0), 4);
                $extraAffected = round($extraQty * $ratio, 4);
                if ($extraAffected <= 0) {
                    continue;
                }
                $extraLines[] = [
                    'order_line_id' => $orderLineId,
                    'order_line_extra_id' => (int)($extra['id'] ?? 0),
                    'extra_id' => (int)($extra['extra_id'] ?? 0),
                    'extra_name_snapshot' => (string)($extra['extra_name'] ?? ''),
                    'qty_per_unit' => $extraQty,
                    'line_qty_affected' => $extraAffected,
                    'unit_price' => round((float)($extra['unit_price'] ?? 0), 2),
                    'subtotal_void' => round($extraAffected * (float)($extra['unit_price'] ?? 0), 2),
                    'status_after' => $lineStatusAfter,
                    'cost_amount_snapshot' => round((float)($extra['cost_amount_snapshot'] ?? 0), 6),
                ];
                $totalAmount += round($extraAffected * (float)($extra['unit_price'] ?? 0), 2);
            }

            foreach ((array)($planByOrderLine[$orderLineId] ?? []) as $planLine) {
                $remainingQty = round((float)($planLine['remaining_qty'] ?? 0), 4);
                if ($remainingQty <= 0) {
                    continue;
                }
                $reverseQty = round($remainingQty * $ratio, 4);
                if ($reverseQty <= 0) {
                    continue;
                }
                $decisions[] = [
                    'line_key' => $this->snapshot_line_key($planLine),
                    'line_id' => (int)($planLine['id'] ?? 0),
                    'return_policy' => $lineReturnToStock ? 'RETURN_TO_STOCK' : 'ADJUSTMENT_ONLY',
                    'reverse_qty' => $reverseQty,
                    'notes' => (string)($requestedLine['notes'] ?? ''),
                ];
            }
        }

        return [
            'product_lines' => $productLines,
            'extra_lines' => $extraLines,
            'decisions' => $decisions,
            'total_qty' => round($totalQty, 4),
            'total_amount' => round($totalAmount, 2),
            'return_to_stock' => $returnToStock,
            'has_processed' => $hasProcessed,
            'is_full' => $isFull,
        ];
    }

    private function apply_order_line_reversal_updates(array $productLines, string $mode): void
    {
        foreach ($productLines as $line) {
            $orderLineId = (int)($line['order_line_id'] ?? 0);
            if ($orderLineId <= 0) {
                continue;
            }
            $statusAfter = (string)($line['line_status_after'] ?? 'OPEN');
            if ($mode === 'REFUND' && $statusAfter === 'VOID') {
                $statusAfter = 'REFUNDED_FULL';
            } elseif ($mode === 'REFUND' && $statusAfter === 'OPEN') {
                $statusAfter = 'REFUNDED_PARTIAL';
            }
            $this->db->where('id', $orderLineId)->update('pos_order_line', [
                'line_status' => $statusAfter,
            ]);
        }
    }

    private function normalize_pos_adjustment_mode(string $mode): string
    {
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, ['NONE', 'AUTO_WASTE', 'AUTO_SPOIL', 'AUTO_ADJUSTMENT'], true)) {
            return 'NONE';
        }
        return $mode;
    }

    private function calculate_shift_summary(int $shiftId): array
    {
        $base = [
            'total_order_count' => 0,
            'total_gross_sales' => 0,
            'total_discount' => 0,
            'total_promo' => 0,
            'total_net_sales' => 0,
            'total_cash_sales' => 0,
            'total_non_cash_sales' => 0,
            'total_deposit_receipts' => 0,
            'total_cash_deposit_receipts' => 0,
            'total_refund' => 0,
            'total_void' => 0,
        ];
        if ($shiftId <= 0) {
            return $base;
        }

        $orderAgg = $this->db->select('
                COUNT(*) AS total_order_count,
                COALESCE(SUM(subtotal_amount),0) AS total_gross_sales,
                COALESCE(SUM(discount_amount),0) AS total_discount,
                COALESCE(SUM(promo_amount),0) AS total_promo,
                COALESCE(SUM(grand_total),0) AS total_net_sales
            ', false)
            ->from('pos_order')
            ->where('shift_id', $shiftId)
            ->where_not_in('status', ['DRAFT', 'PENDING'])
            ->get()
            ->row_array() ?: [];

        $cashAgg = $this->db->select("
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) = 'CASH' THEN pl.amount ELSE 0 END),0) AS total_cash_sales,
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) <> 'CASH' THEN pl.amount ELSE 0 END),0) AS total_non_cash_sales
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->where('p.payment_type', 'FINAL')
            ->get()
            ->row_array() ?: [];

        $depositAgg = $this->db->select('COALESCE(SUM(net_amount),0) AS total_deposit_receipts', false)
            ->from('pos_payment')
            ->where('shift_id', $shiftId)
            ->where('payment_status', 'PAID')
            ->where('payment_type', 'DEPOSIT')
            ->get()
            ->row_array() ?: [];

        $depositCashAgg = $this->db->select("
                COALESCE(SUM(CASE WHEN UPPER(COALESCE(pm.method_type, 'OTHER')) = 'CASH' THEN pl.amount ELSE 0 END),0) AS total_cash_deposit_receipts
            ", false)
            ->from('pos_payment p')
            ->join('pos_payment_line pl', 'pl.payment_id = p.id', 'left')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left')
            ->where('p.shift_id', $shiftId)
            ->where('p.payment_status', 'PAID')
            ->where('p.payment_type', 'DEPOSIT')
            ->get()
            ->row_array() ?: [];

        $refundAgg = $this->db->query(
            "SELECT COALESCE(SUM(r.refund_amount),0) AS total_refund
             FROM pos_refund r
             INNER JOIN pos_order o ON o.id = r.order_id
             WHERE o.shift_id = ? AND r.refund_status = 'POSTED'",
            [$shiftId]
        )->row_array() ?: [];

        $voidAgg = $this->db->query(
            "SELECT COALESCE(SUM(v.amount_void),0) AS total_void
             FROM pos_void v
             INNER JOIN pos_order o ON o.id = v.order_id
             WHERE o.shift_id = ?",
            [$shiftId]
        )->row_array() ?: [];

        return [
            'total_order_count' => (int)($orderAgg['total_order_count'] ?? 0),
            'total_gross_sales' => round((float)($orderAgg['total_gross_sales'] ?? 0), 2),
            'total_discount' => round((float)($orderAgg['total_discount'] ?? 0), 2),
            'total_promo' => round((float)($orderAgg['total_promo'] ?? 0), 2),
            'total_net_sales' => round((float)($orderAgg['total_net_sales'] ?? 0), 2),
            'total_cash_sales' => round((float)($cashAgg['total_cash_sales'] ?? 0), 2),
            'total_non_cash_sales' => round((float)($cashAgg['total_non_cash_sales'] ?? 0), 2),
            'total_deposit_receipts' => round((float)($depositAgg['total_deposit_receipts'] ?? 0), 2),
            'total_cash_deposit_receipts' => round((float)($depositCashAgg['total_cash_deposit_receipts'] ?? 0), 2),
            'total_refund' => round((float)($refundAgg['total_refund'] ?? 0), 2),
            'total_void' => round((float)($voidAgg['total_void'] ?? 0), 2),
        ];
    }

    private function filter_table_payload(string $table, array $payload): array
    {
        if ($table === '' || empty($payload)) {
            return $payload;
        }

        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($this->db->field_exists($key, $table)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function db_error_message(string $fallback): string
    {
        $error = (array)$this->db->error();
        $message = trim((string)($error['message'] ?? ''));
        if ($message === '') {
            return $fallback;
        }
        return $fallback . ' [' . $message . ']';
    }

    private function generate_pos_shift_no(int $outletId, ?string $openedAt = null): string
    {
        $openedAt = $openedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($openedAt));
        $prefix = 'SHIFT-' . $dateKey . '-' . str_pad((string)$outletId, 2, '0', STR_PAD_LEFT);
        $row = $this->db->query(
            "SELECT shift_no FROM pos_shift WHERE shift_no LIKE ? ORDER BY shift_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();
        $next = 1;
        if (!empty($row['shift_no'])) {
            $parts = explode('-', (string)$row['shift_no']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_pos_session_key(int $outletId, int $terminalId, int $employeeId, ?string $loginAt = null): string
    {
        $loginAt = $loginAt ?: date('Y-m-d H:i:s');
        return strtoupper(implode('-', [
            'SESS',
            date('YmdHis', strtotime($loginAt)),
            str_pad((string)$outletId, 2, '0', STR_PAD_LEFT),
            str_pad((string)$terminalId, 2, '0', STR_PAD_LEFT),
            str_pad((string)$employeeId, 3, '0', STR_PAD_LEFT),
        ]));
    }

    private function generate_pos_order_no(?string $orderedAt = null): string
    {
        $orderedAt = $orderedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($orderedAt));
        $prefix = 'POS-' . $dateKey;
        $row = $this->db->query(
            "SELECT order_no FROM pos_order WHERE order_no LIKE ? ORDER BY order_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['order_no'])) {
            $parts = explode('-', (string)$row['order_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_pos_payment_no(string $paymentType = 'FINAL', ?string $paidAt = null): string
    {
        $paidAt = $paidAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($paidAt));
        $type = strtoupper(trim($paymentType));
        $typeCodeMap = [
            'FINAL' => 'PAY',
            'DEPOSIT' => 'DP',
            'REFUND' => 'RFD',
        ];
        $typeCode = $typeCodeMap[$type] ?? 'PAY';
        $prefix = $typeCode . '-' . $dateKey;
        $row = $this->db->query(
            "SELECT payment_no FROM pos_payment WHERE payment_no LIKE ? ORDER BY payment_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['payment_no'])) {
            $parts = explode('-', (string)$row['payment_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_account_mutation_no(string $mutationDate): string
    {
        $datePart = date('Ymd', strtotime($mutationDate));
        $prefix = 'MUT' . $datePart;

        $row = $this->db->select('mutation_no')
            ->from('fin_account_mutation_log')
            ->like('mutation_no', $prefix, 'after')
            ->order_by('mutation_no', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        $seq = 1;
        if (!empty($row['mutation_no'])) {
            $suffix = substr((string)$row['mutation_no'], strlen($prefix));
            if (ctype_digit($suffix)) {
                $seq = ((int)$suffix) + 1;
            }
        }

        do {
            $no = $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($this->db->where('mutation_no', $no)->count_all_results('fin_account_mutation_log') > 0);

        return $no;
    }

    private function generate_job_no(?string $requestedAt = null): string
    {
        $requestedAt = $requestedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($requestedAt));
        $prefix = 'PJOB-' . $dateKey;
        $row = $this->db->query(
            "SELECT job_no FROM pos_printer_job WHERE job_no LIKE ? ORDER BY job_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if (!empty($row['job_no'])) {
            $parts = explode('-', (string)$row['job_no']);
            $next = ((int)end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    public function local_outlet_options(): array
    {
        if (!$this->db->table_exists('pos_outlet')) {
            return [];
        }
        return $this->db->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function local_terminal_options(int $outletId = 0): array
    {
        if (!$this->db->table_exists('pos_terminal')) {
            return [];
        }
        $this->db->select('id, outlet_id, terminal_code, terminal_name')
            ->from('pos_terminal')
            ->where('is_active', 1);
        if ($outletId > 0) {
            $this->db->where('outlet_id', $outletId);
        }
        return $this->db->order_by('terminal_name', 'ASC')->get()->result_array();
    }

    private function distinct_non_empty_values(CI_DB_query_builder $db, string $table, string $column): array
    {
        if (!$db->table_exists($table) || !$db->field_exists($column, $table)) {
            return [];
        }
        $rows = $db->select($column)
            ->from($table)
            ->where($column . ' IS NOT NULL', null, false)
            ->where('TRIM(' . $column . ") <> ''", null, false)
            ->group_by($column)
            ->order_by($column, 'ASC')
            ->get()
            ->result_array();

        return array_values(array_filter(array_map(static function (array $row) use ($column): string {
            return trim((string)($row[$column] ?? ''));
        }, $rows)));
    }

    private function core_record_exists(string $table, int $id): bool
    {
        if ($id <= 0 || !$this->coredb->table_exists($table)) {
            return false;
        }
        return (int)$this->coredb->from($table)->where('id', $id)->count_all_results() > 0;
    }

    private function core_field_exists(string $table, string $field): bool
    {
        return $this->coredb->table_exists($table) && $this->coredb->field_exists($field, $table);
    }

    private function core_printer_template_master_options(): array
    {
        if (!$this->coredb->table_exists('pos_printer_template_master')) {
            return [];
        }
        $db = $this->coredb->select('id, master_code, master_name')
            ->from('pos_printer_template_master')
            ->where('is_active', 1);
        if ($this->core_field_exists('pos_printer_template_master', 'is_default')) {
            $db->order_by('is_default', 'DESC');
        }
        return $db
            ->order_by('master_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function core_printer_template_options(): array
    {
        if (!$this->coredb->table_exists('pos_printer_template')) {
            return [];
        }
        return $this->coredb->select('id, template_code, template_name, document_type, is_default, is_active')
            ->from('pos_printer_template')
            ->where('is_active', 1)
            ->order_by('document_type', 'ASC')
            ->order_by('is_default', 'DESC')
            ->order_by('template_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function active_outlet_options(): array
    {
        if (!$this->coredb->table_exists('pos_outlet')) {
            return [];
        }
        return $this->coredb->select('id, outlet_code, outlet_name')
            ->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function active_terminal_options(): array
    {
        if (!$this->coredb->table_exists('pos_terminal')) {
            return [];
        }
        return $this->coredb->select('id, outlet_id, terminal_code, terminal_name')
            ->from('pos_terminal')
            ->where('is_active', 1)
            ->order_by('terminal_name', 'ASC')
            ->get()
            ->result_array();
    }

    private function core_printer_options(): array 
    { 
        if (!$this->coredb->table_exists('pos_printer')) { 
            return []; 
        } 
        $roleSelect = $this->core_field_exists('pos_printer', 'printer_role') ? 'printer_role' : "'CUSTOM'";
        $scopeSelect = $this->core_field_exists('pos_printer', 'print_scope') ? 'print_scope' : "'DIVISION'";
        return $this->coredb->select("id, printer_code, printer_name, {$roleSelect} AS printer_role, {$scopeSelect} AS print_scope, outlet_id", false) 
            ->from('pos_printer') 
            ->where('is_active', 1) 
            ->order_by('printer_name', 'ASC') 
            ->get() 
            ->result_array(); 
    }

    private function company_account_exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        return (int)$this->coredb->from('fin_company_account')->where('id', $id)->count_all_results() > 0;
    }

    private function terminal_device_key_exists(string $deviceKey, int $excludeId = 0): bool
    {
        $this->coredb->from('pos_terminal')->where('device_key', $deviceKey);
        if ($excludeId > 0) {
            $this->coredb->where('id !=', $excludeId);
        }
        return (int)$this->coredb->count_all_results() > 0;
    }

    private function code_exists(CI_DB_query_builder $db, string $table, string $column, string $code, int $excludeId = 0): bool
    {
        $db->from($table)->where($column, $code);
        if ($excludeId > 0) {
            $db->where('id !=', $excludeId);
        }
        return (int)$db->count_all_results() > 0;
    }

    private function generate_member_no_core(?string $joinedAt = null): string
    {
        $joinedAt = $joinedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($joinedAt));
        $prefix = 'MBR-' . $dateKey;
        $row = $this->coredb->query(
            "SELECT member_no FROM crm_member WHERE member_no LIKE ? ORDER BY member_no DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if ($row && !empty($row['member_no'])) {
            $parts = explode('-', (string)$row['member_no']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_named_code(CI_DB_query_builder $db, string $table, string $column, string $name, string $prefix = '', int $excludeId = 0, int $maxLen = 40): string
    {
        $slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', strtoupper($name)));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'ITEM';
        }
        $base = $prefix . substr($slug, 0, max(1, $maxLen - strlen($prefix) - 5));
        $candidate = $base;
        $seq = 1;
        while ($this->code_exists($db, $table, $column, $candidate, $excludeId)) {
            $suffix = '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
            $cut = max(1, $maxLen - strlen($suffix));
            $candidate = substr($base, 0, $cut) . $suffix;
            $seq++;
        }
        return $candidate;
    }

    private function sales_channel_service_type_options(): array
    {
        return ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'];
    }

    private function explode_service_types(string $raw): array
    {
        $parts = preg_split('/\s*,\s*/', strtoupper(trim($raw)));
        if (!is_array($parts)) {
            return [];
        }

        $allowed = $this->sales_channel_service_type_options();
        $normalized = [];
        foreach ($parts as $part) {
            $value = trim((string)$part);
            if ($value === '' || !in_array($value, $allowed, true)) {
                continue;
            }
            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    private function normalize_allowed_service_types($value): array
    {
        if (is_array($value)) {
            $joined = implode(',', array_map(static function ($item) {
                return strtoupper(trim((string)$item));
            }, $value));
            return $this->explode_service_types($joined);
        }

        return $this->explode_service_types((string)$value);
    }

    private function generate_pos_sales_channel_code(string $name): string
    {
        return $this->generate_named_code($this->coredb, 'pos_sales_channel', 'channel_code', $name, 'CH', 0, 40);
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

    private function normalize_printer_mac_address(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-F0-9]/', '', $value);
        return is_string($value) ? substr($value, 0, 32) : '';
    }

    private function printer_python_port_exists(string $agentHost, int $pythonPort, int $excludeId = 0): bool 
    { 
        if ($pythonPort <= 0) { 
            return false; 
        } 
        if (!$this->core_field_exists('pos_printer', 'python_port')) {
            return false;
        }
        $this->coredb->from('pos_printer') 
            ->where('python_port', $pythonPort) 
            ->where("UPPER(COALESCE(agent_host, '')) =", strtoupper(trim($agentHost))); 
        if ($excludeId > 0) {
            $this->coredb->where('id !=', $excludeId);
        }
        return (bool)$this->coredb->count_all_results();
    }
}
