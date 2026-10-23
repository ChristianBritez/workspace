<?php
session_start();
require_once 'catalogo.php';

$incluidos = require 'usuarios.php';   // cuentas base (admin, juan)

$usuario = is_string($_POST['usuario'] ?? null) ? trim($_POST['usuario']) : '';
$clave   = is_string($_POST['clave']   ?? null) ? $_POST['clave']        : '';
$volver  = is_string($_POST['volver']  ?? null) ? $_POST['volver']       : '';

// Solo se permiten destinos dentro del sitio (ej: "comprar.php?id=matrix")
if (!preg_match('/^[a-z0-9\-]+\.php(\?[a-zA-Z0-9=&%_\-:.]*)?$/', $volver)) {
    $volver = 'bienvenido.php';
}

$rol    = null;
$nombre = $usuario;

if (isset($incluidos[$usuario]) && hash_equals($incluidos[$usuario]['clave'], $clave)) {
    $rol = $incluidos[$usuario]['rol'];
} else {
    // Usuarios registrados desde la página (contraseña guardada con hash)
    $registrados = leerUsuarios();
    $clavebd     = strtolower($usuario);
    if (isset($registrados[$clavebd]) && password_verify($clave, $registrados[$clavebd]['hash'])) {
        $nombre = $clavebd;
        $rol    = $registrados[$clavebd]['rol'] ?? 'usuario';
    }
}

if ($rol !== null) {
    session_regenerate_id(true);
    $_SESSION['usuario'] = $nombre;
    $_SESSION['rol']     = $rol;
    header("Location: $volver");
    exit;
}

$extra = $volver !== 'bienvenido.php' ? '&volver=' . urlencode($volver) : '';
header("Location: index.php?error=1$extra");
exit;
