<?php
// Logo del cine: va en el menú y en el encabezado de cada página.
// Para cambiar el nombre, editá el texto de <strong> y <small>.
$destinoLogo = isset($_SESSION['usuario']) ? 'bienvenido.php' : 'index.php';
?>
<a href="<?= $destinoLogo ?>" class="logo" aria-label="Cinema, ir al inicio">
    <img src="assets/logo-icono.svg" alt="" class="logo-icono" width="38" height="38">
    <span class="logo-texto">
        <strong>CINEMA</strong>
        <small>Tu cine en casa</small>
    </span>
</a>
