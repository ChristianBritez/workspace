<?php
require 'guardia.php';
require 'catalogo.php';

$peliculas = require 'datos.php';
$id = $_GET['id'] ?? $_POST['id'] ?? '';

if (!isset($peliculas[$id])) {
    http_response_code(404);
    echo 'Esa película no existe.';
    exit;
}
$peli = $peliculas[$id];

// ── Configuración propia de esta película ──
const MAX_ENTRADAS = 10;
const MAX_COMBO    = 10;   // máximo de unidades por combo

$cfg      = funcionDe($peli);
$sala     = $cfg['sala'];
$precio   = $sala['precio'];
$columnas = $sala['columnas'];
$filas    = array_map('chr', range(ord('A'), ord('A') + $sala['filas'] - 1));
$combos   = combos();
$archivo  = __DIR__ . '/entradas.json';

// ── Funciones disponibles (según los días y horarios de la película) ──
$funciones = fechasDisponibles($peli);

if (!$funciones) {
    ?>
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sin funciones</title><link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg"></head>
    <body><main class="portal"><section class="hero">
        <h1>😴 Sin funciones</h1>
        <p class="hero-texto"><?= htmlspecialchars($peli['titulo']) ?> no tiene funciones disponibles en los próximos días.</p>
        <div class="hero-botones"><a href="cartelera.php" class="btn">Volver a la cartelera</a></div>
    </section></main></body></html>
    <?php
    exit;
}

// ── Función elegida (si es inválida, usa la primera disponible) ──
$fecha = $_REQUEST['fecha'] ?? '';
if (!is_string($fecha) || !isset($funciones[$fecha])) {
    $fecha = array_key_first($funciones);
}
$hora = $_REQUEST['hora'] ?? '';
if (!is_string($hora) || !in_array($hora, $funciones[$fecha]['horas'], true)) {
    $hora = $funciones[$fecha]['horas'][0];
}

function asientosOcupados(array $entradas, string $id, string $fecha, string $hora): array
{
    $ocupados = [];
    foreach ($entradas as $e) {
        if (entradaCancelada($e)) {
            continue;   // las entradas canceladas liberan sus asientos
        }
        if ($e['pelicula_id'] === $id && $e['fecha'] === $fecha && $e['hora'] === $hora) {
            $ocupados = array_merge($ocupados, $e['asientos']);
        }
    }
    return $ocupados;
}

// Lo que el usuario había completado (para no perderlo si hay un error)
$prev         = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];
$prevAsientos = array_values(array_filter((array)($prev['asientos'] ?? []), 'is_string'));
$prevCombos   = is_array($prev['combo'] ?? null) ? $prev['combo'] : [];
$prevCodigo   = is_string($prev['descuento'] ?? null) ? trim($prev['descuento']) : '';

$errores = [];

// ── Compra ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $validos = [];
    foreach ($filas as $f) {
        for ($c = 1; $c <= $columnas; $c++) {
            $validos[] = $f . $c;
        }
    }
    $elegidos = array_values(array_intersect(array_unique($prevAsientos), $validos));

    if (!$elegidos) {
        $errores[] = 'Elegí al menos un asiento.';
    } elseif (count($elegidos) > MAX_ENTRADAS) {
        $errores[] = 'Podés comprar hasta ' . MAX_ENTRADAS . ' entradas por vez.';
    } else {
        // Se bloquea el archivo para que dos personas no compren el mismo asiento a la vez
        $fp = fopen($archivo, 'c+');
        flock($fp, LOCK_EX);
        $entradas = json_decode(stream_get_contents($fp), true);
        $entradas = is_array($entradas) ? $entradas : [];

        $repetidos = array_intersect($elegidos, asientosOcupados($entradas, $id, $fecha, $hora));

        if ($repetidos) {
            $errores[] = 'Estos asientos ya fueron vendidos: ' . implode(', ', $repetidos) . '. Elegí otros.';
        } else {
            // Los precios se calculan siempre en el servidor, nunca con lo que manda el navegador
            $subEntradas = $precio * count($elegidos);

            $lineasCombos = [];
            $subCombos    = 0;
            foreach ($combos as $cid => $c) {
                $cant = max(0, min(MAX_COMBO, (int)($prevCombos[$cid] ?? 0)));
                if ($cant > 0) {
                    $lineasCombos[] = ['nombre' => $c['nombre'], 'cantidad' => $cant, 'precio' => $c['precio']];
                    $subCombos += $cant * $c['precio'];
                }
            }

            $subtotal  = $subEntradas + $subCombos;
            $descuento = 0;
            $codUsado  = '';

            if ($prevCodigo !== '') {
                $r = validarCodigo($prevCodigo, $subtotal, $_SESSION['usuario'], $entradas);
                if ($r['ok']) {
                    $descuento = $r['descuento'];
                    $codUsado  = $r['codigo'];
                } else {
                    $errores[] = $r['mensaje'];
                }
            }

            if (!$errores) {
                sort($elegidos, SORT_NATURAL);
                $codigo = strtoupper(bin2hex(random_bytes(4)));

                $entradas[] = [
                    'codigo'           => $codigo,
                    'usuario'          => $_SESSION['usuario'],
                    'pelicula_id'      => $id,
                    'titulo'           => $peli['titulo'],
                    'sala'             => $sala['nombre'] . ' · ' . $sala['tipo'],
                    'fecha'            => $fecha,
                    'hora'             => $hora,
                    'asientos'         => $elegidos,
                    'precio_entrada'   => $precio,
                    'combos'           => $lineasCombos,
                    'subtotal'         => $subtotal,
                    'codigo_descuento' => $codUsado,
                    'descuento'        => $descuento,
                    'total'            => $subtotal - $descuento,
                    'comprado'         => date('Y-m-d H:i:s'),
                ];

                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($entradas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);

                header('Location: mis-entradas.php?ok=' . $codigo);
                exit;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// ── Asientos vendidos de esta función ──
$ocupados = asientosOcupados(leerEntradas(), $id, $fecha, $hora);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar entradas · <?= htmlspecialchars($peli['titulo']) ?></title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<header>
    <?php include 'includes/logo.php'; ?>
    <h1><span class="emoji">🎟️</span> Comprar entradas</h1>
    <nav class="acciones">
        <a href="cartelera.php">← Volver a la cartelera</a>
    </nav>
</header>

<main class="portal compra">

    <section class="destacada">
        <?php if (!empty($peli['imagen'])): ?>
            <img src="<?= htmlspecialchars($peli['imagen']) ?>" alt="<?= htmlspecialchars($peli['titulo']) ?>">
        <?php else: ?>
            <div class="sin-imagen">🎞️</div>
        <?php endif; ?>
        <div class="destacada-info">
            <h2><?= htmlspecialchars($peli['titulo']) ?></h2>
            <p class="destacada-meta">
                <?= (int)$peli['anio'] ?> · <?= htmlspecialchars($peli['genero']) ?><br>
                🎞️ <?= htmlspecialchars($sala['nombre'] . ' · ' . $sala['tipo']) ?><br>
                Entrada: <?= plata($precio) ?>
            </p>

            <!-- Elegir función -->
            <form method="GET" class="plano funcion-form">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <label>Día
                    <select name="fecha" onchange="this.form.submit()">
                        <?php foreach ($funciones as $f => $info): ?>
                            <option value="<?= $f ?>" <?= $f === $fecha ? 'selected' : '' ?>>
                                <?= htmlspecialchars($info['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Horario
                    <select name="hora" onchange="this.form.submit()">
                        <?php foreach ($funciones[$fecha]['horas'] as $h): ?>
                            <option value="<?= $h ?>" <?= $h === $hora ? 'selected' : '' ?>><?= $h ?> hs</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript><button type="submit">Ver asientos</button></noscript>
            </form>
        </div>
    </section>

    <?php if ($errores): ?>
        <ul class="errores aviso">
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" class="plano" id="form-asientos">
        <input type="hidden" name="csrf"  value="<?= csrfToken() ?>">
        <input type="hidden" name="id"    value="<?= htmlspecialchars($id) ?>">
        <input type="hidden" name="fecha" value="<?= $fecha ?>">
        <input type="hidden" name="hora"  value="<?= $hora ?>">

        <!-- Asientos -->
        <div class="pantalla">PANTALLA</div>

        <div class="sala">
            <?php foreach ($filas as $f): ?>
                <div class="fila">
                    <span class="fila-letra"><?= $f ?></span>
                    <?php for ($c = 1; $c <= $columnas; $c++):
                        $codigoAsiento = $f . $c;
                        $vendido = in_array($codigoAsiento, $ocupados, true);
                        $marcado = !$vendido && in_array($codigoAsiento, $prevAsientos, true);
                    ?>
                        <label class="asiento <?= $c === intdiv($columnas, 2) ? 'pasillo' : '' ?>">
                            <input type="checkbox" name="asientos[]" value="<?= $codigoAsiento ?>"
                                   <?= $vendido ? 'disabled' : '' ?> <?= $marcado ? 'checked' : '' ?>>
                            <span title="<?= $codigoAsiento ?>"><?= $c ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="leyenda">
            <span><i class="libre"></i> Libre</span>
            <span><i class="elegido"></i> Elegido</span>
            <span><i class="vendido"></i> Vendido</span>
        </div>

        <!-- Combos -->
        <section class="bloque">
            <h3>🍿 Sumá tu combo <small>(opcional)</small></h3>
            <div class="combos-grid">
                <?php foreach ($combos as $cid => $c): ?>
                    <div class="combo">
                        <div class="combo-emoji"><?= $c['emoji'] ?></div>
                        <div class="combo-info">
                            <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                            <small><?= htmlspecialchars($c['desc']) ?></small>
                            <span><?= plata($c['precio']) ?></span>
                        </div>
                        <input type="number" class="combo-cant" name="combo[<?= htmlspecialchars($cid) ?>]"
                               min="0" max="<?= MAX_COMBO ?>" step="1"
                               value="<?= max(0, min(MAX_COMBO, (int)($prevCombos[$cid] ?? 0))) ?>"
                               data-precio="<?= $c['precio'] ?>"
                               aria-label="Cantidad de <?= htmlspecialchars($c['nombre']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Código de descuento -->
        <section class="bloque">
            <h3>🏷️ Código de descuento <small>(opcional)</small></h3>
            <div class="descuento-box">
                <input type="text" id="codigo" name="descuento" maxlength="30" autocomplete="off"
                       placeholder="Ej: ESTUDIANTE" value="<?= htmlspecialchars($prevCodigo) ?>">
                <button type="button" id="btn-aplicar">Aplicar</button>
            </div>
            <p class="msg-codigo" id="msg-codigo"></p>
        </section>

        <!-- Resumen -->
        <div class="resumen">
            <div class="resumen-detalle">
                <p>Entradas (<span id="cantidad">0</span> × <?= plata($precio) ?>): <span id="sub-entradas">$0</span></p>
                <p id="fila-combos" hidden>Combos: <span id="sub-combos">$0</span></p>
                <p id="fila-dto" hidden class="dto">Descuento: <span id="dto">−$0</span></p>
                <p class="total">Total: <strong id="total">$0</strong></p>
            </div>
            <button type="submit" id="btn-comprar" disabled>Confirmar compra</button>
        </div>
        <p class="nota">Compra simulada: no se realiza ningún cobro real.</p>
    </form>

</main>

<script>
    const PRECIO = <?= $precio ?>;
    const MAX = <?= MAX_ENTRADAS ?>;
    const MAX_COMBO = <?= MAX_COMBO ?>;
    const fmt = n => '$' + n.toLocaleString('es-AR');

    const checks    = document.querySelectorAll('input[name="asientos[]"]');
    const combos    = document.querySelectorAll('.combo-cant');
    const inCodigo  = document.getElementById('codigo');
    const btnAplicar = document.getElementById('btn-aplicar');
    const msgCodigo = document.getElementById('msg-codigo');
    let dto = null;   // {tipo, valor} cuando hay un código válido aplicado

    function actualizar() {
        const n    = document.querySelectorAll('input[name="asientos[]"]:checked').length;
        const subE = n * PRECIO;
        let subC = 0;
        combos.forEach(i => subC += (parseInt(i.value) || 0) * parseInt(i.dataset.precio));
        const sub = subE + subC;

        let d = 0;
        if (dto) {
            d = dto.tipo === 'porcentaje' ? Math.round(sub * dto.valor / 100) : dto.valor;
            d = Math.min(d, sub);
        }

        document.getElementById('cantidad').textContent    = n;
        document.getElementById('sub-entradas').textContent = fmt(subE);
        document.getElementById('sub-combos').textContent  = fmt(subC);
        document.getElementById('dto').textContent         = '−' + fmt(d);
        document.getElementById('total').textContent       = fmt(sub - d);
        document.getElementById('fila-combos').hidden = subC === 0;
        document.getElementById('fila-dto').hidden    = !dto;
        document.getElementById('btn-comprar').disabled = n === 0;
    }

    checks.forEach(c => c.addEventListener('change', () => {
        if (document.querySelectorAll('input[name="asientos[]"]:checked').length > MAX) {
            c.checked = false;
            alert('Podés comprar hasta ' + MAX + ' entradas por vez.');
        }
        actualizar();
    }));

    combos.forEach(i => i.addEventListener('input', () => {
        i.value = Math.max(0, Math.min(MAX_COMBO, parseInt(i.value) || 0));
        actualizar();
    }));

    function mensaje(texto, ok) {
        msgCodigo.textContent = texto;
        msgCodigo.className = 'msg-codigo ' + (ok ? 'ok' : 'mal');
    }

    inCodigo.addEventListener('input', () => { dto = null; mensaje('', true); actualizar(); });
    inCodigo.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); btnAplicar.click(); } });

    btnAplicar.addEventListener('click', async () => {
        const codigo = inCodigo.value.trim();
        if (!codigo) { mensaje('Ingresá un código.', false); return; }
        try {
            const r = await fetch('validar-codigo.php?codigo=' + encodeURIComponent(codigo));
            const j = await r.json();
            if (j.ok) { dto = { tipo: j.tipo, valor: j.valor }; mensaje('✅ ' + j.mensaje, true); }
            else      { dto = null; mensaje('❌ ' + j.mensaje, false); }
        } catch (e) {
            dto = null;
            mensaje('No se pudo validar el código. Probá de nuevo.', false);
        }
        actualizar();
    });

    actualizar();
    if (inCodigo.value.trim()) btnAplicar.click();   // si volvió con un código escrito, lo vuelve a validar
</script>

</body>
</html>
