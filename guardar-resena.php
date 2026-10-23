<?php
require 'guardia.php';
require_once 'catalogo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cartelera.php');
    exit;
}
verificarCsrf();

$peliculas = require 'datos.php';
$id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';

if (!isset($peliculas[$id])) {
    header('Location: cartelera.php');
    exit;
}

$volver     = 'pelicula.php?id=' . urlencode($id);
$estrellas  = (int)($_POST['estrellas'] ?? 0);
$comentario = is_string($_POST['comentario'] ?? null) ? trim($_POST['comentario']) : '';

if ($estrellas < 1 || $estrellas > 5) {
    header("Location: $volver&msg=error_estrellas#resenas");
    exit;
}
if (mb_strlen($comentario) > 500) {
    header("Location: $volver&msg=error_largo#resenas");
    exit;
}

$usuario = $_SESSION['usuario'];

// Cada usuario tiene una sola valoración por película: si ya existía, se reemplaza
modificarJson(__DIR__ . '/resenas.json', function (array $lista) use ($id, $usuario, $estrellas, $comentario) {
    $lista = array_values(array_filter($lista, fn($r) => !($r['pelicula_id'] === $id && $r['usuario'] === $usuario)));
    $lista[] = [
        'pelicula_id' => $id,
        'usuario'     => $usuario,
        'estrellas'   => $estrellas,
        'comentario'  => $comentario,
        'fecha'       => date('Y-m-d H:i:s'),
    ];
    return $lista;
});

header("Location: $volver&msg=guardada#resenas");
exit;
