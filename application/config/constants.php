<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Custom constants for BingX API
define('BINGX_SPOT_API_URL_PRODUCTION', 'https://open-api.bingx.com');
define('BINGX_FUTURES_API_URL_PRODUCTION', 'https://open-api.bingx.com');
define('BINGX_SPOT_API_URL_SANDBOX', 'https://open-api.bingx.com'); // Same as production for spot
define('BINGX_FUTURES_API_URL_SANDBOX', 'https://open-api-vst.bingx.com'); // Sandbox URL for futures

// File upload paths
define('UPLOAD_PATH', './uploads/');

/*
| -------------------------------------------------------------------------
| Journal Viewer
| -------------------------------------------------------------------------
| Carpeta donde EA_Signals.mq5 escribe bxlite_journal/live/state.
| Dev (XAMPP): la carpeta EA/journals del repo.
| Prod (Debian + Wine): apuntar a
|   /home/<user>/.mt5/drive_c/Program Files/MetaTrader 5/MQL5/Files/
| (www-data necesita read+traverse; ver spec, sección Permissions).
*/
// Prod (Debian): symlink /var/www/journals -> carpeta Wine MQL5/Files (ver comandos abajo).
// Dev (XAMPP): cae a EA/journals del repo.
defined('JOURNALS_PATH') OR define('JOURNALS_PATH',
    is_dir('/var/www/journals') ? '/var/www/journals/' : FCPATH . 'EA/journals/');