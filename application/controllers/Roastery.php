<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Roastery extends MY_Controller
{
    private const PAGE_PACKAGING_LABEL = 'production.roastery.packaging_label.index';
    private const LABEL_TEMPLATE_UNIVERSAL = 'universal-10cm';

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
        $requestedTemplateRaw = trim((string)$this->input->get('template', true));
        $requestedTemplate = $this->normalize_label_template($requestedTemplateRaw);
        $editRow = $editId > 0 && $tableReady ? $this->Coffee_packaging_label_model->find($editId) : null;

        if (!$tableReady) {
            $this->session->set_flashdata('warning', 'Tabel label packaging kopi belum ada. Jalankan SQL 2026-07-26a terlebih dahulu.');
        }
        if ($editId > 0 && $tableReady && empty($editRow)) {
            $this->session->set_flashdata('error', 'Label yang akan diedit tidak ditemukan.');
            redirect('roastery/packaging-labels');
            return;
        }

        $formMode = $newMode || $editId > 0;
        $storedTemplate = !empty($editRow) && $this->is_universal_label($editRow)
            ? self::LABEL_TEMPLATE_UNIVERSAL
            : 'legacy-studio';
        $selectedTemplate = $formMode && $requestedTemplateRaw !== ''
            ? $requestedTemplate
            : $storedTemplate;

        $this->render('roastery/coffee_packaging_label_index', [
            'page_title' => 'Label Packaging Kopi',
            'active_menu' => 'production.roastery.packaging_label',
            'filters' => $filters,
            'labels' => $this->Coffee_packaging_label_model->list_labels($filters),
            'product_options' => $this->Coffee_packaging_label_model->roastery_product_options(),
            'artwork_gallery' => $this->image_gallery('uploads/coffee-labels', $this->Coffee_packaging_label_model->image_usage_map()),
            'logo_gallery' => $this->image_gallery('uploads/coffee-labels/logos', [], ['png', 'svg']),
            'edit_row' => $editRow,
            'form_mode' => $newMode || $editId > 0,
            'label_template' => $selectedTemplate,
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
        $requestedTemplateRaw = trim((string)$this->input->post('label_template', true));
        $requestedTemplate = $this->normalize_label_template($requestedTemplateRaw);
        $returnUrl = 'roastery/packaging-labels' . ($id > 0
            ? '?edit=' . $id . ($requestedTemplate === self::LABEL_TEMPLATE_UNIVERSAL ? '&template=' . self::LABEL_TEMPLATE_UNIVERSAL : '')
            : '?new=1' . ($requestedTemplate === self::LABEL_TEMPLATE_UNIVERSAL ? '&template=' . self::LABEL_TEMPLATE_UNIVERSAL : ''));
        $hasMediaUpload = !empty($_FILES['label_image']['name'])
            || !empty($_FILES['logo_image']['name'])
            || !empty($_FILES['badge_logo_image']['name']);
        if ($hasMediaUpload && !$this->ensure_label_storage_ready([
            'uploads/coffee-labels',
            'uploads/coffee-labels/logos',
        ])) {
            redirect($returnUrl);
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

        $labelName = trim((string)$this->input->post('label_name', true));
        $coffeeName = trim((string)$this->input->post('coffee_name', true));
        $origin = trim((string)$this->input->post('origin', true));
        $processMethod = trim((string)$this->input->post('process_method', true));
        $productId = max(0, (int)$this->input->post('product_id', true));
        if ($labelName === '') {
            $this->session->set_flashdata('error', 'Nama label wajib diisi agar variasi label mudah dibedakan.');
            redirect($returnUrl);
            return;
        }
        if ($productId > 0) {
            $product = $this->Coffee_packaging_label_model->find_roastery_product($productId);
            if (empty($product)) {
                $this->session->set_flashdata('error', 'Produk roastery yang dipilih tidak ditemukan atau sudah tidak aktif.');
                redirect($returnUrl);
                return;
            }
            $coffeeName = trim((string)($product['product_name'] ?? ''));
        }
        if ($coffeeName === '') {
            $this->session->set_flashdata('error', 'Nama produk wajib diisi.');
            redirect($returnUrl);
            return;
        }

        $designJson = $this->sanitize_design_json((string)$this->input->post('design_json', false));
        $designData = json_decode($designJson, true);
        if (!is_array($designData)) {
            $designData = [];
        }
        $designMeta = is_array($designData['meta'] ?? null) ? $designData['meta'] : [];
        $labelTemplate = $requestedTemplateRaw !== ''
            ? $requestedTemplate
            : (!empty($existing) && $this->is_universal_label($existing)
                ? self::LABEL_TEMPLATE_UNIVERSAL
                : 'legacy-studio');
        $imagePath = (string)($existing['image_path'] ?? '');
        $logoPath = (string)($existing['logo_path'] ?? '');
        $badgeLogoPath = (string)($designMeta['badge_logo_path'] ?? '');
        $artworkChanged = false;
        $galleryPath = $this->valid_gallery_image_path((string)$this->input->post('gallery_image_path', true), 'uploads/coffee-labels/');
        if ($galleryPath !== '') {
            $imagePath = $galleryPath;
            $artworkChanged = true;
        }
        $logoGalleryPath = $this->valid_gallery_image_path((string)$this->input->post('gallery_logo_path', true), 'uploads/coffee-labels/logos/', ['png', 'svg']);
        if ($logoGalleryPath !== '') {
            $logoPath = $logoGalleryPath;
        }
        $badgeLogoGalleryPath = $this->valid_gallery_image_path((string)$this->input->post('gallery_badge_logo_path', true), 'uploads/coffee-labels/logos/', ['png', 'svg']);
        if ($badgeLogoGalleryPath !== '') {
            $badgeLogoPath = $badgeLogoGalleryPath;
        }

        $upload = $this->handle_png_upload_field('label_image', 'uploads/coffee-labels', (string)($existing['image_path'] ?? ''));
        if ($upload === false) {
            redirect($returnUrl);
            return;
        }
        if (is_array($upload) && !empty($upload['image_path'])) {
            $imagePath = $upload['image_path'];
            $artworkChanged = true;
        }
        $logoUpload = $this->handle_logo_upload_field('logo_image', 'uploads/coffee-labels/logos');
        if ($logoUpload === false) {
            redirect($returnUrl);
            return;
        }
        if (is_array($logoUpload) && !empty($logoUpload['image_path'])) {
            $logoPath = $logoUpload['image_path'];
        }
        $badgeLogoUpload = $this->handle_logo_upload_field('badge_logo_image', 'uploads/coffee-labels/logos');
        if ($badgeLogoUpload === false) {
            redirect($returnUrl);
            return;
        }
        if (is_array($badgeLogoUpload) && !empty($badgeLogoUpload['image_path'])) {
            $badgeLogoPath = $badgeLogoUpload['image_path'];
        }

        if ($artworkChanged) {
            $imagePath = $this->ensure_named_artwork_path($imagePath, $coffeeName, $processMethod, $origin, $id);
        }
        if ($badgeLogoPath !== '') {
            $designMeta['badge_logo_path'] = $badgeLogoPath;
        } else {
            unset($designMeta['badge_logo_path']);
        }
        $designData['meta'] = $designMeta;
        if ($labelTemplate === self::LABEL_TEMPLATE_UNIVERSAL) {
            $designData['layout'] = 'namua-universal-10cm-v1';
            $designData['print'] = [
                'paper' => 'A4',
                'columns' => 2,
                'label_width_mm' => 100,
                'min_height_mm' => 68,
            ];
        } elseif (($designData['layout'] ?? '') === 'namua-universal-10cm-v1') {
            unset($designData['layout']);
        }
        $designJson = json_encode($designData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        $themePreset = trim((string)$this->input->post('theme_preset', true));
        if ($themePreset === 'namua-universal') {
            $themePreset = 'heritage-cream';
        }

        $userId = (int)($this->current_user['id'] ?? 0);
        $data = [
            'label_name' => $labelName,
            'product_id' => $productId > 0 ? $productId : null,
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
            'canvas_width_mm' => $labelTemplate === self::LABEL_TEMPLATE_UNIVERSAL
                ? 100
                : max(40, min(160, (int)$this->input->post('canvas_width_mm', true))),
            'canvas_height_mm' => $labelTemplate === self::LABEL_TEMPLATE_UNIVERSAL
                ? 68
                : max(60, min(240, (int)$this->input->post('canvas_height_mm', true))),
            'theme_preset' => $labelTemplate === self::LABEL_TEMPLATE_UNIVERSAL
                ? 'namua-universal'
                : ($themePreset ?: 'heritage-cream'),
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

        $this->session->set_flashdata(
            'success',
            $labelTemplate === self::LABEL_TEMPLATE_UNIVERSAL
                ? 'Label packaging kopi universal 10 cm berhasil disimpan.'
                : 'Label packaging kopi studio berhasil disimpan.'
        );
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
        if (array_key_exists('label_name', $source)) {
            $source['label_name'] = substr(trim((string)($source['label_name'] ?? $source['coffee_name'])) . ' - Copy', 0, 160);
        } else {
            $source['coffee_name'] = substr(trim((string)$source['coffee_name']) . ' - Copy', 0, 160);
        }
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

        $isUniversalTemplate = $this->is_universal_label($row);
        $this->render('roastery/coffee_packaging_label_index', [
            'page_title' => 'Preview Cetak Label Kopi',
            'active_menu' => 'production.roastery.packaging_label',
            'filters' => ['q' => '', 'status' => 'ACTIVE'],
            'labels' => [],
            'product_options' => $this->Coffee_packaging_label_model->roastery_product_options(),
            'artwork_gallery' => $this->image_gallery('uploads/coffee-labels', $this->Coffee_packaging_label_model->image_usage_map()),
            'logo_gallery' => $this->image_gallery('uploads/coffee-labels/logos', [], ['png', 'svg']),
            'edit_row' => $row,
            'form_mode' => true,
            'label_template' => $isUniversalTemplate ? self::LABEL_TEMPLATE_UNIVERSAL : 'legacy-studio',
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

    private function handle_logo_upload_field(string $fieldName, string $relativeDir)
    {
        if (empty($_FILES[$fieldName]['name'])) {
            return [];
        }

        $ext = strtolower(pathinfo((string)$_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        if ($ext !== 'svg') {
            return $this->handle_png_upload_field($fieldName, $relativeDir);
        }

        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if (!$this->ensure_label_storage_ready([$relativeDir])) {
            return false;
        }

        $tmp = (string)($_FILES[$fieldName]['tmp_name'] ?? '');
        $size = (int)($_FILES[$fieldName]['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > 1024 * 1024) {
            $this->session->set_flashdata('error', 'Logo SVG tidak valid atau lebih dari 1MB.');
            return false;
        }

        $svg = (string)@file_get_contents($tmp);
        if ($svg === '' || stripos($svg, '<svg') === false || preg_match('/<\s*(script|foreignObject)\b|javascript\s*:|on[a-z]+\s*=/i', $svg)) {
            $this->session->set_flashdata('error', 'Logo SVG mengandung elemen yang tidak diizinkan.');
            return false;
        }

        $fileName = bin2hex(random_bytes(16)) . '.svg';
        $relativePath = $relativeDir . '/' . $fileName;
        $target = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!@move_uploaded_file($tmp, $target)) {
            $this->session->set_flashdata('error', 'Gagal menyimpan logo SVG.');
            return false;
        }
        @chmod($target, 0666);

        return [
            'image_path' => $relativePath,
            'image_mime' => 'image/svg+xml',
        ];
    }

    private function normalize_label_template(string $template): string
    {
        $template = strtolower(trim($template));
        if (in_array($template, [self::LABEL_TEMPLATE_UNIVERSAL, 'namua-universal-10cm-v1'], true)) {
            return self::LABEL_TEMPLATE_UNIVERSAL;
        }

        return 'legacy-studio';
    }

    private function is_universal_label(array $label): bool
    {
        if (trim((string)($label['theme_preset'] ?? '')) === 'namua-universal') {
            return true;
        }

        $design = json_decode((string)($label['design_json'] ?? ''), true);
        if (!is_array($design)) {
            return false;
        }

        return $this->normalize_label_template((string)($design['layout'] ?? '')) === self::LABEL_TEMPLATE_UNIVERSAL;
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

    private function image_gallery(string $relativeDir, array $usageMap = [], array $extensions = ['png']): array
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $dir = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        $extensions = array_values(array_unique(array_filter(array_map(static function ($ext) {
            return strtolower(trim((string)$ext, '. '));
        }, $extensions))));
        if (empty($extensions)) {
            $extensions = ['png'];
        }
        foreach ($extensions as $ext) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.' . $ext) ?: [] as $file) {
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

    private function valid_gallery_image_path(string $path, string $expectedPrefix = 'uploads/coffee-labels/', array $extensions = ['png']): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $expectedPrefix = trim(str_replace('\\', '/', $expectedPrefix), '/');
        $extensions = array_values(array_unique(array_filter(array_map(static function ($ext) {
            return strtolower(trim((string)$ext, '. '));
        }, $extensions))));
        if (empty($extensions)) {
            $extensions = ['png'];
        }
        if ($path === '' || strpos($path, $expectedPrefix . '/') !== 0 || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
            return '';
        }

        $abs = FCPATH . ltrim($path, '/');
        return is_file($abs) ? $path : '';
    }
}
