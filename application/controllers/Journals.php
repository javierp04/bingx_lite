<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Journals: analítica unificada de trades del EA (DB-backed).
 * Drill-down: overview (por símbolo) -> symbol (lista de trades) -> trade (detalle, estilo mock).
 * Fuente: user_telegram_signals + LEFT JOIN ea_trade_* (rico cuando el EA ya reportó).
 */
class Journals extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->session->userdata('logged_in')) redirect('auth');
        if ($this->session->userdata('role') !== 'admin') show_error('Acceso denegado', 403);
        $this->load->model('Telegram_signals_model');
        $this->load->library('journal_stats');
        $this->load->helper('journal_labels');
        $this->load->helper('trade_view');
    }

    public function index() {
        $data['title']    = 'Journals';
        $data['readable'] = true;
        $data['path']     = 'Base de datos (user_telegram_signals + ea_trade_*)';
        $data['symbols']  = array();
        $data['global']   = $this->empty_global();
        $data['chart']    = array('pnl_by_symbol' => array(), 'order_types' => array(),
                                  'exit_levels' => array(), 'cum' => array());

        $allRows = array();
        foreach ($this->Telegram_signals_model->journal_list_symbols() as $sym) {
            $rows = $this->Telegram_signals_model->journal_rows_for_symbol($sym);
            if (count($rows) === 0) continue;
            $k = $this->journal_stats->per_symbol($rows);
            $k['symbol'] = $sym;
            $data['symbols'][] = $k;
            $data['chart']['pnl_by_symbol'][$sym] = $k['pnl_total'];
            $allRows = array_merge($allRows, $rows);
        }
        $data['global'] = $this->aggregate_global($data['symbols']);
        // El pie de order type solo cuenta trades que colocaron orden: los rechazos pre-ejecución
        // (sin order_type) no tienen tipo y solo ensuciarían con una porción "—". Consistente con per_symbol.
        $otDist = $this->journal_stats->distribution($allRows, 'order_type');
        unset($otDist['']);
        $data['chart']['order_types'] = $this->relabel($otDist, 'journal_order_label');
        $data['chart']['exit_levels'] = $this->relabel($this->journal_stats->distribution($allRows, 'exit_level'), 'journal_exit_label');
        $data['chart']['cum']         = $this->journal_stats->cumulative_pnl($allRows);

        $this->load->view('templates/header', $data);
        $this->load->view('journals/overview', $data);
        $this->load->view('templates/footer');
    }

    public function symbol($sym = '') {
        $sym = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sym)); // whitelist anti path-traversal
        if ($sym === '') redirect('journals');

        $rows   = $this->Telegram_signals_model->journal_rows_for_symbol($sym);
        $trades = $this->Telegram_signals_model->journal_trades_for_symbol($sym);

        if (count($trades) === 0) {
            $this->session->set_flashdata('warning', "No hay trades para el símbolo $sym");
            redirect('journals');
        }

        $data['title']  = "Journal $sym";
        $data['symbol'] = $sym;
        $data['kpi']    = $this->journal_stats->per_symbol($rows);
        $data['trades'] = $trades;
        $data['chart']  = array(
            'cum'         => $this->journal_stats->cumulative_pnl($rows),
            'scatter'     => $this->scatter_data($rows),
            'exit_levels' => $this->relabel($this->journal_stats->distribution($rows, 'exit_level'), 'journal_exit_label'),
        );

        $this->load->view('templates/header', $data);
        $this->load->view('journals/detail', $data);
        $this->load->view('templates/footer');
    }

    public function trade($sym = '', $user_signal_id = 0) {
        $signal = $this->Telegram_signals_model->get_signal_detail_admin($user_signal_id);
        if (!$signal) {
            $this->session->set_flashdata('error', 'Trade no encontrado');
            redirect('journals');
        }

        $data['title']      = 'Trade #' . $user_signal_id;
        $data['sym']        = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sym)) ?: $signal->ticker_symbol;
        $data['signal']     = $signal;
        $data['snapshot']   = $this->Telegram_signals_model->get_trade_snapshot($user_signal_id);
        $data['correction'] = $this->Telegram_signals_model->get_trade_correction($user_signal_id);
        $data['events']     = $this->Telegram_signals_model->get_timeline_events($signal);
        $data['back_url']   = base_url('journals/symbol/' . $data['sym']);

        $this->load->view('templates/header', $data);
        $this->load->view('journals/trade_detail', $data);
        $this->load->view('templates/footer');
    }

    /** Remapea las claves de una distribución (código -> etiqueta legible vía helper). Suma si colisionan. */
    private function relabel($dist, $labeller) {
        $out = array();
        foreach ($dist as $k => $v) {
            $label = $labeller((string)$k);
            $out[$label] = (isset($out[$label]) ? $out[$label] : 0) + $v;
        }
        return $out;
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
