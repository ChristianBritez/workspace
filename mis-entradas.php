<?php
require 'guardia.php';
require_once 'catalogo.php';

$todas = leerEntradas();
$mias  = array_values(array_filter($todas, fn($e) => $e['usuario'] === $_SESSION['usuario']));

// Funciones más próximas primero
usort($mias, fn($a, $b) => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));

$ok = is_string($_GET['ok'] ?? null) ? $_GET['ok'] : '';

$mensajes = [
    'cancelada'    => ['exito',   '✅ Entrada cancelada. Los asientos quedaron libres y se reintegra el importe (simulado).'],
    'nocancelable' => ['errores', 'Esa entrada ya no se puede cancelar.'],
];
$msg = $mensajes[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis entradas · Cine</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>

<header>
    <?php include 'includes/logo.php'; ?>
    <h1><span class="emoji">🎟️</span> Mis entradas</h1>
    <nav class="acciones">
        <a href="bienvenido.php">Inicio</a>
        <a href="cartelera.php" class="btn-agregar">Ver cartelera</a>
        <a href="logout.php">Salir (<?= htmlspecialchars($_SESSION['usuario']) ?>)</a>
    </nav>
</header>

<main class="portal">

    <?php if ($ok !== ''): ?>
        <p class="exito">✅ ¡Compra realizada! Tu código es <strong><?= htmlspecialchars($ok) ?></strong>.</p>
    <?php endif; ?>

    <?php if ($msg): ?>
        <p class="<?= $msg[0] ?> aviso-ancho"><?= htmlspecialchars($msg[1]) ?></p>
    <?php endif; ?>

    <?php if (!$mias): ?>
        <section class="hero">
            <p class="hero-texto">Todavía no compraste entradas.</p>
            <div class="hero-botones">
                <a href="cartelera.php" class="btn">🎬 Ver cartelera</a>
            </div>
        </section>
    <?php else: ?>
        <p class="nota-cancel">Podés cancelar una entrada hasta <?= CANCELAR_HASTA_MIN ?> minutos antes de la función.</p>

        <div class="entradas">
            <?php foreach ($mias as $e):
                $cancelada = entradaCancelada($e);
                $pasada    = !$cancelada && ($e['fecha'] . ' ' . $e['hora']) < date('Y-m-d H:i');
            ?>
                <article class="entrada <?= $pasada ? 'pasada' : '' ?> <?= $cancelada ? 'cancelada' : '' ?> <?= $e['codigo'] === $ok ? 'nueva' : '' ?>">
                    <div class="entrada-info">
                        <h3><?= htmlspecialchars($e['titulo']) ?>
                            <?php if ($cancelada): ?><span class="etiqueta-cancelada">CANCELADA</span><?php endif; ?>
                        </h3>
                        <p>📅 <?= date('d/m/Y', strtotime($e['fecha'])) ?> · 🕒 <?= htmlspecialchars($e['hora']) ?> hs<?= !empty($e['sala']) ? ' · 🎞️ ' . htmlspecialchars($e['sala']) : '' ?></p>
                        <p>💺 Asientos: <?= htmlspecialchars(implode(', ', $e['asientos'])) ?></p>
                        <?php if (!empty($e['combos'])): ?>
                            <p>🍿 <?= htmlspecialchars(implode(', ', array_map(fn($c) => $c['cantidad'] . '× ' . $c['nombre'], $e['combos']))) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($e['descuento'])): ?>
                            <p>🏷️ Descuento <?= htmlspecialchars($e['codigo_descuento']) ?>: −$<?= number_format($e['descuento'], 0, ',', '.') ?></p>
                        <?php endif; ?>
                        <p>💵 <?= $cancelada ? 'Reintegro' : 'Total' ?>: $<?= number_format($e['total'], 0, ',', '.') ?></p>

                        <?php if ($pasada): ?>
                            <p><em>Función finalizada</em></p>
                            <?php if (!empty($e['pelicula_id'])): ?>
                                <p><a class="link-valorar" href="pelicula.php?id=<?= urlencode($e['pelicula_id']) ?>#resenas">⭐ Valorar esta película</a></p>
                            <?php endif; ?>
                        <?php elseif (puedeCancelar($e)): ?>
                            <form method="POST" action="cancelar-entrada.php" class="plano"
                                  onsubmit="return confirm('¿Cancelar esta entrada? Los asientos quedarán libres.')">
                                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                                <input type="hidden" name="codigo" value="<?= htmlspecialchars($e['codigo']) ?>">
                                <button type="submit" class="btn-cancelar">Cancelar entrada</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="entrada-codigo">
                        <span>CÓDIGO</span>
                        <strong><?= htmlspecialchars($e['codigo']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

</body>
</html>
