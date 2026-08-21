<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PosPrinterPreviewService
{
    private $booleanKeys = [
        'show_logo','show_header','show_invoice_no','show_payment_no','show_customer','show_table_no',
        'show_order_time','show_payment_time','show_cashier_order','show_cashier_payment','show_product_name',
        'show_qty','show_extra','show_notes','show_order_notes','show_subtotal','show_payment_breakdown','show_discount',
        'show_compliment','show_deposit_applied','show_grand_total','show_paid_amount','show_balance_due',
        'show_void_reason','show_refund_reason','show_footer','show_price','show_footer_barcode','show_wifi_info',
        'show_customer_point_info','show_customer_stamp_info','show_customer_voucher'
    ];

    public function defaultGeneralSettings(): array
    {
        return [
            'title' => 'NAMUA COFFEE N EATERY',
            'subtitle' => 'Jl. Magnolia, Desa Kabongan Kidul, Rembang',
            'logo_url' => base_url('assets/img/logo.png'),
            'wifi_name' => '',
            'wifi_password' => '',
            'show_customer_point_info' => false,
            'show_customer_stamp_info' => false,
            'show_customer_voucher' => false,
            'customer_voucher_limit' => 1,
            'customer_voucher_message_template' => 'Selamat, Anda mendapat voucher {voucher_benefit}. Gunakan sebelum {voucher_expiry}.',
            'customer_voucher_align' => 'CENTER',
            'header_lines' => ['ORDER CEPAT, SAJI HANGAT.'],
            'footer_lines' => ['TERIMA KASIH SUDAH BERKUNJUNG'],
        ];
    }

    public function documentTypeLabel(string $documentType): string
    {
        $map = [
            'RECEIPT' => 'Struk Pembayaran',
            'KITCHEN_TICKET' => 'Kitchen Ticket',
            'VOID_SLIP' => 'Slip Void',
            'REFUND_SLIP' => 'Slip Refund',
            'DEPOSIT_RECEIPT' => 'Struk Deposit',
        ];
        $documentType = strtoupper(trim($documentType));
        return $map[$documentType] ?? $documentType;
    }

    public function defaultPayload(string $documentType = 'RECEIPT', array $generalSettings = []): array
    {
        $documentType = strtoupper(trim($documentType));
        $general = array_merge($this->defaultGeneralSettings(), $generalSettings);
        $logoUrl = $this->normalizeLogoUrl((string)($general['logo_url'] ?? ''));
        return [
            'title' => (string)($general['title'] ?? 'NAMUA COFFEE N EATERY'),
            'subtitle' => (string)($general['subtitle'] ?? 'Jl. Magnolia, Desa Kabongan Kidul, Rembang'),
            'logo_url' => $logoUrl,
            'show_logo' => true,
            'show_header' => true,
            'show_invoice_no' => true,
            'show_payment_no' => $documentType === 'RECEIPT',
            'show_customer' => true,
            'show_table_no' => true,
            'show_order_time' => true,
            'show_payment_time' => false,
            'show_cashier_order' => true,
            'show_cashier_payment' => $documentType === 'RECEIPT',
            'show_product_name' => true,
            'show_qty' => true,
            'show_extra' => true,
            'show_notes' => true,
            'show_order_notes' => true,
            'show_subtotal' => $documentType === 'RECEIPT',
            'show_payment_breakdown' => $documentType === 'RECEIPT',
            'show_discount' => $documentType === 'RECEIPT',
            'show_compliment' => $documentType === 'RECEIPT',
            'show_deposit_applied' => $documentType === 'RECEIPT',
            'show_grand_total' => $documentType === 'RECEIPT',
            'show_paid_amount' => $documentType === 'RECEIPT',
            'show_balance_due' => $documentType === 'RECEIPT',
            'show_void_reason' => $documentType === 'VOID_SLIP',
            'show_refund_reason' => $documentType === 'REFUND_SLIP',
            'show_footer' => true,
            'show_price' => $documentType !== 'KITCHEN_TICKET',
            'division_filter' => $documentType === 'KITCHEN_TICKET' ? 'KITCHEN' : 'ALL',
            'header_lines' => $this->normalizeLines($general['header_lines'] ?? ['ORDER CEPAT, SAJI HANGAT.']),
            'footer_lines' => $this->normalizeLines($general['footer_lines'] ?? ['TERIMA KASIH SUDAH BERKUNJUNG']),
            'header_align' => 'CENTER',
            'footer_align' => 'CENTER',
            'show_footer_barcode' => true,
            'footer_barcode_source' => 'ORDER_NO',
            'footer_barcode_custom' => '',
            'show_wifi_info' => false,
            'wifi_name' => (string)($general['wifi_name'] ?? ''),
            'wifi_password' => (string)($general['wifi_password'] ?? ''),
            'show_customer_point_info' => !empty($general['show_customer_point_info']),
            'show_customer_stamp_info' => !empty($general['show_customer_stamp_info']),
            'show_customer_voucher' => !empty($general['show_customer_voucher']),
            'customer_voucher_limit' => max(1, min(5, (int)($general['customer_voucher_limit'] ?? 1))),
            'customer_voucher_message_template' => (string)($general['customer_voucher_message_template'] ?? 'Selamat, Anda mendapat voucher {voucher_benefit}. Gunakan sebelum {voucher_expiry}.'),
            'customer_voucher_align' => strtoupper((string)($general['customer_voucher_align'] ?? 'CENTER')),
        ];
    }

    public function decodePayload($rawPayload, string $documentType = 'RECEIPT', array $generalSettings = []): array
    {
        $payload = [];
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        } elseif (is_array($rawPayload)) {
            $payload = $rawPayload;
        }

        $result = array_merge($this->defaultPayload($documentType, $generalSettings), $payload);
        $result['logo_url'] = $this->normalizeLogoUrl((string)($result['logo_url'] ?? ''));
        $result['header_lines'] = $this->normalizeLines($result['header_lines'] ?? []);
        $result['footer_lines'] = $this->normalizeLines($result['footer_lines'] ?? []);
        foreach ($this->booleanKeys as $key) {
            $result[$key] = !empty($result[$key]);
        }
        $result['division_filter'] = $this->enumValue($result['division_filter'] ?? 'ALL', ['ALL','BAR','KITCHEN'], 'ALL');
        $result['header_align'] = $this->enumValue($result['header_align'] ?? 'CENTER', ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $result['footer_align'] = $this->enumValue($result['footer_align'] ?? 'CENTER', ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $result['customer_voucher_align'] = $this->enumValue($result['customer_voucher_align'] ?? 'CENTER', ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $result['footer_barcode_source'] = $this->enumValue($result['footer_barcode_source'] ?? 'ORDER_NO', ['ORDER_NO','PAYMENT_NO','VOID_NO','REFUND_NO','VOUCHER_CODE','CUSTOM'], 'ORDER_NO');
        $result['customer_voucher_limit'] = max(1, min(5, (int)($result['customer_voucher_limit'] ?? 1)));
        return $result;
    }

    public function payloadFromInput(array $input, string $documentType = 'RECEIPT', array $generalSettings = []): array
    {
        $payload = $this->defaultPayload($documentType, $generalSettings);
        $payload['title'] = trim((string)($input['payload_title'] ?? $input['title'] ?? $payload['title']));
        $payload['subtitle'] = trim((string)($input['payload_subtitle'] ?? $input['subtitle'] ?? ''));
        $payload['logo_url'] = $this->normalizeLogoUrl((string)($input['logo_url'] ?? $payload['logo_url']));
        $payload['division_filter'] = $this->enumValue((string)($input['division_filter'] ?? 'ALL'), ['ALL','BAR','KITCHEN'], 'ALL');
        $payload['header_align'] = $this->enumValue((string)($input['header_align'] ?? 'CENTER'), ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $payload['footer_align'] = $this->enumValue((string)($input['footer_align'] ?? 'CENTER'), ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $payload['customer_voucher_align'] = $this->enumValue((string)($input['customer_voucher_align'] ?? 'CENTER'), ['LEFT','CENTER','RIGHT','JUSTIFY'], 'CENTER');
        $payload['footer_barcode_source'] = $this->enumValue((string)($input['footer_barcode_source'] ?? 'ORDER_NO'), ['ORDER_NO','PAYMENT_NO','VOID_NO','REFUND_NO','VOUCHER_CODE','CUSTOM'], 'ORDER_NO');
        $payload['footer_barcode_custom'] = trim((string)($input['footer_barcode_custom'] ?? ''));
        $payload['wifi_name'] = trim((string)($input['wifi_name'] ?? ''));
        $payload['wifi_password'] = trim((string)($input['wifi_password'] ?? ''));
        $payload['customer_voucher_limit'] = max(1, min(5, (int)($input['customer_voucher_limit'] ?? 1)));
        $payload['customer_voucher_message_template'] = trim((string)($input['customer_voucher_message_template'] ?? ''));
        $payload['header_lines'] = $this->normalizeLines($input['header_lines'] ?? []);
        $payload['footer_lines'] = $this->normalizeLines($input['footer_lines'] ?? []);

        foreach ($this->booleanKeys as $key) {
            $payload[$key] = $this->boolValue($input[$key] ?? false);
        }

        return $this->decodePayload($payload, $documentType, $generalSettings);
    }

    public function buildPreviewPackage(array $payload, array $printer = [], string $documentType = 'RECEIPT'): array
    {
        $payload = $this->decodePayload($payload, $documentType);
        $paperWidthMm = ((int)($printer['paper_width_mm'] ?? 80) === 58) ? 58 : 80;
        $charsPerLine = max(24, min(64, (int)($printer['chars_per_line'] ?? ($paperWidthMm === 58 ? 32 : 48))));
        $lines = $this->buildPreviewLines($documentType, $payload, $charsPerLine);

        return [
            'payload' => $payload,
            'document_type' => strtoupper(trim($documentType)),
            'document_type_label' => $this->documentTypeLabel($documentType),
            'logo_url' => ($payload['show_logo'] && $payload['logo_url'] !== '') ? $payload['logo_url'] : '',
            'lines' => $lines,
            'paper_width_mm' => $paperWidthMm,
            'chars_per_line' => $charsPerLine,
            'summary' => [
                'printer_name' => (string)($printer['printer_name'] ?? '-'),
                'printer_role' => (string)($printer['printer_role'] ?? 'CUSTOM'),
                'print_scope' => (string)($printer['print_scope'] ?? 'DIVISION'),
                'outlet_name' => (string)($printer['outlet_name'] ?? 'GLOBAL'),
                'connection_type' => (string)($printer['connection_type'] ?? 'LOCAL_AGENT'),
                'python_port' => (int)($printer['python_port'] ?? 0),
                'agent_host' => (string)($printer['agent_host'] ?? ''),
                'device_name' => (string)($printer['system_device_name'] ?? $printer['device_name'] ?? ''),
            ],
        ];
    }

    private function buildPreviewLines(string $documentType, array $payload, int $width): array
    {
        $documentType = strtoupper(trim($documentType));
        $lines = [];
        $divider = str_repeat('=', $width);
        $dash = str_repeat('-', $width);
        $items = array_values(array_filter($this->sampleItems(), function (array $item) use ($payload) {
            return ($payload['division_filter'] ?? 'ALL') === 'ALL' || strtoupper((string)$item['division']) === strtoupper((string)$payload['division_filter']);
        }));

        $lines[] = $divider;
        if ($payload['show_header']) {
            $lines[] = $this->alignLine(strtoupper((string)$payload['title']), $width, (string)$payload['header_align']);
            if ($payload['subtitle'] !== '') {
                $lines[] = $this->alignLine((string)$payload['subtitle'], $width, (string)$payload['header_align']);
            }
            foreach ($payload['header_lines'] as $line) {
                $lines[] = $this->alignLine($line, $width, (string)$payload['header_align']);
            }
            $lines[] = $dash;
        }

        switch ($documentType) {
            case 'VOID_SLIP':
                if ($payload['show_invoice_no']) {
                    $lines[] = 'ORDER      ORD-202605-0018';
                }
                $lines[] = 'VOID       VOID-20260528-0001';
                if ($payload['show_void_reason']) {
                    $lines[] = 'ALASAN     SALAH INPUT ITEM';
                }
                $lines[] = 'APPROVAL   SUPERVISOR';
                break;
            case 'REFUND_SLIP':
                $lines[] = 'REFUND     RFD-20260528-0001';
                if ($payload['show_customer']) {
                    $lines[] = 'CUSTOMER   BUDI MEMBER';
                }
                if ($payload['show_product_name']) {
                    $lines[] = 'ITEM       1 x AMERICANO ICE';
                }
                $lines[] = 'NILAI      23.000';
                if ($payload['show_refund_reason']) {
                    $lines[] = 'ALASAN     PRODUK TIDAK SESUAI';
                }
                break;
            case 'DEPOSIT_RECEIPT':
                $lines[] = 'RESERVASI  RSV-20260528-0002';
                if ($payload['show_customer']) {
                    $lines[] = 'CUSTOMER   BUDI';
                }
                $lines[] = 'DEPOSIT    250.000';
                break;
            case 'KITCHEN_TICKET':
            case 'RECEIPT':
            default:
                if ($payload['show_invoice_no']) {
                    $lines[] = 'ORDER      ORD-20260528-0018';
                }
                if ($payload['show_payment_no']) {
                    $lines[] = 'PAYMENT    PAY-20260528-0002';
                }
                if ($payload['show_customer']) {
                    $lines[] = 'CUSTOMER   BUDI MEMBER';
                }
                if ($payload['show_table_no']) {
                    $lines[] = 'MEJA       A-01';
                }
                if ($payload['show_order_time']) {
                    $lines[] = 'WAKTU      28-05-2026 15:30';
                } elseif ($payload['show_payment_time']) {
                    $lines[] = 'BAYAR      28-05-2026 15:45';
                }
                if ($payload['show_cashier_order'] || $payload['show_cashier_payment']) {
                    $lines[] = 'KASIR      ANNISA';
                }
                if ($payload['show_order_notes']) {
                    $lines[] = 'CATATAN';
                    $lines[] = 'Meja dekat jendela, request sambal terpisah.';
                }
                foreach ($items as $item) {
                    if ($payload['show_product_name']) {
                        $label = $payload['show_qty'] ? ($item['qty'] . ' x ' . $item['name']) : $item['name'];
                        if ($payload['show_price']) {
                            $priceWidth = min(12, max(9, (int)round($width * 0.28)));
                            $nameWidth = max(10, $width - $priceWidth);
                            $label = $this->padRight($label, $nameWidth) . $this->padLeft($this->formatNumber($item['price']), $priceWidth);
                        }
                        $lines[] = $label;
                    }
                    if ($payload['show_extra']) {
                        $lines[] = '+ EXTRA SHOT x1';
                    }
                    if ($payload['show_notes'] && $item['note'] !== '') {
                        $lines[] = '  NOTE: ' . $item['note'];
                    }
                }
                if ($documentType === 'RECEIPT') {
                    $total = 0;
                    foreach ($items as $item) {
                        $total += ((float)$item['price'] * (float)$item['qty']);
                    }
                    $discount = 5000;
                    $compliment = 2000;
                    $deposit = 10000;
                    $grandTotal = max(0, $total - $discount - $compliment);
                    $paidAmount = 15000;
                    $balanceDue = max(0, $grandTotal - $deposit - $paidAmount);
                    $lines[] = $dash;
                    if ($payload['show_subtotal']) {
                        $lines[] = 'SUBTOTAL' . $this->padLeft($this->formatNumber($total), max(8, $width - 8));
                    }
                    if ($payload['show_discount']) {
                        $lines[] = 'DISKON' . $this->padLeft($this->formatNumber($discount), max(8, $width - 6));
                    }
                    if ($payload['show_compliment']) {
                        $lines[] = 'COMPLIMENT' . $this->padLeft($this->formatNumber($compliment), max(8, $width - 10));
                    }
                    if ($payload['show_deposit_applied']) {
                        $lines[] = 'DP' . $this->padLeft($this->formatNumber($deposit), max(8, $width - 2));
                    }
                    if ($payload['show_grand_total']) {
                        $lines[] = 'TOTAL' . $this->padLeft($this->formatNumber($grandTotal), max(8, $width - 5));
                    }
                    if ($payload['show_payment_breakdown']) {
                        $lines[] = 'TUNAI' . $this->padLeft($this->formatNumber($paidAmount), max(8, $width - 5));
                    }
                    if ($payload['show_paid_amount']) {
                        $lines[] = 'SUDAH BAYAR' . $this->padLeft($this->formatNumber($paidAmount), max(8, $width - 11));
                    }
                    if ($payload['show_balance_due']) {
                        $lines[] = 'KURANG BAYAR' . $this->padLeft($this->formatNumber($balanceDue), max(8, $width - 12));
                    }
                }
                break;
        }

        if ($payload['show_footer']) {
            $lines[] = $dash;
            foreach ($payload['footer_lines'] as $line) {
                $lines[] = $this->alignLine($line, $width, (string)$payload['footer_align']);
            }
            if ($payload['show_wifi_info']) {
                if ($payload['wifi_name'] !== '') {
                    $lines[] = 'WIFI: ' . $payload['wifi_name'];
                }
                if ($payload['wifi_password'] !== '') {
                    $lines[] = 'PASS: ' . $payload['wifi_password'];
                }
            }
            if ($payload['show_customer_point_info']) {
                $lines[] = 'Poin transaksi ini        1.00';
                $lines[] = 'Total poin Anda          12.00';
            }
            if ($payload['show_customer_stamp_info']) {
                $lines[] = 'Stamp transaksi ini       1.00';
                $lines[] = 'Total stamp Anda          4.00';
            }
            if ($payload['show_customer_voucher']) {
                $voucherMessage = $payload['customer_voucher_message_template'] !== ''
                    ? $payload['customer_voucher_message_template']
                    : 'Selamat, Anda mendapat voucher {voucher_benefit}. Gunakan sebelum {voucher_expiry}.';
                $voucherMessage = str_replace(
                    ['{voucher_benefit}', '{voucher_code}', '{voucher_expiry}', '{voucher_type}', '{voucher_value}', '{voucher_max_discount}'],
                    ['Rp 20.000', 'VCH-ABC123', '31-05-2026 23:59', 'FIX', 'Rp 20.000', 'Rp 20.000'],
                    $voucherMessage
                );
                foreach (preg_split('/\r?\n/', $voucherMessage) as $line) {
                    $line = trim((string)$line);
                    if ($line !== '') {
                        $lines[] = $this->alignLine($line, $width, (string)$payload['customer_voucher_align']);
                    }
                }
            }
            if ($payload['show_footer_barcode']) {
                $barcodeValue = 'ORD-20260528-0018';
                switch ((string)$payload['footer_barcode_source']) {
                    case 'PAYMENT_NO': $barcodeValue = 'PAY-20260528-0002'; break;
                    case 'VOID_NO': $barcodeValue = 'VOID-20260528-0001'; break;
                    case 'REFUND_NO': $barcodeValue = 'RFD-20260528-0001'; break;
                    case 'VOUCHER_CODE': $barcodeValue = 'VCH-ABC123'; break;
                    case 'CUSTOM': $barcodeValue = $payload['footer_barcode_custom'] !== '' ? $payload['footer_barcode_custom'] : 'CUSTOM-BARCODE'; break;
                }
                $lines[] = 'BARCODE    ' . $barcodeValue;
            }
        }

        $lines[] = $divider;
        return $lines;
    }

    private function sampleItems(): array
    {
        return [
            ['division' => 'BAR', 'qty' => 1, 'name' => 'AMERICANO ICE', 'price' => 23000, 'note' => 'Less ice'],
            ['division' => 'BAR', 'qty' => 1, 'name' => 'CAPPUCCINO HOT', 'price' => 28000, 'note' => ''],
            ['division' => 'KITCHEN', 'qty' => 1, 'name' => 'NASI GORENG AYAM', 'price' => 42000, 'note' => 'Tanpa acar'],
            ['division' => 'KITCHEN', 'qty' => 1, 'name' => 'KENTANG GORENG', 'price' => 18000, 'note' => 'Extra mayo'],
        ];
    }

    private function normalizeLines($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static function ($line) {
                return trim((string)$line);
            }, $value), static function ($line) {
                return $line !== '';
            }));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(static function ($line) {
            return trim((string)$line);
        }, preg_split('/\r?\n/', $value)), static function ($line) {
            return $line !== '';
        }));
    }

    private function normalizeLogoUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return base_url('assets/img/logo.png');
        }

        $normalized = strtolower($url);
        if (strpos($normalized, 'core.namuacoffee.com/assets/img/logo') !== false) {
            return base_url('assets/img/logo.png');
        }

        return $url;
    }

    private function boolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string)$value));
        return in_array($value, ['1','true','yes','on'], true);
    }

    private function enumValue(string $value, array $allowed, string $default): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function alignLine(string $text, int $width, string $align): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) >= $width) {
            return mb_substr($text, 0, $width);
        }
        $align = strtoupper(trim($align));
        if ($align === 'RIGHT') {
            return str_repeat(' ', max(0, $width - mb_strlen($text))) . $text;
        }
        if ($align === 'LEFT') {
            return $text;
        }
        if ($align === 'JUSTIFY') {
            $words = preg_split('/\s+/', $text);
            if (!$words || count($words) <= 1) {
                return $text;
            }
            $chars = mb_strlen(implode('', $words));
            $slots = count($words) - 1;
            $totalSpaces = max($slots, $width - $chars);
            $base = intdiv($totalSpaces, $slots);
            $extra = $totalSpaces % $slots;
            $built = '';
            foreach ($words as $index => $word) {
                $built .= $word;
                if ($index < $slots) {
                    $built .= str_repeat(' ', $base + ($extra > 0 ? 1 : 0));
                    if ($extra > 0) {
                        $extra--;
                    }
                }
            }
            return $built;
        }
        $pad = (int)floor(($width - mb_strlen($text)) / 2);
        return str_repeat(' ', max(0, $pad)) . $text;
    }

    private function formatNumber($number): string
    {
        return number_format((float)$number, 0, ',', '.');
    }

    private function padLeft(string $text, int $length): string
    {
        if (mb_strlen($text) >= $length) {
            return mb_substr($text, 0, $length);
        }
        return str_repeat(' ', $length - mb_strlen($text)) . $text;
    }

    private function padRight(string $text, int $length): string
    {
        if (mb_strlen($text) >= $length) {
            return mb_substr($text, 0, $length);
        }
        return $text . str_repeat(' ', $length - mb_strlen($text));
    }
}
