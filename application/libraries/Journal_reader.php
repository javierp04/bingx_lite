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
}
