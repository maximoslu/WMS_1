<?php
session_start();
$error_message = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS - Iniciar Sesión</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="public/css/main.css">
</head>
<body>
    <div class="login-container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card login-card shadow-lg p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="public/assets/img/logo_empresa.png" alt="Logo Empresa" class="login-logo mb-3" onerror="this.src='https://via.placeholder.com/150?text=Logo+Empresa'">
                <h4 class="mb-1 fw-bold">Acceso WMS</h4>
                <p class="text-muted small">Por favor, ingrese sus credenciales</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
            <?php endif; ?>

            <form action="auth_process.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="ejemplo@empresa.com">
                    <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    <div class="invalid-feedback">La contraseña es requerida.</div>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-login py-2">Iniciar Sesión</button>
                </div>
                
                <div class="d-flex justify-content-between mt-4 text-sm auth-links">
                    <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#infoModal">Recuperar contraseña</a>
                    <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#infoModal">Crear nuevo usuario</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para funciones en desarrollo -->
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="infoModalLabel">Aviso Importante</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Esta función estará disponible pronto. Por favor, contacte con el administrador de sistemas por el momento.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>