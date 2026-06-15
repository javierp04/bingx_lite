<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lee los archivos de journal/live/state que escribe EA_Signals.mq5 y los
 * convierte en arrays PHP. Sin stats ni HTML: solo archivos -> arrays.
 */
class Journal_reader {

    private $basePath;

    public function __construct($params = array()) {
        $path = isset($params['path']) ? $params['path']
              : (defined('JOURNALS_PATH') ? JOURNALS_PATH : '');
        $this->basePath = rtrim($path, "/\\") . DIRECTORY_SEPARATOR;
    }

    public function base_path() { return $this->basePath; }

    public function is_readable_dir() {
        return is_dir($this->basePath) && is_readable($this->basePath);
    }

    /**
     * Parsea bxlite_<kind>_<uid>_<symbol>.<ext>.
     * Devuelve ['kind'=>, 'user_id'=>int, 'symbol'=>] o null.
     */
    public function parse_filename($filename) {
        if (!preg_match('/^bxlite_(journal|live|state)_(\d+)_(.+)\.(csv|json)$/', $filename, $m)) {
            return null;
        }
        return array('kind' => $m[1], 'user_id' => (int)$m[2], 'symbol' => $m[3]);
    }

    /**
     * Parsea un CSV: 1ra línea header, cada fila siguiente -> assoc por nombre.
     * Saltea filas cuyo nº de campos != header. Castea numéricos.
     */
    public function parse_csv($filepath) {
        $rows = array();
        $raw = @file_get_contents($filepath);
        if ($raw === false || trim($raw) === '') return $rows;
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        if (count($lines) < 2) return $rows;
        $header = str_getcsv($lines[0]);
        $ncol = count($header);
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') continue;
            $fields = str_getcsv($lines[$i]);
            if (count($fields) !== $ncol) continue;
            $row = array();
            foreach ($header as $c => $name) {
                $row[$name] = $this->cast($fields[$c]);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function cast($v) {
        if (is_numeric($v)) {
            return ($v == (int)$v && strpos($v, '.') === false) ? (int)$v : (float)$v;
        }
        return $v;
    }

    /** Metadata de todos los archivos reconocidos. */
    private function all_files() {
        $out = array();
        if (!$this->is_readable_dir()) return $out;
        foreach (scandir($this->basePath) as $f) {
            $info = $this->parse_filename($f);
            if ($info !== null) {
                $info['file'] = $this->basePath . $f;
                $out[] = $info;
            }
        }
        return $out;
    }

    /** Símbolos presentes (union de journal/live/state), ordenados. */
    public function list_symbols() {
        $symbols = array();
        foreach ($this->all_files() as $info) $symbols[$info['symbol']] = true;
        $out = array_keys($symbols);
        sort($out);
        return $out;
    }

    /** Filas de todos los bxlite_journal_*_<symbol>.csv, con user_id por fila. */
    public function read_journal($symbol) {
        $rows = array();
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'journal' || $info['symbol'] !== $symbol) continue;
            foreach ($this->parse_csv($info['file']) as $row) {
                $row['user_id'] = $info['user_id'];
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** Fila viva del live csv, o null. */
    public function read_live($symbol) {
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'live' || $info['symbol'] !== $symbol) continue;
            $rows = $this->parse_csv($info['file']);
            if (count($rows) > 0) {
                $rows[0]['user_id'] = $info['user_id'];
                return $rows[0];
            }
        }
        return null;
    }

    /** State json decodificado, o null si no existe / no parseable. */
    public function read_state($symbol) {
        foreach ($this->all_files() as $info) {
            if ($info['kind'] !== 'state' || $info['symbol'] !== $symbol) continue;
            $raw = @file_get_contents($info['file']);
            if ($raw === false) return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }
}
