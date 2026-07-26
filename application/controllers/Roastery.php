<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roastery extends MY_Controller
{
    private const PAGE_PACKAGING_LABEL = 'production.roastery.packaging_label.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Coffee_packaging_label_model');
        $this->load->helper(['url', 'form']);
    }

    public function packaging_labels()
    {
        $this->require_permission(self::PAGE_PACKAGING_LABEL, 'view');

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))) ?: 'ACTIVE',
        ];
        if (!in_array($filters['status'], ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $filters['status'] = 'ACTIVE';
        }

        $editId = (int)$this->input->get('edit', true);
        $editRow = $editId > 0 ? $this->Coffee_packaging_label_model->find($editId) : null;
        $tableReady = $this->Coffee_packaging_label_model->table_ready();

        if (!$tableReady) {
            $this->session->set_flashdata('warning', 'Tabel label packaging kopi belum ada. Jalankan SQL 2026-07-26a terlebih dahulu.');
        }

        $this->render('roastery/coffee_packaging_label_index', [
            'page_title' => 'Label Packaging Kopi',
            'active_menu' => 'production.roastery.packaging_label',
            'filters' => $filters,
            'labels' => $this->Coffee_packaging_label_model->list_labels($filters),
            'edit_row' => $editRow,
            'table_ready' => $tableReady,
            'can_create' => $this->can(self::PAGE_PACKAGING_LABEL, 'create'),
            'can_edit' => $this->can(self::PAGE_PACKAGING_LABEL, 'edit'),
            'can_delete' => $this->can(self::PAGE_PACKAGING_LABEL, 'delete'),
        ]);
    }

    public function packaging_label_save()
    {
        $id = (int)$this->input->post('id', true);
        $this->require_permission(self::PAGE_PACKAGING_LABEL, $id > 0 ? 'edit' : 'create');

        if (!$this->Coffee_packaging_label_model->table_ready()) {
            $this->session->set_flashdata('error', 'Tabel label packaging kopi belum ada. Jalankan SQL 2026-07-26a terlebih dahulu.');
            redirect('roastery/packaging-labels');
            return;
        }

        $existing = $id > 0 ? $this->Coffee_packaging_label_model->find($id) : null;
        if ($id > 0 && empty($existing)) {
            $this->session->set_flashdata('error', 'Label tidak ditemukan.');
            redirect('roastery/packaging-labels');
            return;
        }

        $coffeeName = trim((string)$this->input->post('coffee_name', true));
        if ($coffeeName === '') {
            $this->session->set_flashdata('error', 'Nama kopi wajib diisi.');
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : ''));
            return;
        }

        $designJson = $this->sanitize_design_json((string)$this->input->post('design_json', false));
        $imagePath = (string)($existing['image_path'] ?? '');
        $upload = $this->handle_png_upload($existing);
        if ($upload === false) {
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : ''));
            return;
        }
        if (is_array($upload) && !empty($upload['image_path'])) {
            $imagePath = $upload['image_path'];
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        $data = [
            'coffee_name' => $coffeeName,
            'origin' => trim((string)$this->input->post('origin', true)),
            'process_method' => trim((string)$this->input->post('process_method', true)),
            'roast_level' => trim((string)$this->input->post('roast_level', true)),
            'weight_text' => trim((string)$this->input->post('weight_text', true)),
            'tasting_notes' => trim((string)$this->input->post('tasting_notes', true)),
            'brew_suggestion' => trim((string)$this->input->post('brew_suggestion', true)),
            'batch_no' => trim((string)$this->input->post('batch_no', true)),
            'roast_date' => $this->nullable_date((string)$this->input->post('roast_date', true)),
            'expiry_date' => $this->nullable_date((string)$this->input->post('expiry_date', true)),
            'description' => trim((string)$this->input->post('description', true)),
            'image_path' => $imagePath,
            'canvas_width_mm' => max(40, min(160, (int)$this->input->post('canvas_width_mm', true))),
            'canvas_height_mm' => max(60, min(240, (int)$this->input->post('canvas_height_mm', true))),
            'theme_preset' => trim((string)$this->input->post('theme_preset', true)) ?: 'heritage-cream',
            'design_json' => $designJson,
            'is_active' => (int)$this->input->post('is_active', true) === 0 ? 0 : 1,
            'updated_by' => $userId > 0 ? $userId : null,
        ];
        if ($id <= 0) {
            $data['created_by'] = $userId > 0 ? $userId : null;
        }

        $savedId = $this->Coffee_packaging_label_model->save($data, $id);
        if ($savedId <= 0) {
            $this->session->set_flashdata('error', 'Gagal menyimpan label packaging kopi.');
            redirect('roastery/packaging-labels');
            return;
        }

        $this->session->set_flashdata('success', 'Label packaging kopi berhasil disimpan. Preview sudah siap dicetak.');
        redirect('roastery/packaging-labels?edit=' . $savedId);
    }

    public function packaging_label_delete($id)
    {
        $this->require_permission(self::PAGE_PACKAGING_LABEL, 'delete');
        $id = (int)$id;
        if ($id <= 0) {
            show_404();
            return;
        }

        $ok = $this->Coffee_packaging_label_model->set_active($id, false, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Label dinonaktifkan.' : 'Gagal menonaktifkan label.');
        redirect('roastery/packaging-labels');
    }

    private function handle_png_upload(?array $existing = null)
    {
        if (empty($_FILES['label_image']['name'])) {
            return [];
        }

        $uploadDir = FCPATH . 'uploads/coffee-labels';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->session->set_flashdata('error', 'Folder upload label kopi tidak bisa dibuat.');
            return false;
        }
        if (!is_writable($uploadDir)) {
            @chmod($uploadDir, 0777);
        }
        if (!is_writable($uploadDir)) {
            $this->session->set_flashdata('error', 'Folder upload label kopi tidak writable: ' . $uploadDir);
            return false;
        }

        $config = [
            'upload_path' => $uploadDir,
            'allowed_types' => 'png',
            'max_size' => 8192,
            'encrypt_name' => true,
            'remove_spaces' => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config, true);
        if (!$this->upload->do_upload('label_image')) {
            $this->session->set_flashdata('error', strip_tags((string)$this->upload->display_errors('', '')));
            return false;
        }

        $up = $this->upload->data();
        $relativePath = 'uploads/coffee-labels/' . $up['file_name'];

        $oldPath = (string)($existing['image_path'] ?? '');
        if ($oldPath !== '' && strpos($oldPath, 'uploads/coffee-labels/') === 0) {
            $oldAbs = FCPATH . ltrim(str_replace('\\', '/', $oldPath), '/');
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        return [
            'image_path' => $relativePath,
            'image_mime' => (string)($up['file_type'] ?? 'image/png'),
        ];
    }

    private function nullable_date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function sanitize_design_json(string $json): string
    {
        $json = trim($json);
        if ($json === '') {
            return '{}';
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return '{}';
        }

        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
