<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_report_model extends CI_Model
{
    private function order_customer_display_expr(string $orderAlias = 'o', string $memberAlias = 'm'): string
    {
        $memberExpr = $memberAlias !== '' ? ($memberAlias . '.member_name') : "''";
        if ($this->db->field_exists('customer_name', 'pos_order')) {
            return "COALESCE(NULLIF(TRIM({$orderAlias}.customer_name), ''), {$memberExpr})";
        }

        return $memberExpr;
    }

    public function outlet_options(): array
    {
        return $this->db->from('pos_outlet')
            ->where('is_active', 1)
            ->order_by('outlet_name', 'ASC')
            ->get()
            ->result_array();
    }

    public function sales_summary_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_sales_summary($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->sales_summary_rows($filters, $limit, $offset),
            'overview' => $this->sales_summary_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function sales_detail_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_sales_detail($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->sales_detail_rows($filters, $limit, $offset),
            'overview' => $this->sales_detail_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function payment_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_payment_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->payment_rows($filters, $limit, $offset),
            'overview' => $this->payment_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function refund_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_refund_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->refund_rows($filters, $limit, $offset),
            'overview' => $this->refund_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function void_report(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 25)));
        $total = $this->count_void_rows($filters);
        [$page, $offset, $totalPages] = $this->paginate($total, $page, $limit);

        return [
            'rows' => $this->void_rows($filters, $limit, $offset),
            'overview' => $this->void_overview($filters),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'offset' => $offset,
            ],
        ];
    }

    public function find_payment(int $id): ?array
    {
        $row = $this->db->select('p.*, o.order_no, o.status AS order_status, o.service_type, o.order_scope, o.ordered_at, o.paid_at AS order_paid_at, po.outlet_name, m.member_no, m.member_name, e.employee_name AS cashier_name')
            ->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->where('p.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function payment_lines(int $paymentId): array
    {
        $select = 'pl.*, pm.method_name, pm.method_type';
        $db = $this->db->from('pos_payment_line pl')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');

        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $select .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $db->join('fin_company_account acc', 'acc.id = pl.company_account_id', 'left');
        } elseif ($this->db->field_exists('company_account_id', 'pos_payment_method')) {
            $select .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $db->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        }

        return $db->select($select)
            ->where('pl.payment_id', $paymentId)
            ->order_by('pl.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_refund(int $id): ?array
    {
        $row = $this->db->select('r.*, o.order_no, o.status AS order_status, o.service_type, o.order_scope, o.ordered_at, po.outlet_name, m.member_no, m.member_name, pm.method_name, acc.account_code AS company_account_code, acc.account_name AS company_account_name, e.employee_name AS refunded_by_name')
            ->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left')
            ->where('r.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function refund_lines(int $refundId): array
    {
        return $this->db->select('rl.*, ol.line_no AS order_line_no, p.product_name, ex.extra_name, COALESCE(p.product_name, ex.extra_name) AS item_name')
            ->from('pos_refund_line rl')
            ->join('pos_order_line ol', 'ol.id = rl.order_line_id', 'left')
            ->join('mst_product p', 'p.id = rl.product_id', 'left')
            ->join('mst_extra ex', 'ex.id = rl.extra_id', 'left')
            ->where('rl.refund_id', $refundId)
            ->order_by('rl.line_no', 'ASC')
            ->get()
            ->result_array();
    }

    public function find_void(int $id): ?array
    {
        $row = $this->db->select('v.*, po.outlet_name, m.member_no, m.member_name, e.employee_name AS actor_name')
            ->from('pos_void v')
            ->join('pos_outlet po', 'po.id = v.outlet_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left')
            ->where('v.id', $id)
            ->limit(1)
            ->get()
            ->row_array();

        return $row ?: null;
    }

    public function void_lines(int $voidId): array
    {
        return $this->db->from('pos_void_line')
            ->where('void_id', $voidId)
            ->order_by('line_no_snapshot', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    public function void_extras(int $voidId): array
    {
        return $this->db->from('pos_void_line_extra')
            ->where('void_id', $voidId)
            ->order_by('void_line_id', 'ASC')
            ->order_by('id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_payment_rows(int $orderId): array
    {
        $payments = $this->db->select('p.*, e.employee_name AS cashier_name')
            ->from('pos_payment p')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->where('p.order_id', $orderId)
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'ASC', false)
            ->order_by('p.id', 'ASC')
            ->get()
            ->result_array();

        if (empty($payments)) {
            return [];
        }

        $paymentIds = array_map('intval', array_column($payments, 'id'));
        $lineSelect = 'pl.*, pm.method_name, pm.method_type';
        $lineDb = $this->db->from('pos_payment_line pl')
            ->join('pos_payment_method pm', 'pm.id = pl.payment_method_id', 'left');
        if ($this->db->field_exists('company_account_id', 'pos_payment_line')) {
            $lineSelect .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $lineDb->join('fin_company_account acc', 'acc.id = pl.company_account_id', 'left');
        } elseif ($this->db->field_exists('company_account_id', 'pos_payment_method')) {
            $lineSelect .= ', acc.account_code AS company_account_code, acc.account_name AS company_account_name, acc.bank_name AS company_bank_name';
            $lineDb->join('fin_company_account acc', 'acc.id = pm.company_account_id', 'left');
        }

        $lines = $lineDb->select($lineSelect)
            ->where_in('pl.payment_id', $paymentIds)
            ->order_by('pl.payment_id', 'ASC')
            ->order_by('pl.line_no', 'ASC')
            ->get()
            ->result_array();

        $grouped = [];
        foreach ($lines as $line) {
            $grouped[(int)($line['payment_id'] ?? 0)][] = $line;
        }

        foreach ($payments as &$payment) {
            $payment['lines'] = $grouped[(int)($payment['id'] ?? 0)] ?? [];
        }
        unset($payment);

        return $payments;
    }

    public function order_refund_rows(int $orderId): array
    {
        return $this->db->select('r.*, e.employee_name AS refunded_by_name, pm.method_name, acc.account_name AS company_account_name')
            ->from('pos_refund r')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('fin_company_account acc', 'acc.id = r.company_account_id', 'left')
            ->where('r.order_id', $orderId)
            ->order_by('r.refunded_at', 'ASC')
            ->order_by('r.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_void_rows(int $orderId): array
    {
        return $this->db->select('v.*, e.employee_name AS actor_name')
            ->from('pos_void v')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left')
            ->where('v.order_id', $orderId)
            ->order_by('v.created_at', 'ASC')
            ->order_by('v.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_point_ledger_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_point_ledger')) {
            return [];
        }

        $db = $this->db->from('pos_point_ledger l');
        $select = 'l.*';
        if ($this->db->table_exists('pos_point_rule')) {
            $db->join('pos_point_rule r', 'r.id = l.rule_id', 'left');
            $select .= ', r.rule_name';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = l.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('l.order_id', $orderId)
            ->order_by('l.created_at', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_stamp_ledger_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_stamp_ledger')) {
            return [];
        }

        $db = $this->db->from('pos_stamp_ledger l');
        $select = 'l.*';
        if ($this->db->table_exists('pos_stamp_campaign')) {
            $db->join('pos_stamp_campaign c', 'c.id = l.campaign_id', 'left');
            $select .= ', c.campaign_name';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = l.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('l.order_id', $orderId)
            ->order_by('l.created_at', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_voucher_redemption_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_voucher_redemption')) {
            return [];
        }

        $db = $this->db->from('pos_voucher_redemption vr');
        $select = 'vr.*';
        if ($this->db->table_exists('pos_voucher_issue')) {
            $db->join('pos_voucher_issue vi', 'vi.id = vr.voucher_issue_id', 'left');
            $select .= ', vi.voucher_issue_no, vi.voucher_code';
            if ($this->db->table_exists('pos_voucher_campaign')) {
                $db->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left');
                $select .= ', vc.campaign_name, vc.campaign_code';
            }
        }
        if ($this->db->table_exists('pos_payment')) {
            $db->join('pos_payment p', 'p.id = vr.payment_id', 'left');
            $select .= ', p.payment_no';
        }

        return $db->select($select, false)
            ->where('vr.order_id', $orderId)
            ->order_by('vr.redeemed_at', 'ASC')
            ->order_by('vr.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function order_voucher_issue_rows(int $orderId): array
    {
        if (!$this->db->table_exists('pos_voucher_issue') || !$this->db->field_exists('source_order_id', 'pos_voucher_issue')) {
            return [];
        }

        $db = $this->db->from('pos_voucher_issue vi');
        $select = 'vi.*';
        if ($this->db->table_exists('pos_voucher_campaign')) {
            $db->join('pos_voucher_campaign vc', 'vc.id = vi.campaign_id', 'left');
            $select .= ', vc.campaign_name, vc.campaign_code, vc.voucher_type';
        }
        if ($this->db->table_exists('crm_member')) {
            $db->join('crm_member m', 'm.id = vi.member_id', 'left');
            $select .= ', m.member_no, m.member_name';
        }

        return $db->select($select, false)
            ->where('vi.source_order_id', $orderId)
            ->order_by('vi.issued_at', 'ASC')
            ->order_by('vi.id', 'ASC')
            ->get()
            ->result_array();
    }

    private function sales_summary_rows(array $filters, int $limit, int $offset): array
    {
        $this->sales_summary_base_query($filters);
        $customerExpr = $this->order_customer_display_expr('o', 'm');

        return $this->db->select("\n                o.id,\n                o.order_no,\n                o.status,\n                o.order_scope,\n                o.service_type,\n                o.table_no,\n                o.ordered_at,\n                o.confirmed_at,\n                o.paid_at,\n                po.outlet_name,\n                m.member_no,\n                m.member_name,\n                {$customerExpr} AS customer_display_name,\n                e.employee_name AS cashier_name,\n                COALESCE(ls.line_count, 0) AS line_count,\n                COALESCE(ls.qty_total, 0) AS qty_total,\n                COALESCE(o.subtotal_amount, 0) AS subtotal_amount,\n                COALESCE(o.discount_amount, 0) AS discount_amount,\n                COALESCE(o.promo_amount, 0) AS promo_amount,\n                COALESCE(o.voucher_amount, 0) AS voucher_amount,\n                COALESCE(o.point_redeem_amount, 0) AS point_redeem_amount,\n                COALESCE(o.compliment_amount, 0) AS compliment_amount,\n                COALESCE(o.tax_amount, 0) AS tax_amount,\n                COALESCE(o.service_amount, 0) AS service_amount,\n                COALESCE(o.grand_total, 0) AS grand_total,\n                COALESCE(o.paid_total, 0) AS paid_total,\n                COALESCE(o.change_total, 0) AS change_total,\n                COALESCE(rf.refund_amount, 0) AS refund_amount,\n                COALESCE(vd.void_amount, 0) AS void_amount,\n                COALESCE(o.paid_total, 0) AS net_sales,\n                COALESCE(pm.method_names, '') AS payment_method_names\n            ", false)
            ->order_by('COALESCE(o.paid_at, o.confirmed_at, o.ordered_at)', 'DESC', false)
            ->order_by('o.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_sales_summary(array $filters): int
    {
        $this->sales_summary_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function sales_summary_overview(array $filters): array
    {
        $this->sales_summary_base_query($filters);
        return $this->db->select("\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(ls.line_count), 0) AS line_count,\n                COALESCE(SUM(ls.qty_total), 0) AS qty_total,\n                COALESCE(SUM(o.subtotal_amount), 0) AS gross_sales,\n                COALESCE(SUM(o.grand_total), 0) AS grand_total,\n                COALESCE(SUM(o.discount_amount), 0) AS discount_amount,\n                COALESCE(SUM(o.promo_amount), 0) AS promo_amount,\n                COALESCE(SUM(rf.refund_amount), 0) AS refund_amount,\n                COALESCE(SUM(vd.void_amount), 0) AS void_amount,\n                COALESCE(SUM(o.paid_total), 0) AS paid_total,\n                COALESCE(SUM(GREATEST(COALESCE(o.grand_total, 0) - COALESCE(o.paid_total, 0), 0)), 0) AS balance_due,\n                COALESCE(SUM(o.paid_total), 0) AS net_sales\n            ", false)
            ->get()
            ->row_array() ?: [];
    }

    private function sales_summary_base_query(array $filters): void
    {
        $this->db->from('pos_order o')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('org_employee e', 'e.id = o.cashier_employee_id', 'left')
            ->join($this->order_line_summary_subquery() . ' ls', 'ls.order_id = o.id', 'left', false)
            ->join($this->refund_summary_subquery() . ' rf', 'rf.order_id = o.id', 'left', false)
            ->join($this->void_summary_subquery() . ' vd', 'vd.order_id = o.id', 'left', false)
            ->join($this->payment_method_summary_subquery() . ' pm', 'pm.order_id = o.id', 'left', false)
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID']);

        $this->apply_order_filters($filters);
        $paymentMethodId = (int)($filters['payment_method_id'] ?? 0);
        if ($paymentMethodId > 0) {
            $this->db->where('FIND_IN_SET(' . $paymentMethodId . ', COALESCE(pm.method_ids, \'\')) > 0', null, false);
        }
    }

    private function sales_detail_rows(array $filters, int $limit, int $offset): array
    {
        $this->sales_detail_base_query($filters);

        $rows = $this->db->select("\n                p.id AS product_id,\n                p.product_code,\n                p.product_name,\n                pd.name AS division_name,\n                pc.name AS category_name,\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(l.qty), 0) AS qty_total,\n                COALESCE(SUM(l.net_amount), 0) AS product_amount,\n                COALESCE(SUM(xs.extra_amount), 0) AS extra_amount,\n                COALESCE(SUM(l.net_amount), 0) + COALESCE(SUM(xs.extra_amount), 0) AS gross_sales,\n                COALESCE(SUM(l.cogs_amount), 0) AS cogs_amount\n            ", false)
            ->group_by(['p.id', 'p.product_code', 'p.product_name', 'pd.name', 'pc.name'])
            ->order_by('gross_sales', 'DESC', false)
            ->order_by('p.product_name', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        if (empty($rows)) {
            return [];
        }

        $refundMap = $this->sales_detail_refund_rows($filters, array_map('intval', array_column($rows, 'product_id')));
        foreach ($rows as &$row) {
            $productId = (int)($row['product_id'] ?? 0);
            $refund = $refundMap[$productId] ?? ['refund_qty' => 0, 'refund_amount' => 0];
            $grossSales = (float)($row['gross_sales'] ?? 0);
            $refundAmount = (float)($refund['refund_amount'] ?? 0);
            $cogsAmount = (float)($row['cogs_amount'] ?? 0);
            $row['refund_qty'] = (float)($refund['refund_qty'] ?? 0);
            $row['refund_amount'] = $refundAmount;
            $row['net_sales'] = round($grossSales - $refundAmount, 2);
            $row['gross_profit'] = round((float)$row['net_sales'] - $cogsAmount, 2);
        }
        unset($row);

        usort($rows, static function (array $left, array $right): int {
            $netCompare = (float)($right['net_sales'] ?? 0) <=> (float)($left['net_sales'] ?? 0);
            if ($netCompare !== 0) {
                return $netCompare;
            }

            return strcasecmp((string)($left['product_name'] ?? ''), (string)($right['product_name'] ?? ''));
        });

        return $rows;
    }

    private function count_sales_detail(array $filters): int
    {
        $this->sales_detail_base_query($filters);
        $row = $this->db->select('COUNT(DISTINCT p.id) AS total', false)
            ->get()
            ->row_array();

        return (int)($row['total'] ?? 0);
    }

    private function sales_detail_overview(array $filters): array
    {
        $this->sales_detail_base_query($filters);
        $overview = $this->db->select("\n                COUNT(DISTINCT p.id) AS product_count,\n                COUNT(DISTINCT o.id) AS order_count,\n                COALESCE(SUM(l.qty), 0) AS qty_total,\n                COALESCE(SUM(l.net_amount), 0) AS product_amount,\n                COALESCE(SUM(xs.extra_amount), 0) AS extra_amount,\n                COALESCE(SUM(l.net_amount), 0) + COALESCE(SUM(xs.extra_amount), 0) AS gross_sales,\n                COALESCE(SUM(l.cogs_amount), 0) AS cogs_amount\n            ", false)
            ->get()
            ->row_array() ?: [];

        $refundOverview = $this->sales_detail_refund_overview($filters);
        $overview['refund_qty'] = (float)($refundOverview['refund_qty'] ?? 0);
        $overview['refund_amount'] = (float)($refundOverview['refund_amount'] ?? 0);
        $overview['net_sales'] = round((float)($overview['gross_sales'] ?? 0) - (float)($overview['refund_amount'] ?? 0), 2);
        $overview['gross_profit'] = round((float)($overview['net_sales'] ?? 0) - (float)($overview['cogs_amount'] ?? 0), 2);

        return $overview;
    }

    private function sales_detail_base_query(array $filters): void
    {
        $this->db->from('pos_order_line l')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->join($this->order_line_extra_summary_subquery() . ' xs', 'xs.order_line_id = l.id', 'left', false)
            ->where_not_in('o.status', ['DRAFT', 'PENDING', 'VOID'])
            ->where('l.line_type', 'PRODUCT')
            ->where('l.line_status <>', 'VOID');

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'pd.name', 'pc.name', 'l.notes']);
    }

    private function sales_detail_refund_rows(array $filters, array $productIds): array
    {
        $productIds = array_values(array_filter(array_map('intval', $productIds), static function (int $id): bool {
            return $id > 0;
        }));
        if (empty($productIds)) {
            return [];
        }

        $this->db->from('pos_refund_line rl')
            ->join('pos_order_line l', 'l.id = rl.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('rl.line_type', 'PRODUCT')
            ->where_in('rl.product_id', $productIds);

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'pd.name', 'pc.name']);

        $rows = $this->db->select('rl.product_id, COALESCE(SUM(rl.qty_refunded), 0) AS refund_qty, COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount', false)
            ->group_by('rl.product_id')
            ->get()
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['product_id'] ?? 0)] = $row;
        }

        return $map;
    }

    private function sales_detail_refund_overview(array $filters): array
    {
        $this->db->from('pos_refund_line rl')
            ->join('pos_order_line l', 'l.id = rl.order_line_id', 'inner')
            ->join('pos_order o', 'o.id = l.order_id', 'inner')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = o.member_id', 'left')
            ->join('mst_product p', 'p.id = l.product_id', 'left')
            ->join('mst_product_division pd', 'pd.id = p.product_division_id', 'left')
            ->join('mst_product_category pc', 'pc.id = p.product_category_id', 'left')
            ->where('rl.line_type', 'PRODUCT');

        $this->apply_order_filters($filters, 'o', ['p.product_name', 'p.product_code', 'pd.name', 'pc.name']);

        return $this->db->select('COALESCE(SUM(rl.qty_refunded), 0) AS refund_qty, COALESCE(SUM(rl.amount_refunded), 0) AS refund_amount', false)
            ->get()
            ->row_array() ?: [];
    }

    private function payment_rows(array $filters, int $limit, int $offset): array
    {
        $this->payment_base_query($filters);
        return $this->db->select("p.*, o.order_no, o.status AS order_status, o.service_type, po.outlet_name, m.member_no, m.member_name, e.employee_name AS cashier_name, COALESCE(pl.amount_total, 0) AS amount_total, COALESCE(pl.method_names, '') AS method_names", false)
            ->order_by('COALESCE(p.paid_at, p.created_at)', 'DESC', false)
            ->order_by('p.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_payment_rows(array $filters): int
    {
        $this->payment_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function payment_overview(array $filters): array
    {
        $this->payment_base_query($filters);
        return $this->db->select('COUNT(DISTINCT p.id) AS payment_count, COALESCE(SUM(p.net_amount), 0) AS net_amount, COALESCE(SUM(p.discount_amount), 0) AS discount_amount, COALESCE(SUM(p.promo_amount), 0) AS promo_amount, COALESCE(SUM(p.deposit_applied_amount), 0) AS deposit_applied_amount, COALESCE(SUM(p.change_amount), 0) AS change_amount', false)
            ->get()
            ->row_array() ?: [];
    }

    private function payment_base_query(array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        $paymentType = strtoupper(trim((string)($filters['payment_type'] ?? 'FINAL')));

        $this->db->from('pos_payment p')
            ->join('pos_order o', 'o.id = p.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = p.member_id', 'left')
            ->join('org_employee e', 'e.id = p.cashier_employee_id', 'left')
            ->join($this->payment_line_summary_subquery() . ' pl', 'pl.payment_id = p.id', 'left', false);

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'p.payment_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(COALESCE(p.paid_at, p.created_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('p.payment_status', $status);
        }
        if ($paymentType !== '' && $paymentType !== 'ALL') {
            $this->db->where('p.payment_type', $paymentType);
        }
    }

    private function refund_rows(array $filters, int $limit, int $offset): array
    {
        $this->refund_base_query($filters);
        return $this->db->select('r.*, o.order_no, o.status AS order_status, o.service_type, po.outlet_name, m.member_no, m.member_name, pm.method_name, e.employee_name AS refunded_by_name', false)
            ->order_by('r.refunded_at', 'DESC')
            ->order_by('r.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_refund_rows(array $filters): int
    {
        $this->refund_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function refund_overview(array $filters): array
    {
        $this->refund_base_query($filters);
        return $this->db->select("COUNT(DISTINCT r.id) AS refund_count, COALESCE(SUM(r.refund_amount), 0) AS refund_amount, COALESCE(SUM(CASE WHEN r.return_to_stock = 1 THEN r.refund_amount ELSE 0 END), 0) AS return_to_stock_amount, COALESCE(SUM(CASE WHEN r.processed_state = 'PROCESSED' THEN r.refund_amount ELSE 0 END), 0) AS processed_amount", false)
            ->get()
            ->row_array() ?: [];
    }

    private function refund_base_query(array $filters): void
    {
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));

        $this->db->from('pos_refund r')
            ->join('pos_order o', 'o.id = r.order_id', 'left')
            ->join('pos_outlet po', 'po.id = o.outlet_id', 'left')
            ->join('crm_member m', 'm.id = r.member_id', 'left')
            ->join('pos_payment_method pm', 'pm.id = r.payment_method_id', 'left')
            ->join('org_employee e', 'e.id = r.refunded_by', 'left');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'r.refund_no',
            'o.order_no',
            'm.member_no',
            'm.member_name',
            'r.reference_no',
            'r.reason',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0));
        $this->apply_date_range_filter('DATE(r.refunded_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($status !== '' && $status !== 'ALL') {
            $this->db->where('r.refund_status', $status);
        }
    }

    private function void_rows(array $filters, int $limit, int $offset): array
    {
        $this->void_base_query($filters);
        return $this->db->select('v.*, po.outlet_name, m.member_no, m.member_name, e.employee_name AS actor_name', false)
            ->order_by('v.created_at', 'DESC')
            ->order_by('v.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
    }

    private function count_void_rows(array $filters): int
    {
        $this->void_base_query($filters);
        return (int)$this->db->count_all_results();
    }

    private function void_overview(array $filters): array
    {
        $this->void_base_query($filters);
        return $this->db->select("COUNT(DISTINCT v.id) AS void_count, COALESCE(SUM(v.amount_void), 0) AS amount_void, COALESCE(SUM(v.total_qty_void), 0) AS total_qty_void, COALESCE(SUM(CASE WHEN v.void_scope = 'FULL' THEN 1 ELSE 0 END), 0) AS full_void_count, COALESCE(SUM(CASE WHEN v.processed_state = 'PROCESSED' THEN v.amount_void ELSE 0 END), 0) AS processed_amount", false)
            ->get()
            ->row_array() ?: [];
    }

    private function void_base_query(array $filters): void
    {
        $scope = strtoupper(trim((string)($filters['void_scope'] ?? 'ALL')));

        $this->db->from('pos_void v')
            ->join('pos_outlet po', 'po.id = v.outlet_id', 'left')
            ->join('crm_member m', 'm.id = v.member_id', 'left')
            ->join('org_employee e', 'e.id = v.actor_employee_id', 'left');

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), [
            'v.void_no',
            'v.order_no_snapshot',
            'v.member_name_snapshot',
            'm.member_no',
            'm.member_name',
            'v.reason',
        ]);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), 'v');
        $this->apply_date_range_filter('DATE(v.created_at)', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        if ($scope !== '' && $scope !== 'ALL') {
            $this->db->where('v.void_scope', $scope);
        }
    }

    private function apply_order_filters(array $filters, string $orderAlias = 'o', array $extraFields = []): void
    {
        $customerExpr = $this->order_customer_display_expr($orderAlias, 'm');
        $fields = [
            $orderAlias . '.order_no',
            $orderAlias . '.table_no',
            $customerExpr,
            'm.member_no',
            'm.member_name',
            'po.outlet_name',
        ];
        foreach ($extraFields as $field) {
            $fields[] = $field;
        }

        $this->apply_keyword_filter((string)($filters['q'] ?? ''), $fields);
        $this->apply_outlet_filter((int)($filters['outlet_id'] ?? 0), $orderAlias);
        $this->apply_date_range_filter('DATE(COALESCE(' . $orderAlias . '.paid_at, ' . $orderAlias . '.confirmed_at, ' . $orderAlias . '.ordered_at))', (string)($filters['date_from'] ?? ''), (string)($filters['date_to'] ?? ''));

        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if ($status !== '' && $status !== 'ALL') {
            $this->db->where($orderAlias . '.status', $status);
        }

        $scope = strtoupper(trim((string)($filters['order_scope'] ?? 'ALL')));
        if ($scope !== '' && $scope !== 'ALL') {
            $this->db->where($orderAlias . '.order_scope', $scope);
        }

        $serviceType = strtoupper(trim((string)($filters['service_type'] ?? 'ALL')));
        if ($serviceType !== '' && $serviceType !== 'ALL') {
            $this->db->where($orderAlias . '.service_type', $serviceType);
        }
    }

    private function apply_keyword_filter(string $q, array $fields): void
    {
        $q = trim($q);
        if ($q === '' || empty($fields)) {
            return;
        }

        $this->db->group_start();
        foreach (array_values($fields) as $index => $field) {
            if ($index === 0) {
                $this->db->like($field, $q);
            } else {
                $this->db->or_like($field, $q);
            }
        }
        $this->db->group_end();
    }

    private function apply_outlet_filter(int $outletId, string $alias = 'o'): void
    {
        if ($outletId > 0) {
            $this->db->where($alias . '.outlet_id', $outletId);
        }
    }

    private function apply_date_range_filter(string $expression, string $dateFrom, string $dateTo): void
    {
        if ($dateFrom !== '') {
            $this->db->where($expression . ' >=', $dateFrom, false);
        }
        if ($dateTo !== '') {
            $this->db->where($expression . ' <=', $dateTo, false);
        }
    }

    private function order_line_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_id,\n                COUNT(*) AS line_count,\n                COALESCE(SUM(qty), 0) AS qty_total\n            FROM pos_order_line\n            WHERE line_status <> 'VOID'\n            GROUP BY order_id\n        )";
    }

    private function order_line_extra_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_line_id,\n                COALESCE(SUM(net_amount), 0) AS extra_amount\n            FROM pos_order_line_extra\n            GROUP BY order_line_id\n        )";
    }

    private function refund_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_id,\n                COUNT(*) AS refund_count,\n                COALESCE(SUM(refund_amount), 0) AS refund_amount\n            FROM pos_refund\n            WHERE refund_status = 'POSTED'\n            GROUP BY order_id\n        )";
    }

    private function void_summary_subquery(): string
    {
        return "(\n            SELECT\n                order_id,\n                COUNT(*) AS void_count,\n                COALESCE(SUM(amount_void), 0) AS void_amount\n            FROM pos_void\n            GROUP BY order_id\n        )";
    }

    private function payment_method_summary_subquery(): string
    {
        return "(\n            SELECT\n                p.order_id,\n                GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name ASC SEPARATOR ', ') AS method_names\n            FROM pos_payment p\n            INNER JOIN pos_payment_line pl ON pl.payment_id = p.id AND pl.status = 'PAID'\n            INNER JOIN pos_payment_method pm ON pm.id = pl.payment_method_id\n            WHERE p.payment_status = 'PAID' AND p.payment_type = 'FINAL'\n            GROUP BY p.order_id\n        )";
    }

    private function payment_line_summary_subquery(): string
    {
        return "(\n            SELECT\n                pl.payment_id,\n                COALESCE(SUM(pl.amount), 0) AS amount_total,\n                GROUP_CONCAT(DISTINCT pm.method_name ORDER BY pm.method_name ASC SEPARATOR ', ') AS method_names\n            FROM pos_payment_line pl\n            INNER JOIN pos_payment_method pm ON pm.id = pl.payment_method_id\n            GROUP BY pl.payment_id\n        )";
    }

    private function paginate(int $total, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        return [$page, $offset, $totalPages];
    }
}
