<?php
session_start();

// Si ya inició sesión, no tiene sentido mostrar el login
if (isset($_SESSION['usuario'])) {
    header('Location: bienvenido.php');
    exit;
}

$error  = $_GET['error']  ?? '';
$volver = $_GET['volver'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>
<?php include 'includes/header.php'; ?>

<h1 class="titulo-centrado">🎟️ Iniciar sesión</h1>
<p class="subtitulo-centrado">Entrá para comprar tus entradas y ver tus reservas.</p>

<?php if ($error === '1'): ?>
    <p class="errores aviso">Usuario o contraseña incorrectos.</p>
<?php elseif ($error === 'sesion'): ?>
    <p class="errores aviso">Tenés que iniciar sesión para continuar.</p>
<?php endif; ?>

<form action="procesar-login.php" method="POST">
  <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">
  <label>Usuario:
    <input type="text" name="usuario" autocomplete="username" required>
  </label>
  <label>Contraseña:
    <input type="password" name="clave" autocomplete="current-password" required>
  </label>
  <button type="submit">Entrar</button>
</form>

<p class="subtitulo-centrado">¿No tenés cuenta? <a href="registro.php">Registrate acá</a></p>

<?php include 'includes/footer.php'; ?>
</body>
</html>
