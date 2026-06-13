<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->session->userdata('logged_in')) {
            redirect('auth');
        }
        if ($this->session->userdata('role') != 'admin') {
            $this->session->set_flashdata('error', 'Access denied. Admin privileges required.');
            redirect('dashboard');
        }

        $this->load->model('Setting_model');
    }

    public function index()
    {
        $data['title'] = 'AI Settings';
        $data['providers'] = [
            'gemini' => 'Gemini 2.5 Flash',
            'openai' => 'OpenAI GPT-4o',
            'claude' => 'Claude'
        ];
        $data['ai_mode'] = $this->Setting_model->get_ai_mode('dual');
        list($data['ai_provider_a'], $data['ai_provider_b']) = $this->Setting_model->get_provider_pair();
        $data['settings_ready'] = $this->Setting_model->is_ready();

        $this->load->view('templates/header', $data);
        $this->load->view('settings/index', $data);
        $this->load->view('templates/footer');
    }

    public function save()
    {
        // Sin la tabla, no se puede persistir: avisar en vez de "guardar" en falso.
        if (!$this->Setting_model->is_ready()) {
            $this->session->set_flashdata('error', 'La tabla system_settings no existe. Aplicá la migración database/migrations/2026-06-13-ai-provider-gemini-and-settings.sql y volvé a intentar.');
            redirect('settings');
            return;
        }

        $mode = $this->input->post('ai_mode');
        $a    = $this->input->post('ai_provider_a');
        $b    = $this->input->post('ai_provider_b');
        $valid = $this->Setting_model->supported_providers();

        if (!in_array($mode, ['single', 'dual'], true) || !in_array($a, $valid, true) || !in_array($b, $valid, true)) {
            $this->session->set_flashdata('error', 'Valores inválidos.');
            redirect('settings');
            return;
        }
        if ($mode === 'dual' && $a === $b) {
            $this->session->set_flashdata('error', 'En modo Dual, los dos proveedores deben ser distintos.');
            redirect('settings');
            return;
        }

        $this->Setting_model->set('ai_mode', $mode);
        $this->Setting_model->set('ai_provider_a', $a);
        $this->Setting_model->set('ai_provider_b', $b);

        $this->session->set_flashdata('success', 'Configuración de IA guardada.');
        redirect('settings');
    }
}
