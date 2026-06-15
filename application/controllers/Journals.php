<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Journals extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->session->userdata('logged_in')) redirect('auth');
        if ($this->session->userdata('role') !== 'admin') show_error('Acceso denegado', 403);
        $this->load->library('journal_reader');
        $this->load->library('journal_stats');
    }

    public function index() {
        $data['title']    = 'Journals';
        $data['readable'] = $this->journal_reader->is_readable_dir();
        $data['path']     = $this->journal_reader->base_path();
        $data['symbols']  = array();
        $data['global']   = $this->empty_global();
        $data['chart']    = array('pnl_by_symbol' => array(), 'order_types' => array(),
                                  'exit_levels' => array(), 'cum' => array());

        if ($data['readable']) {
            $allRows = array();
            foreach ($this->journal_reader->list_symbols() as $sym) {
                $rows = $this->journal_reader->read_journal($sym);
                if (count($rows) === 0) continue;
                $k = $this->journal_stats->per_symbol($rows);
                $k['symbol'] = $sym;
                $data['symbols'][] = $k;
                $data['chart']['pnl_by_symbol'][$sym] = $k['pnl_total'];
                $allRows = array_merge($allRows, $rows);
            }
            $data['global'] = $this->aggregate_global($data['symbols']);
            $data['chart']['order_types'] = $this->journal_stats->distribution($allRows, 'order_type');
            $data['chart']['exit_levels'] = $this->journal_stats->distribution($allRows, 'exit_level');
            $data['chart']['cum']         = $this->journal_stats->cumulative_pnl($allRows);
        }

        $this->load->view('templates/header', $data);
        $this->load->view('journals/overview', $data);
        $this->load->view('templates/footer');
    }

    public function symbol($sym = '') {
        $sym = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sym)); // whitelist anti path-traversal
        if ($sym === '') redirect('journals');

        $rows  = $this->journal_reader->read_journal($sym);
        $state = $this->journal_reader->read_state($sym);
        $live  = $this->journal_reader->read_live($sym);

        if (count($rows) === 0 && $state === null && $live === null) {
            $this->session->set_flashdata('warning', "No hay datos para el símbolo $sym");
            redirect('journals');
        }

        $data['title']  = "Journal $sym";
        $data['symbol'] = $sym;
        $data['kpi']    = $this->journal_stats->per_symbol($rows);
        $data['rows']   = $rows;
        $data['state']  = $state;
        $data['live']   = $live;
        $data['chart']  = array(
            'cum'         => $this->journal_stats->cumulative_pnl($rows),
            'scatter'     => $this->scatter_data($rows),
            'exit_levels' => $this->journal_stats->distribution($rows, 'exit_level'),
        );

        $this->load->view('templates/header', $data);
        $this->load->view('journals/detail', $data);
        $this->load->view('templates/footer');
    }

    private function scatter_data($rows) {
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'x'    => isset($r['dist_entry']) ? (float)$r['dist_entry'] : 0,
                'y'    => isset($r['t1']) ? (float)$r['t1'] : 0,
                'type' => isset($r['order_type']) ? (string)$r['order_type'] : '',
                'pnl'  => isset($r['gross_pnl']) ? (float)$r['gross_pnl'] : 0,
            );
        }
        return $out;
    }

    private function empty_global() {
        return array('total'=>0,'operated'=>0,'cancelled'=>0,'wins'=>0,'losses'=>0,
                     'win_rate'=>null,'pnl_total'=>0.0,'cancel_rate'=>null);
    }

    private function aggregate_global($symbols) {
        $g = $this->empty_global();
        foreach ($symbols as $k) {
            $g['total']     += $k['total'];
            $g['operated']  += $k['operated'];
            $g['cancelled'] += $k['cancelled'];
            $g['wins']      += $k['wins'];
            $g['losses']    += $k['losses'];
            $g['pnl_total'] += $k['pnl_total'];
        }
        $g['pnl_total']   = round($g['pnl_total'], 2);
        $g['win_rate']    = $g['operated'] > 0 ? round($g['wins'] / $g['operated'] * 100, 1) : null;
        $g['cancel_rate'] = $g['total'] > 0 ? round($g['cancelled'] / $g['total'] * 100, 1) : null;
        return $g;
    }
}
