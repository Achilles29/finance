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
        $this->ensure_label_storage_ready([
            'uploads/coffee-labels',
            'uploads/coffee-labels/logos',
        ], false);

        $filters = [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => strtoupper(trim((string)$this->input->get('status', true))) ?: 'ACTIVE',
        ];
        if (!in_array($filters['status'], ['ACTIVE', 'INACTIVE', 'ALL'], true)) {
            $filters['status'] = 'ACTIVE';
        }

        $tableReady = $this->Coffee_packaging_label_model->table_ready();
        $editId = (int)$this->input->get('edit', true);
        $newMode = (int)$this->input->get('new', true) === 1;
        $editRow = $editId > 0 && $tableReady ? $this->Coffee_packaging_label_model->find($editId) : null;

        if (!$tableReady) {
            $this->session->set_flashdata('warning', 'Tabel label packaging kopi belum ada. Jalankan SQL 2026-07-26a terlebih dahulu.');
        }
        if ($editId > 0 && $tableReady && empty($editRow)) {
            $this->session->set_flashdata('error', 'Label yang akan diedit tidak ditemukan.');
            redirect('roastery/packaging-labels');
            return;
        }

        $this->render('roastery/coffee_packaging_label_index', [
            'page_title' => 'Label Packaging Kopi',
            'active_menu' => 'production.roastery.packaging_label',
            'filters' => $filters,
            'labels' => $this->Coffee_packaging_label_model->list_labels($filters),
            'artwork_gallery' => $this->image_gallery('uploads/coffee-labels', $this->Coffee_packaging_label_model->image_usage_map()),
            'logo_gallery' => $this->image_gallery('uploads/coffee-labels/logos'),
            'edit_row' => $editRow,
            'form_mode' => $newMode || $editId > 0,
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
        if (!$this->ensure_label_storage_ready([
            'uploads/coffee-labels',
            'uploads/coffee-labels/logos',
        ])) {
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : '?new=1'));
            return;
        }

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
        $origin = trim((string)$this->input->post('origin', true));
        $processMethod = trim((string)$this->input->post('process_method', true));
        if ($coffeeName === '') {
            $this->session->set_flashdata('error', 'Nama kopi wajib diisi.');
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : '?new=1'));
            return;
        }

        $designJson = $this->sanitize_design_json((string)$this->input->post('design_json', false));
        $imagePath = (string)($existing['image_path'] ?? '');
        $logoPath = (string)($existing['logo_path'] ?? '');
        $galleryPath = $this->valid_gallery_image_path((string)$this->input->post('gallery_image_path', true), 'uploads/coffee-labels/');
        if ($galleryPath !== '') {
            $imagePath = $galleryPath;
        }
        $logoGalleryPath = $this->valid_gallery_image_path((string)$this->input->post('gallery_logo_path', true), 'uploads/coffee-labels/logos/');
        if ($logoGalleryPath !== '') {
            $logoPath = $logoGalleryPath;
        }

        $upload = $this->handle_png_upload_field('label_image', 'uploads/coffee-labels', (string)($existing['image_path'] ?? ''));
        if ($upload === false) {
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : '?new=1'));
            return;
        }
        if (is_array($upload) && !empty($upload['image_path'])) {
            $imagePath = $upload['image_path'];
        }
        $logoUpload = $this->handle_png_upload_field('logo_image', 'uploads/coffee-labels/logos', (string)($existing['logo_path'] ?? ''));
        if ($logoUpload === false) {
            redirect('roastery/packaging-labels' . ($id > 0 ? '?edit=' . $id : '?new=1'));
            return;
        }
        if (is_array($logoUpload) && !empty($logoUpload['image_path'])) {
            $logoPath = $logoUpload['image_path'];
        }

        $imagePath = $this->ensure_named_artwork_path($imagePath, $coffeeName, $processMethod, $origin, $id);

        $userId = (int)($this->current_user['id'] ?? 0);
        $data = [
            'coffee_name' => $coffeeName,
            'origin' => $origin,
            'process_method' => $processMethod,
            'roast_level' => trim((string)$this->input->post('roast_level', true)),
            'body_level' => trim((string)$this->input->post('body_level', true)),
            'elevation_text' => trim((string)$this->input->post('elevation_text', true)),
            'bean_type' => trim((string)$this->input->post('bean_type', true)),
            'weight_text' => trim((string)$this->input->post('weight_text', true)),
            'tasting_notes' => trim((string)$this->input->post('tasting_notes', true)),
            'brew_suggestion' => trim((string)$this->input->post('brew_suggestion', true)),
            'batch_no' => trim((string)$this->input->post('batch_no', true)),
            'roast_date' => $this->nullable_date((string)$this->input->post('roast_date', true)),
            'expiry_date' => $this->nullable_date((string)$this->input->post('expiry_date', true)),
            'description' => trim((string)$this->input->post('description', true)),
            'footer_note' => trim((string)$this->input->post('footer_note', true)),
            'image_path' => $imagePath,
            'logo_path' => $logoPath,
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
        redirect('roastery/packaging-labels');
    }

    public function packaging_label_duplicate($id)
    {
        $this->require_permission(self::PAGE_PACKAGING_LABEL, 'create');
        $id = (int)$id;
        if ($id <= 0) {
            show_404();
            return;
        }

        if (!$this->Coffee_packaging_label_model->table_ready()) {
            $this->session->set_flashdata('error', 'Tabel label packaging kopi belum ada. Jalankan SQL 2026-07-26a terlebih dahulu.');
            redirect('roastery/packaging-labels');
            return;
        }

        $source = $this->Coffee_packaging_label_model->find($id);
        if (empty($source)) {
            $this->session->set_flashdata('error', 'Label sumber untuk duplikat tidak ditemukan.');
            redirect('roastery/packaging-labels');
            return;
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        unset($source['id'], $source['created_at'], $source['updated_at']);
        $source['label_code'] = $this->Coffee_packaging_label_model->next_label_code();
        $source['coffee_name'] = substr(trim((string)$source['coffee_name']) . ' - Copy', 0, 160);
        $source['is_active'] = 1;
        $source['created_by'] = $userId > 0 ? $userId : null;
        $source['updated_by'] = $userId > 0 ? $userId : null;

        $newId = $this->Coffee_packaging_label_model->save($source, 0);
        if ($newId <= 0) {
            $this->session->set_flashdata('error', 'Gagal menduplikat label.');
            redirect('roastery/packaging-labels');
            return;
        }

        $this->session->set_flashdata('success', 'Label berhasil diduplikat. Silakan sesuaikan copy-nya lalu simpan.');
        redirect('roastery/packaging-labels?edit=' . $newId);
    }

    public function packaging_label_print($id)
    {
        $this->require_permission(self::PAGE_PACKAGING_LABEL, 'view');
        $id = (int)$id;
        if ($id <= 0) {
            show_404();
            return;
        }

        $tableReady = $this->Coffee_packaging_label_model->table_ready();
        $row = $tableReady ? $this->Coffee_packaging_label_model->find($id) : null;
        if (empty($row)) {
            $this->session->set_flashdata('error', 'Label yang akan dicetak tidak ditemukan.');
            redirect('roastery/packaging-labels');
            return;
        }

        $this->render('roastery/coffee_packaging_label_index', [
            'page_title' => 'Preview Cetak Label Kopi',
            'active_menu' => 'production.roastery.packaging_label',
            'filters' => ['q' => '', 'status' => 'ACTIVE'],
            'labels' => [],
            'artwork_gallery' => $this->image_gallery('uploads/coffee-labels', $this->Coffee_packaging_label_model->image_usage_map()),
            'logo_gallery' => $this->image_gallery('uploads/coffee-labels/logos'),
            'edit_row' => $row,
            'form_mode' => true,
            'print_auto' => true,
            'table_ready' => $tableReady,
            'can_create' => $this->can(self::PAGE_PACKAGING_LABEL, 'create'),
            'can_edit' => $this->can(self::PAGE_PACKAGING_LABEL, 'edit'),
            'can_delete' => $this->can(self::PAGE_PACKAGING_LABEL, 'delete'),
        ]);
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

    public function packaging_label_activate($id)
    {
        $this->require_permission(self::PAGE_PACKAGING_LABEL, 'edit');
        $id = (int)$id;
        if ($id <= 0) {
            show_404();
            return;
        }

        $ok = $this->Coffee_packaging_label_model->set_active($id, true, (int)($this->current_user['id'] ?? 0));
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Label diaktifkan kembali.' : 'Gagal mengaktifkan label.');
        redirect('roastery/packaging-labels?status=INACTIVE');
    }

    private function handle_png_upload(?array $existing = null)
    {
        return $this->handle_png_upload_field('label_image', 'uploads/coffee-labels', (string)($existing['image_path'] ?? ''));
    }

    private function handle_png_upload_field(string $fieldName, string $relativeDir, string $oldPath = '')
    {
        if (empty($_FILES[$fieldName]['name'])) {
            return [];
        }

        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if (!$this->ensure_label_storage_ready([$relativeDir])) {
            return false;
        }
        $uploadDir = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        $config = [
            'upload_path' => $uploadDir,
            'allowed_types' => 'png',
            'max_size' => 8192,
            'encrypt_name' => true,
            'remove_spaces' => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config, true);
        if (!$this->upload->do_upload($fieldName)) {
            $this->session->set_flashdata('error', strip_tags((string)$this->upload->display_errors('', '')));
            return false;
        }

        $up = $this->upload->data();
        $relativePath = $relativeDir . '/' . $up['file_name'];

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

    private function image_gallery(string $relativeDir, array $usageMap = []): array
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $dir = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.png') ?: [] as $file) {
            $name = basename($file);
            $path = $relativeDir . '/' . $name;
            $usage = $usageMap[$path] ?? null;
            $displayName = is_array($usage) && trim((string)($usage['display_name'] ?? '')) !== ''
                ? $this->label_artwork_slug(
                    (string)$usage['display_name'],
                    (string)($usage['process_method'] ?? ''),
                    (string)($usage['origin'] ?? '')
                )
                : pathinfo($name, PATHINFO_FILENAME);
            if (is_array($usage) && (int)($usage['count'] ?? 0) > 1) {
                $displayName .= ' +' . ((int)$usage['count'] - 1);
            }
            $items[$path] = [
                'path' => $path,
                'name' => $displayName,
                'file_name' => $name,
                'url' => base_url($path),
                'mtime' => @filemtime($file) ?: 0,
            ];
        }

        uasort($items, static function ($a, $b) {
            return ((int)$b['mtime'] <=> (int)$a['mtime']) ?: strcmp((string)$a['name'], (string)$b['name']);
        });

        return array_values($items);
    }

    private function ensure_named_artwork_path(string $imagePath, string $coffeeName, string $processMethod, string $origin, int $currentId = 0): string
    {
        $imagePath = trim(str_replace('\\', '/', $imagePath));
        if ($imagePath === '' || strpos($imagePath, 'uploads/coffee-labels/') !== 0 || strpos($imagePath, 'uploads/coffee-labels/logos/') === 0) {
            return $imagePath;
        }
        if (strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)) !== 'png') {
            return $imagePath;
        }

        $abs = FCPATH . ltrim($imagePath, '/');
        if (!is_file($abs)) {
            return $imagePath;
        }

        $slug = $this->label_artwork_slug($coffeeName, $processMethod, $origin);
        if ($slug === '') {
            return $imagePath;
        }

        $dirRel = 'uploads/coffee-labels';
        $baseName = pathinfo($imagePath, PATHINFO_FILENAME);
        $usedByOtherLabels = $this->Coffee_packaging_label_model->count_by_image_path($imagePath, $currentId) > 0;
        if (!$usedByOtherLabels && preg_match('/^' . preg_quote($slug, '/') . '(-\d+)?$/', $baseName)) {
            return $imagePath;
        }

        $targetRel = $this->unique_artwork_path($dirRel, $slug, $imagePath);
        if ($targetRel === $imagePath) {
            return $imagePath;
        }
        if (!$this->ensure_label_storage_ready([$dirRel])) {
            return $imagePath;
        }

        $targetAbs = FCPATH . ltrim($targetRel, '/');
        if ($usedByOtherLabels) {
            return @copy($abs, $targetAbs) ? $targetRel : $imagePath;
        }

        if (@rename($abs, $targetAbs)) {
            return $targetRel;
        }

        return @copy($abs, $targetAbs) ? $targetRel : $imagePath;
    }

    private function label_artwork_slug(string $coffeeName, string $processMethod = '', string $origin = ''): string
    {
        $name = trim($coffeeName);
        $suffix = trim($processMethod) !== '' ? trim($processMethod) : trim($origin);
        $raw = trim($name . ' ' . $suffix);
        $raw = strtolower($raw);
        $raw = preg_replace('/[^a-z0-9]+/', '-', $raw);
        $raw = trim((string)$raw, '-');
        return substr($raw !== '' ? $raw : 'coffee-label', 0, 90);
    }

    private function unique_artwork_path(string $dirRel, string $slug, string $currentPath = ''): string
    {
        $dirRel = trim($dirRel, '/');
        $candidate = $dirRel . '/' . $slug . '.png';
        if ($candidate === $currentPath || !is_file(FCPATH . ltrim($candidate, '/'))) {
            return $candidate;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = $dirRel . '/' . $slug . '-' . $i . '.png';
            if ($candidate === $currentPath || !is_file(FCPATH . ltrim($candidate, '/'))) {
                return $candidate;
            }
        }

        return $dirRel . '/' . $slug . '-' . date('His') . '.png';
    }

    private function ensure_label_storage_ready(array $relativeDirs, bool $setFlash = true): bool
    {
        foreach ($relativeDirs as $relativeDir) {
            $relativeDir = trim(str_replace('\\', '/', (string)$relativeDir), '/');
            if ($relativeDir === '') {
                continue;
            }

            $absDir = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (!is_dir($absDir) && !@mkdir($absDir, 0777, true) && !is_dir($absDir)) {
                if ($setFlash) {
                    $this->session->set_flashdata('error', 'Folder upload label kopi tidak bisa dibuat: ' . $absDir);
                }
                return false;
            }

            @chmod($absDir, 0777);
            if (!is_writable($absDir)) {
                if ($setFlash) {
                    $this->session->set_flashdata('error', 'Folder upload label kopi tidak writable: ' . $absDir);
                }
                return false;
            }
        }

        return true;
    }

    private function valid_gallery_image_path(string $path, string $expectedPrefix = 'uploads/coffee-labels/'): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $expectedPrefix = trim(str_replace('\\', '/', $expectedPrefix), '/');
        if ($path === '' || strpos($path, $expectedPrefix . '/') !== 0 || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'png') {
            return '';
        }

        $abs = FCPATH . ltrim($path, '/');
        return is_file($abs) ? $path : '';
    }
}
