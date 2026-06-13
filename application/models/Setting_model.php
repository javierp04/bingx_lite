<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Settings genéricos (key/value) en system_settings.
 * Resiliente: si la tabla aún no existe (migración no aplicada), get() devuelve el default.
 */
class Setting_model extends CI_Model
{
    // True si la tabla existe (migración aplicada). La UI la usa para no "guardar" en falso.
    public function is_ready()
    {
        return $this->db->table_exists('system_settings');
    }

    public function get($key, $default = null)
    {
        if (!$this->db->table_exists('system_settings')) {
            return $default;
        }
        $row = $this->db->get_where('system_settings', ['setting_key' => $key])->row();
        return ($row && $row->setting_value !== null && $row->setting_value !== '')
            ? $row->setting_value
            : $default;
    }

    public function set($key, $value)
    {
        if (!$this->db->table_exists('system_settings')) {
            return false;
        }
        $exists = $this->db->get_where('system_settings', ['setting_key' => $key])->row();
        if ($exists) {
            $this->db->where('setting_key', $key);
            return $this->db->update('system_settings', [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        return $this->db->insert('system_settings', [
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function all()
    {
        if (!$this->db->table_exists('system_settings')) {
            return [];
        }
        $rows = $this->db->get('system_settings')->result();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->setting_key] = $r->setting_value;
        }
        return $out;
    }

    // Resuelve un setting con la MISMA precedencia que usa el webhook real:
    // system_settings (DB) -> config.php -> default. Asi validacion y ejecucion coinciden.
    public function resolve($key, $default = null)
    {
        $val = $this->get($key, null);
        if ($val !== null && $val !== '') {
            return $val;
        }
        $cfg = $this->config->item($key);
        return ($cfg !== false && $cfg !== null && $cfg !== '') ? $cfg : $default;
    }

    // Modo de analisis IA: 'single' o 'dual'.
    public function get_ai_mode($default = 'single')
    {
        return $this->resolve('ai_mode', $default);
    }

    // Par de proveedores del consenso dual: [A, B]. Dinamico segun lo seleccionado en Settings.
    public function get_provider_pair()
    {
        return [
            $this->resolve('ai_provider_a', 'gemini'),
            $this->resolve('ai_provider_b', 'openai'),
        ];
    }
}
