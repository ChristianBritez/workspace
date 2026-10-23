<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function esAdmin(): bool
{
    return ($_SESSION['rol'] ?? '') === 'admin';
}

// Corta la página si el usuario no es administrador
function requerirAdmin(): void
{
    if (esAdmin()) {
        return;
    }
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso restringido</title>
        <link rel="stylesheet" href="css/styles.css">
    <link rel="icon" type="image/svg+xml" href="assets/logo-icono.svg">
    </head>
    <body>
    <main class="portal">
        <section class="hero">
            <h1>🚫 Acceso restringido</h1>
            <p class="hero-texto">Esta sección es solo para administradores.</p>
            <div class="hero-botones">
                <a href="cartelera.php" class="btn">Volver a la cartelera</a>
            </div>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

// Token para que nadie pueda hacerte borrar o editar sin querer desde otra página
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verificarCsrf(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(400);
        echo 'Solicitud inválida. Volvé atrás y recargá la página.';
        exit;
    }
}
