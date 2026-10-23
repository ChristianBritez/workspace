<?php
require_once 'roles.php';
require_once 'catalogo.php';

// Si ya inició sesión no hace falta registrarse
if (isset($_SESSION['usuario'])) {
    header('Location: bienvenido.php');
    exit;
}

$errores = [];
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $usuario = is_string($_POST['usuario'] ?? null) ? strtolower(trim($_POST['usuario'])) : '';
    $clave   = is_string($_POST['clave']   ?? null) ? $_POST['clave']   : '';
    $clave2  = is_string($_POST['clave2']  ?? null) ? $_POST['clave2']  : '';

    if (!preg_match('/^[a-z0-9._-]{3,20}$/', $usuario)) {
        $errores[] = 'El usuario debe tener de 3 a 20 caracteres: letras, números, punto, guion o guion bajo.';
    }
    if (strlen($clave) < 6 || strlen($clave) > 72) {
        $errores[] = 'La contraseña debe tener entre 6 y 72 caracteres.';
    }
    if ($clave !== $clave2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $base      = require 'usuarios.php';
        $incluidos = array_change_key_case($base, CASE_LOWER);
        $duplicado = isset($incluidos[$usuario]);

        if (!$duplicado) {
            modificarJson(__DIR__ . '/usuarios.json', function (array $lista) use ($usuario, $clave, &$duplicado) {
                if (isset($lista[$usuario])) {
                    $duplicado = true;
                    return $lista;
                }
                $lista[$usuario] = [
                    'hash'   => password_hash($clave, PASSWORD_DEFAULT),   // la contraseña nunca se guarda en texto
                    'rol'    => 'usuario',
                    'creado' => date('Y-m-d H:i:s'),
                ];
                return $lista;
            });
        }

        if ($duplicado) {
            $errores[] = 'Ese nombre de usuario ya está en uso. Probá con otro.';
        } else {
            // Cuenta creada: se inicia sesión directamente
            session_regenerate_id(true);
            $_SESSION['usuario'] = $usuario;
            $_SESSION['rol']     = 'usuario';
            header('Location: bienvenido.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>
<?php include 'includes/header.php'; ?>

<h1 class="titulo-centrado">✨ Crear cuenta</h1>
<p class="subtitulo-centrado">Registrate para comprar entradas, valorar películas y comentar.</p>

<?php if ($errores): ?>
    <ul class="errores aviso">
        <?php foreach ($errores as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="POST">
  <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

  <label>Usuario:
    <input type="text" name="usuario" value="<?= htmlspecialchars($usuario) ?>"
           autocomplete="username" maxlength="20" required>
  </label>
  <label>Contraseña:
    <input type="password" name="clave" autocomplete="new-password" minlength="6" required>
  </label>
  <label>Repetir contraseña:
    <input type="password" name="clave2" autocomplete="new-password" minlength="6" required>
  </label>
  <button type="submit">Crear cuenta</button>
</form>

<p class="subtitulo-centrado">¿Ya tenés cuenta? <a href="index.php">Iniciá sesión</a></p>

<?php include 'includes/footer.php'; ?>
</body>
</html>
