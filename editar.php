<?php
require 'guardia.php';
requerirAdmin();
require 'catalogo.php';

$archivo   = __DIR__ . '/peliculas.json';
$peliculas = require 'datos.php';

$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (!isset($peliculas[$id])) {
    http_response_code(404);
    echo 'Esa película no existe.';
    exit;
}
$peli    = $peliculas[$id];
$errores = [];

$titulo = $peli['titulo'];
$anio   = (string)$peli['anio'];
$genero = $peli['genero'];
$f      = funcionesFormulario($peli);
$trailer = !empty($peli['trailer']) ? 'https://www.youtube.com/watch?v=' . $peli['trailer'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $titulo = trim($_POST['titulo'] ?? '');
    $anio   = trim($_POST['anio']   ?? '');
    $genero = trim($_POST['genero'] ?? '');

    if ($titulo === '' || mb_strlen($titulo) > 100) {
        $errores[] = 'El título es obligatorio (máximo 100 caracteres).';
    }
    if (!ctype_digit($anio) || (int)$anio < 1888 || (int)$anio > (int)date('Y') + 5) {
        $errores[] = 'El año no es válido.';
    }
    if ($genero === '' || mb_strlen($genero) > 50) {
        $errores[] = 'El género es obligatorio (máximo 50 caracteres).';
    }

    $f = leerFuncionesPost($errores);
    $trailer = is_string($_POST['trailer'] ?? null) ? trim($_POST['trailer']) : '';
    $trailerId = $trailer === '' ? '' : youtubeId($trailer);
    if ($trailerId === null) {
        $errores[] = 'El link del tráiler no es válido. Pegá un link de YouTube (youtube.com o youtu.be).';
    }

    // Imagen nueva (opcional)
    $subio = isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($subio) {
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));

        if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir la imagen.';
        } elseif (!in_array($ext, $permitidas, true) || @getimagesize($_FILES['imagen']['tmp_name']) === false) {
            $errores[] = 'La imagen debe ser JPG, PNG o WEBP.';
        } elseif ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            $errores[] = 'La imagen no puede pesar más de 5 MB.';
        }
    }

    if (!$errores) {
        $rutaImagen = $peli['imagen'] ?? '';

        if ($subio) {
            $carpeta = __DIR__ . '/imagenes';
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0755, true);
            }
            $nombre = $id . '.' . $ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], "$carpeta/$nombre");
            $nuevaRuta = "imagenes/$nombre";

            // Borra la imagen anterior si quedó sin uso
            $vieja = $rutaImagen;
            if ($vieja !== '' && $vieja !== $nuevaRuta && str_starts_with($vieja, 'imagenes/')) {
                $otra = array_filter($peliculas, fn($p, $k) => $k !== $id && ($p['imagen'] ?? '') === $vieja, ARRAY_FILTER_USE_BOTH);
                if (!$otra && file_exists(__DIR__ . '/' . $vieja)) {
                    unlink(__DIR__ . '/' . $vieja);
                }
            }
            $rutaImagen = $nuevaRuta;
        }

        $peliculas[$id] = [
            'titulo' => $titulo,
            'anio'   => (int)$anio,
            'genero' => $genero,
            'imagen' => $rutaImagen,
            'sala'     => $f['sala'],
            'horarios' => $f['horarios'],
            'dias'     => $f['dias'],
            'trailer'  => $trailerId,
        ];

        file_put_contents(
            $archivo,
            json_encode($peliculas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        header('Location: cartelera.php?msg=editada');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar película</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
</head>
<body>

    <header>
    <?php include 'includes/logo.php'; ?>
        <h1><span class="emoji">✏️</span> Editar película</h1>
        <nav class="acciones">
            <a href="cartelera.php">← Volver a la cartelera</a>
        </nav>
    </header>

    <?php if ($errores): ?>
        <ul class="errores">
            <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

        <label>Título
            <input type="text" name="titulo" value="<?= htmlspecialchars($titulo) ?>" required>
        </label>

        <label>Año
            <input type="text" name="anio" inputmode="numeric" value="<?= htmlspecialchars($anio) ?>" required>
        </label>

        <label>Género
            <input type="text" name="genero" value="<?= htmlspecialchars($genero) ?>" required>
        </label>

        <label>Tráiler de YouTube (opcional)
            <input type="text" name="trailer" value="<?= htmlspecialchars($trailer) ?>"
                   placeholder="Pegá el link del video de YouTube">
        </label>

        <?php include 'includes/campos-funciones.php'; ?>

        <?php if (!empty($peli['imagen'])): ?>
            <p class="preview-actual">
                <img src="<?= htmlspecialchars($peli['imagen']) ?>" alt="Imagen actual">
                <span>Imagen actual</span>
            </p>
        <?php endif; ?>

        <label>Cambiar imagen (opcional)
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
        </label>

        <button type="submit">Guardar cambios</button>
    </form>

</body>
</html>
