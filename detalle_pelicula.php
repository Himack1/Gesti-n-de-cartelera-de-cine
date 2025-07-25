<?php
// detalle_pelicula.php
require_once 'funciones_bd.php';
include 'conexion.php';

// Verificar conexión
if (!isset($conn) || !$conn) {
    error_log("detalle_pelicula: sin conexión a BD");
    echo "<p>Error de conexión a la base de datos.</p>";
    exit;
}

// Función auxiliar para formatear fecha
function formatoFecha($valor) {
    if ($valor instanceof DateTime) {
        return $valor->format('d/m/Y');
    }
    if (is_string($valor) && $valor !== '') {
        $ts = strtotime($valor);
        if ($ts !== false) {
            return date('d/m/Y', $ts);
        }
    }
    return '';
}

// Obtener parámetros de consulta
$getId = $_GET['id'] ?? null;
$getTitulo = $_GET['titulo'] ?? null;
$pelicula = null;

// Buscar película por ID o título
if ($getId !== null && is_numeric($getId) && (int)$getId > 0) {
    $id_pelicula = (int)$getId;
    $tsql = "SELECT * FROM dbo.peliculas WHERE id_pelicula = ?";
    $params = [$id_pelicula];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    if ($stmt !== false) {
        $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
    }
}

if (!$pelicula && $getTitulo !== null && trim($getTitulo) !== '') {
    $titulo = trim($getTitulo);
    $tsql = "SELECT TOP 1 * FROM dbo.peliculas WHERE titulo = ? OR titulo LIKE ?";
    $params = [$titulo, '%' . $titulo . '%'];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    if ($stmt !== false) {
        $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
    }
}

// Mostrar error si no se encontró la película
if (!$pelicula) {
    // ... (código de error existente)
    // Cerrar conexión antes de salir
    if (is_resource($conn)) {
        if (is_resource($conn)) {
            sqlsrv_close($conn);
        }
    }
    exit;
}

// Cerrar conexión de la película aquí (antes de usar funciones_bd)
if (is_resource($conn)) {
    sqlsrv_close($conn);
}


// Obtener funciones de la película usando la función de funciones_bd.php
$id_pelicula = $pelicula['id_pelicula'];
$funcionesRaw = obtenerFuncionesPorPelicula($id_pelicula);

// Filtrar funciones: solo las que son hoy o en el futuro
$funciones = [];
$hoy = new DateTime('today'); // Solo la fecha de hoy, sin hora

foreach ($funcionesRaw as $funcion) {
    // Usar el formato Y-m-d que es reconocido universalmente
    $fechaHoraOriginal = $funcion['fecha_hora'] ?? null;
    
    if ($fechaHoraOriginal instanceof DateTime) {
        $fechaFuncion = $fechaHoraOriginal;
    } else {
        $fechaFuncion = new DateTime($fechaHoraOriginal);
    }
    
    // Comparar solo la parte de fecha (ignorar hora)
    $fechaFuncion->setTime(0, 0, 0);
    
    if ($fechaFuncion >= $hoy) {
        $funciones[] = $funcion;
    }
}

// Agrupar funciones por fecha
$funciones_por_fecha = [];
foreach ($funciones as $funcion) {
    $fechaKey = $funcion['fecha_formateada'] ?? '';
    if ($fechaKey !== '') {
        if (!isset($funciones_por_fecha[$fechaKey])) {
            $funciones_por_fecha[$fechaKey] = [];
        }
        $funciones_por_fecha[$fechaKey][] = $funcion;
    }
}
?>



<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pelicula['titulo'] ?? ''); ?> - Caribbean Cinemas</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-film"></i> Caribbean Cinemas</h1>
            </div>
            <nav class="nav">
                <a href="cartelera.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i> Volver a Cartelera
                </a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="movie-detail">
                <div class="movie-header">
                    <div class="movie-poster">
                        <img src="<?php echo htmlspecialchars($pelicula['imagen_url'] ?? 'placeholder.jpg'); ?>"
                            alt="<?php echo htmlspecialchars($pelicula['titulo'] ?? ''); ?>"
                            class="poster-img">
                    </div>

                    <div class="movie-info">
                        <h1 class="movie-title"><?php echo htmlspecialchars($pelicula['titulo'] ?? ''); ?></h1>

                        <div class="movie-details">
                            <?php
                            // Duración
                            $dur = $pelicula['duracion_min'] ?? '';
                            if ($dur !== ''): ?>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($dur . ' minutos'); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Clasificación
                            $clas = $pelicula['clasificacion'] ?? '';
                            if ($clas !== ''): ?>
                                <div class="detail-item">
                                    <i class="fas fa-certificate"></i>
                                    <span><?php echo htmlspecialchars($clas); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Género
                            $gen = $pelicula['genero'] ?? '';
                            if ($gen !== ''): ?>
                                <div class="detail-item">
                                    <i class="fas fa-tags"></i>
                                    <span><?php echo htmlspecialchars($gen); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Fecha de estreno
                            $fechaVal = $pelicula['fecha_estreno'] ?? null;
                            $fmtEstreno = formatoFecha($fechaVal);
                            if ($fmtEstreno !== ''): ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo htmlspecialchars($fmtEstreno); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Formato de película
                            $formPel = $pelicula['formato'] ?? '';
                            if ($formPel !== ''): ?>
                                <div class="detail-item">
                                    <i class="fas fa-tv"></i>
                                    <span><?php echo htmlspecialchars($formPel); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pelicula['trailer_url'])): ?>
                            <div class="trailer-section">
                                <button class="btn-trailer"
                                    onclick='openTrailer(<?php echo json_encode($pelicula['trailer_url'] ?? ''); ?>)'>
                                    <i class="fas fa-play"></i> Ver Trailer
                                </button>

                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="showtimes-section">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-alt"></i> Horarios y Funciones
                    </h2>

                    <?php if (empty($funciones_por_fecha)): ?>
                        <div class="no-showtimes">
                            <i class="fas fa-info-circle"></i>
                            <p>No hay funciones disponibles para esta película.</p>
                        </div>
                    <?php else: ?>
                        <div class="showtimes-container">
                            <?php foreach ($funciones_por_fecha as $fecha => $funciones_fecha): ?>
                                <div class="date-group">
                                    <h3 class="date-header"><?php echo htmlspecialchars($fecha); ?></h3>
                                    <div class="times-grid">
                                        <?php foreach ($funciones_fecha as $funcion): ?>
                                            <div class="time-card">
                                                <div class="time-info">
                                                    <span class="time"><?php echo htmlspecialchars($funcion['hora_formateada']); ?></span>
                                                    <span class="sala"><?php echo htmlspecialchars($funcion['nombre_sala']); ?></span>
                                                    <span class="format"><?php echo htmlspecialchars($funcion['formato_sala']); ?></span>
                                                </div>
                                                <div class="price-info">
                                                    <span class="price">RD$<?php echo number_format($funcion['precio'], 2); ?></span>
                                                    <button class="btn-book" onclick="bookTicket(<?php echo (int)$funcion['id_funcion']; ?>)">Reservar</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para el trailer -->
    <div id="trailerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTrailer()">&times;</span>
            <div class="video-container">
                <iframe id="trailerFrame" width="100%" height="400" frameborder="0" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <script src="detalle_peli.js?v=1.1"></script>
</body>

</html>

<?php
// Cerrar conexión
// sqlsrv_close($conn); // Ya se cerró la conexión anteriormente
// para evitar espacios en blanco al final del archivo.
// No cerrar con '
?>



<style>
    /* Reset y estilos base */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        min-height: 100vh;
        color: #fff;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Header */
    .header {
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        padding: 1rem 0;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .header .container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo h1 {
        color: #ffd700;
        font-size: 1.8rem;
        font-weight: bold;
    }

    .logo i {
        margin-right: 10px;
    }

    .nav-link {
        color: #fff;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 25px;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    /* Contenido principal */
    .main-content {
        padding: 2rem 0;
    }

    .movie-detail {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    /* Header de la película */
    .movie-header {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .movie-poster {
        position: relative;
    }

    .poster-img {
        width: 100%;
        height: 450px;
        object-fit: cover;
        border-radius: 15px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        transition: transform 0.3s ease;
    }

    .poster-img:hover {
        transform: scale(1.05);
    }

    .movie-info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 1.5rem;
    }

    .movie-title {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
        background: linear-gradient(45deg, #ffd700, #ffed4a);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .movie-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        font-size: 1rem;
    }

    .detail-item i {
        color: #ffd700;
        font-size: 1.1rem;
    }

    /* Sección del trailer */
    .trailer-section {
        margin-top: 1rem;
    }

    .btn-trailer {
        background: linear-gradient(45deg, #ff4757, #ff6b7a);
        color: white;
        border: none;
        padding: 15px 30px;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
    }

    .btn-trailer:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255, 71, 87, 0.4);
    }

    .btn-trailer i {
        font-size: 1.2rem;
    }

    /* Sección de horarios */
    .showtimes-section {
        margin-top: 2rem;
    }

    .section-title {
        font-size: 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 15px;
        color: #ffd700;
    }

    .section-title i {
        font-size: 1.8rem;
    }

    .no-showtimes {
        text-align: center;
        padding: 3rem;
        color: #ccc;
    }

    .no-showtimes i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #ffd700;
    }

    .showtimes-container {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .date-group {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 1.5rem;
    }

    .date-header {
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 1rem;
        color: #ffd700;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid rgba(255, 215, 0, 0.3);
    }

    .times-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .time-card {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .time-card:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .time-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .time {
        font-size: 1.3rem;
        font-weight: bold;
        color: #ffd700;
    }

    .sala {
        font-size: 0.9rem;
        color: #ccc;
    }

    .format {
        font-size: 0.8rem;
        background: rgba(255, 215, 0, 0.2);
        color: #ffd700;
        padding: 2px 8px;
        border-radius: 5px;
        display: inline-block;
    }

    .price-info {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .price {
        font-size: 1.2rem;
        font-weight: bold;
        color: #4ecdc4;
    }

    .btn-book {
        background: linear-gradient(45deg, #4ecdc4, #44a08d);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(78, 205, 196, 0.3);
    }

    .btn-book:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }

    /* Modal del trailer */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
    }

    .modal-content {
        background: #1a1a1a;
        margin: 5% auto;
        padding: 20px;
        border-radius: 15px;
        width: 90%;
        max-width: 800px;
        position: relative;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    }

    .close {
        color: #fff;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .close:hover {
        color: #ff4757;
    }

    .video-container {
        margin-top: 20px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .movie-header {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .movie-title {
            font-size: 2rem;
        }

        .movie-details {
            grid-template-columns: 1fr;
        }

        .times-grid {
            grid-template-columns: 1fr;
        }

        .time-card {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }

        .price-info {
            align-items: center;
        }

        .modal-content {
            width: 95%;
            margin: 10% auto;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 0 10px;
        }

        .movie-detail {
            padding: 1rem;
        }

        .movie-title {
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
        }
    }
</style>