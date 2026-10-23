<?php
require 'guardia.php';
requerirAdmin();
require_once 'catalogo.php';

// Solo se elimina por POST (con token), nunca por un simple link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cartelera.php');
    exit;
}
verificarCsrf();

$archivo   = __DIR__ . '/peliculas.json';
$peliculas = require 'datos.php';
$id = $_POST['id'] ?? '';

if (isset($peliculas[$id])) {
    $imagen = $peliculas[$id]['imagen'] ?? '';
    unset($peliculas[$id]);

    // Borra la imagen si ninguna otra película la usa
    if ($imagen !== '' && str_starts_with($imagen, 'imagenes/')) {
        $otra = array_filter($peliculas, fn($p) => ($p['imagen'] ?? '') === $imagen);
        if (!$otra && file_exists(__DIR__ . '/' . $imagen)) {
            unlink(__DIR__ . '/' . $imagen);
        }
    }

    file_put_contents(
        $archivo,
        json_encode($peliculas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

modificarJson(__DIR__ . '/resenas.json', fn(array $l) => array_values(array_filter($l, fn($r) => ($r['pelicula_id'] ?? '') !== $id)));

header('Location: cartelera.php?msg=eliminada');
exit;
