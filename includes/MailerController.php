<?php
/**
 * WMS_1 - Controlador de Correo via Google Apps Script Webhook
 * Token: WMS_SECURE_CLOUD_2026
 * Estrategia dual: cURL primero, file_get_contents como fallback anti-firewall.
 */
class MailerController {
    private $scriptUrl = "https://script.google.com/macros/s/AKfycbz1viQ4CafvtnQO28X-2oDAXwcwopKG55HI01E56-K34K3llCi2X-ZF3cSF0jGHaSPE/exec";
    private $token     = "WMS_SECURE_CLOUD_2026";

    public function enviarCorreo($destinatario, $asunto, $mensajeHtml) {
        $payload = json_encode([
            "email"   => $destinatario,
            "asunto"  => $asunto,
            "mensaje" => $mensajeHtml,
            "token"   => $this->token
        ]);

        // --- MÉTODO 1: cURL (preferido) ---
        if (function_exists('curl_init')) {
            $ch = curl_init($this->scriptUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_POST,           true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT,        10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$curlErr && $response !== false) {
                $ok = (trim($response) === "OK");
                if (!$ok) error_log("[Mailer/cURL] Resp inesperada (HTTP {$httpCode}): " . $response);
                return $ok;
            }
            // cURL falló (firewall, timeout…) → intentar fallback
            error_log("[Mailer/cURL] Error, intentando fallback file_get_contents: " . $curlErr);
        }

        // --- MÉTODO 2: file_get_contents + stream_context (fallback anti-firewall) ---
        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($payload),
                    'content'       => $payload,
                    'timeout'       => 10,
                    'ignore_errors' => true,   // permite leer respuesta aunque el HTTP status sea error
                    'follow_location' => 1,    // seguir redirecciones de Google
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ]
            ]);

            $response = @file_get_contents($this->scriptUrl, false, $context);

            if ($response !== false) {
                $ok = (trim($response) === "OK");
                if (!$ok) error_log("[Mailer/fgc] Resp inesperada: " . $response);
                return $ok;
            }
            error_log("[Mailer/fgc] file_get_contents también falló.");
        }

        // Ambos métodos fallaron
        error_log("[Mailer] Sin método de envío disponible. Payload: " . $payload);
        return false;
    }
}