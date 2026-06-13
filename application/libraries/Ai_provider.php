<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ai_provider — FUENTE UNICA de la definicion de cada proveedor de IA de vision.
 *
 * Antes, "lo que sabe el sistema sobre una IA" estaba repartido en ~6 lugares
 * (dispatch, una funcion call_* por proveedor, extraccion de respuesta, validacion
 * de key, lista de soportados, labels de la UI). Agregar una IA implicaba tocar
 * todos esos lugares y era facil desincronizarlos.
 *
 * Ahora cada proveedor se define en UNA entrada de registry(). Para AGREGAR una IA
 * nueva alcanza con agregar una entrada con estos campos:
 *   - label         : string  — nombre visible en la UI (Settings y debug)
 *   - api_key_cfg   : string  — nombre del item en config.php que tiene la API key
 *   - model_cfg     : string  — nombre del item en config.php que tiene el modelo
 *   - model_default : string  — modelo a usar si el item de config no esta seteado
 *   - endpoint      : callable(string $key, string $model): string  — URL final del POST
 *   - headers       : callable(string $key): string[]               — headers HTTP del POST
 *   - payload       : callable(string $prompt, string $img_b64, string $model): array — body JSON
 *   - response_path : (string|int)[] — ruta de claves para extraer el texto de la respuesta
 *
 * El dispatch, la validacion y la extraccion son genericos y leen de aca, asi que
 * NO hay que tocar TradeReader, Debug ni Settings al sumar un proveedor.
 */
class Ai_provider
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    private function registry()
    {
        return [
            'gemini' => [
                'label'         => 'Gemini 2.5 Flash',
                'api_key_cfg'   => 'gemini_api_key',
                'model_cfg'     => 'gemini_model',
                'model_default' => 'gemini-2.5-flash',
                // Gemini lleva la API key en la URL (no en headers).
                'endpoint'      => function ($key, $model) {
                    return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);
                },
                'headers'       => function ($key) {
                    return ["Content-Type: application/json"];
                },
                // Imagen como inline_data base64 (sin prefijo data:).
                'payload'       => function ($prompt, $img, $model) {
                    return [
                        "contents" => [[
                            "parts" => [
                                ["text" => $prompt],
                                ["inline_data" => ["mime_type" => "image/png", "data" => $img]]
                            ]
                        ]],
                        "generationConfig" => ["response_mime_type" => "application/json"]
                    ];
                },
                'response_path' => ['candidates', 0, 'content', 'parts', 0, 'text'],
            ],
            'openai' => [
                'label'         => 'OpenAI GPT-4o',
                'api_key_cfg'   => 'openai_api_key',
                'model_cfg'     => 'openai_model',
                'model_default' => 'gpt-4o',
                'endpoint'      => function ($key, $model) {
                    return "https://api.openai.com/v1/chat/completions";
                },
                'headers'       => function ($key) {
                    return ["Authorization: Bearer {$key}", "Content-Type: application/json"];
                },
                // OpenAI espera la imagen con el prefijo data:image.
                'payload'       => function ($prompt, $img, $model) {
                    return [
                        "model" => $model,
                        "messages" => [[
                            "role" => "user",
                            "content" => [
                                ["type" => "text", "text" => $prompt],
                                ["type" => "image_url", "image_url" => ["url" => "data:image/png;base64,{$img}"]]
                            ]
                        ]]
                    ];
                },
                'response_path' => ['choices', 0, 'message', 'content'],
            ],
            'claude' => [
                'label'         => 'Claude Sonnet',
                'api_key_cfg'   => 'claude_api_key',
                'model_cfg'     => 'claude_model',
                'model_default' => 'claude-sonnet-4-6',
                'endpoint'      => function ($key, $model) {
                    return "https://api.anthropic.com/v1/messages";
                },
                'headers'       => function ($key) {
                    return ["x-api-key: {$key}", "anthropic-version: 2023-06-01", "Content-Type: application/json"];
                },
                // Claude espera la imagen SIN el prefijo data:image.
                'payload'       => function ($prompt, $img, $model) {
                    return [
                        "model" => $model,
                        "max_tokens" => 4096,
                        "messages" => [[
                            "role" => "user",
                            "content" => [
                                ["type" => "text", "text" => $prompt],
                                [
                                    "type" => "image",
                                    "source" => ["type" => "base64", "media_type" => "image/png", "data" => $img]
                                ]
                            ]
                        ]]
                    ];
                },
                'response_path' => ['content', 0, 'text'],
            ],
        ];
    }

    /** Nombres de los proveedores soportados, en orden de UI. */
    public function names()
    {
        return array_keys($this->registry());
    }

    /** Mapa [nombre => label] para los <select> de la UI. */
    public function labels()
    {
        $out = [];
        foreach ($this->registry() as $name => $def) {
            $out[$name] = $def['label'];
        }
        return $out;
    }

    /** True si el proveedor existe en el registry. */
    public function has($name)
    {
        return isset($this->registry()[$name]);
    }

    /** API key configurada (config.php) para el proveedor; '' si no existe o no esta seteada. */
    public function api_key($name)
    {
        $reg = $this->registry();
        if (!isset($reg[$name])) {
            return '';
        }
        return (string) $this->CI->config->item($reg[$name]['api_key_cfg']);
    }

    /**
     * Arma la request HTTP de un proveedor: ['url' => ..., 'headers' => [...], 'payload' => [...]].
     * Devuelve ['error' => mensaje] si el proveedor no existe o falta la API key.
     */
    public function build_request($name, $prompt, $image_base64)
    {
        $reg = $this->registry();
        if (!isset($reg[$name])) {
            return ['error' => "Proveedor de IA desconocido: {$name}"];
        }

        $def = $reg[$name];
        $key = (string) $this->CI->config->item($def['api_key_cfg']);
        if ($key === '') {
            return ['error' => "API key no configurada para {$name} ({$def['api_key_cfg']})"];
        }
        $model = $this->CI->config->item($def['model_cfg']) ?: $def['model_default'];

        return [
            'url'     => $def['endpoint']($key, $model),
            'headers' => $def['headers']($key),
            'payload' => $def['payload']($prompt, $image_base64, $model),
        ];
    }

    /**
     * Extrae el texto de la respuesta cruda siguiendo response_path del proveedor.
     * Devuelve null si el proveedor no existe o la ruta no llega a un string no vacio.
     */
    public function extract_text($name, $response)
    {
        $reg = $this->registry();
        if (!isset($reg[$name])) {
            return null;
        }
        $node = $response;
        foreach ($reg[$name]['response_path'] as $key) {
            if (!is_array($node) || !isset($node[$key])) {
                return null;
            }
            $node = $node[$key];
        }
        return is_string($node) ? ($node ?: null) : null;
    }
}
