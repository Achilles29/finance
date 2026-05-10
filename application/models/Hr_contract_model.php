<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Hr_contract_model extends CI_Model
{
    public function get_employee_options(): array
    {
        return $this->db->select("e.id AS value, CONCAT(e.employee_code, ' - ', e.employee_name) AS label", false)
            ->from('org_employee e')
            ->where('e.is_active', 1)
            ->order_by('e.employee_name', 'ASC')
            ->get()->result_array();
    }

    public function get_template_options(): array
    {
        return $this->db->select('id AS value, template_name AS label', false)
            ->from('hr_contract_template')
            ->where('is_active', 1)
            ->order_by('template_name', 'ASC')
            ->get()->result_array();
    }

    public function count_templates(array $filters): int
    {
        $this->build_template_list_query($filters, false);
        $row = $this->db->select('COUNT(DISTINCT t.id) AS cnt', false)->get()->row_array();
        return (int)($row['cnt'] ?? 0);
    }

    public function list_templates(array $filters, int $limit, int $offset): array
    {
        $this->build_template_list_query($filters, true);
        return $this->db->order_by('t.template_name', 'ASC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_template_list_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('t.*, u.username AS created_by_username, COUNT(c.id) AS contract_count', false);
        } else {
            $this->db->select('t.id');
        }

        $this->db->from('hr_contract_template t')
            ->join('auth_user u', 'u.id = t.created_by', 'left')
            ->join('hr_contract c', 'c.template_id = t.id', 'left')
            ->group_by('t.id');

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('t.template_code', $q)
                ->or_like('t.template_name', $q)
                ->or_like('t.body_html', $q)
                ->group_end();
        }

        $active = strtoupper(trim((string)($filters['is_active'] ?? '')));
        if ($active === '1' || $active === '0') {
            $this->db->where('t.is_active', (int)$active);
        }

        $contractType = strtoupper(trim((string)($filters['contract_type'] ?? '')));
        if ($contractType !== '') {
            $this->db->where('t.contract_type', $contractType);
        }
    }

    public function get_template(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->select('t.*, u.username AS created_by_username')
            ->from('hr_contract_template t')
            ->join('auth_user u', 'u.id = t.created_by', 'left')
            ->where('t.id', $id)
            ->limit(1)
            ->get()->row_array();

        return $row ?: null;
    }

    public function get_template_by_code(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $row = $this->db->from('hr_contract_template')
            ->where('template_code', $code)
            ->where('is_active', 1)
            ->limit(1)
            ->get()->row_array();

        return $row ?: null;
    }

    public function save_template(array $payload, int $id = 0): array
    {
        $data = [
            'template_code' => strtoupper(trim((string)($payload['template_code'] ?? ''))),
            'template_name' => trim((string)($payload['template_name'] ?? '')),
            'contract_type' => strtoupper(trim((string)($payload['contract_type'] ?? 'K1'))),
            'duration_months' => max(1, (int)($payload['duration_months'] ?? 3)),
            'body_html' => (string)($payload['body_html'] ?? ''),
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
        ];

        if ($data['template_code'] === '' || $data['template_name'] === '') {
            return ['ok' => false, 'message' => 'Kode template dan nama template wajib diisi.'];
        }

        if (!in_array($data['contract_type'], ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $data['contract_type'] = 'CUSTOM';
        }

        $this->db->from('hr_contract_template')->where('template_code', $data['template_code']);
        if ($id > 0) {
            $this->db->where('id !=', $id);
        }
        if ($this->db->count_all_results() > 0) {
            return ['ok' => false, 'message' => 'Kode template sudah digunakan.'];
        }

        if ($id > 0) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update('hr_contract_template', $data);
            if ($this->db->affected_rows() < 0) {
                return ['ok' => false, 'message' => 'Gagal memperbarui template kontrak.'];
            }
            return ['ok' => true, 'id' => $id, 'message' => 'Template kontrak berhasil diperbarui.'];
        }

        $data['created_by'] = !empty($payload['created_by']) ? (int)$payload['created_by'] : null;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('hr_contract_template', $data);
        $newId = (int)$this->db->insert_id();

        if ($newId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat template kontrak.'];
        }

        return ['ok' => true, 'id' => $newId, 'message' => 'Template kontrak berhasil dibuat.'];
    }

    public function delete_template(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID template tidak valid.'];
        }

        $template = $this->get_template($id);
        if (!$template) {
            return ['ok' => false, 'message' => 'Template tidak ditemukan.'];
        }

        $used = (int)$this->db->from('hr_contract')->where('template_id', $id)->count_all_results();
        if ($used > 0) {
            return ['ok' => false, 'message' => 'Template sudah dipakai kontrak dan tidak dapat dihapus. Nonaktifkan saja.'];
        }

        $this->db->where('id', $id)->delete('hr_contract_template');
        if ($this->db->affected_rows() <= 0) {
            return ['ok' => false, 'message' => 'Gagal menghapus template kontrak.'];
        }

        return ['ok' => true, 'message' => 'Template kontrak berhasil dihapus.'];
    }

    public function count_contracts(array $filters): int
    {
        $this->build_contract_list_query($filters, false);
        return (int)$this->db->count_all_results();
    }

    public function list_contracts(array $filters, int $limit, int $offset): array
    {
        $this->build_contract_list_query($filters, true);
        return $this->db->order_by('c.start_date', 'DESC')
            ->order_by('c.id', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();
    }

    private function build_contract_list_query(array $filters, bool $withSelect): void
    {
        if ($withSelect) {
            $this->db->select('c.*, e.employee_code, e.employee_name, d.division_name, p.position_name, t.template_name');
        }

        $this->db->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left');

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $this->db->group_start()
                ->like('c.contract_number', $q)
                ->or_like('e.employee_code', $q)
                ->or_like('e.employee_name', $q)
                ->or_like('c.notes', $q)
                ->group_end();
        }

        if (!empty($filters['status'])) {
            $this->db->where('c.status', (string)$filters['status']);
        }
        if (!empty($filters['contract_type'])) {
            $this->db->where('c.contract_type', (string)$filters['contract_type']);
        }
        if (!empty($filters['employee_id'])) {
            $this->db->where('c.employee_id', (int)$filters['employee_id']);
        }
        if (!empty($filters['template_id'])) {
            $this->db->where('c.template_id', (int)$filters['template_id']);
        }
        if (!empty($filters['date_start'])) {
            $this->db->where('c.start_date >=', (string)$filters['date_start']);
        }
        if (!empty($filters['date_end'])) {
            $this->db->where('c.end_date <=', (string)$filters['date_end']);
        }
    }

    public function get_contract_detail(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->select('c.*, e.employee_code, e.employee_name, e.employee_nip, e.email, e.mobile_phone, d.division_name, p.position_name, t.template_name, t.template_code, uc.username AS created_by_username, ug.username AS generated_by_username')
            ->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->join('auth_user uc', 'uc.id = c.created_by', 'left')
            ->join('auth_user ug', 'ug.id = c.generated_by', 'left')
            ->where('c.id', $id)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return null;
        }

        $row['approvals'] = $this->db->from('hr_contract_approval')
            ->where('contract_id', $id)
            ->order_by('approver_role', 'ASC')
            ->get()->result_array();

        $row['signatures'] = $this->db->select('id, contract_id, signer_role, signer_name, signer_user_id, signature_data, signed_at, ip_address')
            ->from('hr_contract_signature')
            ->where('contract_id', $id)
            ->order_by('signer_role', 'ASC')
            ->get()->result_array();

        $row['snapshot'] = $this->db->from('hr_contract_comp_snapshot')
            ->where('contract_id', $id)
            ->limit(1)
            ->get()->row_array() ?: null;

        if (!empty($row['snapshot']['id'])) {
            $row['snapshot_lines'] = $this->db->from('hr_contract_comp_snapshot_line')
                ->where('snapshot_id', (int)$row['snapshot']['id'])
                ->order_by('sort_order', 'ASC')
                ->get()->result_array();
        } else {
            $row['snapshot_lines'] = [];
        }

        return $row;
    }

    public function get_contract_by_token(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = $this->db->select('id')->from('hr_contract')->where('verification_token', $token)->limit(1)->get()->row_array();
        if (!$row) {
            return null;
        }

        return $this->get_contract_detail((int)$row['id']);
    }

    public function create_draft_auto(array $payload, int $actorId): array
    {
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $startDate = trim((string)($payload['start_date'] ?? ''));
        $endDate = trim((string)($payload['end_date'] ?? ''));
        $templateId = (int)($payload['template_id'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($employeeId <= 0 || $startDate === '') {
            return ['ok' => false, 'message' => 'Pegawai dan tanggal mulai wajib diisi.'];
        }

        $employee = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$employee) {
            return ['ok' => false, 'message' => 'Data pegawai tidak ditemukan.'];
        }

        $template = null;
        if ($templateId > 0) {
            $template = $this->db->from('hr_contract_template')->where('id', $templateId)->limit(1)->get()->row_array();
            if (!$template) {
                return ['ok' => false, 'message' => 'Template kontrak tidak ditemukan.'];
            }
        }

        if ($endDate === '') {
            $duration = max(1, (int)($template['duration_months'] ?? 3));
            $endDate = $this->add_months($startDate, $duration);
        }

        if ($startDate > $endDate) {
            return ['ok' => false, 'message' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.'];
        }

        $contractType = strtoupper(trim((string)($payload['contract_type'] ?? '')));
        if ($contractType === '' && !empty($template['contract_type'])) {
            $contractType = strtoupper((string)$template['contract_type']);
        }
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $contractNumber = $this->generate_contract_number($contractType, $startDate);

        $insert = [
            'contract_number' => $contractNumber,
            'employee_id' => $employeeId,
            'template_id' => $templateId > 0 ? $templateId : null,
            'contract_type' => $contractType,
            'status' => 'DRAFT',
            'position_snapshot' => (string)($employee['position_name'] ?? ''),
            'division_snapshot' => (string)($employee['division_name'] ?? ''),
            'basic_salary' => (float)($employee['basic_salary'] ?? 0),
            'position_allowance' => (float)($employee['position_allowance'] ?? 0),
            'other_allowance' => (float)($employee['objective_allowance'] ?? 0),
            'meal_rate' => (float)($employee['meal_rate'] ?? 0),
            'overtime_rate' => (float)($employee['overtime_rate'] ?? 0),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'notes' => $notes,
            'created_by' => $actorId > 0 ? $actorId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($template['body_html'])) {
            $insert['body_html'] = (string)$template['body_html'];
        }

        $this->db->insert('hr_contract', $insert);
        $newId = (int)$this->db->insert_id();

        if ($newId <= 0) {
            return ['ok' => false, 'message' => 'Gagal membuat draft kontrak.'];
        }

        return ['ok' => true, 'id' => $newId, 'message' => 'Draft kontrak berhasil dibuat.'];
    }

    public function preview_contract_html(int $templateId, int $employeeId, array $payload = []): array
    {
        $template = $this->get_template($templateId);
        if (!$template) {
            return ['ok' => false, 'message' => 'Template kontrak tidak ditemukan.'];
        }
        return $this->preview_contract_html_inline((string)($template['body_html'] ?? ''), $employeeId, $payload, (string)($template['contract_type'] ?? 'CUSTOM'), (int)($template['duration_months'] ?? 3));
    }

    public function preview_contract_html_inline(string $templateHtml, int $employeeId, array $payload = [], string $fallbackContractType = 'CUSTOM', int $fallbackDurationMonths = 3): array
    {
        $templateHtml = trim($templateHtml);
        if ($templateHtml === '') {
            return ['ok' => false, 'message' => 'Body template kosong.'];
        }

        $employee = $this->db->select('e.*, d.division_name, p.position_name')
            ->from('org_employee e')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->where('e.id', $employeeId)
            ->limit(1)
            ->get()->row_array();

        if (!$employee) {
            return ['ok' => false, 'message' => 'Data pegawai tidak ditemukan.'];
        }

        $startDate = trim((string)($payload['start_date'] ?? date('Y-m-d')));
        if ($startDate === '') {
            $startDate = date('Y-m-d');
        }

        $endDate = trim((string)($payload['end_date'] ?? ''));
        if ($endDate === '') {
            $endDate = $this->add_months($startDate, max(1, $fallbackDurationMonths));
        }

        $contractType = strtoupper(trim((string)($payload['contract_type'] ?? $fallbackContractType ?: 'CUSTOM')));
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $vars = [
            'contract_number' => 'DRAFT-' . date('YmdHis'),
            'contract_type' => $contractType,
            'employee_code' => (string)($employee['employee_code'] ?? ''),
            'employee_name' => (string)($employee['employee_name'] ?? ''),
            'division_name' => (string)($payload['division_snapshot'] ?? $employee['division_name'] ?? ''),
            'position_name' => (string)($payload['position_snapshot'] ?? $employee['position_name'] ?? ''),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'basic_salary' => (float)($payload['basic_salary'] ?? $employee['basic_salary'] ?? 0),
            'position_allowance' => (float)($payload['position_allowance'] ?? $employee['position_allowance'] ?? 0),
            'other_allowance' => (float)($payload['other_allowance'] ?? $employee['objective_allowance'] ?? 0),
            'meal_rate' => (float)($payload['meal_rate'] ?? $employee['meal_rate'] ?? 0),
            'overtime_rate' => (float)($payload['overtime_rate'] ?? $employee['overtime_rate'] ?? 0),
        ];
        $vars['fixed_total'] = (float)$vars['basic_salary'] + (float)$vars['position_allowance'] + (float)$vars['other_allowance'];

        return [
            'ok' => true,
            'html' => $this->sanitize_contract_body($this->render_contract_html($templateHtml, $vars)),
            'vars' => $vars,
        ];
    }

    public function generate_contract(int $id, int $actorId): array
    {
        $row = $this->db->select('c.*, e.employee_code, e.employee_name, e.basic_salary AS e_basic_salary, e.position_allowance AS e_position_allowance, e.objective_allowance AS e_other_allowance, e.meal_rate AS e_meal_rate, e.overtime_rate AS e_overtime_rate, d.division_name, p.position_name, t.body_html AS template_body_html')
            ->from('hr_contract c')
            ->join('org_employee e', 'e.id = c.employee_id', 'left')
            ->join('org_division d', 'd.id = e.division_id', 'left')
            ->join('org_position p', 'p.id = e.position_id', 'left')
            ->join('hr_contract_template t', 't.id = c.template_id', 'left')
            ->where('c.id', $id)
            ->limit(1)
            ->get()->row_array();

        if (!$row) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if ((string)$row['status'] !== 'DRAFT') {
            return ['ok' => false, 'message' => 'Generate hanya bisa dilakukan dari status DRAFT.'];
        }

        $contractType = strtoupper((string)$row['contract_type']);
        if (!in_array($contractType, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $contractType = 'CUSTOM';
        }

        $contractNumber = $this->generate_contract_number($contractType, (string)$row['start_date'], $id);
        $basic = (float)($row['e_basic_salary'] ?? 0);
        $positionAllowance = (float)($row['e_position_allowance'] ?? 0);
        $otherAllowance = (float)($row['e_other_allowance'] ?? 0);
        $mealRate = (float)($row['e_meal_rate'] ?? 0);
        $overtimeRate = (float)($row['e_overtime_rate'] ?? 0);
        $fixedTotal = $basic + $positionAllowance + $otherAllowance;

        $templateHtml = trim((string)($row['template_body_html'] ?? ''));
        if ($templateHtml === '') {
            $templateHtml = trim((string)($row['body_html'] ?? ''));
        }

        $renderedBody = $this->render_contract_html($templateHtml, [
            'contract_number' => $contractNumber,
            'contract_type' => $contractType,
            'employee_code' => (string)($row['employee_code'] ?? ''),
            'employee_name' => (string)($row['employee_name'] ?? ''),
            'division_name' => (string)($row['division_name'] ?? ''),
            'position_name' => (string)($row['position_name'] ?? ''),
            'start_date' => (string)($row['start_date'] ?? ''),
            'end_date' => (string)($row['end_date'] ?? ''),
            'basic_salary' => $basic,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'overtime_rate' => $overtimeRate,
            'fixed_total' => $fixedTotal,
        ]);

        $this->db->trans_start();

        $verificationToken = $this->ensure_unique_verification_token();

        $this->db->where('id', $id)->update('hr_contract', [
            'contract_number' => $contractNumber,
            'status' => 'GENERATED',
            'position_snapshot' => (string)($row['position_name'] ?? ''),
            'division_snapshot' => (string)($row['division_name'] ?? ''),
            'basic_salary' => $basic,
            'position_allowance' => $positionAllowance,
            'other_allowance' => $otherAllowance,
            'meal_rate' => $mealRate,
            'overtime_rate' => $overtimeRate,
            'verification_token' => $verificationToken,
            'body_html' => $this->sanitize_contract_body($renderedBody),
            'generated_by' => $actorId > 0 ? $actorId : null,
            'generated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $snapshot = $this->db->from('hr_contract_comp_snapshot')->where('contract_id', $id)->limit(1)->get()->row_array();
        $snapshotPayload = [
            'contract_id' => $id,
            'employee_id' => (int)$row['employee_id'],
            'effective_start' => (string)$row['start_date'],
            'effective_end' => (string)$row['end_date'],
            'basic_salary_amount' => $basic,
            'position_allowance_amount' => $positionAllowance,
            'other_allowance_amount' => $otherAllowance,
            'meal_rate_amount' => $mealRate,
            'overtime_rate_amount' => $overtimeRate,
            'fixed_total_amount' => $fixedTotal,
            'source_notes' => 'Auto snapshot saat generate kontrak',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($snapshot) {
            $snapshotId = (int)$snapshot['id'];
            $this->db->where('id', $snapshotId)->update('hr_contract_comp_snapshot', $snapshotPayload);
        } else {
            $snapshotPayload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('hr_contract_comp_snapshot', $snapshotPayload);
            $snapshotId = (int)$this->db->insert_id();
        }

        if ($snapshotId > 0) {
            $this->db->where('snapshot_id', $snapshotId)->delete('hr_contract_comp_snapshot_line');
            $lines = [
                ['code' => 'GAJI_POKOK', 'name' => 'Gaji Pokok', 'type' => 'EARNING', 'amount' => $basic, 'sort' => 1],
                ['code' => 'TUNJANGAN_JABATAN', 'name' => 'Tunjangan Jabatan', 'type' => 'EARNING', 'amount' => $positionAllowance, 'sort' => 2],
                ['code' => 'TUNJANGAN_LAIN', 'name' => 'Tunjangan Lain', 'type' => 'EARNING', 'amount' => $otherAllowance, 'sort' => 3],
                ['code' => 'UANG_MAKAN', 'name' => 'Uang Makan', 'type' => 'EARNING', 'amount' => $mealRate, 'sort' => 4],
                ['code' => 'LEMBUR_PER_JAM', 'name' => 'Lembur per Jam', 'type' => 'EARNING', 'amount' => $overtimeRate, 'sort' => 5],
            ];

            foreach ($lines as $line) {
                $this->db->insert('hr_contract_comp_snapshot_line', [
                    'snapshot_id' => $snapshotId,
                    'component_code_snapshot' => $line['code'],
                    'component_name_snapshot' => $line['name'],
                    'component_type' => $line['type'],
                    'amount' => (float)$line['amount'],
                    'sort_order' => (int)$line['sort'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal generate kontrak dan snapshot.'];
        }

        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Kontrak berhasil di-generate dengan nomor otomatis dan snapshot gaji.'];
    }

    public function approve_contract(int $id, string $approverRole, string $approvalAction, string $approverName, int $approverUserId, string $note = ''): array
    {
        $approverRole = strtoupper(trim($approverRole));
        $approvalAction = strtoupper(trim($approvalAction));
        if (!in_array($approverRole, ['EMPLOYEE', 'COMPANY'], true)) {
            return ['ok' => false, 'message' => 'Role approval tidak valid.'];
        }
        if (!in_array($approvalAction, ['APPROVED', 'REVOKED'], true)) {
            return ['ok' => false, 'message' => 'Aksi approval tidak valid.'];
        }

        $contract = $this->db->select('id,status')->from('hr_contract')->where('id', $id)->limit(1)->get()->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if (!in_array((string)$contract['status'], ['DRAFT', 'GENERATED', 'SIGNED'], true)) {
            return ['ok' => false, 'message' => 'Status kontrak tidak dapat diproses approval.'];
        }

        $now = date('Y-m-d H:i:s');
        $sql = "INSERT INTO hr_contract_approval (contract_id, approver_role, approval_status, approver_name, approver_user_id, approval_note, approved_at, revoked_at, ip_address, user_agent, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    approval_status = VALUES(approval_status),
                    approver_name = VALUES(approver_name),
                    approver_user_id = VALUES(approver_user_id),
                    approval_note = VALUES(approval_note),
                    approved_at = VALUES(approved_at),
                    revoked_at = VALUES(revoked_at),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    updated_at = VALUES(updated_at)";

        $this->db->query($sql, [
            $id,
            $approverRole,
            $approvalAction,
            $approverName,
            $approverUserId > 0 ? $approverUserId : null,
            $note,
            $now,
            $approvalAction === 'REVOKED' ? $now : null,
            (string)$this->input->ip_address(),
            substr((string)$this->input->user_agent(), 0, 255),
            $now,
            $now,
        ]);

        $this->sync_signed_status($id);
        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Approval kontrak berhasil disimpan.'];
    }

    public function sign_contract(int $id, string $signerRole, string $signerName, int $signerUserId, string $signatureData): array
    {
        $signerRole = strtoupper(trim($signerRole));
        if (!in_array($signerRole, ['EMPLOYEE', 'COMPANY'], true)) {
            return ['ok' => false, 'message' => 'Role tanda tangan tidak valid.'];
        }

        $signatureData = trim($signatureData);
        if ($signatureData === '' || strlen($signatureData) < 30 || strpos($signatureData, 'data:image') !== 0) {
            return ['ok' => false, 'message' => 'Data tanda tangan tidak valid.'];
        }

        $contract = $this->db->select('id,status')->from('hr_contract')->where('id', $id)->limit(1)->get()->row_array();
        if (!$contract) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }
        if (!in_array((string)$contract['status'], ['GENERATED', 'SIGNED'], true)) {
            return ['ok' => false, 'message' => 'Status kontrak tidak bisa ditandatangani.'];
        }

        $this->db->trans_start();
        $this->db->where('contract_id', $id)->where('signer_role', $signerRole)->delete('hr_contract_signature');

        $this->db->insert('hr_contract_signature', [
            'contract_id' => $id,
            'signer_role' => $signerRole,
            'signer_name' => $signerName,
            'signer_user_id' => $signerUserId > 0 ? $signerUserId : null,
            'signature_data' => $signatureData,
            'signed_at' => date('Y-m-d H:i:s'),
            'ip_address' => (string)$this->input->ip_address(),
            'user_agent' => substr((string)$this->input->user_agent(), 0, 255),
        ]);

        $this->sync_signed_status($id);

        $this->db->trans_complete();
        if (!$this->db->trans_status()) {
            return ['ok' => false, 'message' => 'Gagal menyimpan tanda tangan kontrak.'];
        }

        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Tanda tangan kontrak berhasil disimpan.'];
    }

    public function transition_status(int $id, string $toStatus): array
    {
        $toStatus = strtoupper(trim($toStatus));
        $allowed = ['GENERATED', 'SIGNED', 'ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'];
        if (!in_array($toStatus, $allowed, true)) {
            return ['ok' => false, 'message' => 'Status tujuan tidak valid.'];
        }

        $row = $this->db->select('id,status')->from('hr_contract')->where('id', $id)->limit(1)->get()->row_array();
        if (!$row) {
            return ['ok' => false, 'message' => 'Kontrak tidak ditemukan.'];
        }

        $from = strtoupper((string)$row['status']);
        $map = [
            'DRAFT' => ['GENERATED', 'CANCELLED'],
            'GENERATED' => ['SIGNED', 'ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'],
            'SIGNED' => ['ACTIVE', 'EXPIRED', 'TERMINATED', 'CANCELLED'],
            'ACTIVE' => ['EXPIRED', 'TERMINATED'],
            'EXPIRED' => [],
            'TERMINATED' => [],
            'CANCELLED' => [],
        ];

        if (!in_array($toStatus, $map[$from] ?? [], true)) {
            return ['ok' => false, 'message' => 'Transisi status dari ' . $from . ' ke ' . $toStatus . ' tidak diizinkan.'];
        }

        if ($toStatus === 'SIGNED' && !$this->has_complete_signoff($id)) {
            return ['ok' => false, 'message' => 'Belum bisa SIGNED: approval dan tanda tangan EMPLOYEE + COMPANY wajib lengkap.'];
        }

        if ($toStatus === 'ACTIVE' && !$this->has_complete_signoff($id)) {
            return ['ok' => false, 'message' => 'Belum bisa ACTIVE: approval dan tanda tangan belum lengkap.'];
        }

        $this->db->where('id', $id)->update('hr_contract', [
            'status' => $toStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->refresh_document_verification($id);

        return ['ok' => true, 'message' => 'Status kontrak berhasil diubah ke ' . $toStatus . '.'];
    }

    public function refresh_document_verification(int $contractId): ?array
    {
        $contractId = (int)$contractId;
        if ($contractId <= 0) {
            return null;
        }

        $row = $this->db->select('id, verification_token, final_document_hash, document_issued_at')
            ->from('hr_contract')
            ->where('id', $contractId)
            ->limit(1)
            ->get()->row_array();
        if (!$row) {
            return null;
        }

        $token = (string)($row['verification_token'] ?? '');
        if ($token === '') {
            $token = $this->ensure_unique_verification_token($contractId);
        }

        $hash = $this->compute_document_hash($contractId);
        if ($hash === null) {
            return null;
        }

        $issuedAt = !empty($row['document_issued_at']) ? (string)$row['document_issued_at'] : date('Y-m-d H:i:s');

        $this->db->where('id', $contractId)->update('hr_contract', [
            'verification_token' => $token,
            'final_document_hash' => $hash,
            'document_issued_at' => $issuedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'verification_token' => $token,
            'final_document_hash' => $hash,
            'document_issued_at' => $issuedAt,
        ];
    }

    private function compute_document_hash(int $contractId): ?string
    {
        $row = $this->get_contract_detail($contractId);
        if (!$row) {
            return null;
        }

        $payload = [
            'contract_number' => (string)($row['contract_number'] ?? ''),
            'employee_code' => (string)($row['employee_code'] ?? ''),
            'employee_name' => (string)($row['employee_name'] ?? ''),
            'contract_type' => (string)($row['contract_type'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'start_date' => (string)($row['start_date'] ?? ''),
            'end_date' => (string)($row['end_date'] ?? ''),
            'body_html' => $this->sanitize_contract_body((string)($row['body_html'] ?? '')),
            'comp' => [
                'basic_salary' => round((float)($row['basic_salary'] ?? 0), 2),
                'position_allowance' => round((float)($row['position_allowance'] ?? 0), 2),
                'other_allowance' => round((float)($row['other_allowance'] ?? 0), 2),
                'meal_rate' => round((float)($row['meal_rate'] ?? 0), 2),
                'overtime_rate' => round((float)($row['overtime_rate'] ?? 0), 2),
            ],
            'approvals' => array_map(static function ($a) {
                return [
                    'approver_role' => (string)($a['approver_role'] ?? ''),
                    'approval_status' => (string)($a['approval_status'] ?? ''),
                    'approver_name' => (string)($a['approver_name'] ?? ''),
                    'approved_at' => (string)($a['approved_at'] ?? ''),
                ];
            }, (array)($row['approvals'] ?? [])),
            'signatures' => array_map(static function ($s) {
                return [
                    'signer_role' => (string)($s['signer_role'] ?? ''),
                    'signer_name' => (string)($s['signer_name'] ?? ''),
                    'signed_at' => (string)($s['signed_at'] ?? ''),
                    'signature_hash' => hash('sha256', (string)($s['signature_data'] ?? '')),
                ];
            }, (array)($row['signatures'] ?? [])),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return null;
        }

        return hash('sha256', $json);
    }

    private function has_complete_signoff(int $contractId): bool
    {
        $approved = $this->db->select('COUNT(DISTINCT approver_role) AS cnt', false)
            ->from('hr_contract_approval')
            ->where('contract_id', $contractId)
            ->where('approval_status', 'APPROVED')
            ->where_in('approver_role', ['EMPLOYEE', 'COMPANY'])
            ->get()->row_array();

        $signed = $this->db->select('COUNT(DISTINCT signer_role) AS cnt', false)
            ->from('hr_contract_signature')
            ->where('contract_id', $contractId)
            ->where_in('signer_role', ['EMPLOYEE', 'COMPANY'])
            ->get()->row_array();

        return ((int)($approved['cnt'] ?? 0) >= 2) && ((int)($signed['cnt'] ?? 0) >= 2);
    }

    private function sync_signed_status(int $contractId): void
    {
        $contract = $this->db->select('id,status')->from('hr_contract')->where('id', $contractId)->limit(1)->get()->row_array();
        if (!$contract) {
            return;
        }

        $status = strtoupper((string)$contract['status']);
        if (!in_array($status, ['GENERATED', 'SIGNED'], true)) {
            return;
        }

        if ($this->has_complete_signoff($contractId)) {
            $this->db->where('id', $contractId)->update('hr_contract', [
                'status' => 'SIGNED',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function sanitize_contract_body(string $html): string
    {
        return trim($html);
    }

    private function render_contract_html(string $template, array $vars): string
    {
        if ($template === '') {
            return '';
        }

        $replace = [
            '{{CONTRACT_NUMBER}}' => (string)($vars['contract_number'] ?? ''),
            '{{CONTRACT_TYPE}}' => (string)($vars['contract_type'] ?? ''),
            '{{EMPLOYEE_CODE}}' => (string)($vars['employee_code'] ?? ''),
            '{{EMPLOYEE_NAME}}' => (string)($vars['employee_name'] ?? ''),
            '{{DIVISION_NAME}}' => (string)($vars['division_name'] ?? ''),
            '{{POSITION_NAME}}' => (string)($vars['position_name'] ?? ''),
            '{{START_DATE}}' => (string)($vars['start_date'] ?? ''),
            '{{END_DATE}}' => (string)($vars['end_date'] ?? ''),
            '{{BASIC_SALARY}}' => number_format((float)($vars['basic_salary'] ?? 0), 2, '.', ''),
            '{{POSITION_ALLOWANCE}}' => number_format((float)($vars['position_allowance'] ?? 0), 2, '.', ''),
            '{{OTHER_ALLOWANCE}}' => number_format((float)($vars['other_allowance'] ?? 0), 2, '.', ''),
            '{{MEAL_RATE}}' => number_format((float)($vars['meal_rate'] ?? 0), 2, '.', ''),
            '{{OVERTIME_RATE}}' => number_format((float)($vars['overtime_rate'] ?? 0), 2, '.', ''),
            '{{FIXED_TOTAL}}' => number_format((float)($vars['fixed_total'] ?? 0), 2, '.', ''),
        ];

        return strtr($template, $replace);
    }

    private function add_months(string $startDate, int $months): string
    {
        $ts = strtotime($startDate);
        if ($ts === false) {
            $ts = time();
        }

        return date('Y-m-d', strtotime('+' . max(1, $months) . ' months', $ts));
    }

    private function ensure_unique_verification_token(int $excludeId = 0): string
    {
        for ($i = 0; $i < 10; $i++) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $token = sha1(uniqid('ctr', true) . microtime(true));
            }
            $this->db->from('hr_contract')->where('verification_token', $token);
            if ($excludeId > 0) {
                $this->db->where('id !=', $excludeId);
            }
            if ($this->db->count_all_results() === 0) {
                return $token;
            }
        }

        return sha1(uniqid('ctr', true) . microtime(true));
    }

    private function generate_contract_number(string $contractType, string $startDate, int $excludeId = 0): string
    {
        $period = date('Ym', strtotime($startDate ?: date('Y-m-d')));
        $typeCode = strtoupper(trim($contractType));
        if (!in_array($typeCode, ['K1', 'K2', 'K3', 'CUSTOM'], true)) {
            $typeCode = 'CUSTOM';
        }

        $prefix = 'CTR/' . $typeCode . '/' . $period . '/';

        $rows = $this->db->select('contract_number')
            ->from('hr_contract')
            ->like('contract_number', $prefix, 'after')
            ->get()->result_array();

        $maxSeq = 0;
        foreach ($rows as $r) {
            $no = (string)($r['contract_number'] ?? '');
            if (preg_match('/(\d+)$/', $no, $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $next = $maxSeq + 1;
        while ($next <= 999999) {
            $candidate = $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
            $this->db->from('hr_contract')->where('contract_number', $candidate);
            if ($excludeId > 0) {
                $this->db->where('id !=', $excludeId);
            }
            $exists = $this->db->count_all_results() > 0;
            if (!$exists) {
                return $candidate;
            }
            $next++;
        }

        return $prefix . date('His');
    }
}
