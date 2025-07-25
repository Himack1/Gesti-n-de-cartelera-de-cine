<?php
session_start();
include 'conexion.php';
include 'funciones_bd.php';

// Verificar si existe la reserva en sesión
if (!isset($_SESSION['reserva'])) {
    header('Location: cartelera.php');
    exit;
}

// Recuperar datos de la reserva
$reserva = $_SESSION['reserva'];
$id_funcion = $reserva['id_funcion'];
$asientos = $reserva['asientos'];  // Esto es un string (ej: "B2,B4,B3")
$asientosArray = explode(',', $asientos);


// Recibir datos del formulario de pago si existen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['reserva'] += [
        'nombre_pelicula' => $_POST['nombre_pelicula'],
        'fecha_formateada' => $_POST['fecha_formateada'],
        'hora_formateada' => $_POST['hora_formateada'],
        'nombre_sala' => $_POST['nombre_sala'],
        'formato_sala' => $_POST['formato_sala'],
        'precio_unitario' => $_POST['precio_unitario'],
        'id_pelicula' => $_POST['id_pelicula']
    ];
    $reserva = $_SESSION['reserva']; // Actualizar variable local
}

// Obtener detalles de la función con datos de sesión como respaldo
$funcion = [
    'id_funcion' => $id_funcion,
    'precio' => $reserva['precio_unitario'] ?? 350.00,
    'fecha_formateada' => $reserva['fecha_formateada'] ?? 'Fecha no disponible',
    'hora_formateada' => $reserva['hora_formateada'] ?? 'Hora no disponible',
    'nombre_sala' => $reserva['nombre_sala'] ?? 'Sala no disponible',
    'formato_sala' => $reserva['formato_sala'] ?? 'N/A',
    'id_pelicula' => $reserva['id_pelicula'] ?? 0
];

// Obtener detalles de la película
$pelicula = [
    'titulo' => $funcion['nombre_pelicula'] ?? 'Película Desconocida',
    'imagen_url' => 'placeholder.jpg',
    'duracion_min' => 'N/A',
    'clasificacion' => 'N/A'
];

// Si tenemos un ID de película válido, intentar obtener más detalles
if ($funcion['id_pelicula'] > 0) {
    $peliculaObtenida = obtenerPeliculaPorId($funcion['id_pelicula']);
    if ($peliculaObtenida) {
        // Combinar los datos obtenidos con los valores por defecto
        $pelicula = array_merge($pelicula, $peliculaObtenida);
        
        // Formatear la duración si está disponible
        if (isset($pelicula['duracion_min']) && is_numeric($pelicula['duracion_min'])) {
            $pelicula['duracion_min'] = number_format($pelicula['duracion_min']) . ' min';
        }
    }
}

// Procesar combos CORREGIDO
$combosInfo = [];
$totalCombos = 0;
if (isset($reserva['combos']) && is_string($reserva['combos'])) {
    $combosData = json_decode($reserva['combos'], true);

    if ($combosData) {
        foreach ($combosData as $comboId => $combo) {
            if (($combo['cantidad'] ?? 0) > 0) {
                $combosInfo[] = [
                    'id' => $comboId, // AQUÍ ESTABA EL ERROR PRINCIPAL
                    'nombre' => $combo['nombre'] ?? 'Combo ' . $comboId,
                    'cantidad' => $combo['cantidad'],
                    'precio' => $combo['precio'] ?? 0,
                    'subtotal' => $combo['cantidad'] * ($combo['precio'] ?? 0)
                ];
                $totalCombos += $combo['cantidad'] * ($combo['precio'] ?? 0);
            }
        }
    }
}
if ($funcion['id_pelicula'] > 0) {
    $detallesExtra = obtenerDetallesPelicula($funcion['id_pelicula']);
    if ($detallesExtra) {
        $pelicula = array_merge($pelicula, $detallesExtra);
    }
}

// Calcular totales CORRECTAMENTE
$totalEntradas = count($asientosArray) * $funcion['precio'];
$totalReserva = $reserva['total'];

// Calcular impuestos (18%)
$subtotal = $totalEntradas + $totalCombos;
$impuestos = $subtotal * 0.18;
$totalCalculado = $subtotal + $impuestos;

// Si hay discrepancia, usar el total guardado pero mostrar ambos
$mostrarTotal = ($totalReserva != $totalCalculado) ? $totalReserva : $totalCalculado;

// Generar código único para el boleto
$codigoConfirmacion = strtoupper(uniqid('CC-'));

// Crear datos para el QR con información completa del boleto
$datosQR = [
    'codigo' => $codigoConfirmacion,
    'pelicula' => $pelicula['titulo'],
    'fecha' => $funcion['fecha_formateada'],
    'hora' => $funcion['hora_formateada'],
    'sala' => $funcion['nombre_sala'],
    'asientos' => implode(', ', $asientosArray),
    'total' => $mostrarTotal,
    'entradas' => count($asientosArray)
];

// Convertir datos a JSON para el QR
$datosQRString = json_encode($datosQR);

// URL para generar QR usando API gratuita
$urlQR = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($datosQRString);


function guardarEnHistorial($conn, $id_reserva, $codigoConfirmacion, $pelicula, $funcion, $asientosArray, $combosInfo, $subtotal, $impuestos, $mostrarTotal) {
    // Verificar si hay un usuario logueado
    if (!isset($_SESSION['usuario_correo']) || empty($_SESSION['usuario_correo'])) {
        // No hay usuario logueado, no guardar en historial
        error_log("No se guardará en historial: usuario no logueado");
        return true; // No es un error, simplemente no guardamos
    }
    
    try {
        // Obtener datos del usuario logueado
        $usuario_email = $_SESSION['usuario_correo'];
        $usuario_nombre = $_SESSION['usuario_nombre'] ?? null; // Opcional
        
        // Preparar datos para el historial
        $pelicula_titulo = $pelicula['titulo'];
        $fecha_funcion = $funcion['fecha_formateada'];
        $hora_funcion = $funcion['hora_formateada'];
        $sala = $funcion['nombre_sala'] . ' (' . $funcion['formato_sala'] . ')';
        $asientos_reservados = implode(',', $asientosArray);
        $cantidad_entradas = count($asientosArray);
        $precio_entrada = $funcion['precio'];
        
        // Convertir combos a JSON para almacenar
        $combos_json = null;
        if (!empty($combosInfo)) {
            $combos_json = json_encode($combosInfo, JSON_UNESCAPED_UNICODE);
        }
        
        // SQL para insertar en historial
        $sqlHistorial = "INSERT INTO Historial_Compras (
            usuario_email, usuario_nombre, id_reserva, codigo_confirmacion, 
            pelicula_titulo, fecha_funcion, hora_funcion, sala, 
            asientos_reservados, cantidad_entradas, precio_entrada, 
            combos_info, subtotal, impuestos, total, fecha_compra, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), 'completada')";
        
        $paramsHistorial = [
            $usuario_email,
            $usuario_nombre,
            intval($id_reserva),
            $codigoConfirmacion,
            $pelicula_titulo,
            $fecha_funcion,
            $hora_funcion,
            $sala,
            $asientos_reservados,
            $cantidad_entradas,
            floatval($precio_entrada),
            $combos_json,
            floatval($subtotal),
            floatval($impuestos),
            floatval($mostrarTotal)
        ];
        
        $stmtHistorial = sqlsrv_query($conn, $sqlHistorial, $paramsHistorial);
        if ($stmtHistorial === false) {
            throw new Exception("Error al insertar en historial: " . print_r(sqlsrv_errors(), true));
        }
        
        error_log("Historial guardado exitosamente para usuario: $usuario_email");
        return true;
        
    } catch (Exception $e) {
        error_log("Error al guardar historial: " . $e->getMessage());
        // No lanzamos la excepción porque el historial es secundario
        // La reserva principal debe continuar aunque falle el historial
        return false;
    }
}

$claveReserva = md5($id_funcion . '_' . $asientos . '_' . json_encode($combosInfo ?? []));

if (!isset($_SESSION['reservas_guardadas'][$claveReserva])) {
    try {
        // Verificar conexión
        if (!$conn) {
            throw new Exception("No hay conexión a la base de datos");
        }
        
        // Verificar que los asientos no estén ya reservados en la BD
        $asientosOcupados = [];
        $sqlVerificar = "SELECT ra.asiento 
                        FROM Reserva_Asientos ra 
                        INNER JOIN Reservas r ON ra.id_reserva = r.id_reserva 
                        WHERE r.id_funcion = ? AND ra.asiento IN (" . 
                        str_repeat('?,', count($asientosArray) - 1) . "?) AND r.estado = 'confirmada'";
        
        $paramsVerificar = array_merge([intval($id_funcion)], $asientosArray);
        $stmtVerificar = sqlsrv_query($conn, $sqlVerificar, $paramsVerificar);
        
        if ($stmtVerificar !== false) {
            while ($row = sqlsrv_fetch_array($stmtVerificar, SQLSRV_FETCH_ASSOC)) {
                $asientosOcupados[] = $row['asiento'];
            }
        }
        
        // Si hay asientos ocupados, lanzar error
        if (!empty($asientosOcupados)) {
            throw new Exception("Los siguientes asientos ya están reservados: " . implode(', ', $asientosOcupados));
        }
        
        // Iniciar transacción
        if (sqlsrv_begin_transaction($conn) === false) {
            throw new Exception("Error al iniciar transacción: " . print_r(sqlsrv_errors(), true));
        }
        
        // 1. INSERTAR RESERVA PRINCIPAL
        $sqlReserva = "INSERT INTO Reservas (codigo_confirmacion, id_funcion, fecha_reserva, total, subtotal, impuestos, estado) 
                       OUTPUT INSERTED.id_reserva
                       VALUES (?, ?, GETDATE(), ?, ?, ?, 'confirmada')";
        
        $paramsReserva = [
            $codigoConfirmacion,
            intval($id_funcion),
            floatval($mostrarTotal),
            floatval($subtotal),
            floatval($impuestos)
        ];
        
        $stmtReserva = sqlsrv_query($conn, $sqlReserva, $paramsReserva);
        if ($stmtReserva === false) {
            throw new Exception("Error al insertar reserva: " . print_r(sqlsrv_errors(), true));
        }
        
        // Obtener el ID de la reserva recién insertada
        if (sqlsrv_fetch($stmtReserva)) {
            $id_reserva = sqlsrv_get_field($stmtReserva, 0);
        } else {
            throw new Exception("Error al obtener ID de reserva insertada");
        }
        
        // Validar que se obtuvo el ID
        if (empty($id_reserva) || !is_numeric($id_reserva)) {
            throw new Exception("ID de reserva inválido: " . $id_reserva);
        }
        
        // 2. INSERTAR ASIENTOS RESERVADOS (UNO POR UNO)
        $sqlAsiento = "INSERT INTO Reserva_Asientos (id_reserva, asiento, precio) VALUES (?, ?, ?)";
        $asientosInsertados = 0;
        
        foreach ($asientosArray as $asiento) {
            $asientoLimpio = trim($asiento);
            
            // Validar que el asiento no esté vacío
            if (empty($asientoLimpio)) {
                continue;
            }
            
            $paramsAsiento = [
                intval($id_reserva),
                $asientoLimpio,
                floatval($funcion['precio'])
            ];
            
            $stmtAsiento = sqlsrv_query($conn, $sqlAsiento, $paramsAsiento);
            if ($stmtAsiento === false) {
                throw new Exception("Error al insertar asiento '$asientoLimpio': " . print_r(sqlsrv_errors(), true));
            }
            
            $asientosInsertados++;
        }
        
        // Validar que se insertaron asientos
        if ($asientosInsertados === 0) {
            throw new Exception("No se insertaron asientos en la reserva");
        }
        
        // 3. INSERTAR COMBOS DE COMIDA (SI EXISTEN)
        $combosInsertados = 0;
        
        if (!empty($combosInfo)) {
            $sqlCombo = "INSERT INTO Reserva_Combos (id_reserva, id_combo, nombre_combo, cantidad, precio_unitario, subtotal) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            
            foreach ($combosInfo as $combo) {
                // Validar datos del combo
                if (empty($combo['id']) || empty($combo['nombre']) || $combo['cantidad'] <= 0) {
                    continue;
                }
                
                $paramsCombo = [
                    intval($id_reserva),
                    intval($combo['id']),
                    substr(trim($combo['nombre']), 0, 100), // Truncar a 100 caracteres
                    intval($combo['cantidad']),
                    floatval($combo['precio']),
                    floatval($combo['subtotal'])
                ];
                
                $stmtCombo = sqlsrv_query($conn, $sqlCombo, $paramsCombo);
                if ($stmtCombo === false) {
                    throw new Exception("Error al insertar combo '{$combo['nombre']}': " . print_r(sqlsrv_errors(), true));
                }
                
                $combosInsertados++;
            }
        }
        
        // Confirmar la transacción
        if (sqlsrv_commit($conn) === false) {
            throw new Exception("Error al confirmar transacción: " . print_r(sqlsrv_errors(), true));
        }
        
        // Marcar esta reserva específica como guardada
        if (!isset($_SESSION['reservas_guardadas'])) {
            $_SESSION['reservas_guardadas'] = [];
        }
        $_SESSION['reservas_guardadas'][$claveReserva] = [
            'id_reserva' => $id_reserva,
            'codigo' => $codigoConfirmacion,
            'timestamp' => time()
        ];

        guardarEnHistorial($conn, $id_reserva, $codigoConfirmacion, $pelicula, $funcion, $asientosArray, $combosInfo, $subtotal, $impuestos, $mostrarTotal);

        // Log de éxito detallado
        $mensajeLog = "RESERVA GUARDADA EXITOSAMENTE:\n";
        $mensajeLog .= "- ID Reserva: $id_reserva\n";
        $mensajeLog .= "- Código: $codigoConfirmacion\n";
        $mensajeLog .= "- Asientos insertados: $asientosInsertados\n";
        $mensajeLog .= "- Combos insertados: $combosInsertados\n";
        $mensajeLog .= "- Total: RD$" . number_format($mostrarTotal, 2);
        error_log($mensajeLog);
        
        // Mostrar mensaje de éxito al usuario
        echo "<div style='background:#d4edda; color:#155724; padding:15px; margin:15px 0; border:1px solid #c3e6cb; border-radius:5px;'>";
        echo "<h4><i class='fas fa-check-circle'></i> ¡Reserva guardada correctamente!</h4>";
        echo "<p><strong>ID de Reserva:</strong> $id_reserva</p>";
        echo "<p><strong>Código de Confirmación:</strong> $codigoConfirmacion</p>";
        echo "<p><strong>Asientos reservados:</strong> $asientosInsertados</p>";
        if ($combosInsertados > 0) {
            echo "<p><strong>Combos seleccionados:</strong> $combosInsertados</p>";
        } else {
            echo "<p><strong>Combos:</strong> No seleccionados</p>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        if ($conn) {
            sqlsrv_rollback($conn);
        }
        
        // Registrar error detallado
        $errorCompleto = "ERROR AL GUARDAR RESERVA:\n";
        $errorCompleto .= "- Mensaje: " . $e->getMessage() . "\n";
        $errorCompleto .= "- Código confirmación: " . ($codigoConfirmacion ?? 'N/A') . "\n";
        $errorCompleto .= "- ID función: " . ($id_funcion ?? 'N/A') . "\n";
        $errorCompleto .= "- Asientos: " . implode(', ', $asientosArray ?? []) . "\n";
        $errorCompleto .= "- Combos: " . count($combosInfo ?? []);
        error_log($errorCompleto);
        
        // Mostrar error al usuario con información específica
        echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:15px 0; border:1px solid #f5c6cb; border-radius:5px;'>";
        echo "<h4><i class='fas fa-exclamation-triangle'></i> Error en el sistema</h4>";
        echo "<p><strong>No se pudo guardar la reserva:</strong></p>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        
        // Si el error es por asientos ocupados, mostrar mensaje específico
        if (strpos($e->getMessage(), 'ya están reservados') !== false) {
            echo "<p><strong>Solución:</strong> Por favor, seleccione otros asientos disponibles.</p>";
            echo "<a href='seleccion_asientos.php?id_funcion=$id_funcion' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; display:inline-block; margin-top:10px;'>";
            echo "<i class='fas fa-arrow-left'></i> Seleccionar otros asientos";
            echo "</a>";
        } else {
            echo "<p>Por favor, intente nuevamente o contacte al soporte técnico.</p>";
            echo "<p><strong>Código de referencia:</strong> " . date('Y-m-d H:i:s') . "</p>";
            echo "<div style='text-align:center; margin:20px 0;'>";
            echo "<button onclick='location.reload()' style='background:#007bff; color:white; padding:10px 20px; border:none; border-radius:5px; cursor:pointer;'>";
            echo "<i class='fas fa-redo'></i> Reintentar Guardado";
            echo "</button>";
            echo "</div>";
        }
        echo "</div>";
    }
} else {
    // Esta reserva específica ya fue guardada
    $reservaInfo = $_SESSION['reservas_guardadas'][$claveReserva];
    $reservaId = $reservaInfo['id_reserva'];
    $codigoExistente = $reservaInfo['codigo'];
    
    error_log("Reserva específica ya guardada. ID: $reservaId, Código: $codigoExistente");
    
    echo "<div style='background:#d1ecf1; color:#0c5460; padding:15px; margin:15px 0; border:1px solid #bee5eb; border-radius:5px;'>";
    echo "<h4><i class='fas fa-info-circle'></i> Reserva ya procesada</h4>";
    echo "<p>Esta reserva específica ya fue guardada anteriormente en el sistema.</p>";
    echo "<p><strong>ID de Reserva:</strong> $reservaId</p>";
    echo "<p><strong>Código:</strong> $codigoExistente</p>";
    echo "<p><em>Puede realizar nuevas reservas con diferentes asientos o funciones.</em></p>";
    echo "</div>";
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Reserva - Caribbean Cinemas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
        }

        .logo h1 {
            color: #ffd700;
            font-size: 2rem;
        }

        .confirmation-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .confirmation-header i {
            font-size: 4rem;
            color: #4ecdc4;
            margin-bottom: 15px;
        }

        .confirmation-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ffd700, #ffed4a);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .confirmation-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .confirmation-number {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
        }

        .number-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-top: 15px;
        }

        .number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #ffd700;
        }

        .qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .qr-code {
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .qr-code img {
            display: block;
            border-radius: 5px;
        }

        .qr-label {
            font-size: 0.9rem;
            color: #ccc;
            text-align: center;
        }

        @media (max-width: 768px) {
            .number-container {
                flex-direction: column;
                gap: 20px;
            }
        }

        .details-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .details-container {
                grid-template-columns: 1fr;
            }
        }

        .detail-section {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            padding: 20px;
        }

        .detail-section h2 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 215, 0, 0.3);
            color: #ffd700;
        }

        .movie-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .movie-poster {
            width: 120px;
            height: 180px;
            border-radius: 10px;
            overflow: hidden;
        }

        .movie-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-details {
            flex: 1;
        }

        .movie-details h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-label {
            font-weight: bold;
            color: #ffd700;
        }

        .seats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .seat-badge {
            background: rgba(78, 205, 196, 0.3);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .combos-container {
            margin-top: 15px;
        }

        .combo-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .summary-section {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.1rem;
        }

        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4ecdc4;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            margin-top: 10px;
            padding-top: 15px;
        }

        .btn-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-print {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            border: none;
        }

        .btn-home {
            background: linear-gradient(45deg, #ffd700, #ffed4a);
            color: #333;
            border: none;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .ticket-note {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .ticket-note i {
            color: #ffd700;
            margin-right: 10px;
        }

        /* Estilos específicos para impresión - VERSIÓN MÁS GRANDE */
@media print {
    /* Resetear márgenes de página y forzar contenido en una página */
    @page {
        margin: 0.4in;
        size: A4;
    }
    
    /* Ocultar elementos innecesarios */
    .btn-container {
        display: none !important;
    }
    
    /* Ajustar el contenedor principal */
    body {
        background: white !important;
        color: black !important;
        font-size: 12px !important; /* Aumentado de 10px a 12px */
        line-height: 1.3 !important; /* Aumentado de 1.2 a 1.3 */
        padding: 0 !important;
        margin: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }
    
    .container {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 8px !important; /* Aumentado de 5px a 8px */
        height: auto !important;
    }
    
    /* Ajustar header - más compacto pero más grande */
    header {
        background: white !important;
        color: black !important;
        margin-bottom: 12px !important; /* Aumentado de 8px a 12px */
        padding: 8px !important; /* Aumentado de 5px a 8px */
        border-radius: 0 !important;
    }
    
    .logo h1 {
        color: black !important;
        font-size: 1.5rem !important; /* Aumentado de 1.2rem a 1.5rem */
        margin: 0 !important;
    }
    
    /* Ajustar tarjeta de confirmación - más compacta pero más grande */
    .confirmation-card {
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        border: 1px solid #ccc !important;
        border-radius: 0 !important;
        padding: 12px !important; /* Aumentado de 8px a 12px */
        margin-bottom: 0 !important;
        page-break-inside: avoid !important;
        height: auto !important;
    }
    
    /* Ajustar header de confirmación - más compacto pero más grande */
    .confirmation-header {
        margin-bottom: 12px !important; /* Aumentado de 8px a 12px */
        text-align: center !important;
    }
    
    .confirmation-header i {
        font-size: 2rem !important; /* Aumentado de 1.5rem a 2rem */
        color: black !important;
        margin-bottom: 5px !important; /* Aumentado de 3px a 5px */
    }
    
    .confirmation-header h1 {
        font-size: 1.6rem !important; /* Aumentado de 1.3rem a 1.6rem */
        color: black !important;
        background: none !important;
        -webkit-text-fill-color: black !important;
        margin-bottom: 5px !important; /* Aumentado de 3px a 5px */
    }
    
    .confirmation-header p {
        font-size: 1.1rem !important; /* Aumentado de 0.9rem a 1.1rem */
        opacity: 1 !important;
        color: black !important;
        margin: 0 !important;
    }
    
    /* Ajustar número de confirmación - más compacto pero más grande */
    .confirmation-number {
        background: #f5f5f5 !important;
        color: black !important;
        margin-bottom: 12px !important; /* Aumentado de 8px a 12px */
        padding: 8px !important; /* Aumentado de 5px a 8px */
        border: 1px solid #ddd !important;
        border-radius: 0 !important;
        text-align: center !important;
    }
    
    .number-container {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 15px !important; /* Aumentado de 10px a 15px */
        margin-top: 8px !important; /* Aumentado de 5px a 8px */
    }
    
    .number {
        font-size: 1.3rem !important; /* Aumentado de 1rem a 1.3rem */
        color: black !important;
        font-weight: bold !important;
    }
    
    .qr-container {
        gap: 5px !important; /* Aumentado de 3px a 5px */
    }
    
    .qr-code {
        background: white !important;
        padding: 4px !important; /* Aumentado de 2px a 4px */
        border: 1px solid #ddd !important;
        border-radius: 0 !important;
    }
    
    .qr-code img {
        width: 80px !important; /* Aumentado de 60px a 80px */
        height: 80px !important; /* Aumentado de 60px a 80px */
    }
    
    .qr-label {
        color: black !important;
        font-size: 0.9rem !important; /* Aumentado de 0.7rem a 0.9rem */
    }
    
    /* Ajustar contenedor de detalles - layout horizontal compacto pero más grande */
    .details-container {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 8px !important; /* Aumentado de 5px a 8px */
        margin-bottom: 12px !important; /* Aumentado de 8px a 12px */
    }
    
    .detail-section {
        background: white !important;
        color: black !important;
        border: 1px solid #ddd !important;
        border-radius: 0 !important;
        padding: 8px !important; /* Aumentado de 5px a 8px */
        margin-bottom: 0 !important;
        flex: 1 !important;
        min-width: 45% !important;
    }
    
    .detail-section h2 {
        color: black !important;
        border-bottom: 1px solid #ddd !important;
        margin-bottom: 8px !important; /* Aumentado de 5px a 8px */
        padding-bottom: 4px !important; /* Aumentado de 2px a 4px */
        font-size: 1.1rem !important; /* Aumentado de 0.9rem a 1.1rem */
    }
    
    /* Ajustar información de película - más compacta pero más grande */
    .movie-info {
        display: flex !important;
        gap: 10px !important; /* Aumentado de 8px a 10px */
        margin-bottom: 8px !important; /* Aumentado de 5px a 8px */
    }
    
    .movie-poster {
        width: 50px !important; /* Aumentado de 40px a 50px */
        height: 75px !important; /* Aumentado de 60px a 75px */
    }
    
    .movie-details {
        flex: 1 !important;
    }
    
    .movie-details h3 {
        font-size: 1.1rem !important; /* Aumentado de 0.9rem a 1.1rem */
        color: black !important;
        margin-bottom: 4px !important; /* Aumentado de 2px a 4px */
    }
    
    .movie-details p {
        color: black !important;
        font-size: 0.9rem !important; /* Aumentado de 0.7rem a 0.9rem */
        margin: 2px 0 !important; /* Aumentado de 1px a 2px */
    }
    
    /* Ajustar items de información - más compactos pero más grandes */
    .info-item {
        border-bottom: 1px solid #eee !important;
        padding: 4px 0 !important; /* Aumentado de 2px a 4px */
        font-size: 1rem !important; /* Aumentado de 0.8rem a 1rem */
        display: flex !important;
        justify-content: space-between !important;
    }
    
    .info-label {
        color: black !important;
        font-weight: bold !important;
    }
    
    /* Ajustar asientos - más compactos pero más grandes */
    .seats-container {
        margin-top: 8px !important; /* Aumentado de 5px a 8px */
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 5px !important; /* Aumentado de 3px a 5px */
    }
    
    .seat-badge {
        background: #f0f0f0 !important;
        color: black !important;
        border: 1px solid #ddd !important;
        padding: 3px 6px !important; /* Aumentado de 1px 4px a 3px 6px */
        font-size: 0.9rem !important; /* Aumentado de 0.7rem a 0.9rem */
        border-radius: 0 !important;
    }
    
    /* Ajustar combos - más compactos pero más grandes */
    .combo-item {
        padding: 4px 0 !important; /* Aumentado de 2px a 4px */
        font-size: 1rem !important; /* Aumentado de 0.8rem a 1rem */
        color: black !important;
    }
    
    /* Ajustar sección de resumen - más compacta pero más grande */
    .summary-section {
        background: #f9f9f9 !important;
        border: 1px solid #ddd !important;
        border-radius: 0 !important;
        padding: 8px !important; /* Aumentado de 5px a 8px */
        margin-top: 8px !important; /* Aumentado de 5px a 8px */
    }
    
    .summary-item {
        padding: 4px 0 !important; /* Aumentado de 2px a 4px */
        font-size: 1rem !important; /* Aumentado de 0.8rem a 1rem */
        color: black !important;
    }
    
    .total {
        font-size: 1.2rem !important; /* Aumentado de 0.9rem a 1.2rem */
        color: black !important;
        border-top: 1px solid #ddd !important;
        margin-top: 5px !important; /* Aumentado de 3px a 5px */
        padding-top: 8px !important; /* Aumentado de 5px a 8px */
    }
    
    /* Ajustar nota del ticket - más compacta pero más grande */
    .ticket-note {
        background: #f0f0f0 !important;
        border: 1px solid #ddd !important;
        color: black !important;
        border-radius: 0 !important;
        padding: 8px !important; /* Aumentado de 5px a 8px */
        margin-top: 8px !important; /* Aumentado de 5px a 8px */
        font-size: 0.9rem !important; /* Aumentado de 0.7rem a 0.9rem */
        text-align: center !important;
    }
    
    .ticket-note i {
        color: black !important;
    }
    
    /* Forzar todo en una página */
    * {
        page-break-inside: avoid !important;
    }
    
    .container {
        page-break-after: avoid !important;
        page-break-before: avoid !important;
    }
    
    .confirmation-card {
        page-break-after: avoid !important;
        page-break-before: avoid !important;
    }
    
    /* Reducir espacios entre elementos pero mantener legibilidad */
    .details-container > * {
        margin-bottom: 0 !important;
    }
    
    h2 {
        margin-top: 0 !important;
        margin-bottom: 8px !important; /* Aumentado de 5px a 8px */
    }
    
    p {
        margin-top: 0 !important;
        margin-bottom: 4px !important; /* Aumentado de 2px a 4px */
    }
}
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo">
                <h1><i class="fas fa-film"></i> Caribbean Cinemas</h1>
            </div>
        </header>

        <div class="confirmation-card">
            <div class="confirmation-header">
                <i class="fas fa-check-circle"></i>
                <h1>¡Reserva Confirmada!</h1>
                <p>Gracias por tu compra. A continuación los detalles de tu reserva.</p>
            </div>

            <div class="confirmation-number">
                <p>Número de confirmación:</p>
                <div class="number-container">
                    <div class="number"><?= $codigoConfirmacion ?></div>
                    <div class="qr-container">
                        <div class="qr-code">
                            <img src="<?= $urlQR ?>" alt="Código QR del boleto" onerror="this.style.display='none'">
                        </div>
                        <div class="qr-label">Escanea para ver detalles</div>
                    </div>
                </div>
            </div>

            <div class="details-container">
                <div class="detail-section">
                    <h2><i class="fas fa-ticket-alt"></i> Detalles de la Función</h2>

                    <div class="movie-info">
                        <div class="movie-poster">
                            <img src="<?= $pelicula['imagen_url'] ?? 'placeholder.jpg' ?>" alt="<?= $pelicula['titulo'] ?>">
                        </div>
                        <div class="movie-details">
                            <h3><?= $pelicula['titulo'] ?></h3>
                            <p><i class="fas fa-clock"></i> <?= $pelicula['duracion_min'] ?? 'N/A' ?></p>
                            <p><i class="fas fa-certificate"></i> <?= $pelicula['clasificacion'] ?? 'N/A' ?></p>
                        </div>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Fecha y Hora:</span>
                        <span><?= $funcion['fecha_formateada'] ?? 'N/A' ?> a las <?= $funcion['hora_formateada'] ?? 'N/A' ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Sala:</span>
                        <span><?= $funcion['nombre_sala'] ?? 'N/A' ?> (<?= $funcion['formato_sala'] ?? 'N/A' ?>)</span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Asientos:</span>
                        <span><?= count($asientosArray) ?></span>
                    </div>

                    <div class="seats-container">
                        <?php foreach ($asientosArray as $asiento): ?>
                            <div class="seat-badge"><?= $asiento ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="detail-section">
                    <h2><i class="fas fa-receipt"></i> Detalles de Pago</h2>

                    <div class="info-item">
                        <span class="info-label">Entradas:</span>
                        <span>RD$<?= number_format($totalEntradas, 2) ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Cantidad:</span>
                        <span><?= count($asientosArray) ?> x RD$<?= number_format($funcion['precio'], 2) ?></span>
                    </div>

                    <?php if (!empty($combosInfo)): ?>
                        <h2 style="margin-top: 20px;"><i class="fas fa-utensils"></i> Combos de Comida</h2>
                        <div class="combos-container">
                            <?php foreach ($combosInfo as $combo): ?>
                                <div class="combo-item">
                                    <span><?= $combo['nombre'] ?> x<?= $combo['cantidad'] ?></span>
                                    <span>RD$<?= number_format($combo['subtotal'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="summary-section">
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span>RD$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="summary-item">
                            <span>Impuestos (18%):</span>
                            <span>RD$<?= number_format($impuestos, 2) ?></span>
                        </div>
                        <div class="summary-item total">
                            <span>Total:</span>
                            <span>RD$<?= number_format($mostrarTotal, 2) ?></span>
                        </div>

                        <?php if ($totalReserva != $totalCalculado): ?>
                            <div class="summary-item" style="color: #ff9800; font-style: italic;">
                                <span>Nota:</span>
                                <span>El total difiere del cálculo (RD$<?= number_format($totalCalculado, 2) ?>)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ticket-note">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Presenta este código QR o el número de confirmación en taquilla para retirar tus boletos.
            </div>
        </div>

        <div class="btn-container">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir Confirmación
            </button>
            <a href="cartelera.php" class="btn btn-home">
                <i class="fas fa-home"></i> Volver a Cartelera
            </a>
        </div>
    </div>
</body>

</html>
<?php
// Limpiar la reserva de la sesión después de mostrar la confirmación
unset($_SESSION['reserva']);
?>