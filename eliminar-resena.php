<?php
require 'guardia.php';
require_once 'catalogo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cartelera.php');
    exit;
}
verificarCsrf();

$id    = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
$autor = is_string($_POST['autor'] ?? null) ? $_POST['autor'] : '';

// Puede borrarla su autor o un administrador (moderación)
if ($autor === $_SESSION['usuario'] || esAdmin()) {
    modificarJson(__DIR__ . '/resenas.json', function (array $lista) use ($id, $autor) {
        return array_values(array_filter($lista, fn($r) => !($r['pelicula_id'] === $id && $r['usuario'] === $autor)));
    });
}

header('Location: pelicula.php?id=' . urlencode($id) . '&msg=eliminada#resenas');
exit;
