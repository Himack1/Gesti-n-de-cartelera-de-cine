<?php
// procesar_reserva.php
session_start();

include 'conexion.php';
include 'funciones_bd.php';
limpiarErroresSesion();

/**
 * Obtiene los asientos reservados para una función específica
 * @param int $id_funcion ID de la función
 * @return array Array con los asientos reservados
 */
function obtenerAsientosReservados($id_funcion) {
    global $conn;
    
    $asientos_reservados = [];
    
    // Verificar que la conexión existe
    if (!$conn) {
        error_log("Error: Conexión a la base de datos no disponible");
        return $asientos_reservados;
    }
    
    try {
        // Consulta para obtener todos los asientos reservados de una función
        $sql = "SELECT DISTINCT ra.asiento 
                FROM Reserva_Asientos ra 
                INNER JOIN Reservas r ON ra.id_reserva = r.id_reserva 
                WHERE r.id_funcion = ? 
                AND r.estado = 'confirmada'
                ORDER BY ra.asiento";

        $stmt = sqlsrv_prepare($conn, $sql, array($id_funcion));

        if ($stmt && sqlsrv_execute($stmt)) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $asientos_reservados[] = $row['asiento'];
            }
        } else {
            // Log del error para debugging
            $errors = sqlsrv_errors();
            error_log("Error al obtener asientos reservados: " . print_r($errors, true));
        }
        
    } catch (Exception $e) {
        error_log("Excepción al obtener asientos reservados: " . $e->getMessage());
    }
    
    return $asientos_reservados;
}

/**
 * Verifica si una función está completamente llena
 * @param int $id_funcion ID de la función
 * @param int $total_asientos Total de asientos en la sala (por defecto 60: 6 filas x 10 columnas)
 * @return bool true si está llena, false si no
 */
function verificarFuncionLlena($id_funcion, $total_asientos = 60) {
    $asientos_reservados = obtenerAsientosReservados($id_funcion);
    return count($asientos_reservados) >= $total_asientos;
}

/**
 * Obtiene el conteo de asientos reservados y disponibles
 * @param int $id_funcion ID de la función
 * @param int $total_asientos Total de asientos en la sala
 * @return array Array con información de ocupación
 */
function obtenerEstadoOcupacion($id_funcion, $total_asientos = 60) {
    $asientos_reservados = obtenerAsientosReservados($id_funcion);
    $reservados = count($asientos_reservados);
    $disponibles = $total_asientos - $reservados;
    
    return [
        'total' => $total_asientos,
        'reservados' => $reservados,
        'disponibles' => $disponibles,
        'porcentaje_ocupacion' => round(($reservados / $total_asientos) * 100, 2),
        'asientos_reservados' => $asientos_reservados,
        'esta_llena' => $reservados >= $total_asientos
    ];
}

// Verificar la conexión a la base de datos
if (!$conn) {
    die("
        <div style='color: white; background: #d32f2f; padding: 30px; text-align: center; border-radius: 10px; margin: 50px;'>
            <h2><i class='fas fa-exclamation-triangle'></i> Error de Conexión</h2>
            <p>No se pudo establecer conexión con la base de datos.</p>
            <p>Por favor, verifique la configuración de la base de datos.</p>
        </div>
    ");
}

// Obtener id_funcion de la URL o del POST
$id_funcion = $_GET['funcion'] ?? $_POST['id_funcion'] ?? 0;

// Validar que sea un número válido
if (!is_numeric($id_funcion) || $id_funcion <= 0) {
    $error_message = "No se ha especificado una función válida para reservar.";

    // Si hay un error en sesión, usarlo como mensaje principal
    if (isset($_SESSION['error']) && !empty($_SESSION['error'])) {
        $error_message = $_SESSION['error'];
        unset($_SESSION['error']);
    }

    die("
        <div style='color: white; background: #d32f2f; padding: 30px; text-align: center; border-radius: 10px; margin: 50px;'>
            <h2><i class='fas fa-exclamation-triangle'></i> Error en la reserva</h2>
            <p>$error_message</p>
            <p>Por favor seleccione una función desde la <a href='cartelera.php' style='color: #ffcc00;'>cartelera de películas</a>.</p>
            <p>ID de función recibido: " . htmlspecialchars($id_funcion) . "</p>
        </div>
    ");
}

$funcion = obtenerFuncionPorId($id_funcion);

// Si no se pudo obtener la función, mostrar error y detener
if (!$funcion) {
    $error_message = "No se pudo cargar la información de la función con ID: $id_funcion";

    // Si hay un error específico en sesión, usarlo
    if (isset($_SESSION['error']) && !empty($_SESSION['error'])) {
        $error_message = $_SESSION['error'];
        unset($_SESSION['error']);
    }

    die("<div style='color: white; background: red; padding: 20px; text-align: center;'>
        <h2>Error: Función no encontrada</h2>
        <p>$error_message</p>
        <p><a href='cartelera.php' style='color: white;'>Volver a la cartelera</a></p>
    </div>");
}

// Verificar si la función está llena ANTES de mostrar la página
$estado_ocupacion = obtenerEstadoOcupacion($id_funcion);

if ($estado_ocupacion['esta_llena']) {
    die("
        <div style='color: white; background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%); padding: 40px; text-align: center; border-radius: 15px; margin: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);'>
            <h2><i class='fas fa-exclamation-circle' style='font-size: 3rem; color: #ffcc00; margin-bottom: 20px;'></i></h2>
            <h2 style='margin-bottom: 20px;'>¡FUNCIÓN LLENA!</h2>
            <p style='font-size: 1.2rem; margin-bottom: 20px;'>Lo sentimos, todos los asientos para esta función ya están reservados.</p>
            <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3>{$funcion['nombre_pelicula']}</h3>
                <p><i class='fas fa-calendar'></i> {$funcion['fecha_formateada']} <i class='fas fa-clock'></i> {$funcion['hora_formateada']}</p>
                <p><i class='fas fa-theater-masks'></i> {$funcion['nombre_sala']} ({$funcion['formato_sala']})</p>
                <p><strong>Ocupación: {$estado_ocupacion['reservados']}/{$estado_ocupacion['total']} asientos</strong></p>
            </div>
            <p style='margin-bottom: 30px;'>Te sugerimos:</p>
            <div style='display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;'>
                <a href='cartelera.php' style='background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; transition: all 0.3s;'>
                    <i class='fas fa-film'></i> Ver otras funciones
                </a>
                <a href='cartelera.php?pelicula={$funcion['id_pelicula']}' style='background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; transition: all 0.3s;'>
                    <i class='fas fa-clock'></i> Otros horarios
                </a>
            </div>
        </div>
        <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    ");
}

// Definir combos de comida disponibles
$combos = [
    [
        'id' => 1,
        'nombre' => 'Combo Clásico',
        'descripcion' => 'Palomitas medianas + refresco mediano',
        'precio' => 250,
        'imagen' => 'images/combo_clasico.jpg'
    ],
    [
        'id' => 2,
        'nombre' => 'Combo Familiar',
        'descripcion' => 'Palomitas grandes + 2 refrescos grandes + nachos',
        'precio' => 550,
        'imagen' => 'images/combo_familiar.jpg'
    ],
    [
        'id' => 3,
        'nombre' => 'Combo Dulce',
        'descripcion' => 'Palomitas medianas + refresco mediano + chocolates',
        'precio' => 350,
        'imagen' => 'images/combo_dulce.jpg'
    ],
    [
        'id' => 4,
        'nombre' => 'Combo Individual',
        'descripcion' => 'Palomitas pequeñas + refresco pequeño',
        'precio' => 180,
        'imagen' => 'images/combo_individual.jpg'
    ]
];

// Si no se ha enviado el formulario de asientos
if (!isset($_POST['asientos'])) {
    // Obtener asientos ya reservados usando la nueva función
    $reservados = $estado_ocupacion['asientos_reservados'];

    // Configuración de la sala
    $filas = range('A', 'F');
    $columnas = range(1, 10);
}
// Si se enviaron asientos
else {
    // Asegurarnos de que tenemos el ID de función
    $id_funcion = $_POST['id_funcion'] ?? $id_funcion;

    $_SESSION['reserva'] = [
        'id_funcion' => $id_funcion,
        'asientos' => $_POST['asientos'],
        'combos' => $_POST['combos'] ?? [],
        'total' => $_POST['total'],
        // Añadir datos críticos para la confirmación
        'nombre_pelicula' => $funcion['nombre_pelicula'],
        'fecha_formateada' => $funcion['fecha_formateada'],
        'hora_formateada' => $funcion['hora_formateada'],
        'nombre_sala' => $funcion['nombre_sala'],
        'formato_sala' => $funcion['formato_sala'],
        'precio_unitario' => $funcion['precio'],
        'id_pelicula' => $funcion['id_pelicula']
    ];
    header('Location: pago.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Asientos y Combos - Caribbean Cinemas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 20px;
        }

        .logo img {
            max-height: 80px;
        }

        .movie-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .ocupacion-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .ocupacion-bar {
            width: 100%;
            height: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }

        .ocupacion-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50 0%, #FF9800 50%, #f44336 100%);
            transition: width 0.3s ease;
        }

        .sala-container {
            background: rgba(0, 0, 0, 0.5);
            padding: 30px;
            border-radius: 15px;
            max-width: 800px;
            margin: 0 auto 30px;
        }

        .screen {
            height: 30px;
            background: #ddd;
            border-radius: 50%;
            margin-bottom: 40px;
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
            text-align: center;
            line-height: 30px;
            color: #333;
            font-weight: bold;
        }

        .combos-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .combos-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .combo-card {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .combo-card.selected {
            background: rgba(33, 150, 243, 0.3);
            border: 2px solid #2196F3;
            transform: translateY(-5px);
        }

        .combo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .combo-image {
            width: 100%;
            height: 290px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .combo-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .combo-desc {
            font-size: 0.9rem;
            color: #ccc;
            margin-bottom: 10px;
            min-height: 40px;
        }

        .combo-price {
            font-weight: bold;
            font-size: 1.2rem;
            color: #4ecdc4;
        }

        .combo-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .combo-controls button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: #4ecdc4;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .combo-quantity {
            width: 40px;
            text-align: center;
            background: white;
            border: none;
            border-radius: 5px;
            padding: 5px;
            color: #333;
        }

        .selection-summary {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
        }

        .btn-continue {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
            margin: 30px auto;
            width: 300px;
            text-align: center;
        }

        .btn-continue:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-continue:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 3px;
        }

        .available {
            background: #4CAF50;
        }

        .selected {
            background: #2196F3;
        }

        .reserved {
            background: #f44336;
        }

        /* Nuevos estilos para la disposición de asientos */
        .rows-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .seat-row {
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .seat {
            width: 40px;
            height: 40px;
            background: #4CAF50;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .seat.reserved {
            background: #f44336;
            cursor: not-allowed;
        }

        .seat.selected {
            background: #2196F3;
            transform: scale(1.1);
        }

        .seat-number {
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        /* Elimina el ícono de silla */
        .seat i {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo">
                <img src="images/logo.jpg" alt="Caribbean Cinemas">
            </div>
            <h1><i class="fas fa-ticket-alt"></i> Selección de Asientos y Combos</h1>
        </header>

        <div class="movie-info">
            <h2><?= $funcion['nombre_pelicula'] ?? 'Película no disponible' ?></h2>
            <p><i class="fas fa-calendar"></i> <?= $funcion['fecha_formateada'] ?? 'Fecha no disponible' ?>
                <i class="fas fa-clock"></i> <?= $funcion['hora_formateada'] ?? 'Hora no disponible' ?>
            </p>
            <p><i class="fas fa-theater-masks"></i> <?= $funcion['nombre_sala'] ?? 'Sala no disponible' ?> (<?= $funcion['formato_sala'] ?? 'N/A' ?>)</p>
            <p><i class="fas fa-money-bill-wave"></i> Precio por asiento: RD$<?= number_format($funcion['precio'] ?? 0, 2) ?></p>
            
            <!-- Información de ocupación -->
            <div class="ocupacion-info">
                <p><i class="fas fa-users"></i> Ocupación: <?= $estado_ocupacion['reservados'] ?>/<?= $estado_ocupacion['total'] ?> asientos 
                   (<?= $estado_ocupacion['porcentaje_ocupacion'] ?>%)</p>
                <div class="ocupacion-bar">
                    <div class="ocupacion-fill" style="width: <?= $estado_ocupacion['porcentaje_ocupacion'] ?>%"></div>
                </div>
                <p><i class="fas fa-chair"></i> Asientos disponibles: <?= $estado_ocupacion['disponibles'] ?></p>
            </div>
        </div>

        <div class="sala-container">
            <div class="screen">PANTALLA</div>

            <!-- Contenedor de filas de asientos -->
            <div class="rows-container">
                <?php foreach ($filas as $fila): ?>
                    <div class="seat-row">
                        <?php foreach ($columnas as $col): ?>
                            <?php
                            $asientoId = $fila . $col;
                            $reservado = in_array($asientoId, $reservados);
                            ?>
                            <div class="seat <?= $reservado ? 'reserved' : '' ?>"
                                data-asiento="<?= $asientoId ?>">
                                <span class="seat-number"><?= $asientoId ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-box available"></div>
                    <span>Disponible</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box selected"></div>
                    <span>Seleccionado</span>
                </div>
                <div class="legend-item">
                    <div class="legend-box reserved"></div>
                    <span>Ocupado</span>
                </div>
            </div>
        </div>

        <div class="combos-section">
            <h2><i class="fas fa-utensils"></i> Combos de Comida</h2>
            <p>Selecciona los combos que deseas agregar a tu reserva:</p>

            <div class="combos-container">
                <?php foreach ($combos as $combo): ?>
                    <div class="combo-card" data-combo="<?= $combo['id'] ?>">
                        <div class="combo-image" style="background-image: url('<?= $combo['imagen'] ?>');"></div>
                        <div class="combo-name"><?= $combo['nombre'] ?></div>
                        <div class="combo-desc"><?= $combo['descripcion'] ?></div>
                        <div class="combo-price">RD$<?= number_format($combo['precio'], 2) ?></div>

                        <div class="combo-controls">
                            <button type="button" class="btn-minus" data-combo="<?= $combo['id'] ?>">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="combo_<?= $combo['id'] ?>"
                                name="combos[<?= $combo['id'] ?>]"
                                class="combo-quantity"
                                value="0" min="0" readonly>
                            <button type="button" class="btn-plus" data-combo="<?= $combo['id'] ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="selection-summary">
            <h3>Resumen de Selección</h3>
            <p>Asientos seleccionados: <span id="selected-seats">Ninguno</span></p>
            <p>Combos seleccionados: <span id="selected-combos">Ninguno</span></p>
            <p>Total: RD$<span id="total-price">0.00</span></p>
        </div>

        <form id="reservation-form" action="procesar_reserva.php?funcion=<?= $id_funcion ?>" method="post">
            <input type="hidden" name="id_funcion" value="<?= $id_funcion ?>">
            <input type="hidden" name="asientos" id="asientos-input">
            <input type="hidden" name="combos" id="combos-input">
            <input type="hidden" name="total" id="total-input">

            <button type="submit" class="btn-continue" disabled>
                Continuar al Pago <i class="fas fa-arrow-right"></i>
            </button>
        </form>
    </div>

    <script>
        const precioPorAsiento = <?= $funcion['precio'] ?>;
        const asientos = document.querySelectorAll('.seat:not(.reserved)');
        const selectedSeatsDisplay = document.getElementById('selected-seats');
        const selectedCombosDisplay = document.getElementById('selected-combos');
        const totalPriceDisplay = document.getElementById('total-price');
        const asientosInput = document.getElementById('asientos-input');
        const combosInput = document.getElementById('combos-input');
        const totalInput = document.getElementById('total-input');
        const btnContinue = document.querySelector('.btn-continue');

        let selectedSeats = [];
        let selectedCombos = {};

        <?php foreach ($combos as $combo): ?>
            selectedCombos[<?= $combo['id'] ?>] = {
                id: <?= $combo['id'] ?>,
                nombre: "<?= $combo['nombre'] ?>",
                precio: <?= $combo['precio'] ?>,
                cantidad: 0
            };
        <?php endforeach; ?>

        // Manejo de asientos
        asientos.forEach(seat => {
            seat.addEventListener('click', () => {
                const asientoId = seat.getAttribute('data-asiento');

                if (seat.classList.contains('selected')) {
                    // Deseleccionar
                    seat.classList.remove('selected');
                    selectedSeats = selectedSeats.filter(id => id !== asientoId);
                } else {
                    // Seleccionar
                    seat.classList.add('selected');
                    selectedSeats.push(asientoId);
                }

                updateSummary();
            });
        });

        // Manejo de combos
        document.querySelectorAll('.btn-plus').forEach(btn => {
            btn.addEventListener('click', () => {
                const comboId = btn.getAttribute('data-combo');
                const input = document.getElementById(`combo_${comboId}`);
                input.value = parseInt(input.value) + 1;
                selectedCombos[comboId].cantidad = parseInt(input.value);

                // Resaltar combo seleccionado
                const comboCard = btn.closest('.combo-card');
                comboCard.classList.add('selected');

                updateSummary();
            });
        });

        document.querySelectorAll('.btn-minus').forEach(btn => {
            btn.addEventListener('click', () => {
                const comboId = btn.getAttribute('data-combo');
                const input = document.getElementById(`combo_${comboId}`);
                const value = parseInt(input.value);

                if (value > 0) {
                    input.value = value - 1;
                    selectedCombos[comboId].cantidad = parseInt(input.value);

                    // Quitar resaltado si no hay cantidad
                    if (parseInt(input.value) === 0) {
                        const comboCard = btn.closest('.combo-card');
                        comboCard.classList.remove('selected');
                    }

                    updateSummary();
                }
            });
        });

        function updateSummary() {
            // Calcular total de asientos
            const asientosTotal = selectedSeats.length * precioPorAsiento;
            selectedSeatsDisplay.textContent = selectedSeats.join(', ') || 'Ninguno';
            asientosInput.value = selectedSeats.join(',');

            // Calcular total de combos y crear descripción
            let combosTotal = 0;
            let combosDesc = [];

            for (const comboId in selectedCombos) {
                const combo = selectedCombos[comboId];
                if (combo.cantidad > 0) {
                    combosTotal += combo.cantidad * combo.precio;
                    combosDesc.push(`${combo.nombre} x${combo.cantidad}`);
                }
            }

            selectedCombosDisplay.textContent = combosDesc.join(', ') || 'Ninguno';
            combosInput.value = JSON.stringify(selectedCombos);

            // Calcular total general CON IMPUESTOS (18%)
            const subtotal = asientosTotal + combosTotal;
            const impuestos = subtotal * 0.18;
            const total = subtotal + impuestos;

            // Mostrar en la interfaz
            totalPriceDisplay.textContent = total.toFixed(2);
            totalInput.value = total;

            // Habilitar botón si hay asientos seleccionados
            btnContinue.disabled = selectedSeats.length === 0;
        }
    </script>
</body>

</html>