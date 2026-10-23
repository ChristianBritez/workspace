<?php
$logueado = isset($_SESSION['usuario']);
$esAdmin  = ($_SESSION['rol'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>
<nav>
  <?php include __DIR__ . '/logo.php'; ?>
  <a href="<?= $logueado ? 'bienvenido.php' : 'index.php' ?>">Inicio</a>
  <a href="cartelera.php">Cartelera</a>
  <?php if ($logueado): ?>
    <a href="mis-entradas.php">Mis entradas</a>
  <?php endif; ?>

  <div class="dropdown">
    <a href="#">Ayuda ▾</a>
    <div class="dropdown-menu">
      <a href="info.php?seccion=ayuda">Ayuda</a>
      <a href="info.php?seccion=contacto">Contacto</a>
      <a href="info.php?seccion=nosotros">Nosotros</a>
    </div>
  </div>

  <?php if ($logueado): ?>
    <a href="logout.php">Salir (<?= htmlspecialchars($_SESSION['usuario']) ?>)</a>
    <?php if ($esAdmin): ?><span class="rol-badge">ADMIN</span><?php endif; ?>
  <?php else: ?>
    <a href="registro.php">Registrarse</a>
    <a href="index.php">Iniciar sesión</a>
  <?php endif; ?>
</nav>
</body>
</html>
