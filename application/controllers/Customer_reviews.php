<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Public, token-only endpoint reached from the QR on a receipt. */
class Customer_reviews extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pos_customer_review_model');
    }

    public function index($token = '')
    {
        $this->show_page((string)$token);
    }

    public function submit($token = '')
    {
        if (strtoupper((string)$this->input->method()) !== 'POST') {
            redirect('review/' . rawurlencode((string)$token));
            return;
        }
        $result = $this->Pos_customer_review_model->submit(
            (string)$token,
            (int)$this->input->post('rating', true),
            (string)$this->input->post('review_text', false),
            (string)$this->input->ip_address(),
            (string)$this->input->user_agent()
        );
        $this->show_page((string)$token, $result);
    }

    /** Public QR for a permanent outlet area, such as a table tent or exit. */
    public function station($code = '')
    {
        $this->show_station_page((string)$code);
    }

    public function station_submit($code = '')
    {
        if (strtoupper((string)$this->input->method()) !== 'POST') {
            redirect('review/station/' . rawurlencode((string)$code));
            return;
        }
        $result = $this->Pos_customer_review_model->submit_station_review(
            (string)$code,
            [
                'customer_name' => $this->input->post('customer_name', false),
                'mobile_phone' => $this->input->post('mobile_phone', false),
                'rating' => $this->input->post('rating', true),
                'review_text' => $this->input->post('review_text', false),
                'join_member' => $this->input->post('join_member', true),
            ],
            (string)$this->input->ip_address(),
            (string)$this->input->user_agent()
        );
        $this->show_station_page((string)$code, $result);
    }

    private function show_page(string $token, ?array $result = null): void
    {
        $review = $this->Pos_customer_review_model->find_by_token($token);
        $this->load->view('pos/customer_review_form', [
            'review' => $review,
            'token' => $token,
            'result' => $result,
        ]);
    }

    private function show_station_page(string $code, ?array $result = null): void
    {
        $station = $this->Pos_customer_review_model->find_station_by_code($code);
        $this->load->view('pos/customer_review_station_form', [
            'station' => $station,
            'station_code' => $code,
            'result' => $result,
        ]);
    }
}
