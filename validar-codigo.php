<?php
require 'guardia.php';
require 'catalogo.php';

header('Content-Type: application/json; charset=utf-8');

$r = validarCodigo((string)($_GET['codigo'] ?? ''), 0, $_SESSION['usuario']);
unset($r['descuento']);   // el monto lo calcula la página según lo que elija el cliente
echo json_encode($r, JSON_UNESCAPED_UNICODE);
