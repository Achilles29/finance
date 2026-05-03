<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_flow extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Master_model');
    }

    public function item_material()
    {
        if (!$this->db->table_exists('inv_item_material_source_map') || !$this->db->table_exists('inv_item_material_txn')) {
            $this->session->set_flashdata('error', 'Tabel flow item->material belum tersedia. Jalankan SQL: 2026-05-03b_inventory_item_to_material_flow_foundation.sql');
            redirect('dashboard');
            return;
        }

        $q = trim((string)$this->input->get('q', true));
        $mapId = (int)$this->input->get('map_id', true);

        $this->db->select('m.id, m.source_division_id, m.item_id, m.material_id, i.item_code, i.item_name, mt.material_code, mt.material_name, d.name AS source_division_name, m.qty_material_per_item, m.is_active');
        $this->db->from('inv_item_material_source_map m');
        $this->db->join('mst_item i', 'i.id = m.item_id');
        $this->db->join('mst_material mt', 'mt.id = m.material_id');
        $this->db->join('mst_operational_division d', 'd.id = m.source_division_id');
        $this->db->where('m.is_active', 1);
        if ($q !== '') {
            $this->db->group_start();
            $this->db->like('i.item_code', $q);
            $this->db->or_like('i.item_name', $q);
            $this->db->or_like('mt.material_code', $q);
            $this->db->or_like('mt.material_name', $q);
            $this->db->or_like('d.name', $q);
            $this->db->group_end();
        }
        $this->db->order_by('d.name', 'ASC');
        $this->db->order_by('i.item_name', 'ASC');
        $maps = $this->db->get()->result_array();

        if ($mapId <= 0 && !empty($maps)) {
            $mapId = (int)$maps[0]['id'];
        }

        $selectedMap = null;
        foreach ($maps as $m) {
            if ((int)$m['id'] === $mapId) {
                $selectedMap = $m;
                break;
            }
        }

        $this->db->select('t.id, t.trx_no, t.trx_date, d.name AS source_division_name, i.item_name, m.material_name, t.qty_item, t.qty_material, t.notes');
        $this->db->from('inv_item_material_txn t');
        $this->db->join('mst_operational_division d', 'd.id = t.source_division_id');
        $this->db->join('mst_item i', 'i.id = t.item_id');
        $this->db->join('mst_material m', 'm.id = t.material_id');
        $this->db->order_by('t.id', 'DESC');
        $this->db->limit(50);
        $recentTxns = $this->db->get()->result_array();

        $this->render('inventory/item_material_flow', [
            'title' => 'Flow Item ke Material (Operasional)',
            'active_menu' => 'inventory.item_material_flow',
            'maps' => $maps,
            'selected_map_id' => $mapId,
            'selected_map' => $selectedMap,
            'recent_txns' => $recentTxns,
            'q' => $q,
        ]);
    }

    public function item_material_store()
    {
        if (!$this->db->table_exists('inv_item_material_source_map') || !$this->db->table_exists('inv_item_material_txn')) {
            $this->session->set_flashdata('error', 'Tabel flow item->material belum tersedia.');
            redirect('inventory/item-material-flow');
            return;
        }

        $mapId = (int)$this->input->post('map_id', true);
        $qtyItem = (float)$this->input->post('qty_item', true);
        $trxDate = trim((string)$this->input->post('trx_date', true));
        $notes = trim((string)$this->input->post('notes', true));

        if ($mapId <= 0 || $qtyItem <= 0 || $trxDate === '') {
            $this->session->set_flashdata('error', 'Map, tanggal transaksi, dan qty item wajib diisi.');
            redirect('inventory/item-material-flow?map_id=' . $mapId);
            return;
        }

        $map = $this->db
            ->select('m.*, i.item_name, mt.material_name, d.name AS source_division_name')
            ->from('inv_item_material_source_map m')
            ->join('mst_item i', 'i.id = m.item_id')
            ->join('mst_material mt', 'mt.id = m.material_id')
            ->join('mst_operational_division d', 'd.id = m.source_division_id')
            ->where('m.id', $mapId)
            ->where('m.is_active', 1)
            ->get()
            ->row_array();

        if (!$map) {
            $this->session->set_flashdata('error', 'Mapping item-material tidak ditemukan atau tidak aktif.');
            redirect('inventory/item-material-flow');
            return;
        }

        $factor = (float)$map['qty_material_per_item'];
        if ($factor <= 0) {
            $this->session->set_flashdata('error', 'Faktor konversi mapping harus lebih dari 0.');
            redirect('inventory/item-material-flow?map_id=' . $mapId);
            return;
        }

        $qtyMaterial = $qtyItem * $factor;
        $trxNo = $this->generateTrxNo();

        $sourceDivisionId = (int)$map['source_division_id'];
        $itemId = (int)$map['item_id'];
        $materialId = (int)$map['material_id'];

        $this->db->trans_start();

        $this->Master_model->insert('inv_item_material_txn', [
            'trx_no' => $trxNo,
            'trx_date' => $trxDate,
            'source_division_id' => $sourceDivisionId,
            'item_id' => $itemId,
            'material_id' => $materialId,
            'qty_item' => $qtyItem,
            'qty_material' => $qtyMaterial,
            'conversion_factor' => $factor,
            'ref_type' => 'MANUAL',
            'notes' => $notes,
            'created_by' => (int)($this->current_user['id'] ?? 0) ?: null,
        ]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            $this->session->set_flashdata('error', 'Gagal menyimpan transaksi flow item->material.');
            redirect('inventory/item-material-flow?map_id=' . $mapId);
            return;
        }

        $this->session->set_flashdata('success', 'Transaksi flow item->material berhasil disimpan.');
        redirect('inventory/item-material-flow?map_id=' . $mapId);
    }

    private function generateTrxNo(): string
    {
        $prefix = 'IMT' . date('Ymd');

        $row = $this->db
            ->select('COUNT(*) AS n', false)
            ->from('inv_item_material_txn')
            ->where('trx_date', date('Y-m-d'))
            ->get()
            ->row_array();

        $seq = ((int)($row['n'] ?? 0)) + 1;
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

}
