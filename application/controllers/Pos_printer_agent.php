<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pos_printer_agent extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_print_model');
    }

    public function bootstrap()
    {
        if (!$this->verify_printer_agent_key()) {
            return;
        }

        $agentName = trim((string)$this->input->get('agent_name', true));
        if (!$this->Pos_print_model->agent_connection_ready()) {
            $this->bootstrap_error('Konfigurasi Koneksi Printer baru belum lengkap. Jalankan migration printer dan lengkapi Koneksi Printer terlebih dahulu.');
            return;
        }

        // The agent must consume the same connection records edited in the
        // Koneksi Printer page. Never fall back to legacy pos_printer here.
        $rows = $this->Pos_print_model->agent_devices($agentName);
        if (empty($rows)) {
            $this->bootstrap_error('Tidak ada Koneksi Printer aktif untuk agent ini. Periksa nama agent, MAC, port, dan status koneksinya.');
            return;
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'success',
                'message' => 'Printer bootstrap loaded.',
                'config_source' => 'pos_print_connection',
                'data' => $rows,
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function bootstrap_error(string $message): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $this->output
            ->set_status_header(422)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => 'error',
                'message' => $message,
                'config_source' => 'pos_print_connection',
                'data' => [],
            ], JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function verify_printer_agent_key(): bool
    {
        $expectedKey = trim((string)getenv('POS_PRINTER_BOOTSTRAP_KEY'));
        if ($expectedKey === '') {
            return true;
        }

        $providedKey = trim((string)$this->input->get_request_header('X-Printer-Key', true));
        if ($providedKey === '') {
            $providedKey = trim((string)$this->input->get('key', true));
        }

        if (!hash_equals($expectedKey, $providedKey)) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => 'error',
                    'message' => 'Printer agent key tidak valid.',
                ], JSON_INVALID_UTF8_SUBSTITUTE));
            return false;
        }

        return true;
    }
}
