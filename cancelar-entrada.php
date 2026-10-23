<?php
require 'guardia.php';
require_once 'catalogo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis-entradas.php');
    exit;
}
verificarCsrf();

$codigo    = is_string($_POST['codigo'] ?? null) ? $_POST['codigo'] : '';
$cancelada = false;

modificarJson(__DIR__ . '/entradas.json', function (array $entradas) use ($codigo, &$cancelada) {
    foreach ($entradas as &$e) {
        // Solo se puede cancelar una entrada propia y a tiempo
        if ($e['codigo'] === $codigo && $e['usuario'] === $_SESSION['usuario'] && puedeCancelar($e)) {
            $e['estado']       = 'cancelada';
            $e['cancelada_en'] = date('Y-m-d H:i:s');
            $cancelada = true;
        }
    }
    unset($e);
    return $entradas;
});

header('Location: mis-entradas.php?msg=' . ($cancelada ? 'cancelada' : 'nocancelable'));
exit;
