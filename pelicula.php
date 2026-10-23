<?php
require 'guardia.php';
require_once 'catalogo.php';

$peliculas = require 'datos.php';
$id   = is_string($_GET['id'] ?? null) ? $_GET['id'] : array_key_first($peliculas);
$peli = $peliculas[$id] ?? null;

if ($peli === null) {
    echo "Esa película no existe.";
    exit;
}

// ── Tráiler (se vuelve a validar el ID antes de incrustarlo) ──
$trailerId = !empty($peli['trailer']) ? youtubeId($peli['trailer']) : null;

// ── Valoraciones de esta película ──
$resenas = array_values(array_filter(leerResenas(), fn($r) => $r['pelicula_id'] === $id));
usort($resenas, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));   // las más nuevas primero

$cant = count($resenas);
$prom = $cant ? round(array_sum(array_column($resenas, 'estrellas')) / $cant, 1) : 0;

$mia = null;
foreach ($resenas as $r) {
    if ($r['usuario'] === $_SESSION['usuario']) {
        $mia = $r;
    }
}

$mensajes = [
    'guardada'        => ['exito',   '✅ ¡Gracias! Tu valoración se guardó.'],
    'eliminada'       => ['exito',   '🗑️ Valoración eliminada.'],
    'error_estrellas' => ['errores', 'Elegí de 1 a 5 estrellas.'],
    'error_largo'     => ['errores', 'El comentario puede tener hasta 500 caracteres.'],
];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($peli['titulo']) ?> · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>

<header>
    <?php include 'includes/logo.php'; ?>
    <h1><span class="emoji">🎞️</span> Ficha</h1>
    <nav class="acciones">
        <a href="cartelera.php">← Volver a la cartelera</a>
    </nav>
</header>

<main class="portal">
    <section class="destacada">
        <?php if (!empty($peli['imagen'])): ?>
            <img src="<?= htmlspecialchars($peli['imagen']) ?>" alt="<?= htmlspecialchars($peli['titulo']) ?>">
        <?php else: ?>
            <div class="sin-imagen">🎞️</div>
        <?php endif; ?>

        <div class="destacada-info">
            <h2><?= htmlspecialchars($peli['titulo']) ?></h2>
            <p class="destacada-meta">
                Año: <?= (int)$peli['anio'] ?> — <?= htmlspecialchars($peli['genero']) ?>
            </p>
            <?php $fn = funcionDe($peli); ?>
            <p class="destacada-meta">
                🎞️ <?= htmlspecialchars($fn['sala']['nombre'] . ' · ' . $fn['sala']['tipo']) ?> · <?= plata($fn['sala']['precio']) ?> por entrada<br>
                🕒 <?= htmlspecialchars(implode(' · ', $fn['horarios'])) ?> hs<br>
                📅 <?= htmlspecialchars(diasTexto($fn['dias'])) ?>
            </p>
            <p class="destacada-meta">
                <?php if ($cant): ?>
                    <span class="estrellas-fijas"><?= estrellasTexto((int)round($prom)) ?></span>
                    <strong><?= number_format($prom, 1, ',', '') ?></strong> (<?= $cant ?> valoración<?= $cant === 1 ? '' : 'es' ?>)
                <?php else: ?>
                    Todavía sin valoraciones
                <?php endif; ?>
            </p>
            <div class="hero-botones destacada-botones">
                <a href="comprar.php?id=<?= urlencode($id) ?>" class="btn">🎟️ Comprar entradas</a>
                <?php if ($trailerId): ?>
                    <a href="#trailer" class="btn btn-sec">▶ Ver tráiler</a>
                <?php endif; ?>
                <?php if (esAdmin()): ?>
                    <a href="editar.php?id=<?= urlencode($id) ?>" class="btn btn-sec">✏️ Editar</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($trailerId): ?>
    <!-- ── Tráiler ── -->
    <section class="trailer" id="trailer">
        <h2>🎬 Tráiler</h2>
        <div class="video">
            <iframe src="https://www.youtube-nocookie.com/embed/<?= $trailerId ?>"
                    title="Tráiler de <?= htmlspecialchars($peli['titulo']) ?>"
                    loading="lazy"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen></iframe>
        </div>
    </section>
    <?php endif; ?>

    <!-- ── Valoraciones y comentarios ── -->
    <section class="resenas" id="resenas">
        <h2>⭐ Valoraciones y comentarios</h2>

        <?php if ($msg): ?>
            <p class="<?= $msg[0] ?> aviso-ancho"><?= htmlspecialchars($msg[1]) ?></p>
        <?php endif; ?>

        <form method="POST" action="guardar-resena.php" class="plano resena-form">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

            <p class="resena-titulo"><?= $mia ? 'Tu valoración (podés cambiarla)' : 'Dejá tu valoración' ?></p>

            <div class="estrellas">
                <?php for ($v = 5; $v >= 1; $v--): ?>
                    <input type="radio" name="estrellas" id="e<?= $v ?>" value="<?= $v ?>" required
                           <?= $mia && (int)$mia['estrellas'] === $v ? 'checked' : '' ?>>
                    <label for="e<?= $v ?>" class="estrella" title="<?= $v ?> estrella<?= $v === 1 ? '' : 's' ?>">★</label>
                <?php endfor; ?>
            </div>

            <textarea name="comentario" rows="3" maxlength="500"
                      placeholder="Contanos qué te pareció (opcional)"><?= htmlspecialchars($mia['comentario'] ?? '') ?></textarea>

            <button type="submit">Guardar valoración</button>
        </form>

        <?php if (!$resenas): ?>
            <p class="sin-resenas">Todavía nadie valoró esta película. ¡Sé el primero!</p>
        <?php else: ?>
            <div class="lista-resenas">
                <?php foreach ($resenas as $r): ?>
                    <article class="resena">
                        <div class="resena-cabecera">
                            <strong><?= htmlspecialchars($r['usuario']) ?></strong>
                            <span class="estrellas-fijas"><?= estrellasTexto((int)$r['estrellas']) ?></span>
                            <small><?= date('d/m/Y', strtotime($r['fecha'])) ?></small>

                            <?php if ($r['usuario'] === $_SESSION['usuario'] || esAdmin()): ?>
                                <form method="POST" action="eliminar-resena.php" class="plano"
                                      onsubmit="return confirm('¿Eliminar esta valoración?')">
                                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                    <input type="hidden" name="autor" value="<?= htmlspecialchars($r['usuario']) ?>">
                                    <button type="submit" class="btn-chico" title="Eliminar">🗑️</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php if ($r['comentario'] !== ''): ?>
                            <p><?= nl2br(htmlspecialchars($r['comentario'])) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
