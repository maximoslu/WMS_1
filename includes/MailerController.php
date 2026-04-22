<?php
/**
 * WMS_1 - Controlador de Correo via Google Apps Script Webhook (API V3 GET)
 * Token: WMS_SECURE_CLOUD_2026
 *
 * Estrategia de envío (GET):
 *  1. file_get_contents (follow_location nativo)
 *  2. cURL (fallback)
 *
 * Logging: toda comunicación con Google se registra en debug_mail.log
 */
class MailerController {

    // NUEVA URL V3
    private $scriptUrl = "https://script.google.com/macros/s/AKfycbwUNkpcFwoLGWojGhaxVGD1MwvagJSjl0-f1Q-RREMQRaYCrHUlCPwfF1EQQB5T7vIE/exec";
    private $token     = "WMS_SECURE_CLOUD_2026";

    /** Ruta del archivo de log — siempre en la raíz del proyecto (un nivel sobre /includes). */
    private $logFile;

    // ── Propiedades de diagnóstico públicas ──────────────────────────────────
    public $lastResponse  = null;
    public $lastHttpCode  = 0;
    public $lastMethod    = 'none';
    public $lastUrl       = '';

    public function __construct() {
        // __DIR__ es la ruta ABSOLUTA de /includes → dirname() sube a la raíz
        $this->logFile  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug_mail.log';
        $this->lastUrl  = $this->scriptUrl;
    }

    // =========================================================================
    //  API PÚBLICA
    // =========================================================================

    public function enviarCorreo($destinatario, $asunto, $mensajeHtml) {
        // En V3, pasamos todo como Query String
        $params = [
            "email"   => $destinatario,
            "asunto"  => $asunto,
            "mensaje" => $mensajeHtml,
            "token"   => $this->token
        ];

        // Construir la Query String y la URL completa
        $queryString = http_build_query($params);
        $fullUrl = $this->scriptUrl . '?' . $queryString;

        $this->log("=== INICIO ENVÍO (API V3 GET) ===");
        $this->log("Destino : {$destinatario}");
        $this->log("Asunto  : {$asunto}");
        $this->log("Endpoint: {$this->scriptUrl}");
        // Logeamos la URL sin el body completo del HTML para no saturar
        $this->log("URL con parámetros (truncado): " . substr($fullUrl, 0, 180) . "...");

        if (!str_ends_with(rtrim($this->scriptUrl, '/'), '/exec')) {
            $this->log("[AVISO] La URL NO termina en '/exec'. Esto puede causar redirecciones 301/302.");
        }

        // --- MÉTODO 1: file_get_contents (recomendado para firewalls) ---
        if (ini_get('allow_url_fopen')) {
            $this->log("--- Método 1: file_get_contents (GET) ---");
            $this->lastMethod = 'fgc';
            $resultado = $this->enviarConFgc($fullUrl);
            if ($resultado !== null) {
                $this->log("=== FIN ENVÍO (método 1) — resultado: " . ($resultado ? 'OK' : 'FAIL') . " ===\n");
                return $resultado;
            }
            $this->log("file_get_contents falló. Pasando a método 2 (cURL).");
        } else {
            $this->log("[AVISO] allow_url_fopen está deshabilitado. Saltando método 1.");
        }

        // --- MÉTODO 2: cURL (fallback) ---
        if (function_exists('curl_init')) {
            $this->log("--- Método 2: cURL (GET) ---");
            $this->lastMethod = 'curl';
            $resultado = $this->enviarConCurl($fullUrl);
            if ($resultado !== null) {
                $this->log("=== FIN ENVÍO (método 2) — resultado: " . ($resultado ? 'OK' : 'FAIL') . " ===\n");
                return $resultado;
            }
        } else {
            $this->log("[AVISO] cURL no está disponible.");
        }

        // Ambos métodos fallaron
        $this->lastMethod = 'none';
        $this->log("=== FIN ENVÍO — AMBOS MÉTODOS FALLARON ===\n");
        error_log("[Mailer] Sin método de envío disponible.");
        return false;
    }

    // =========================================================================
    //  MÉTODOS PRIVADOS DE TRANSPORTE
    // =========================================================================

    private function enviarConFgc($url) {
        $contextOptions = [
            'http' => [
                'method'          => 'GET', // Método explícito GET
                'timeout'         => 15,
                'ignore_errors'   => true,  // leer body aunque status sea 4xx/5xx
                'follow_location' => 1,     // Google sigue el GET sin problemas nativamente
                'max_redirects'   => 5,
            ],
            'ssl'  => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ]
        ];
        
        $context = stream_context_create($contextOptions);

        $response = @file_get_contents($url, false, $context);

        $headersRaw = isset($http_response_header) ? $http_response_header : [];
        $this->log("Headers HTTP recibidos: " . implode(" | ", $headersRaw));

        if ($response === false) {
            $lastError = error_get_last();
            $this->log("[ERROR fgc] file_get_contents devolvió false. " . ($lastError['message'] ?? ''));
            $this->lastResponse = '(file_get_contents devolvió false — sin respuesta del servidor)';
            return null;
        }

        // Extraer código de estado HTTP (puede ser el último tras las redirecciones nativas)
        $httpCode            = $this->extraerCodigoHttp(array_reverse($headersRaw)); // Coger el código del último redirect si hubo
        if($httpCode == 0) $httpCode = $this->extraerCodigoHttp($headersRaw); // Fallback
        
        $this->lastHttpCode  = $httpCode;
        $this->lastResponse  = $response;
        $this->lastUrl       = $url;
        $this->log("HTTP Status : {$httpCode}");
        $this->log("Body recibido: " . substr($response, 0, 500));

        $ok = (trim($response) === "OK");
        if (!$ok) {
            $this->log("[fgc] Respuesta inesperada (HTTP {$httpCode}): " . $response);
            error_log("[Mailer/fgc] Resp inesperada (HTTP {$httpCode}): " . $response);
        }
        return $ok;
    }

    private function enviarConCurl($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS,      5);
        
        // Forzar GET
        curl_setopt($ch, CURLOPT_HTTPGET,        true);
        
        curl_setopt($ch, CURLOPT_TIMEOUT,        15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER,         false);

        $response    = curl_exec($ch);
        $curlErr     = curl_error($ch);
        $curlErrNo   = curl_errno($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl    = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        // Guardar para diagnóstico público
        $this->lastHttpCode = $httpCode;
        $this->lastUrl      = $finalUrl;
        $this->log("HTTP Status   : {$httpCode}");
        $this->log("URL efectiva  : {$finalUrl}");
        $this->log("Body recibido : " . substr((string)$response, 0, 500));

        if ($curlErr) {
            $this->lastResponse = "(cURL error #{$curlErrNo}: {$curlErr})";
            $this->log("[ERROR cURL #{$curlErrNo}] {$curlErr}");
            error_log("[Mailer/cURL] Error #{$curlErrNo}: {$curlErr}");
            return null;
        }

        if ($response === false) {
            $this->lastResponse = '(curl_exec devolvió false sin mensaje de error)';
            $this->log("[ERROR cURL] curl_exec devolvió false sin mensaje de error.");
            return null;
        }

        $this->lastResponse = (string)$response;
        $ok = (trim($response) === "OK");
        if (!$ok) {
            $this->log("[cURL] Respuesta inesperada (HTTP {$httpCode}): " . $response);
            error_log("[Mailer/cURL] Resp inesperada (HTTP {$httpCode}): " . $response);
        }
        return $ok;
    }

    // =========================================================================
    //  UTILIDADES PRIVADAS
    // =========================================================================

    /** Escribe una línea en debug_mail.log con marca de tiempo. */
    private function log($mensaje) {
        $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
        // error_suppress para evitar que un fallo de escritura rompa la ejecución
        @file_put_contents($this->logFile, $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Extrae el código de estado HTTP.
     */
    private function extraerCodigoHttp(array $headers) {
        foreach($headers as $header) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $header, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }
}