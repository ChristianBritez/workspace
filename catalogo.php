<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

const SLOTS        = ['13:00', '15:00', '16:30', '18:00', '19:30', '21:00', '22:30'];
const DIAS_NOMBRES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const ORDEN_DIAS   = [1, 2, 3, 4, 5, 6, 0];   // semana empezando en lunes
const DIAS_CORTOS  = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];

function salas(): array  { return require __DIR__ . '/salas.php'; }
function combos(): array { return require __DIR__ . '/combos.php'; }

function plata(int $n): string
{
    return '$' . number_format($n, 0, ',', '.');
}

// Sala, horarios y días de una película (con valores por defecto si faltan)
function funcionesFormulario(array $peli): array
{
    $salas = salas();
    $sala  = $peli['sala'] ?? 'sala1';
    if (!isset($salas[$sala])) {
        $sala = array_key_first($salas);
    }

    $horarios = array_values(array_intersect(SLOTS, (array)($peli['horarios'] ?? ['15:00', '18:00', '21:00'])));
    if (!$horarios) {
        $horarios = ['15:00', '18:00', '21:00'];
    }

    $dias = array_values(array_intersect(range(0, 6), array_map('intval', (array)($peli['dias'] ?? range(0, 6)))));
    if (!$dias) {
        $dias = range(0, 6);
    }

    return ['sala' => $sala, 'horarios' => $horarios, 'dias' => $dias];
}

// Igual que la anterior pero con los datos completos de la sala
function funcionDe(array $peli): array
{
    $f = funcionesFormulario($peli);
    $f['sala_id'] = $f['sala'];
    $f['sala']    = salas()[$f['sala_id']];
    return $f;
}

// Lee sala/horarios/días enviados por un formulario; agrega errores si algo no es válido
function leerFuncionesPost(array &$errores): array
{
    $salas = salas();
    $sala  = $_POST['sala'] ?? '';
    if (!is_string($sala) || !isset($salas[$sala])) {
        $errores[] = 'Elegí una sala.';
        $sala = 'sala1';
    }

    $horarios = array_values(array_intersect(SLOTS, array_filter((array)($_POST['horarios'] ?? []), 'is_string')));
    if (!$horarios) {
        $errores[] = 'Elegí al menos un horario.';
    }

    $dias = array_values(array_unique(array_map('intval', (array)($_POST['dias'] ?? []))));
    $dias = array_values(array_filter($dias, fn($d) => $d >= 0 && $d <= 6));
    if (!$dias) {
        $errores[] = 'Elegí al menos un día de la semana.';
    }

    return ['sala' => $sala, 'horarios' => $horarios, 'dias' => $dias];
}

// Próximos días en los que la película tiene función: 'Y-m-d' => ['label' => ..., 'horas' => [...]]
function fechasDisponibles(array $peli, int $dias = 7): array
{
    $f     = funcionDe($peli);
    $ahora = date('H:i');
    $out   = [];

    for ($i = 0; $i < $dias; $i++) {
        $ts = strtotime("+$i day");
        $w  = (int)date('w', $ts);
        if (!in_array($w, $f['dias'], true)) {
            continue;
        }
        $horas = array_values(array_filter($f['horarios'], fn($h) => $i > 0 || $h > $ahora));
        if ($horas) {
            $out[date('Y-m-d', $ts)] = [
                'label' => ucfirst(DIAS_NOMBRES[$w]) . ' ' . date('d/m', $ts),
                'horas' => $horas,
            ];
        }
    }
    return $out;
}

function diasTexto(array $dias): string
{
    if (count($dias) === 7) {
        return 'Todos los días';
    }
    $out = [];
    foreach (ORDEN_DIAS as $d) {
        if (in_array($d, $dias, true)) {
            $out[] = DIAS_CORTOS[$d];
        }
    }
    return implode(' · ', $out);
}

function leerEntradas(): array
{
    $archivo = __DIR__ . '/entradas.json';
    $datos   = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    return is_array($datos) ? $datos : [];
}

// Valida un código de descuento. Devuelve ['ok' => bool, 'mensaje' => ..., y si es válido: codigo, tipo, valor, descuento]
function validarCodigo(string $codigo, int $subtotal, string $usuario, ?array $entradas = null): array
{
    $codigo = strtoupper(trim($codigo));
    if ($codigo === '') {
        return ['ok' => false, 'mensaje' => 'Ingresá un código.'];
    }

    $lista = require __DIR__ . '/descuentos.php';
    if (!isset($lista[$codigo])) {
        return ['ok' => false, 'mensaje' => 'El código no es válido.'];
    }
    $d = $lista[$codigo];

    if (!empty($d['vence']) && date('Y-m-d') > $d['vence']) {
        return ['ok' => false, 'mensaje' => 'Ese código ya venció.'];
    }

    if (!empty($d['un_uso'])) {
        foreach ($entradas ?? leerEntradas() as $e) {
            if (($e['usuario'] ?? '') === $usuario && ($e['codigo_descuento'] ?? '') === $codigo && !entradaCancelada($e)) {
                return ['ok' => false, 'mensaje' => 'Ya usaste este código antes.'];
            }
        }
    }

    $monto = $d['tipo'] === 'porcentaje'
        ? (int)round($subtotal * $d['valor'] / 100)
        : (int)$d['valor'];

    return [
        'ok'        => true,
        'codigo'    => $codigo,
        'tipo'      => $d['tipo'],
        'valor'     => $d['valor'],
        'descuento' => min($monto, $subtotal),
        'mensaje'   => $d['descripcion'] ?? 'Código aplicado',
    ];
}


// ───────── JSON con bloqueo (lectura + escritura segura) ─────────
// $fn recibe el contenido actual (array) y devuelve el contenido nuevo
function modificarJson(string $archivo, callable $fn): void
{
    $fp = fopen($archivo, 'c+');
    flock($fp, LOCK_EX);
    $datos = json_decode(stream_get_contents($fp), true);
    $datos = $fn(is_array($datos) ? $datos : []);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ───────── Usuarios registrados ─────────
function leerUsuarios(): array
{
    $archivo = __DIR__ . '/usuarios.json';
    $datos   = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    return is_array($datos) ? $datos : [];
}

// ───────── Cancelación de entradas ─────────
const CANCELAR_HASTA_MIN = 60;   // se puede cancelar hasta 60 minutos antes de la función

function entradaCancelada(array $e): bool
{
    return ($e['estado'] ?? '') === 'cancelada';
}

function puedeCancelar(array $e): bool
{
    if (entradaCancelada($e)) {
        return false;
    }
    $inicio = strtotime($e['fecha'] . ' ' . $e['hora']);
    return $inicio !== false && ($inicio - time()) >= CANCELAR_HASTA_MIN * 60;
}

// ───────── Valoraciones ─────────
function leerResenas(): array
{
    $archivo = __DIR__ . '/resenas.json';
    $datos   = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    return is_array($datos) ? $datos : [];
}

// pelicula_id => ['prom' => 4.5, 'cant' => 3]
function promedios(?array $resenas = null): array
{
    $acc = [];
    foreach ($resenas ?? leerResenas() as $r) {
        $pid = $r['pelicula_id'] ?? '';
        $acc[$pid]['suma'] = ($acc[$pid]['suma'] ?? 0) + (int)$r['estrellas'];
        $acc[$pid]['cant'] = ($acc[$pid]['cant'] ?? 0) + 1;
    }
    $out = [];
    foreach ($acc as $pid => $a) {
        $out[$pid] = ['prom' => round($a['suma'] / $a['cant'], 1), 'cant' => $a['cant']];
    }
    return $out;
}

function estrellasTexto(int $n): string
{
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}


// ───────── Tráiler (YouTube) ─────────
// Acepta un link de YouTube (watch, youtu.be, shorts, embed) o directamente el ID de 11 caracteres.
// Devuelve el ID del video, o null si no es válido.
function youtubeId(string $entrada): ?string
{
    $entrada = trim($entrada);
    if ($entrada === '') {
        return null;
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $entrada)) {
        return $entrada;
    }
    $patron = '~^https?://(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~i';
    return preg_match($patron, $entrada, $m) ? $m[1] : null;
}
