<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Base controller untuk semua halaman yang butuh login
 *
 * Semua controller kecuali Auth extend class ini.
 * Menangani: cek login, load izin ke session, helper cek izin per aksi.
 */
class MY_Controller extends CI_Controller
{
    private const SIDEBAR_CACHE_TTL_SECONDS = 300;
    private const SIDEBAR_CACHE_DIR = 'sidebar';

    /** Data user yang sedang login (dari session) */
    protected $current_user = [];

    /** Cache izin user: ['page_code' => ['view'=>1, 'create'=>0, ...]] */
    protected $user_perms = [];

    public function __construct()
    {
        parent::__construct();
        $this->_check_auth();
    }

    // ---------------------------------------------------------------
    // AUTH CHECK
    // ---------------------------------------------------------------

    private function _check_auth()
    {
        $user = $this->session->userdata('auth_user');

        if (empty($user)) {
            $this->session->set_flashdata('redirect_after_login', uri_string());
            redirect('login');
        }

        $this->current_user = $user;

        // Muat izin dari session (sudah di-cache saat login)
        $this->user_perms = $this->session->userdata('user_perms') ?? [];
    }

    // ---------------------------------------------------------------
    // PERMISSION HELPERS
    // ---------------------------------------------------------------

    /**
     * Apakah user superadmin? (bypass semua cek)
     */
    protected function is_superadmin(): bool
    {
        return !empty($this->current_user['is_superadmin']);
    }

    /**
     * Cek izin user untuk halaman + aksi tertentu.
     *
     * @param string $page_code  Contoh: 'auth.users.index'
     * @param string $action     Salah satu: view|create|edit|delete|export
     * @return bool
     */
    protected function can(string $page_code, string $action = 'view'): bool
    {
        if ($this->is_superadmin()) {
            return true;
        }

        return !empty($this->user_perms[$page_code]['can_' . $action]);
    }

    /**
     * Paksa cek izin — redirect ke 403 jika tidak punya akses.
     */
    protected function require_permission(string $page_code, string $action = 'view'): void
    {
        if (!$this->can($page_code, $action)) {
            show_error('Anda tidak memiliki izin untuk mengakses halaman ini.', 403, 'Akses Ditolak');
        }
    }

    // ---------------------------------------------------------------
    // VIEW LOADER HELPER
    // ---------------------------------------------------------------

    /**
     * Load view dengan layout (header + sidebar + content + footer).
     * Sidebar data (main tree, my tree, favorites) di-load otomatis di sini.
     *
     * @param string $view       Path view relatif dari application/views/
     * @param array  $data       Data yang dikirim ke view
     * @param bool   $return     Jika TRUE, kembalikan string bukan output langsung
     */
    protected function render(string $view, array $data = [], bool $return = false)
    {
        $data['current_user'] = $this->current_user;
        $data['user_perms']   = $this->user_perms;

        // Load sidebar data otomatis (kecuali sudah diset manual oleh controller)
        if (!isset($data['sidebar_main'])) {
            $sidebar = $this->load_sidebar_cached();
            $data['sidebar_main'] = $sidebar['sidebar_main'];
            $data['sidebar_my'] = $sidebar['sidebar_my'];
            $data['sidebar_favorites'] = $sidebar['sidebar_favorites'];
        }

        $data['content_view'] = $view;
        $data['content_data'] = $data;

        return $this->load->view('layout/main', $data, $return);
    }

    private function load_sidebar_cached(): array
    {
        $userId = (int)($this->current_user['id'] ?? 0);
        $isSuperadmin = $this->is_superadmin();
        $permSignature = md5(json_encode($this->user_perms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cacheFile = $this->get_sidebar_cache_file($userId, $isSuperadmin, $permSignature);
        $cachedSidebar = $this->read_sidebar_cache($cacheFile);
        if ($cachedSidebar !== null) {
            return $cachedSidebar;
        }

        $this->load->model('Menu_model');
        $sidebarData = [
            'sidebar_main' => $this->Menu_model->get_sidebar_tree($this->user_perms, $isSuperadmin, 'MAIN'),
            'sidebar_my' => $this->Menu_model->get_sidebar_tree($this->user_perms, $isSuperadmin, 'MY'),
            'sidebar_favorites' => $this->Menu_model->get_favorites($userId),
        ];

        $this->write_sidebar_cache($cacheFile, $sidebarData);

        return $sidebarData;
    }

    private function get_sidebar_cache_file(int $userId, bool $isSuperadmin, string $permSignature): ?string
    {
        if ($userId <= 0 || $permSignature === '') {
            return null;
        }

        $cacheDir = APPPATH . 'cache/' . self::SIDEBAR_CACHE_DIR;
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
            return null;
        }

        $this->cleanup_sidebar_cache_dir($cacheDir);

        return $cacheDir
            . DIRECTORY_SEPARATOR
            . 'sidebar_'
            . $userId
            . '_'
            . ($isSuperadmin ? '1' : '0')
            . '_'
            . $permSignature
            . '.json';
    }

    private function read_sidebar_cache(?string $cacheFile): ?array
    {
        if ($cacheFile === null || !is_file($cacheFile)) {
            return null;
        }

        $modifiedAt = @filemtime($cacheFile);
        if (!$modifiedAt || $modifiedAt <= (time() - self::SIDEBAR_CACHE_TTL_SECONDS)) {
            @unlink($cacheFile);
            return null;
        }

        $payload = @file_get_contents($cacheFile);
        if ($payload === false || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (
            !is_array($decoded)
            || !isset($decoded['sidebar_main'], $decoded['sidebar_my'], $decoded['sidebar_favorites'])
        ) {
            return null;
        }

        return [
            'sidebar_main' => (array)$decoded['sidebar_main'],
            'sidebar_my' => (array)$decoded['sidebar_my'],
            'sidebar_favorites' => (array)$decoded['sidebar_favorites'],
        ];
    }

    private function write_sidebar_cache(?string $cacheFile, array $sidebarData): void
    {
        if ($cacheFile === null) {
            return;
        }

        $payload = json_encode($sidebarData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        @file_put_contents($cacheFile, $payload, LOCK_EX);
    }

    private function cleanup_sidebar_cache_dir(string $cacheDir): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        foreach (glob($cacheDir . DIRECTORY_SEPARATOR . 'sidebar_*.json') ?: [] as $cacheFile) {
            $modifiedAt = @filemtime($cacheFile);
            if ($modifiedAt && $modifiedAt <= (time() - self::SIDEBAR_CACHE_TTL_SECONDS)) {
                @unlink($cacheFile);
            }
        }
    }
}
