<?php
require_once 'config.php';

// Destruir sesión y eliminar cookie
session_unset();
session_destroy();

// Invalidar la cookie de sesión en el navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al login con JavaScript para limpiar sessionStorage
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head>
<body>
<script>
    sessionStorage.removeItem('loader_visto');
    const motivo = new URLSearchParams(window.location.search).get('motivo');
    window.location.href = motivo === 'inactividad' ? 'login.php?sesion=expirada' : 'login.php';
</script>
</body>
</html>