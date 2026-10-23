<?php
require 'guardia.php';

$peliculas = require 'datos.php';
$admin = esAdmin();

// ── Datos para la página ──
$total   = count($peliculas);
$generos = array_unique(array_column($peliculas, 'genero'));
$ultimoAnio = $total ? max(array_column($peliculas, 'anio')) : '-';

// Película del día: cambia cada día, pero es la misma durante todo el día
$destacadaId = null;
if ($total) {
    $ids = array_keys($peliculas);
    $destacadaId = $ids[(int)date('z') % $total];
    $destacada = $peliculas[$destacadaId];
}

// Últimas 4 agregadas (las más nuevas primero)
$ultimas = array_slice(array_reverse($peliculas, true), 0, 4, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="portal">

    <!-- ── Hero ── -->
    <section class="hero">
        <p class="hero-etiqueta"><?= $admin ? '👑 Panel de administrador' : '🎬 Tu cine en casa' ?></p>
        <h1>Hola, <?= htmlspecialchars($_SESSION['usuario']) ?> 🍿</h1>
        <p class="hero-texto">
            <?php if ($admin): ?>
                Como administrador podés agregar, editar y eliminar películas de la cartelera.
            <?php else: ?>
                Bienvenido al sistema. Mirá la cartelera, elegí tu película y comprá tus entradas.
            <?php endif; ?>
        </p>
        <div class="hero-botones">
            <a href="cartelera.php" class="btn">🎟️ Ver cartelera</a>
            <a href="mis-entradas.php" class="btn btn-sec">🎫 Mis entradas</a>
            <?php if ($admin): ?>
                <a href="agregar.php" class="btn btn-sec">➕ Agregar película</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Estadísticas ── -->
    <section class="stats">
        <div class="stat">
            <strong><?= $total ?></strong>
            <span>Películas en cartelera</span>
        </div>
        <div class="stat">
            <strong><?= count($generos) ?></strong>
            <span>Géneros</span>
        </div>
        <div class="stat">
            <strong><?= htmlspecialchars((string)$ultimoAnio) ?></strong>
            <span>Estreno más reciente</span>
        </div>
    </section>

    <?php if ($destacadaId !== null): ?>
    <!-- ── Película del día ── -->
    <section class="destacada">
        <?php if (!empty($destacada['imagen'])): ?>
            <img src="<?= htmlspecialchars($destacada['imagen']) ?>"
                 alt="<?= htmlspecialchars($destacada['titulo']) ?>">
        <?php else: ?>
            <div class="sin-imagen">🎞️</div>
        <?php endif; ?>

        <div class="destacada-info">
            <p class="hero-etiqueta">⭐ Película del día</p>
            <h2><?= htmlspecialchars($destacada['titulo']) ?></h2>
            <p class="destacada-meta">
                <?= (int)$destacada['anio'] ?> · <?= htmlspecialchars($destacada['genero']) ?>
            </p>
            <div class="hero-botones destacada-botones">
                <a href="comprar.php?id=<?= urlencode($destacadaId) ?>" class="btn">🎟️ Comprar entradas</a>
                <a href="pelicula.php?id=<?= urlencode($destacadaId) ?>" class="btn btn-sec">Ver ficha</a>
            </div>
        </div>
    </section>

    <!-- ── Últimas agregadas ── -->
    <section class="ultimas">
        <h2>Últimas agregadas</h2>
        <div class="cartelera">
            <?php foreach ($ultimas as $id => $peli): ?>
                <article class="pelicula">
                    <?php if (!empty($peli['imagen'])): ?>
                        <img src="<?= htmlspecialchars($peli['imagen']) ?>"
                             alt="<?= htmlspecialchars($peli['titulo']) ?>">
                    <?php else: ?>
                        <div class="sin-imagen">🎞️</div>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($peli['titulo']) ?></h3>
                    <p><?= (int)$peli['anio'] ?></p>
                    <div class="acciones-card">
                        <a href="comprar.php?id=<?= urlencode($id) ?>">🎟️ Comprar entradas</a>
                        <a href="pelicula.php?id=<?= urlencode($id) ?>" class="sec">Ver ficha</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
