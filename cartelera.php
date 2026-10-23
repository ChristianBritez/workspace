<?php
require_once 'roles.php';   // inicia la sesión
require 'catalogo.php';
$prom = promedios();

$peliculas = require 'datos.php';
$logueado  = isset($_SESSION['usuario']);
$admin     = esAdmin();

$mensajes = [
    'agregada'  => '✅ Película agregada.',
    'editada'   => '✅ Cambios guardados.',
    'eliminada' => '🗑️ Película eliminada.',
];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartelera</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>

<body>

    <header>
    <?php include 'includes/logo.php'; ?>
        <h1><span class="emoji">🎬</span> Cartelera</h1>
        <nav class="acciones">
            <a href="<?= $logueado ? 'bienvenido.php' : 'index.php' ?>">Inicio</a>
            <?php if ($logueado): ?>
                <a href="mis-entradas.php">🎟️ Mis entradas</a>
                <?php if ($admin): ?>
                    <a href="agregar.php" class="btn-agregar">➕ Agregar película</a>
                <?php endif; ?>
                <a href="logout.php">Salir (<?= htmlspecialchars($_SESSION['usuario']) ?>)</a>
                <?php if ($admin): ?><span class="rol-badge">ADMIN</span><?php endif; ?>
            <?php else: ?>
                <a href="registro.php">Registrarse</a>
                <a href="index.php" class="btn-agregar">🔑 Iniciar sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($msg): ?>
        <div class="portal"><p class="exito"><?= $msg ?></p></div>
    <?php endif; ?>

    <main class="cartelera">

        <?php foreach ($peliculas as $id => $peli): ?>

            <article class="pelicula">

                <?php if (!empty($peli['imagen'])): ?>
                    <img src="<?= htmlspecialchars($peli['imagen']) ?>"
                         alt="<?= htmlspecialchars($peli['titulo']) ?>">
                <?php else: ?>
                    <div class="sin-imagen">🎞️</div>
                <?php endif; ?>

                <h3><?= htmlspecialchars($peli['titulo']) ?></h3>

                <p><?= (int)$peli['anio'] ?></p>
                <?php $pr = $prom[$id] ?? null; ?>
                <p class="valoracion">
                    <?php if ($pr): ?>
                        <span class="estrellas-fijas">★</span> <?= number_format($pr['prom'], 1, ',', '') ?>
                        <small>(<?= $pr['cant'] ?>)</small>
                    <?php else: ?>
                        <small>Sin valoraciones</small>
                    <?php endif; ?>
                </p>

                <?php $fn = funcionDe($peli); ?>
                <p class="funcion-info">
                    🎞️ <?= htmlspecialchars($fn['sala']['nombre'] . ' · ' . $fn['sala']['tipo']) ?><br>
                    🕒 <?= htmlspecialchars(implode(' · ', $fn['horarios'])) ?><br>
                    📅 <?= htmlspecialchars(diasTexto($fn['dias'])) ?>
                </p>

                <div class="acciones-card">
                    <a href="comprar.php?id=<?= urlencode($id) ?>">🎟️ Comprar entradas</a>
                    <a href="pelicula.php?id=<?= urlencode($id) ?>" class="sec">Ver ficha</a>

                    <?php if ($admin): ?>
                        <div class="admin-acciones">
                            <a href="editar.php?id=<?= urlencode($id) ?>" class="sec">✏️ Editar</a>
                            <form method="POST" action="eliminar.php" class="plano"
                                  onsubmit="return confirm(<?= htmlspecialchars(json_encode('¿Eliminar «' . $peli['titulo'] . '»?'), ENT_QUOTES) ?>)">
                                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                <button type="submit" class="peligro">🗑️ Eliminar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

            </article>

        <?php endforeach; ?>

    </main>

</body>
</html>
