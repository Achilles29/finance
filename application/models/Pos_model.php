<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_model extends CI_Model
{
    /** @var CI_DB_query_builder */
    protected $coredb;

    public function __construct()
    {
        parent::__construct();
        $this->coredb = $this->load->database('core', true);
    }

    public function member_filter_options(): array
    {
        return [
            'tiers' => $this->distinct_non_empty_values($this->coredb, 'crm_member_account', 'tier_code'),
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
        $db->from('crm_customer c')
            ->join('crm_member_account m', 'm.customer_id = c.id', 'inner');

        if ($q !== '') {
            $db->group_start()
                ->like('m.member_no', $q)
                ->or_like('c.customer_code', $q)
                ->or_like('c.customer_name', $q)
                ->or_like('c.phone', $q)
                ->or_like('c.email', $q)
                ->group_end();
        }
        if ($status === 'ACTIVE') {
            $db->where('c.is_active', 1);
        } elseif ($status === 'INACTIVE') {
            $db->where('c.is_active', 0);
        }
        if (in_array($memberStatus, ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'EXPIRED'], true)) {
            $db->where('m.status', $memberStatus);
        }
        if ($tier !== '') {
            $db->where('m.tier_code', $tier);
        }

        $total = (int)$db->count_all_results('', false);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        $rows = $db->select('
            c.id,
            c.customer_code,
            c.customer_name AS member_name,
            c.phone AS mobile_phone,
            c.email,
            c.birth_date,
            c.gender,
            c.notes,
            c.is_active,
            m.id AS member_account_id,
            m.member_no,
            m.tier_code AS member_tier,
            m.joined_at,
            m.expired_at,
            m.status AS member_status
        ')
            ->order_by('c.customer_name', 'ASC')
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
        return $this->coredb->from('crm_customer c')
            ->join('crm_member_account m', 'm.customer_id = c.id', 'inner')
            ->select('
                c.id,
                c.customer_code,
                c.customer_name AS member_name,
                c.phone AS mobile_phone,
                c.email,
                c.birth_date,
                c.gender,
                c.notes,
                c.is_active,
                m.id AS member_account_id,
                m.member_no,
                m.tier_code AS member_tier,
                m.joined_at,
                m.expired_at,
                m.status AS member_status
            ')
            ->where('c.id', $id)
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
        if (!in_array($memberStatus, ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'EXPIRED'], true)) {
            $memberStatus = 'ACTIVE';
        }
        $gender = strtoupper(trim((string)($data['gender'] ?? '')));
        if (!in_array($gender, ['MALE', 'FEMALE', 'OTHER'], true)) {
            $gender = null;
        }

        $db->trans_begin();
        try {
            if ($id > 0) {
                $existing = $this->find_member($id);
                if (!$existing) {
                    throw new RuntimeException('Member tidak ditemukan di database core.');
                }

                $customerPayload = [
                    'customer_name' => $name,
                    'phone' => $this->nullable_text($data['mobile_phone'] ?? ''),
                    'email' => $this->nullable_text($data['email'] ?? ''),
                    'birth_date' => $this->nullable_date($data['birth_date'] ?? ''),
                    'gender' => $gender,
                    'notes' => $this->nullable_text($data['notes'] ?? ''),
                ];
                $db->where('id', $id)->update('crm_customer', $customerPayload);

                $memberNo = strtoupper(trim((string)($data['member_no'] ?? '')));
                if ($memberNo === '') {
                    $memberNo = (string)$existing['member_no'];
                }
                if ($this->code_exists($db, 'crm_member_account', 'member_no', $memberNo, (int)($existing['member_account_id'] ?? 0))) {
                    throw new RuntimeException('Nomor member sudah dipakai di database core.');
                }

                $memberPayload = [
                    'member_no' => $memberNo,
                    'tier_code' => $this->nullable_text($data['member_tier'] ?? ''),
                    'joined_at' => $this->nullable_datetime($data['joined_at'] ?? '') ?: (string)$existing['joined_at'],
                    'expired_at' => $this->nullable_datetime($data['expired_at'] ?? ''),
                    'status' => $memberStatus,
                ];
                if (!empty($existing['member_account_id'])) {
                    $db->where('id', (int)$existing['member_account_id'])->update('crm_member_account', $memberPayload);
                } else {
                    $memberPayload['customer_id'] = $id;
                    $db->insert('crm_member_account', $memberPayload);
                }
            } else {
                $joinedAt = $this->nullable_datetime($data['joined_at'] ?? '') ?: date('Y-m-d H:i:s');
                $customerPayload = [
                    'customer_code' => $this->generate_customer_code_core($joinedAt),
                    'customer_name' => $name,
                    'phone' => $this->nullable_text($data['mobile_phone'] ?? ''),
                    'email' => $this->nullable_text($data['email'] ?? ''),
                    'birth_date' => $this->nullable_date($data['birth_date'] ?? ''),
                    'gender' => $gender,
                    'notes' => $this->nullable_text($data['notes'] ?? ''),
                    'is_active' => 1,
                ];
                $db->insert('crm_customer', $customerPayload);
                $id = (int)$db->insert_id();

                $memberPayload = [
                    'member_no' => $this->generate_member_no_core($joinedAt),
                    'customer_id' => $id,
                    'tier_code' => $this->nullable_text($data['member_tier'] ?? ''),
                    'joined_at' => $joinedAt,
                    'expired_at' => $this->nullable_datetime($data['expired_at'] ?? ''),
                    'status' => $memberStatus,
                ];
                $db->insert('crm_member_account', $memberPayload);
            }

            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan member ke database core.');
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
            return ['ok' => false, 'message' => 'Member tidak ditemukan di database core.'];
        }

        $newValue = ((int)($row['is_active'] ?? 0) === 1) ? 0 : 1;
        $db->trans_begin();
        try {
            $db->where('id', $id)->update('crm_customer', ['is_active' => $newValue]);
            if (!empty($row['member_account_id'])) {
                $memberStatus = strtoupper((string)($row['member_status'] ?? 'ACTIVE'));
                if ($newValue === 0 && $memberStatus === 'ACTIVE') {
                    $memberStatus = 'INACTIVE';
                } elseif ($newValue === 1 && $memberStatus === 'INACTIVE') {
                    $memberStatus = 'ACTIVE';
                }
                $db->where('id', (int)$row['member_account_id'])->update('crm_member_account', ['status' => $memberStatus]);
            }
            if ($db->trans_status() === false) {
                throw new RuntimeException('Gagal mengubah status member di database core.');
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
            'accounts' => $this->active_bank_accounts(),
        ];
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
            ->join('m_bank_account b', 'b.id = pm.bank_account_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('pm.method_code', $q)
                ->or_like('pm.method_name', $q)
                ->or_like('pm.method_type', $q)
                ->or_like('b.bank_name', $q)
                ->or_like('b.account_name', $q)
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

        $rows = $db->select('pm.*, b.bank_name, b.account_name, b.account_no')
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
        return $this->coredb->from('pos_payment_method')
            ->where('id', $id)
            ->limit(1)
            ->get()
            ->row_array() ?: null;
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
            return ['ok' => false, 'message' => 'Kode metode pembayaran sudah dipakai di core.'];
        }

        $bankAccountId = !empty($data['bank_account_id']) ? (int)$data['bank_account_id'] : null;
        if ($bankAccountId !== null && !$this->bank_account_exists($bankAccountId)) {
            return ['ok' => false, 'message' => 'Rekening bank tidak ditemukan di database core.'];
        }

        $payload = [
            'method_code' => $methodCode,
            'method_name' => $name,
            'method_type' => $type,
            'bank_account_id' => $bankAccountId,
        ];

        if ($id > 0) {
            if (!$this->find_payment_method($id)) {
                return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan di core.'];
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
            return ['ok' => false, 'message' => 'Metode pembayaran tidak ditemukan di core.'];
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

    public function active_bank_accounts(): array
    {
        return $this->coredb->select('id, bank_name, account_name, account_no')
            ->from('m_bank_account')
            ->where('is_active', 1)
            ->order_by('bank_name', 'ASC')
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
            ->where("TRIM(COALESCE(p.mac_address, '')) !=", '', false)
            ->where('p.python_port IS NOT NULL', null, false);

        if ($agentHost !== '') {
            $db->group_start()
                ->where("UPPER(COALESCE(p.agent_host, '')) =", $agentHost)
                ->or_where("TRIM(COALESCE(p.agent_host, '')) =", '', false)
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

    public function order_draft_rows(array $filters): array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? 'DRAFT')));
        $outletId = (int)($filters['outlet_id'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));

        $db = $this->db;
        $db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('pos_terminal pt', 'pt.id = o.terminal_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left');
        if ($q !== '') {
            $db->group_start()
                ->like('o.order_no', $q)
                ->or_like('po.outlet_name', $q)
                ->or_like('pt.terminal_name', $q)
                ->or_like('e.employee_name', $q)
                ->group_end();
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
        $rows = $db->select('
            o.id, o.order_no, o.service_type, o.status, o.stock_commit_status, o.ordered_at, o.confirmed_at,
            o.guest_count, o.grand_total, o.notes,
            po.outlet_name, pt.terminal_name, e.employee_name
        ')
            ->order_by('o.ordered_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        return ['rows' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => $totalPages]];
    }

    public function find_order_draft(int $id): ?array
    {
        $header = $this->db->select('o.*')
            ->from('pos_order o')
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
                pd.name AS product_division_name,
                u.code AS uom_code
            ')
            ->from('pos_order_line l')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = l.product_division_id_snapshot', 'left')
            ->join('mst_uom u', 'u.id = l.uom_id', 'left')
            ->where('l.order_id', $id)
            ->order_by('l.line_no', 'ASC')
            ->get()
            ->result_array();

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

        $serviceType = strtoupper(trim((string)($payload['service_type'] ?? 'DINE_IN')));
        if (!in_array($serviceType, ['DINE_IN', 'TAKE_AWAY', 'DELIVERY', 'PICKUP'], true)) {
            $serviceType = 'DINE_IN';
        }

        $lines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $normalized = $this->normalize_order_draft_lines($lines);
        if (!($normalized['ok'] ?? false)) {
            return $normalized;
        }

        $this->db->trans_begin();
        try {
            $existing = null;
            if ($id > 0) {
                $existing = $this->db->from('pos_order')->where('id', $id)->limit(1)->get()->row_array();
                if (!$existing) {
                    throw new RuntimeException('Draft order POS tidak ditemukan.');
                }
                if (!in_array((string)($existing['status'] ?? ''), ['DRAFT', 'PENDING'], true)) {
                    throw new RuntimeException('Hanya draft order berstatus DRAFT/PENDING yang bisa diedit.');
                }
            }

            $headerTotals = $this->calculate_order_totals((array)($normalized['rows'] ?? []));
            $headerPayload = [
                'order_channel' => 'CASHIER',
                'order_scope' => 'REGULAR',
                'service_type' => $serviceType,
                'outlet_id' => $outletId,
                'terminal_id' => $terminalId,
                'cashier_employee_id' => $actorEmployeeId,
                'guest_count' => max(1, (int)($payload['guest_count'] ?? 1)),
                'subtotal_amount' => $headerTotals['subtotal_amount'],
                'grand_total' => $headerTotals['grand_total'],
                'notes' => $this->nullable_text($payload['notes'] ?? ''),
                'status' => 'DRAFT',
                'kitchen_status' => 'PENDING',
                'stock_commit_status' => 'PENDING',
            ];

            if ($id > 0) {
                $this->db->where('id', $id)->update('pos_order', $headerPayload);
                $this->db->where('order_id', $id)->delete('pos_order_line_extra');
                $this->db->where('order_id', $id)->delete('pos_order_line');
            } else {
                $headerPayload['order_no'] = $this->generate_pos_order_no();
                $headerPayload['ordered_at'] = date('Y-m-d H:i:s');
                $this->db->insert('pos_order', $headerPayload);
                $id = (int)$this->db->insert_id();
            }

            $lineNo = 1;
            foreach ($normalized['rows'] as $row) {
                $linePayload = [
                    'order_id' => $id,
                    'line_no' => $lineNo++,
                    'product_id' => (int)$row['product_id'],
                    'bundle_id' => null,
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
                $this->db->insert('pos_order_line', $linePayload);
            }

            if ($id > 0) {
                $this->insert_order_state_log($id, (string)($existing['status'] ?? 'DRAFT'), 'DRAFT', 'ORDER_DRAFT_UPDATE', $actorEmployeeId, 'Draft order diperbarui.');
            } else {
                $this->insert_order_state_log($id, null, 'DRAFT', 'ORDER_DRAFT_CREATE', $actorEmployeeId, 'Draft order dibuat.');
            }

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal menyimpan draft order POS.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $id, 'order_no' => (string)($headerPayload['order_no'] ?? ($existing['order_no'] ?? ''))];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolve_order_stock_commit_payload(int $orderId, int $actorEmployeeId): array
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
        if (empty($lines)) {
            return ['ok' => false, 'message' => 'Order draft belum memiliki produk.'];
        }
        if (!in_array((string)($header['status'] ?? ''), ['DRAFT', 'PENDING'], true)) {
            return ['ok' => false, 'message' => 'Hanya draft order berstatus DRAFT/PENDING yang bisa dikonfirmasi.'];
        }
        if ((string)($header['stock_commit_status'] ?? '') === 'POSTED') {
            return ['ok' => false, 'message' => 'Order ini sudah pernah melakukan stock commit.'];
        }

        $resolvedLines = [];
        $resolvedLineNo = 1;
        foreach ($lines as $line) {
            $recipeRows = $this->load_order_product_recipe_rows((int)($line['product_id'] ?? 0));
            if (empty($recipeRows)) {
                return ['ok' => false, 'message' => 'Produk ' . (string)($line['product_name'] ?? '-') . ' belum memiliki recipe product, jadi belum bisa stock commit.'];
            }
            foreach ($recipeRows as $recipeRow) {
                $resolved = $this->resolve_order_recipe_consumption_line($header, $line, $recipeRow);
                if (!($resolved['ok'] ?? false)) {
                    return $resolved;
                }
                $resolvedLines[] = [
                    'line_no' => $resolvedLineNo++,
                ] + (array)($resolved['line'] ?? []);
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
                'notes' => 'Snapshot commit dari confirm draft order POS ' . (string)($header['order_no'] ?? ''),
            ],
            'lines' => $resolvedLines,
            'resolved_line_count' => count($resolvedLines),
        ];
    }

    public function finalize_order_confirmation(int $orderId, int $snapshotId, int $actorEmployeeId): array
    {
        $row = $this->db->from('pos_order')->where('id', $orderId)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Order POS tidak ditemukan saat finalisasi confirm.'];
        }

        $this->db->trans_begin();
        try {
            $this->db->where('id', $orderId)->update('pos_order', [
                'status' => 'CONFIRMED',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'stock_commit_status' => 'POSTED',
                'stock_committed_at' => date('Y-m-d H:i:s'),
            ]);
            $this->insert_order_state_log($orderId, (string)($row['status'] ?? 'DRAFT'), 'CONFIRMED', 'ORDER_CONFIRM', $actorEmployeeId, 'Draft order dikonfirmasi dan snapshot stock commit #' . $snapshotId . ' dibuat.');
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Gagal memfinalkan status confirm order POS.');
            }
            $this->db->trans_commit();
            return ['ok' => true, 'id' => $orderId];
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $e->getMessage()];
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
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = round((float)($line['qty'] ?? 0), 4);
            if ($productId > 0 && $qty > 0) {
                $productIds[$productId] = $productId;
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
            $unitPrice = round((float)($product['selling_price'] ?? 0), 2);
            $hppStandard = round((float)($product['hpp_standard'] ?? 0), 6);
            $hppLive = round((float)($product['hpp_live_cache'] ?? 0), 6);
            if ($hppLive <= 0) {
                $hppLive = $hppStandard;
            }

            $normalized[] = [
                'product_id' => $productId,
                'product_code' => (string)($product['product_code'] ?? ''),
                'product_name' => (string)($product['product_name'] ?? ''),
                'product_division_id_snapshot' => !empty($product['product_division_id']) ? (int)$product['product_division_id'] : null,
                'operational_division_id' => $this->resolve_product_default_operational_division($product),
                'uom_id' => !empty($product['uom_id']) ? (int)$product['uom_id'] : null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'net_amount' => round($qty * $unitPrice, 2),
                'hpp_standard_snapshot' => $hppStandard,
                'hpp_live_snapshot' => $hppLive,
                'cogs_amount' => round($qty * $hppLive, 2),
                'availability_mode_snapshot' => 'AUTO',
                'notes' => $this->nullable_text($line['notes'] ?? ''),
            ];
        }

        if (empty($normalized)) {
            return ['ok' => false, 'message' => 'Draft order belum punya line produk yang valid.'];
        }

        return ['ok' => true, 'rows' => $normalized];
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

    private function calculate_order_totals(array $rows): array
    {
        $subtotal = 0.0;
        foreach ($rows as $row) {
            $subtotal += (float)($row['net_amount'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        return [
            'subtotal_amount' => $subtotal,
            'grand_total' => $subtotal,
        ];
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
        return $this->coredb->select('id, master_code, master_name')
            ->from('pos_printer_template_master')
            ->where('is_active', 1)
            ->order_by('is_default', 'DESC')
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

    private function bank_account_exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        return (int)$this->coredb->from('m_bank_account')->where('id', $id)->count_all_results() > 0;
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

    private function generate_customer_code_core(?string $createdAt = null): string
    {
        $createdAt = $createdAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($createdAt));
        $prefix = 'CUS-' . $dateKey;
        $row = $this->coredb->query(
            "SELECT customer_code FROM crm_customer WHERE customer_code LIKE ? ORDER BY customer_code DESC LIMIT 1",
            [$prefix . '-%']
        )->row_array();

        $next = 1;
        if ($row && !empty($row['customer_code'])) {
            $parts = explode('-', (string)$row['customer_code']);
            $next = ((int)end($parts)) + 1;
        }
        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generate_member_no_core(?string $joinedAt = null): string
    {
        $joinedAt = $joinedAt ?: date('Y-m-d H:i:s');
        $dateKey = date('Ymd', strtotime($joinedAt));
        $prefix = 'MBR-' . $dateKey;
        $row = $this->coredb->query(
            "SELECT member_no FROM crm_member_account WHERE member_no LIKE ? ORDER BY member_no DESC LIMIT 1",
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
