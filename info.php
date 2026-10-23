<?php
require 'guardia.php';
$seccion = $_GET['seccion'] ?? 'ayuda';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>
<?php include 'includes/header.php'; ?>

<?php if ($seccion == 'ayuda'): ?>
  <h1>Ayuda</h1>
  <p>Esto es un sistema de ejemplo. Podés crear tu cuenta desde Registrarse. Para probar: admin / 1234 (administrador) o juan / 5678.</p>
<?php elseif ($seccion == 'contacto'): ?>
  <h1>Contacto</h1>
  <p>Escribinos a contacto@ejemplo.com</p>
<?php elseif ($seccion == 'nosotros'): ?>
  <h1>Nosotros</h1>
  <p>Somos un equipo de 7° año aprendiendo PHP.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
</body>
</html>
