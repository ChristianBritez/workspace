<?php
// Códigos de descuento (se escriben en mayúsculas).
//   tipo:    'porcentaje' (valor = %) o 'monto' (valor = pesos fijos)
//   vence:   fecha límite opcional, formato AAAA-MM-DD
//   un_uso:  true = cada usuario puede usarlo una sola vez
return [
    'ESTUDIANTE' => ['tipo' => 'porcentaje', 'valor' => 20,   'descripcion' => '20% de descuento aplicado'],
    'BIENVENIDO' => ['tipo' => 'porcentaje', 'valor' => 15,   'descripcion' => '15% de bienvenida (un solo uso)', 'un_uso' => true],
    'PROMO2000'  => ['tipo' => 'monto',      'valor' => 2000, 'descripcion' => '$2.000 de descuento aplicado'],
];
