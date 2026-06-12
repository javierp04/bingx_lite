<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Settings genéricos (key/value) en system_settings.
 * Resiliente: si la tabla aún no existe (migración no aplicada), get() devuelve el default.
 */
class Setting_model extends CI_Model
{
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
}
