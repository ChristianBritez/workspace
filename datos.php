<?php
// Lee las películas desde peliculas.json
// (cartelera.php y pelicula.php siguen usando: $peliculas = require 'datos.php';)

$archivo = __DIR__ . '/peliculas.json';

if (!file_exists($archivo)) {
    return [];
}

$datos = json_decode(file_get_contents($archivo), true);

return is_array($datos) ? $datos : [];
