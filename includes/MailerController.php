<?php
/**
 * WMS_1 - Controlador de Correo via SMTP (PHPMailer)
 * 
 * Historial:
 *  - V3 GET (Google Apps Script): Obsoleto por bloqueos de red.
 *  - Plan B: PHPMailer + SMTP DonDominio.
 */

// Carga manual de PHPMailer (sin Composer)
require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class MailerController {

    // Carga de configuración centralizada
    private function cargarConfig() {
        if (!defined('MAIL_HOST')) {
            $path = dirname(__DIR__) . '/config/db.php';
            if (file_exists($path)) require_once $path;
        }
    }

    /** Ruta del archivo de log */
    private $logFile;

    // ── Propiedades de diagnóstico públicas ──────────────────────────────────
    public $lastResponse  = null;
    public $lastHttpCode  = 0;
    public $lastMethod    = 'none';
    public $lastUrl       = '';

    public function __construct() {
        $this->logFile  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug_mail.log';
    }

    // =========================================================================
    //  API PÚBLICA
    // =========================================================================

    /**
     * Envía un correo electrónico usando SMTP (PHPMailer).
     * Mantiene la misma firma para no romper Login/Registro.
     */
    public function enviarCorreo($destinatario, $asunto, $mensajeHtml) {
        $this->cargarConfig();
        
        $this->log("=== INICIO ENVÍO (SMTP PHPMailer) ===");
        $this->log("Destino : {$destinatario}");
        $this->log("Asunto  : {$asunto}");

        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.dondominio.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('MAIL_USER') ? MAIL_USER : '';
            $mail->Password   = defined('MAIL_PASS') ? MAIL_PASS : '';
            $mail->SMTPSecure = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'ssl'; 
            $mail->Port       = defined('MAIL_PORT') ? MAIL_PORT : 465;
            $mail->CharSet    = 'UTF-8';
            $mail->Hostname   = 'maximosl.com';

            // Desactivar Debug para producción
            $mail->SMTPDebug  = 0;
            $mail->Debugoutput = function($str, $level) {
                $this->log("DEBUG: $str");
            };

            // BYPASS de Certificado SSL (DonDominio compatibilidad)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            // Destinatarios
            $mail->setFrom($mail->Username, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'WMS Sistema');
            $mail->addAddress($destinatario);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $mensajeHtml;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>'], "\n", $mensajeHtml));

            $this->lastMethod = 'smtp';
            
            // Envío
            $mail->send();
            
            $this->lastResponse = "OK";
            $this->lastHttpCode = 200;
            $this->log("=== FIN ENVÍO — RESULTADO: OK ===");
            return true;

        } catch (Exception $e) {
            $errorMsg = "Error de PHPMailer: " . $mail->ErrorInfo;
            $this->lastResponse = $errorMsg;
            $this->lastHttpCode = 500;
            $this->log("[ERROR SMTP] " . $errorMsg);
            return false;
        } catch (\Exception $e) {
            $this->log("[ERROR GENERAL] " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    //  UTILIDADES PRIVADAS
    // =========================================================================

    /** Escribe una línea en debug_mail.log con marca de tiempo. */
    private function log($mensaje) {
        $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
        @file_put_contents($this->logFile, $linea, FILE_APPEND | LOCK_EX);
    }
}