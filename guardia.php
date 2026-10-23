<?php
require_once __DIR__ . '/roles.php';

if (!isset($_SESSION['usuario'])) {
    // Guarda a dónde quería ir para volver después de iniciar sesión
    $volver = urlencode(basename($_SERVER['REQUEST_URI']));
    header("Location: index.php?error=sesion&volver=$volver");
    exit;
}
