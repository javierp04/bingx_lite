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
}
