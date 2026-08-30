<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Finance_revenue_reconciliation_model extends CI_Model
{
    private const HEADER = 'fin_revenue_reconciliation';
    private const LINE = 'fin_revenue_reconciliation_line';
    private const METHOD = 'pos_payment_method';
    private const ACCOUNT = 'fin_company_account';
    private const MUTATION = 'fin_account_mutation_log';

    private function ready(): bool
    {
        return $this->db->table_exists(self::HEADER) && $this->db->table_exists(self::LINE)
            && $this->db->table_exists(self::METHOD) && $this->db->table_exists(self::ACCOUNT)
            && $this->db->table_exists(self::MUTATION);
    }

    private function date($value): ?string
    {
        $value = trim((string)$value);
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function amount($value): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        $value = preg_replace('/[^0-9,\.\-]/', '', str_ireplace('rp', '', (string)$value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value)) : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') === 1 && strlen($value) - strrpos($value, '.') - 1 === 3) {
            $value = str_replace('.', '', $value);
        }
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private function note($value): ?string
    {
        $value = trim(strip_tags((string)$value));
        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function header(int $id): ?array
    {
        return $id > 0 ? ($this->db->get_where(self::HEADER, ['id' => $id], 1)->row_array() ?: null) : null;
    }

    private function latest(string $reconDate, string $revenueDate): ?array
    {
        return $this->db->where(['reconciliation_date' => $reconDate, 'revenue_date' => $revenueDate])
            ->order_by('round_no', 'DESC')->order_by('id', 'DESC')->get(self::HEADER, 1)->row_array() ?: null;
    }

    private function expected_by_method(string $revenueDate): array
    {
        $sql = "SELECT pl.payment_method_id, ROUND(COALESCE(SUM(pl.amount),0),2) expected_amount,
                       COUNT(DISTINCT p.id) transaction_count
                FROM pos_payment p
                JOIN pos_payment_line pl ON pl.payment_id=p.id AND pl.status='PAID'
                WHERE p.payment_status='PAID' AND p.payment_type IN ('FINAL','DEPOSIT')
                  AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY pl.payment_method_id";
        $rows = $this->db->query($sql, [$revenueDate, $revenueDate])->result_array();
        $result = [];
        foreach ($rows as $row) $result[(int)$row['payment_method_id']] = $row;
        if ($this->db->table_exists('pos_refund')) {
            $refunds = $this->db->select('payment_method_id, COALESCE(SUM(refund_amount),0) refund_amount', false)
                ->from('pos_refund')->where('refund_status', 'POSTED')
                ->where('refunded_at >=', $revenueDate)->where('refunded_at <', date('Y-m-d', strtotime($revenueDate . ' +1 day')))
                ->where('payment_method_id IS NOT NULL', null, false)->group_by('payment_method_id')->get()->result_array();
            foreach ($refunds as $refund) {
                $id = (int)$refund['payment_method_id'];
                if (!isset($result[$id])) $result[$id] = ['payment_method_id'=>$id, 'expected_amount'=>0, 'transaction_count'=>0];
                $result[$id]['expected_amount'] = round((float)$result[$id]['expected_amount'] - (float)$refund['refund_amount'], 2);
            }
        }
        return $result;
    }

    public function dashboard(string $reconDate, string $revenueDate, int $headerId = 0): array
    {
        if (!$this->ready()) return ['ok' => false, 'message' => 'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $reconDate = $this->date($reconDate) ?: date('Y-m-d');
        $revenueDate = $this->date($revenueDate) ?: date('Y-m-d', strtotime('-1 day'));
        $header = $this->header($headerId);
        if (!$header || $header['reconciliation_date'] !== $reconDate || $header['revenue_date'] !== $revenueDate) {
            $header = $this->latest($reconDate, $revenueDate);
        }
        $lineMap = [];
        if ($header) {
            foreach ($this->db->get_where(self::LINE, ['reconciliation_id' => (int)$header['id']])->result_array() as $line) {
                $lineMap[(int)$line['payment_method_id']] = $line;
            }
        }
        $expected = $this->expected_by_method($revenueDate);
        $methods = $this->db->select('pm.*, a.account_code, a.account_name, COALESCE(cfg.settlement_delay_days, IF(pm.method_type="CASH",0,1)) settlement_delay_days', false)
            ->from(self::METHOD . ' pm')->join(self::ACCOUNT . ' a', 'a.id=pm.company_account_id', 'left')
            ->join('fin_revenue_reconciliation_method cfg', 'cfg.payment_method_id=pm.id', 'left')
            ->where('pm.is_active', 1)->where('(cfg.is_enabled IS NULL OR cfg.is_enabled=1)', null, false)
            ->order_by('pm.sort_order', 'ASC')->order_by('pm.method_name', 'ASC')->get()->result_array();
        $rows = [];
        $summary = ['expected_total'=>0.0,'actual_total'=>0.0,'difference_total'=>0.0,'checked'=>0,'matched'=>0,'open'=>0,'posted'=>0];
        foreach ($methods as $method) {
            $id = (int)$method['id']; $line = $lineMap[$id] ?? [];
            $expectedAmount = round((float)($expected[$id]['expected_amount'] ?? 0), 2);
            $actual = array_key_exists('actual_amount', $line) && $line['actual_amount'] !== null ? round((float)$line['actual_amount'], 2) : null;
            $difference = $actual === null ? null : round($actual - $expectedAmount, 2);
            $status = $actual === null ? 'UNCHECKED' : ((string)($line['status'] ?? '') === 'POSTED' ? 'POSTED' : (abs($difference) < .005 ? 'MATCHED' : 'OPEN'));
            $rows[] = array_merge($method, $line, [
                'line_id'=>(int)($line['id'] ?? 0), 'payment_method_id'=>$id,
                'expected_amount'=>$expectedAmount, 'actual_amount'=>$actual, 'difference_amount'=>$difference,
                'transaction_count'=>(int)($expected[$id]['transaction_count'] ?? 0), 'status'=>$status,
                'account_id'=>(int)($line['account_id'] ?? $method['company_account_id'] ?? 0),
            ]);
            $summary['expected_total'] += $expectedAmount;
            if ($actual !== null) { $summary['actual_total'] += $actual; $summary['difference_total'] += $difference; $summary['checked']++; }
            if ($status === 'MATCHED') $summary['matched']++; elseif ($status === 'OPEN') $summary['open']++; elseif ($status === 'POSTED') $summary['posted']++;
        }
        foreach (['expected_total','actual_total','difference_total'] as $key) $summary[$key] = round($summary[$key], 2);
        $accounts = $this->db->where('is_active', 1)->order_by('account_name')->get(self::ACCOUNT)->result_array();
        $recent = $this->db->order_by('reconciliation_date','DESC')->order_by('id','DESC')->get(self::HEADER, 8)->result_array();
        return compact('header','rows','summary','accounts','recent') + ['ok'=>true,'reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate];
    }

    private function create_header(string $reconDate, string $revenueDate, int $actor): array
    {
        $last = $this->db->select_max('round_no','last')->get_where(self::HEADER, ['reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate])->row_array();
        $round = max(1, (int)($last['last'] ?? 0) + 1);
        $no = 'REK-PDT-' . date('Ymd', strtotime($revenueDate)) . '-' . date('Ymd', strtotime($reconDate)) . '-' . str_pad((string)$round,2,'0',STR_PAD_LEFT);
        $this->db->insert(self::HEADER, ['reconciliation_no'=>$no,'reconciliation_date'=>$reconDate,'revenue_date'=>$revenueDate,'round_no'=>$round,'status'=>'OPEN','created_by'=>$actor ?: null,'updated_by'=>$actor ?: null,'updated_at'=>date('Y-m-d H:i:s')]);
        return $this->header((int)$this->db->insert_id()) ?: [];
    }

    public function create_round(array $payload, int $actor): array
    {
        if (!$this->ready()) return ['ok'=>false,'message'=>'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $recon = $this->date($payload['reconciliation_date'] ?? ''); $revenue = $this->date($payload['revenue_date'] ?? '');
        if (!$recon || !$revenue || $revenue > $recon || $recon > date('Y-m-d')) return ['ok'=>false,'message'=>'Tanggal pendapatan/rekonsiliasi tidak valid atau berada di masa depan.'];
        $this->db->trans_begin();
        try { $header=$this->create_header($recon,$revenue,$actor); if (!$header) throw new RuntimeException('Sesi tidak dapat dibuat.'); $this->db->trans_commit(); return ['ok'=>true,'header'=>$header,'message'=>'Sesi rekonsiliasi pendapatan dibuat.']; }
        catch(Throwable $e) { $this->db->trans_rollback(); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }

    public function save_line(array $payload, int $actor): array
    {
        if (!$this->ready()) return ['ok'=>false,'message'=>'Migration rekonsiliasi pendapatan belum dijalankan.'];
        $recon=$this->date($payload['reconciliation_date'] ?? ''); $revenue=$this->date($payload['revenue_date'] ?? '');
        $methodId=(int)($payload['payment_method_id'] ?? 0); $actual=$this->amount($payload['actual_amount'] ?? null);
        $accountId=(int)($payload['account_id'] ?? 0); $resolution=strtoupper(trim((string)($payload['resolution_type'] ?? 'NONE')));
        if (!$recon || !$revenue || $revenue>$recon || $recon>date('Y-m-d') || $methodId<1 || $actual===null || $actual<0) return ['ok'=>false,'message'=>'Tanggal, metode, atau nilai settlement tidak valid.'];
        if (!in_array($resolution,['NONE','IN','OUT'],true)) return ['ok'=>false,'message'=>'Tindak lanjut tidak valid.'];
        $expectedMap=$this->expected_by_method($revenue); $expected=round((float)($expectedMap[$methodId]['expected_amount'] ?? 0),2); $difference=round($actual-$expected,2);
        if (abs($difference)<.005) $resolution='NONE';
        if ($resolution==='IN' && $difference<=0) return ['ok'=>false,'message'=>'Settlement lebih kecil dari omzet; gunakan mutasi keluar.'];
        if ($resolution==='OUT' && $difference>=0) return ['ok'=>false,'message'=>'Settlement lebih besar dari omzet; gunakan mutasi masuk.'];
        if ($accountId<1) return ['ok'=>false,'message'=>'Tentukan rekening penerima untuk metode pembayaran ini.'];
        $this->db->trans_begin();
        try {
            $header=$this->header((int)($payload['reconciliation_id'] ?? 0)) ?: $this->latest($recon,$revenue) ?: $this->create_header($recon,$revenue,$actor);
            if (!$header || $header['reconciliation_date']!==$recon || $header['revenue_date']!==$revenue) throw new RuntimeException('Sesi tidak sesuai tanggal.');
            $existing=$this->db->get_where(self::LINE,['reconciliation_id'=>(int)$header['id'],'payment_method_id'=>$methodId],1)->row_array();
            if ($existing && $existing['status']==='POSTED') throw new RuntimeException('Baris sudah diposting.');
            $data=['reconciliation_id'=>(int)$header['id'],'payment_method_id'=>$methodId,'account_id'=>$accountId,'expected_amount'=>$expected,'actual_amount'=>$actual,'difference_amount'=>$difference,'transaction_count'=>(int)($expectedMap[$methodId]['transaction_count'] ?? 0),'resolution_type'=>$resolution,'resolution_note'=>$this->note($payload['resolution_note'] ?? ''),'status'=>abs($difference)<.005?'MATCHED':'OPEN','entered_by'=>$actor ?: null,'entered_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')];
            if ($existing) { $this->db->where('id',(int)$existing['id'])->update(self::LINE,$data); $lineId=(int)$existing['id']; }
            else { $data['created_at']=date('Y-m-d H:i:s'); $this->db->insert(self::LINE,$data); $lineId=(int)$this->db->insert_id(); }
            $this->sync_header((int)$header['id'],$actor); $this->db->trans_commit();
            return ['ok'=>true,'line_id'=>$lineId,'header_id'=>(int)$header['id'],'message'=>abs($difference)<.005?'Settlement sesuai omzet.':'Settlement tersimpan; posting selisih jika sudah terverifikasi.'];
        } catch(Throwable $e) { $this->db->trans_rollback(); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }

    private function sync_header(int $headerId, int $actor): void
    {
        $methodCount=(int)$this->db->from(self::METHOD.' pm')->join('fin_revenue_reconciliation_method cfg','cfg.payment_method_id=pm.id','left')->where('pm.is_active',1)->where('(cfg.is_enabled IS NULL OR cfg.is_enabled=1)',null,false)->count_all_results();
        $settled=(int)$this->db->where('reconciliation_id',$headerId)->where_in('status',['MATCHED','POSTED'])->count_all_results(self::LINE);
        $this->db->where('id',$headerId)->update(self::HEADER,['status'=>$methodCount>0&&$settled>=$methodCount?'COMPLETED':'OPEN','updated_by'=>$actor ?: null,'updated_at'=>date('Y-m-d H:i:s')]);
    }

    public function post_line(int $lineId, int $actor): array
    {
        if (!$this->ready() || $lineId<1) return ['ok'=>false,'message'=>'Baris rekonsiliasi tidak valid.'];
        $this->db->trans_begin();
        try {
            $line=$this->db->query('SELECT l.*,h.reconciliation_no,h.reconciliation_date FROM '.self::LINE.' l JOIN '.self::HEADER.' h ON h.id=l.reconciliation_id WHERE l.id=? FOR UPDATE',[$lineId])->row_array();
            if (!$line || $line['status']==='POSTED' || (int)$line['mutation_id']>0) throw new RuntimeException('Baris tidak ditemukan atau sudah diposting.');
            if ($line['reconciliation_date'] !== date('Y-m-d')) throw new RuntimeException('Selisih hanya dapat diposting pada rekonsiliasi hari ini agar kas berjalan tetap konsisten.');
            $difference=round((float)$line['actual_amount']-(float)$line['expected_amount'],2); $resolution=strtoupper((string)$line['resolution_type']);
            if (abs($difference)<.005 || !in_array($resolution,['IN','OUT'],true)) throw new RuntimeException('Pilih mutasi masuk/keluar untuk selisih yang terverifikasi.');
            if (($resolution==='IN' && $difference<=0)||($resolution==='OUT' && $difference>=0)) throw new RuntimeException('Arah mutasi tidak sesuai selisih settlement.');
            $account=$this->db->query('SELECT * FROM '.self::ACCOUNT.' WHERE id=? AND is_active=1 FOR UPDATE',[(int)$line['account_id']])->row_array();
            if (!$account) throw new RuntimeException('Rekening tujuan tidak aktif.');
            $amount=abs($difference); $before=round((float)$account['current_balance'],2); $after=$resolution==='IN'?$before+$amount:$before-$amount;
            if ($after<-.004) throw new RuntimeException('Saldo rekening tidak cukup untuk mutasi keluar.');
            $after=round(max(0,$after),2); $this->db->where('id',(int)$account['id'])->update(self::ACCOUNT,['current_balance'=>$after]);
            $mutationNo='RPMUT-'.date('Ymd',strtotime($line['reconciliation_date'])).'-'.str_pad((string)$lineId,8,'0',STR_PAD_LEFT).'-'.$resolution;
            $this->db->insert(self::MUTATION,['mutation_no'=>$mutationNo,'mutation_date'=>$line['reconciliation_date'],'account_id'=>(int)$account['id'],'mutation_type'=>$resolution,'amount'=>$amount,'balance_before'=>$before,'balance_after'=>$after,'ref_module'=>'REVENUE_RECON','ref_table'=>self::LINE,'ref_id'=>$lineId,'ref_no'=>$line['reconciliation_no'],'notes'=>mb_substr('Penyesuaian settlement metode pembayaran. '.(string)$line['resolution_note'],0,255),'created_by'=>$actor ?: null,'created_at'=>date('Y-m-d H:i:s')]);
            $mutationId=(int)$this->db->insert_id(); if (!$mutationId) throw new RuntimeException('Mutasi tidak dapat dibuat.');
            $this->db->where('id',$lineId)->update(self::LINE,['difference_amount'=>$difference,'status'=>'POSTED','mutation_id'=>$mutationId,'resolved_by'=>$actor ?: null,'resolved_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $this->sync_header((int)$line['reconciliation_id'],$actor); $this->db->trans_commit();
            return ['ok'=>true,'message'=>'Selisih settlement diposting dan kas berjalan telah diperbarui.'];
        } catch(Throwable $e) { $this->db->trans_rollback(); log_message('error','Revenue reconciliation post failed: '.$e->getMessage()); return ['ok'=>false,'message'=>$e->getMessage()]; }
    }
}
