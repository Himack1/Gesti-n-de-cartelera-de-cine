<?php
session_start();

// Configuración de base de datos
$servidor = "localhost,1433";
$usuario_db = "sa";
$password_db = "123456";
$base_datos = "Cine";

try {
    $conn = new PDO("sqlsrv:server=$servidor;Database=$base_datos", $usuario_db, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$pelicula = $_GET['pelicula'] ?? '';
$sala = $_GET['sala'] ?? '';
$dia_semana = $_GET['dia_semana'] ?? '';

// Obtener datos para los selectores
$peliculas_query = "SELECT DISTINCT pelicula_titulo FROM Historial_Compras ORDER BY pelicula_titulo";
$peliculas = $conn->query($peliculas_query)->fetchAll(PDO::FETCH_COLUMN);

$salas_query = "SELECT DISTINCT sala FROM Historial_Compras ORDER BY sala";
$salas = $conn->query($salas_query)->fetchAll(PDO::FETCH_COLUMN);

// Función para construir WHERE clause
function construirFiltros($fecha_inicio, $fecha_fin, $pelicula, $sala, $dia_semana)
{
    $condiciones = [];
    $parametros = [];

    $condiciones[] = "fecha_compra BETWEEN ? AND ?";
    $parametros[] = $fecha_inicio . ' 00:00:00';
    $parametros[] = $fecha_fin . ' 23:59:59';

    if (!empty($pelicula)) {
        $condiciones[] = "pelicula_titulo = ?";
        $parametros[] = $pelicula;
    }

    if (!empty($sala)) {
        $condiciones[] = "sala = ?";
        $parametros[] = $sala;
    }

    if (!empty($dia_semana)) {
        $condiciones[] = "DATEPART(weekday, fecha_compra) = ?";
        $parametros[] = $dia_semana;
    }

    return [
        'where' => implode(' AND ', $condiciones),
        'params' => $parametros
    ];
}

$filtros = construirFiltros($fecha_inicio, $fecha_fin, $pelicula, $sala, $dia_semana);

// Estadísticas generales (mejoradas con datos de todas las tablas)
$stats_query = "
    SELECT 
        COUNT(DISTINCT hc.codigo_confirmacion) as total_transacciones,
        SUM(hc.total) as ingresos_brutos,
        SUM(hc.subtotal) as ingresos_netos,
        SUM(hc.impuestos) as total_impuestos,
        SUM(hc.cantidad_entradas) as total_entradas,
        AVG(hc.total) as ticket_promedio,
        COUNT(DISTINCT hc.pelicula_titulo) as peliculas_diferentes,
        COUNT(DISTINCT hc.sala) as salas_utilizadas,
        COUNT(DISTINCT hc.usuario_email) as clientes_unicos,
        SUM(CASE WHEN rc.id IS NOT NULL THEN rc.subtotal ELSE 0 END) as ingresos_combos,
        COUNT(DISTINCT rc.id_combo) as tipos_combos_vendidos,
        SUM(CASE WHEN rc.id IS NOT NULL THEN rc.cantidad ELSE 0 END) as total_combos_vendidos
    FROM Historial_Compras hc
    INNER JOIN Reservas r ON hc.id_reserva = r.id_reserva
    LEFT JOIN Reserva_Combos rc ON r.id_reserva = rc.id_reserva
    WHERE " . $filtros['where'];

$stmt = $conn->prepare($stats_query);
$stmt->execute($filtros['params']);
$estadisticas_generales = $stmt->fetch(PDO::FETCH_ASSOC);

// Ventas por día
$ventas_diarias_query = "
    SELECT 
        CAST(fecha_compra AS DATE) as fecha,
        COUNT(*) as transacciones,
        SUM(total) as ingresos,
        SUM(cantidad_entradas) as entradas
    FROM Historial_Compras 
    WHERE " . $filtros['where'] . "
    GROUP BY CAST(fecha_compra AS DATE)
    ORDER BY fecha";

$stmt = $conn->prepare($ventas_diarias_query);
$stmt->execute($filtros['params']);
$ventas_diarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top películas
$top_peliculas_query = "
    SELECT 
        pelicula_titulo,
        COUNT(*) as transacciones,
        SUM(total) as ingresos,
        SUM(cantidad_entradas) as entradas_vendidas
    FROM Historial_Compras 
    WHERE " . $filtros['where'] . "
    GROUP BY pelicula_titulo
    ORDER BY ingresos DESC";

$stmt = $conn->prepare($top_peliculas_query);
$stmt->execute($filtros['params']);
$top_peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ocupación por sala (mejorada con datos de asientos)
$ocupacion_salas_query = "
    SELECT 
        hc.sala,
        COUNT(DISTINCT hc.codigo_confirmacion) as total_reservas,
        COUNT(ra.asiento) as asientos_individuales_vendidos,
        SUM(hc.cantidad_entradas) as entradas_vendidas_historial,
        AVG(CAST(hc.cantidad_entradas AS FLOAT)) as promedio_ocupacion,
        SUM(ra.precio) as ingresos_por_asientos,
        AVG(ra.precio) as precio_promedio_asiento,
        COUNT(DISTINCT hc.fecha_funcion + ' ' + hc.hora_funcion) as funciones_unicas
    FROM Historial_Compras hc
    INNER JOIN Reservas r ON hc.id_reserva = r.id_reserva
    LEFT JOIN Reserva_Asientos ra ON r.id_reserva = ra.id_reserva
    WHERE " . $filtros['where'] . "
    GROUP BY hc.sala
    ORDER BY entradas_vendidas_historial DESC";

$stmt = $conn->prepare($ocupacion_salas_query);
$stmt->execute($filtros['params']);
$ocupacion_salas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ventas por día de la semana
$ventas_dia_semana_query = "
    SELECT 
        CASE DATEPART(weekday, fecha_compra)
            WHEN 1 THEN 'Domingo'
            WHEN 2 THEN 'Lunes'
            WHEN 3 THEN 'Martes'
            WHEN 4 THEN 'Miércoles'
            WHEN 5 THEN 'Jueves'
            WHEN 6 THEN 'Viernes'
            WHEN 7 THEN 'Sábado'
        END as dia_semana,
        DATEPART(weekday, fecha_compra) as dia_numero,
        COUNT(*) as transacciones,
        SUM(total) as ingresos,
        AVG(total) as ticket_promedio
    FROM Historial_Compras 
    WHERE " . $filtros['where'] . "
    GROUP BY DATEPART(weekday, fecha_compra)
    ORDER BY dia_numero";

$stmt = $conn->prepare($ventas_dia_semana_query);
$stmt->execute($filtros['params']);
$ventas_dia_semana = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ventas de combos (snack bar)
$ventas_combos_query = "
    SELECT 
        rc.nombre_combo,
        SUM(rc.cantidad) as cantidad_vendida,
        SUM(rc.subtotal) as ingresos_combo,
        AVG(rc.precio_unitario) as precio_promedio
    FROM Reserva_Combos rc
    INNER JOIN Reservas r ON rc.id_reserva = r.id_reserva
    INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
    WHERE " . str_replace('fecha_compra', 'hc.fecha_compra', $filtros['where']) . "
    GROUP BY rc.nombre_combo
    ORDER BY ingresos_combo DESC";

$stmt = $conn->prepare($ventas_combos_query);
$stmt->execute($filtros['params']);
$ventas_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Análisis detallado de asientos por sala
$asientos_populares_query = "
    SELECT 
        hc.sala,
        ra.asiento,
        COUNT(*) as veces_reservado,
        SUM(ra.precio) as ingresos_asiento,
        AVG(ra.precio) as precio_promedio,
        MIN(hc.fecha_compra) as primera_reserva,
        MAX(hc.fecha_compra) as ultima_reserva
    FROM Reserva_Asientos ra
    INNER JOIN Reservas r ON ra.id_reserva = r.id_reserva
    INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
    WHERE " . str_replace('fecha_compra', 'hc.fecha_compra', $filtros['where']) . "
    GROUP BY hc.sala, ra.asiento
    ORDER BY hc.sala, veces_reservado DESC";

$stmt = $conn->prepare($asientos_populares_query);
$stmt->execute($filtros['params']);
$asientos_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Análisis de reservas por estado
$reservas_estado_query = "
    SELECT 
        r.estado,
        COUNT(*) as cantidad_reservas,
        SUM(r.total) as ingresos_por_estado,
        AVG(r.total) as promedio_por_reserva,
        SUM(r.subtotal) as subtotal_estado,
        SUM(r.impuestos) as impuestos_estado
    FROM Reservas r
    INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
    WHERE " . str_replace('fecha_compra', 'hc.fecha_compra', $filtros['where']) . "
    GROUP BY r.estado
    ORDER BY cantidad_reservas DESC";

$stmt = $conn->prepare($reservas_estado_query);
$stmt->execute($filtros['params']);
$reservas_por_estado = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Análisis de mix de productos (entradas vs combos)
$mix_productos_query = "
    SELECT 
        'Entradas' as tipo_producto,
        SUM(hc.cantidad_entradas) as cantidad,
        SUM(hc.cantidad_entradas * hc.precio_entrada) as ingresos,
        AVG(hc.precio_entrada) as precio_promedio
    FROM Historial_Compras hc
    WHERE " . $filtros['where'] . "
    
    UNION ALL
    
    SELECT 
        'Combos' as tipo_producto,
        SUM(rc.cantidad) as cantidad,
        SUM(rc.subtotal) as ingresos,
        AVG(rc.precio_unitario) as precio_promedio
    FROM Reserva_Combos rc
    INNER JOIN Reservas r ON rc.id_reserva = r.id_reserva
    INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
    WHERE " . str_replace('fecha_compra', 'hc.fecha_compra', $filtros['where']);

$stmt = $conn->prepare($mix_productos_query);
$stmt->execute(array_merge($filtros['params'], $filtros['params']));
$mix_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - CineMax</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .filters-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-filter {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .table-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
        }

        .data-table th:first-child {
            border-radius: 12px 0 0 12px;
        }

        .data-table th:last-child {
            border-radius: 0 12px 12px 0;
        }

        .data-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .btn-export {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-confirmada {
            background: #d4edda;
            color: #155724;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-completada {
            background: #d1ecf1;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

/*btn cartelera*/

.cartelera-btn-container {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
}

/* Si prefieres que sea relativo en lugar de fijo, usa esta alternativa:
.cartelera-btn-container {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 100;
}
*/

.btn-cartelera {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
    text-decoration: none;
    border-radius: 15px;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
    border: none;
    cursor: pointer;
    backdrop-filter: blur(10px);
}

.btn-cartelera:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
    text-decoration: none;
    color: white;
    background: linear-gradient(135deg, #ff5252 0%, #d63031 100%);
}

.btn-cartelera:active {
    transform: translateY(-1px) scale(1.02);
}

.btn-cartelera svg {
    transition: transform 0.3s ease;
}

.btn-cartelera:hover svg {
    transform: rotate(5deg) scale(1.1);
}

.btn-cartelera span {
    font-weight: 700;
    letter-spacing: 0.5px;
}

/* Animación de entrada */
.cartelera-btn-container {
    animation: slideInLeft 0.6s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .cartelera-btn-container {
        top: 15px;
        left: 15px;
    }
    
    .btn-cartelera {
        padding: 12px 16px;
        font-size: 14px;
        gap: 10px;
    }
    
    .btn-cartelera svg {
        width: 20px;
        height: 20px;
    }
}

@media (max-width: 480px) {
    .btn-cartelera span {
        display: none; /* Solo mostrar el ícono en móviles muy pequeños */
    }
    
    .btn-cartelera {
        padding: 12px;
        border-radius: 50%;
        width: 48px;
        height: 48px;
        justify-content: center;
    }
}

/* Efecto de pulso sutil para llamar la atención */
.btn-cartelera::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 100%;
    background: rgba(255, 107, 107, 0.3);
    border-radius: 15px;
    transform: translate(-50%, -50%) scale(0);
    animation: pulse 2s infinite;
    z-index: -1;
}

@keyframes pulse {
    0% {
        transform: translate(-50%, -50%) scale(0);
        opacity: 1;
    }
    70% {
        transform: translate(-50%, -50%) scale(1.2);
        opacity: 0;
    }
    100% {
        transform: translate(-50%, -50%) scale(1.2);
        opacity: 0;
    }
}

/* Asegurar que el botón tenga posición relativa para el pseudo-elemento */
.btn-cartelera {
    position: relative;
    overflow: hidden;
}



    </style>
</head>

<body>
    <div class="container">

<div class="cartelera-btn-container">
                <a href="perfil.php" class="btn-cartelera">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2" />
                        <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2" />
                        <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2" />
                        <circle cx="8" cy="9" r="2" stroke="currentColor" stroke-width="2" />
                        <path d="M14 9L16 11L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Ver Perfil</span>
                </a>
            </div>

        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Reportes y Estadísticas</h1>
            <p>Panel de control para análisis de ventas y rendimiento del cine</p>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="pelicula">Película</label>
                        <select id="pelicula" name="pelicula">
                            <option value="">Todas las películas</option>
                            <?php foreach ($peliculas as $peli): ?>
                                <option value="<?php echo htmlspecialchars($peli); ?>"
                                    <?php echo ($pelicula === $peli) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($peli); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="sala">Sala</label>
                        <select id="sala" name="sala">
                            <option value="">Todas las salas</option>
                            <?php foreach ($salas as $s): ?>
                                <option value="<?php echo htmlspecialchars($s); ?>"
                                    <?php echo ($sala === $s) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="dia_semana">Día de la Semana</label>
                        <select id="dia_semana" name="dia_semana">
                            <option value="">Todos los días</option>
                            <option value="1" <?php echo ($dia_semana === '1') ? 'selected' : ''; ?>>Domingo</option>
                            <option value="2" <?php echo ($dia_semana === '2') ? 'selected' : ''; ?>>Lunes</option>
                            <option value="3" <?php echo ($dia_semana === '3') ? 'selected' : ''; ?>>Martes</option>
                            <option value="4" <?php echo ($dia_semana === '4') ? 'selected' : ''; ?>>Miércoles</option>
                            <option value="5" <?php echo ($dia_semana === '5') ? 'selected' : ''; ?>>Jueves</option>
                            <option value="6" <?php echo ($dia_semana === '6') ? 'selected' : ''; ?>>Viernes</option>
                            <option value="7" <?php echo ($dia_semana === '7') ? 'selected' : ''; ?>>Sábado</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Aplicar Filtros
                </button>
            </form>
        </div>

        <!-- Estadísticas Generales (Actualizadas) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="value">$<?php echo number_format($estadisticas_generales['ingresos_brutos'] ?? 0, 2); ?></div>
                <div class="label">Ingresos Brutos</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="value">$<?php echo number_format($estadisticas_generales['ingresos_netos'] ?? 0, 2); ?></div>
                <div class="label">Ingresos Netos</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-cocktail"></i></div>
                <div class="value">$<?php echo number_format($estadisticas_generales['ingresos_combos'] ?? 0, 2); ?></div>
                <div class="label">Ingresos Snack Bar</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="value"><?php echo number_format($estadisticas_generales['total_entradas'] ?? 0); ?></div>
                <div class="label">Total Entradas</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="value"><?php echo number_format($estadisticas_generales['total_transacciones'] ?? 0); ?></div>
                <div class="label">Transacciones</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value"><?php echo number_format($estadisticas_generales['clientes_unicos'] ?? 0); ?></div>
                <div class="label">Clientes Únicos</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-film"></i></div>
                <div class="value"><?php echo number_format($estadisticas_generales['peliculas_diferentes'] ?? 0); ?></div>
                <div class="label">Películas Activas</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-chart-bar"></i></div>
                <div class="value">$<?php echo number_format($estadisticas_generales['ticket_promedio'] ?? 0, 2); ?></div>
                <div class="label">Ticket Promedio</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <!-- Ventas Diarias -->
            <div class="chart-container">
                <h3 class="chart-title">Evolución de Ventas Diarias</h3>
                <canvas id="ventasDiariasChart"></canvas>
            </div>

            <!-- Top Películas -->
            <div class="chart-container">
                <h3 class="chart-title">Top Películas por Ingresos</h3>
                <canvas id="topPeliculasChart"></canvas>
            </div>

            <!-- Ocupación de Salas -->
            <div class="chart-container">
                <h3 class="chart-title">Ocupación por Sala</h3>
                <canvas id="ocupacionSalasChart"></canvas>
            </div>

            <!-- Ventas por Día de Semana -->
            <div class="chart-container">
                <h3 class="chart-title">Ventas por Día de la Semana</h3>
                <canvas id="ventasDiaChart"></canvas>
            </div>
            <!-- Mix de Productos -->
            <div class="chart-container">
                <h3 class="chart-title">Mix de Productos (Entradas vs Combos)</h3>
                <canvas id="mixProductosChart"></canvas>
            </div>
        </div>

        <!-- Tablas de Datos -->
        <!-- Top Películas -->
        <div class="table-container">
            <div class="export-buttons">
                <button class="btn-export" onclick="exportarTabla('peliculas-table', 'top_peliculas.csv')">
                    <i class="fas fa-download"></i> Exportar CSV
                </button>
            </div>
            <h3 class="chart-title">Ranking de Películas</h3>
            <table class="data-table" id="peliculas-table">
                <thead>
                    <tr>
                        <th>Película</th>
                        <th>Transacciones</th>
                        <th>Entradas Vendidas</th>
                        <th>Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_peliculas as $pelicula): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pelicula['pelicula_titulo']); ?></td>
                            <td><?php echo number_format($pelicula['transacciones']); ?></td>
                            <td><?php echo number_format($pelicula['entradas_vendidas']); ?></td>
                            <td>$<?php echo number_format($pelicula['ingresos'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Ventas de Combos -->
        <?php if (!empty($ventas_combos)): ?>
            <div class="table-container">
                <div class="export-buttons">
                    <button class="btn-export" onclick="exportarTabla('combos-table', 'ventas_combos.csv')">
                        <i class="fas fa-download"></i> Exportar CSV
                    </button>
                </div>
                <h3 class="chart-title">Ventas de Snack Bar (Combos)</h3>
                <table class="data-table" id="combos-table">
                    <thead>
                        <tr>
                            <th>Combo</th>
                            <th>Cantidad Vendida</th>
                            <th>Precio Promedio</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventas_combos as $combo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($combo['nombre_combo']); ?></td>
                                <td><?php echo number_format($combo['cantidad_vendida']); ?></td>
                                <td>$<?php echo number_format($combo['precio_promedio'], 2); ?></td>
                                <td>$<?php echo number_format($combo['ingresos_combo'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Análisis de Ocupación Detallado -->
        <div class="table-container">
            <div class="export-buttons">
                <button class="btn-export" onclick="exportarTabla('ocupacion-detallada', 'ocupacion_salas_detallada.csv')">
                    <i class="fas fa-download"></i> Exportar CSV
                </button>
            </div>
            <h3 class="chart-title">Análisis Detallado de Ocupación por Sala</h3>
            <table class="data-table" id="ocupacion-detallada">
                <thead>
                    <tr>
                        <th>Sala</th>
                        <th>Reservas</th>
                        <th>Funciones</th>
                        <th>Asientos Vendidos</th>
                        <th>Entradas (Historial)</th>
                        <th>Ingresos por Asientos</th>
                        <th>Precio Prom. Asiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ocupacion_salas as $sala): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sala['sala']); ?></td>
                            <td><?php echo number_format($sala['total_reservas']); ?></td>
                            <td><?php echo number_format($sala['funciones_unicas']); ?></td>
                            <td><?php echo number_format($sala['asientos_individuales_vendidos']); ?></td>
                            <td><?php echo number_format($sala['entradas_vendidas_historial']); ?></td>
                            <td>$<?php echo number_format($sala['ingresos_por_asientos'] ?? 0, 2); ?></td>
                            <td>$<?php echo number_format($sala['precio_promedio_asiento'] ?? 0, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Asientos Populares -->
        <?php if (!empty($asientos_populares)): ?>
            <div class="table-container">
                <div class="export-buttons">
                    <button class="btn-export" onclick="exportarTabla('asientos-populares', 'asientos_populares.csv')">
                        <i class="fas fa-download"></i> Exportar CSV
                    </button>
                </div>
                <h3 class="chart-title">Asientos Más Populares (Top 50)</h3>
                <table class="data-table" id="asientos-populares">
                    <thead>
                        <tr>
                            <th>Sala</th>
                            <th>Asiento</th>
                            <th>Veces Reservado</th>
                            <th>Ingresos</th>
                            <th>Precio Promedio</th>
                            <th>Primera Reserva</th>
                            <th>Última Reserva</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $contador = 0;
                        foreach ($asientos_populares as $asiento):
                            if ($contador >= 50) break;
                            $contador++;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asiento['sala']); ?></td>
                                <td><?php echo htmlspecialchars($asiento['asiento']); ?></td>
                                <td><?php echo number_format($asiento['veces_reservado']); ?></td>
                                <td>$<?php echo number_format($asiento['ingresos_asiento'], 2); ?></td>
                                <td>$<?php echo number_format($asiento['precio_promedio'], 2); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($asiento['primera_reserva'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($asiento['ultima_reserva'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Análisis de Estados de Reserva -->
        <?php if (!empty($reservas_por_estado)): ?>
            <div class="table-container">
                <h3 class="chart-title">Análisis por Estado de Reservas</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>Ingresos</th>
                            <th>Promedio por Reserva</th>
                            <th>Subtotal</th>
                            <th>Impuestos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservas_por_estado as $estado): ?>
                            <tr>
                                <td><span class="badge badge-<?php echo $estado['estado']; ?>"><?php echo ucfirst($estado['estado']); ?></span></td>
                                <td><?php echo number_format($estado['cantidad_reservas']); ?></td>
                                <td>$<?php echo number_format($estado['ingresos_por_estado'], 2); ?></td>
                                <td>$<?php echo number_format($estado['promedio_por_reserva'], 2); ?></td>
                                <td>$<?php echo number_format($estado['subtotal_estado'], 2); ?></td>
                                <td>$<?php echo number_format($estado['impuestos_estado'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Datos para los gráficos
        const ventasDiarias = <?php echo json_encode($ventas_diarias); ?>;
        const topPeliculas = <?php echo json_encode(array_slice($top_peliculas, 0, 10)); ?>;
        const ocupacionSalas = <?php echo json_encode($ocupacion_salas); ?>;
        const ventasDiaSemana = <?php echo json_encode($ventas_dia_semana); ?>;
        const mixProductos = <?php echo json_encode($mix_productos); ?>;

        // Configuración común de Chart.js
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#666';

        // Gráfico de Ventas Diarias
        const ctxVentasDiarias = document.getElementById('ventasDiariasChart').getContext('2d');
        new Chart(ctxVentasDiarias, {
            type: 'line',
            data: {
                labels: ventasDiarias.map(v => v.fecha),
                datasets: [{
                    label: 'Ingresos',
                    data: ventasDiarias.map(v => v.ingresos),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Entradas Vendidas',
                    data: ventasDiarias.map(v => v.entradas),
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Gráfico Top Películas
        const ctxTopPeliculas = document.getElementById('topPeliculasChart').getContext('2d');
        new Chart(ctxTopPeliculas, {
            type: 'bar',
            data: {
                labels: topPeliculas.map(p => p.pelicula_titulo.length > 20 ?
                    p.pelicula_titulo.substring(0, 20) + '...' : p.pelicula_titulo),
                datasets: [{
                    label: 'Ingresos',
                    data: topPeliculas.map(p => p.ingresos),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico Ocupación Salas (actualizado)
        const ctxOcupacionSalas = document.getElementById('ocupacionSalasChart').getContext('2d');
        new Chart(ctxOcupacionSalas, {
            type: 'doughnut',
            data: {
                labels: ocupacionSalas.map(s => s.sala),
                datasets: [{
                    data: ocupacionSalas.map(s => s.entradas_vendidas_historial),
                    backgroundColor: [
                        '#667eea', '#764ba2', '#f093fb', '#f5576c',
                        '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'
                    ],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const sala = ocupacionSalas[context.dataIndex];
                                return `${context.label}: ${context.parsed} entradas (${sala.total_reservas} reservas)`;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico Mix de Productos
        const ctxMixProductos = document.getElementById('mixProductosChart').getContext('2d');
        new Chart(ctxMixProductos, {
            type: 'doughnut',
            data: {
                labels: mixProductos.map(p => p.tipo_producto),
                datasets: [{
                    label: 'Ingresos',
                    data: mixProductos.map(p => p.ingresos),
                    backgroundColor: ['#667eea', '#764ba2'],
                    borderWidth: 3,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const producto = mixProductos[context.dataIndex];
                                const porcentaje = ((context.parsed / mixProductos.reduce((sum, p) => sum + parseFloat(p.ingresos), 0)) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed.toLocaleString()} (${porcentaje}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico Ventas por Día
        const ctxVentasDia = document.getElementById('ventasDiaChart').getContext('2d');
        new Chart(ctxVentasDia, {
            type: 'bar',
            data: {
                labels: ventasDiaSemana.map(d => d.dia_semana),
                datasets: [{
                    label: 'Ingresos',
                    data: ventasDiaSemana.map(d => d.ingresos),
                    backgroundColor: 'rgba(118, 75, 162, 0.8)',
                    borderColor: '#764ba2',
                    borderWidth: 2
                }, {
                    label: 'Transacciones',
                    data: ventasDiaSemana.map(d => d.transacciones),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Función para exportar tablas a CSV
        function exportarTabla(tableId, filename) {
            const table = document.getElementById(tableId);
            let csv = [];

            // Obtener headers
            const headers = [];
            const headerCells = table.querySelectorAll('thead th');
            headerCells.forEach(cell => headers.push(cell.textContent.trim()));
            csv.push(headers.join(','));

            // Obtener datos
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    let cellData = cell.textContent.trim();
                    // Limpiar datos monetarios para CSV
                    cellData = cellData.replace(/[$,]/g, '');
                    rowData.push(`"${cellData}"`);
                });
                csv.push(rowData.join(','));
            });

            // Crear y descargar archivo
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8;'
            });
            const link = document.createElement('a');

            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Función para imprimir reporte
        function imprimirReporte() {
            window.print();
        }

        // Auto-refresh cada 5 minutos (opcional)
        // setInterval(() => {
        //     location.reload();
        // }, 300000);
    </script>

    <!-- Estilos para impresión -->
    <style media="print">
        body {
            background: white !important;
        }

        .filters-section {
            display: none;
        }

        .export-buttons {
            display: none;
        }

        .chart-container,
        .stat-card,
        .table-container {
            background: white !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
    </style>
</body>

</html>