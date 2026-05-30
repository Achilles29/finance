<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Loyalty extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Loyalty_model');
    }

    public function members()
    {
        $this->require_permission('loyalty.member.index', 'view');
        $this->render('loyalty/member_index', [
            'page_title' => 'Member & Loyalitas',
            'active_menu' => 'loyalty.member.index',
            'filters' => $this->member_filters(),
            'filter_options' => $this->Loyalty_model->member_filter_options(),
        ]);
    }

    public function members_data()
    {
        $this->require_permission('loyalty.member.index', 'view');
        $this->json_ok($this->Loyalty_model->member_rows($this->member_filters()));
    }

    public function member_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('loyalty.member.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Loyalty_model->save_member($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function member_toggle($id)
    {
        $this->require_permission('loyalty.member.index', 'edit');
        $result = $this->Loyalty_model->toggle_member((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function member_delete($id)
    {
        $this->require_permission('loyalty.member.index', 'delete');
        $result = $this->Loyalty_model->delete_member((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus member.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function point_rules()
    {
        $this->require_permission('loyalty.point_rule.index', 'view');
        $this->render('loyalty/promo_index', $this->promo_page_data('point_rule'));
    }

    public function point_rules_data()
    {
        $this->require_permission('loyalty.point_rule.index', 'view');
        $this->json_ok($this->Loyalty_model->point_rule_rows($this->promo_filters('earn_mode', ['ALL', 'AMOUNT', 'PRODUCT', 'FLAT'])));
    }

    public function point_rule_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('loyalty.point_rule.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Loyalty_model->save_point_rule($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan rule poin.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function point_rule_toggle($id)
    {
        $this->require_permission('loyalty.point_rule.index', 'edit');
        $result = $this->Loyalty_model->toggle_point_rule((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status rule poin.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function point_rule_delete($id)
    {
        $this->require_permission('loyalty.point_rule.index', 'delete');
        $result = $this->Loyalty_model->delete_point_rule((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus rule poin.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function stamp_campaigns()
    {
        $this->require_permission('loyalty.stamp_campaign.index', 'view');
        $this->render('loyalty/promo_index', $this->promo_page_data('stamp_campaign'));
    }

    public function stamp_campaigns_data()
    {
        $this->require_permission('loyalty.stamp_campaign.index', 'view');
        $this->json_ok($this->Loyalty_model->stamp_campaign_rows($this->promo_filters('earn_mode', ['ALL', 'TXN', 'AMOUNT', 'PRODUCT'])));
    }

    public function stamp_campaign_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('loyalty.stamp_campaign.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Loyalty_model->save_stamp_campaign($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan campaign stamp.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function stamp_campaign_toggle($id)
    {
        $this->require_permission('loyalty.stamp_campaign.index', 'edit');
        $result = $this->Loyalty_model->toggle_stamp_campaign((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status campaign stamp.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function stamp_campaign_delete($id)
    {
        $this->require_permission('loyalty.stamp_campaign.index', 'delete');
        $result = $this->Loyalty_model->delete_stamp_campaign((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus campaign stamp.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function voucher_campaigns()
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'view');
        $this->render('loyalty/promo_index', $this->promo_page_data('voucher_campaign'));
    }

    public function voucher_campaigns_data()
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'view');
        $this->json_ok($this->Loyalty_model->voucher_campaign_rows($this->promo_filters('issue_mode', ['ALL', 'PUBLIC', 'AUTO_FROM_TXN', 'MEMBER_TARGETED', 'MANUAL'])));
    }

    public function voucher_campaign_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('loyalty.voucher_campaign.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Loyalty_model->save_voucher_campaign($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan campaign voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function voucher_campaign_toggle($id)
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'edit');
        $result = $this->Loyalty_model->toggle_voucher_campaign((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status campaign voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'is_active' => (int)$result['is_active']]);
    }

    public function voucher_campaign_delete($id)
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'delete');
        $result = $this->Loyalty_model->delete_voucher_campaign((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus campaign voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function vouchers()
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'view');
        $this->render('loyalty/promo_index', $this->promo_page_data('voucher_issue'));
    }

    public function vouchers_data()
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'view');
        $this->json_ok($this->Loyalty_model->voucher_issue_rows($this->promo_filters('voucher_status', ['ALL', 'OPEN', 'REDEEMED', 'EXPIRED', 'VOID'])));
    }

    public function voucher_save()
    {
        $payload = $this->request_payload();
        $id = (int)($payload['id'] ?? 0);
        $this->require_permission('loyalty.voucher_campaign.index', $id > 0 ? 'edit' : 'create');
        $result = $this->Loyalty_model->save_voucher_issue($payload);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menyimpan voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id']]);
    }

    public function voucher_toggle($id)
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'edit');
        $result = $this->Loyalty_model->toggle_voucher_issue((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal mengubah status voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$result['id'], 'voucher_status' => (string)$result['voucher_status']]);
    }

    public function voucher_delete($id)
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'delete');
        $result = $this->Loyalty_model->delete_voucher_issue((int)$id);
        if (!($result['ok'] ?? false)) {
            $this->json_error((string)($result['message'] ?? 'Gagal menghapus voucher.'), 422);
            return;
        }
        $this->json_ok(['id' => (int)$id]);
    }

    public function product_search()
    {
        $this->require_permission('loyalty.point_rule.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $rows = array_map(function (array $row): array {
            if (!empty($row['photo_path'])) {
                $row['photo_path'] = base_url(ltrim((string)$row['photo_path'], '/'));
            } else {
                $row['photo_path'] = '';
            }
            return $row;
        }, $this->Loyalty_model->product_search($q));
        $this->json_ok(['rows' => $rows]);
    }

    public function member_search()
    {
        $this->require_permission('loyalty.voucher_campaign.index', 'view');
        $q = trim((string)$this->input->get('q', true));
        $this->json_ok(['rows' => $this->Loyalty_model->member_search($q)]);
    }

    private function member_filters(): array
    {
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true) ?: 'ACTIVE')),
            'member_status' => strtoupper(trim((string)$this->input->get('member_status', true) ?: 'ALL')),
            'tier' => trim((string)$this->input->get('tier', true)),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 50)),
        ];
    }

    private function promo_filters(string $modeKey, array $allowedModes): array
    {
        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true) ?: 'ACTIVE')),
            $modeKey => strtoupper(trim((string)$this->input->get($modeKey, true) ?: 'ALL')),
            'page' => max(1, (int)$this->input->get('page', true)),
            'limit' => max(1, min(200, (int)$this->input->get('limit', true) ?: 25)),
        ];

        if (!in_array($filters['status'], ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $filters['status'] = 'ACTIVE';
        }
        if (!in_array($filters[$modeKey], $allowedModes, true)) {
            $filters[$modeKey] = 'ALL';
        }

        return $filters;
    }

    private function promo_page_data(string $entity): array
    {
        $voucherCampaignOptions = $this->Loyalty_model->voucher_campaign_options(['PUBLIC', 'MANUAL', 'MEMBER_TARGETED', 'AUTO_FROM_TXN']);

        if ($entity === 'point_rule') {
            return [
                'page_title' => 'Aturan Poin',
                'active_menu' => 'loyalty.point_rule.index',
                'promo_tab_active' => 'point-rule',
                'filters' => $this->promo_filters('earn_mode', ['ALL', 'AMOUNT', 'PRODUCT', 'FLAT']),
                'promo_config' => [
                    'title' => 'Aturan Poin',
                    'subtitle' => 'Di sini kita tentukan kapan member dapat poin. Bahasanya kita buat operasional: belanja berapa, beli produk apa, lalu poinnya berapa.',
                    'entity_label' => 'Aturan Poin',
                    'new_label' => 'Buat Aturan Poin',
                    'data_url' => site_url('loyalty/point-rules/data'),
                    'save_url' => site_url('loyalty/point-rules/save'),
                    'toggle_base_url' => site_url('loyalty/point-rules/toggle'),
                    'delete_base_url' => site_url('loyalty/point-rules/delete'),
                    'product_search_url' => site_url('loyalty/product-search'),
                    'primary_filter_key' => 'earn_mode',
                    'primary_filter_options' => [
                        ['value' => 'ALL', 'label' => 'Semua Cara Dapat Poin'],
                        ['value' => 'AMOUNT', 'label' => 'Dari nominal belanja'],
                        ['value' => 'PRODUCT', 'label' => 'Dari produk tertentu'],
                        ['value' => 'FLAT', 'label' => 'Poin tetap per transaksi'],
                    ],
                    'columns' => [
                        ['key' => 'rule_name', 'label' => 'Nama Aturan'],
                        ['key' => 'earn_mode_label', 'label' => 'Cara Dapat Poin'],
                        ['key' => 'required_product_name', 'label' => 'Produk Pemicu'],
                        ['key' => 'amount_per_point', 'label' => 'Belanja per 1 poin', 'type' => 'money'],
                        ['key' => 'flat_point', 'label' => 'Poin tetap', 'type' => 'number'],
                        ['key' => 'point_expiry_days', 'label' => 'Kadaluarsa (hari)'],
                        ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                    ],
                    'fields' => [
                        ['name' => 'rule_code', 'label' => 'Kode internal', 'type' => 'text', 'placeholder' => 'Boleh dikosongkan, sistem buat otomatis'],
                        ['name' => 'rule_name', 'label' => 'Nama aturan', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Poin belanja reguler'],
                        ['name' => 'earn_mode', 'label' => 'Cara member dapat poin', 'type' => 'select', 'options' => [
                            ['value' => 'AMOUNT', 'label' => 'Dari nominal belanja'],
                            ['value' => 'PRODUCT', 'label' => 'Dari produk tertentu'],
                            ['value' => 'FLAT', 'label' => 'Poin tetap per transaksi'],
                        ]],
                        ['name' => 'spend_basis', 'label' => 'Hitung dari', 'type' => 'select', 'options' => [
                            ['value' => 'NET', 'label' => 'Netto setelah diskon'],
                            ['value' => 'GROSS', 'label' => 'Bruto sebelum diskon'],
                        ]],
                        ['name' => 'amount_per_point', 'label' => 'Belanja per 1 poin', 'type' => 'number', 'step' => '0.01'],
                        ['name' => 'flat_point', 'label' => 'Poin tetap', 'type' => 'number', 'step' => '0.0001'],
                        ['name' => 'required_product_id', 'label' => 'Produk pemicu', 'type' => 'ajax_product', 'placeholder' => 'Cari nama produk bila poin hanya berlaku untuk produk tertentu', 'display_key' => 'required_product_name'],
                        ['name' => 'min_spend_amount', 'label' => 'Minimal belanja', 'type' => 'number', 'step' => '0.01'],
                        ['name' => 'point_expiry_days', 'label' => 'Masa berlaku poin (hari)', 'type' => 'number', 'step' => '1'],
                        ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                    ],
                ],
            ];
        }

        if ($entity === 'stamp_campaign') {
            return [
                'page_title' => 'Campaign Stamp',
                'active_menu' => 'loyalty.stamp_campaign.index',
                'promo_tab_active' => 'stamp-campaign',
                'filters' => $this->promo_filters('earn_mode', ['ALL', 'TXN', 'AMOUNT', 'PRODUCT']),
                'promo_config' => [
                    'title' => 'Program Stamp',
                    'subtitle' => 'Cocok untuk skema beli berkala. Misalnya beli kopi 8x, dapat 1 hadiah. Kita atur pola kumpul dan tebusnya di sini.',
                    'entity_label' => 'Program Stamp',
                    'new_label' => 'Buat Program Stamp',
                    'data_url' => site_url('loyalty/stamp-campaigns/data'),
                    'save_url' => site_url('loyalty/stamp-campaigns/save'),
                    'toggle_base_url' => site_url('loyalty/stamp-campaigns/toggle'),
                    'delete_base_url' => site_url('loyalty/stamp-campaigns/delete'),
                    'product_search_url' => site_url('loyalty/product-search'),
                    'primary_filter_key' => 'earn_mode',
                    'primary_filter_options' => [
                        ['value' => 'ALL', 'label' => 'Semua Cara Dapat Stamp'],
                        ['value' => 'TXN', 'label' => 'Setiap transaksi'],
                        ['value' => 'AMOUNT', 'label' => 'Dari nominal belanja'],
                        ['value' => 'PRODUCT', 'label' => 'Dari produk tertentu'],
                    ],
                    'columns' => [
                        ['key' => 'campaign_name', 'label' => 'Nama Program'],
                        ['key' => 'earn_mode_label', 'label' => 'Cara Dapat Stamp'],
                        ['key' => 'required_product_name', 'label' => 'Produk Pemicu'],
                        ['key' => 'stamp_per_earn', 'label' => 'Stamp didapat', 'type' => 'number'],
                        ['key' => 'redeem_required_stamp', 'label' => 'Stamp untuk tebus', 'type' => 'number'],
                        ['key' => 'end_date', 'label' => 'Berakhir', 'type' => 'date'],
                        ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                    ],
                    'fields' => [
                        ['name' => 'campaign_code', 'label' => 'Kode internal', 'type' => 'text', 'placeholder' => 'Boleh dikosongkan, sistem buat otomatis'],
                        ['name' => 'campaign_name', 'label' => 'Nama program', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Stamp kopi pagi'],
                        ['name' => 'earn_mode', 'label' => 'Cara member dapat stamp', 'type' => 'select', 'options' => [
                            ['value' => 'TXN', 'label' => 'Setiap transaksi'],
                            ['value' => 'AMOUNT', 'label' => 'Dari nominal belanja'],
                            ['value' => 'PRODUCT', 'label' => 'Dari produk tertentu'],
                        ]],
                        ['name' => 'amount_step', 'label' => 'Step nominal', 'type' => 'number', 'step' => '0.01'],
                        ['name' => 'stamp_per_earn', 'label' => 'Stamp yang diberikan', 'type' => 'number', 'step' => '0.0001'],
                        ['name' => 'required_product_id', 'label' => 'Produk pemicu', 'type' => 'ajax_product', 'placeholder' => 'Cari nama produk bila program hanya berlaku untuk produk tertentu', 'display_key' => 'required_product_name'],
                        ['name' => 'redeem_required_stamp', 'label' => 'Jumlah stamp untuk tebus', 'type' => 'number', 'step' => '0.0001'],
                        ['name' => 'start_date', 'label' => 'Mulai berlaku', 'type' => 'date'],
                        ['name' => 'end_date', 'label' => 'Berakhir', 'type' => 'date'],
                        ['name' => 'stamp_expiry_days', 'label' => 'Masa berlaku stamp (hari)', 'type' => 'number', 'step' => '1'],
                        ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                    ],
                ],
            ];
        }

        if ($entity === 'voucher_issue') {
            return [
                'page_title' => 'Voucher',
                'active_menu' => 'loyalty.voucher_campaign.index',
                'promo_tab_active' => 'voucher-issue',
                'filters' => $this->promo_filters('voucher_status', ['ALL', 'OPEN', 'REDEEMED', 'EXPIRED', 'VOID']),
                'promo_config' => [
                    'title' => 'Voucher Siap Pakai',
                    'subtitle' => 'Ini daftar voucher aktual yang benar-benar bisa dipakai pelanggan. Bisa dibuat manual untuk umum, atau nanti otomatis digenerate dari promo transaksi.',
                    'entity_label' => 'Voucher',
                    'new_label' => 'Buat Voucher Manual',
                    'data_url' => site_url('loyalty/vouchers/data'),
                    'save_url' => site_url('loyalty/vouchers/save'),
                    'toggle_base_url' => site_url('loyalty/vouchers/toggle'),
                    'delete_base_url' => site_url('loyalty/vouchers/delete'),
                    'member_search_url' => site_url('loyalty/member-search'),
                    'primary_filter_key' => 'voucher_status',
                    'primary_filter_options' => [
                        ['value' => 'ALL', 'label' => 'Semua Status Voucher'],
                        ['value' => 'OPEN', 'label' => 'Siap dipakai'],
                        ['value' => 'REDEEMED', 'label' => 'Sudah dipakai'],
                        ['value' => 'EXPIRED', 'label' => 'Kadaluarsa'],
                        ['value' => 'VOID', 'label' => 'Dibatalkan'],
                    ],
                    'columns' => [
                        ['key' => 'voucher_code', 'label' => 'Kode Voucher'],
                        ['key' => 'campaign_name', 'label' => 'Sumber Aturan'],
                        ['key' => 'member_name', 'label' => 'Khusus Member'],
                        ['key' => 'voucher_status_label', 'label' => 'Status'],
                        ['key' => 'amount_snapshot', 'label' => 'Benefit', 'type' => 'voucher_issue_benefit'],
                        ['key' => 'expired_at', 'label' => 'Kadaluarsa', 'type' => 'date'],
                    ],
                    'fields' => [
                        ['name' => 'campaign_id', 'label' => 'Aturan voucher', 'type' => 'select', 'options' => array_merge([['value' => '', 'label' => '- pilih aturan voucher -']], $voucherCampaignOptions)],
                        ['name' => 'voucher_code', 'label' => 'Kode voucher', 'type' => 'text', 'placeholder' => 'Boleh dikosongkan, sistem buat otomatis'],
                        ['name' => 'member_id', 'label' => 'Khusus member', 'type' => 'ajax_member', 'placeholder' => 'Opsional. Cari nama member atau nomor HP bila voucher hanya untuk member tertentu', 'display_key' => 'member_name_display', 'search_url' => site_url('loyalty/member-search')],
                        ['name' => 'expired_at', 'label' => 'Kadaluarsa', 'type' => 'date'],
                        ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'placeholder' => 'Contoh: voucher goodwill, voucher kompensasi, voucher hadiah'],
                    ],
                ],
            ];
        }

        return [
            'page_title' => 'Promo Voucher',
            'active_menu' => 'loyalty.voucher_campaign.index',
            'promo_tab_active' => 'voucher-campaign',
            'filters' => $this->promo_filters('issue_mode', ['ALL', 'PUBLIC', 'AUTO_FROM_TXN', 'MEMBER_TARGETED', 'MANUAL']),
            'promo_config' => [
                'title' => 'Aturan Promo Voucher',
                'subtitle' => 'Halaman ini khusus untuk mendefinisikan promo yang nanti bisa mengeluarkan voucher otomatis. Jadi ini aturan promonya, bukan daftar voucher aktualnya.',
                'entity_label' => 'Promo Voucher',
                'new_label' => 'Buat Aturan Promo Voucher',
                'data_url' => site_url('loyalty/voucher-campaigns/data'),
                'save_url' => site_url('loyalty/voucher-campaigns/save'),
                'toggle_base_url' => site_url('loyalty/voucher-campaigns/toggle'),
                'delete_base_url' => site_url('loyalty/voucher-campaigns/delete'),
                'product_search_url' => site_url('loyalty/product-search'),
                'primary_filter_key' => 'issue_mode',
                'primary_filter_options' => [
                    ['value' => 'ALL', 'label' => 'Semua Cara Voucher Keluar'],
                    ['value' => 'PUBLIC', 'label' => 'Voucher umum'],
                    ['value' => 'AUTO_FROM_TXN', 'label' => 'Otomatis dari transaksi'],
                    ['value' => 'MEMBER_TARGETED', 'label' => 'Khusus member tertentu'],
                    ['value' => 'MANUAL', 'label' => 'Diterbitkan manual'],
                ],
                    'columns' => [
                    ['key' => 'campaign_name', 'label' => 'Nama Promo'],
                    ['key' => 'issue_mode_label', 'label' => 'Cara Voucher Keluar'],
                    ['key' => 'voucher_type_label', 'label' => 'Bentuk Benefit'],
                    ['key' => 'discount_value', 'label' => 'Benefit', 'type' => 'voucher_campaign_benefit'],
                    ['key' => 'min_spend_amount', 'label' => 'Minimal belanja', 'type' => 'money'],
                    ['key' => 'end_date', 'label' => 'Berakhir', 'type' => 'date'],
                    ['key' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['name' => 'campaign_code', 'label' => 'Kode internal', 'type' => 'text', 'placeholder' => 'Boleh dikosongkan, sistem buat otomatis'],
                    ['name' => 'campaign_name', 'label' => 'Nama promo', 'type' => 'text', 'required' => true, 'placeholder' => 'Contoh: Belanja 100 ribu dapat voucher'],
                    ['name' => 'issue_mode', 'label' => 'Cara voucher keluar', 'type' => 'select', 'options' => [
                        ['value' => 'PUBLIC', 'label' => 'Voucher umum'],
                        ['value' => 'AUTO_FROM_TXN', 'label' => 'Otomatis dari transaksi'],
                        ['value' => 'MEMBER_TARGETED', 'label' => 'Khusus member tertentu'],
                        ['value' => 'MANUAL', 'label' => 'Diterbitkan manual'],
                    ]],
                    ['name' => 'voucher_type', 'label' => 'Bentuk benefit', 'type' => 'select', 'options' => [
                        ['value' => 'AMOUNT', 'label' => 'Potongan nominal'],
                        ['value' => 'PERCENT', 'label' => 'Potongan persen'],
                        ['value' => 'FREE_PRODUCT', 'label' => 'Gratis produk'],
                    ]],
                    ['name' => 'discount_value', 'label' => 'Nilai benefit', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'max_discount_amount', 'label' => 'Batas potongan maksimal', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'min_spend_amount', 'label' => 'Minimal belanja', 'type' => 'number', 'step' => '0.01'],
                    ['name' => 'trigger_product_id', 'label' => 'Produk pemicu', 'type' => 'ajax_product', 'placeholder' => 'Cari nama produk bila promo dipicu oleh produk tertentu', 'display_key' => 'trigger_product_name'],
                    ['name' => 'free_product_id', 'label' => 'Produk gratis', 'type' => 'ajax_product', 'placeholder' => 'Cari nama produk gratis bila benefit berupa item gratis', 'display_key' => 'free_product_name'],
                    ['name' => 'free_qty', 'label' => 'Jumlah produk gratis', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'valid_day_count', 'label' => 'Masa berlaku voucher (hari)', 'type' => 'number', 'step' => '1'],
                    ['name' => 'point_cost', 'label' => 'Biaya poin', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'stamp_cost', 'label' => 'Biaya stamp', 'type' => 'number', 'step' => '0.0001'],
                    ['name' => 'start_date', 'label' => 'Mulai berlaku', 'type' => 'date'],
                    ['name' => 'end_date', 'label' => 'Berakhir', 'type' => 'date'],
                    ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ],
            ],
        ];
    }

    private function request_payload(): array
    {
        $raw = trim((string)$this->input->raw_input_stream);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        $post = $this->input->post(NULL, true);
        return is_array($post) ? $post : [];
    }

    private function json_ok(array $data = [], int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $payload = array_merge(['ok' => true], $data);
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function json_error(string $message, int $status = 422, array $extra = []): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $payload = array_merge(['ok' => false, 'message' => $message], $extra);
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
