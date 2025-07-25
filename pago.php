<?php
// pago.php
include 'conexion.php';
include 'funciones_bd.php';
session_start();
limpiarErroresSesion();

// Verificar que hay una reserva en proceso
if (!isset($_SESSION['reserva'])) {
    header('Location: cartelera.php');
    exit;
}

$reserva = $_SESSION['reserva'];
$total = $reserva['total'];
$asientos = str_replace(',', ', ', $_SESSION['reserva']['asientos']);

// Procesar combos
$combos_desc = [];
if (!empty($reserva['combos'])) {
    $combos_data = json_decode($reserva['combos'], true);
    foreach ($combos_data as $combo) {
        if ($combo['cantidad'] > 0) {
            $combos_desc[] = "{$combo['nombre']} x{$combo['cantidad']}";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago - Caribbean Cinemas</title>
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
            max-width: 800px;
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

        .resumen-reserva {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .payment-container {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }

        .card-group {
            display: flex;
            gap: 15px;
        }

        .card-group>div {
            flex: 1;
        }

        .btn-pagar {
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

        .btn-pagar:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo">
                <img src="images/logo.jpg" alt="Caribbean Cinemas">
            </div>
            <h1><i class="fas fa-credit-card"></i> Información de Pago</h1>
        </header>

        <div class="resumen-reserva">
            <h2>Resumen de tu Reserva</h2>
            <p>Asientos seleccionados: <?= $asientos ?></p>
            <?php if (!empty($combos_desc)): ?>
                <p>Combos: <?= implode(', ', $combos_desc) ?></p>
            <?php endif; ?>
            <p>Total a pagar: <strong>RD$<?= number_format($total, 2) ?></strong></p>
        </div>

        <div class="payment-container">
            <form id="payment-form" action="confirmacion_reserva.php" method="post">
                <h2>Datos del Titular</h2>
                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre Completo</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" required>
                </div>

                <h2>Información de Pago</h2>
                <div class="form-group">
                    <label for="tarjeta"><i class="fas fa-credit-card"></i> Número de Tarjeta</label>
                    <input type="text" id="tarjeta" name="tarjeta" placeholder="1234 5678 9012 3456" required>
                </div>

                <div class="card-group">
                    <div class="form-group">
                        <label for="vencimiento"><i class="fas fa-calendar"></i> Fecha de Vencimiento</label>
                        <input type="text" id="vencimiento" name="vencimiento" placeholder="MM/AA" required>
                    </div>

                    <div class="form-group">
                        <label for="cvv"><i class="fas fa-lock"></i> CVV</label>
                        <input type="text" id="cvv" name="cvv" placeholder="123" required>
                    </div>
                </div>

                <!-- ... dentro del formulario, antes del botón de pago -->
                <input type="hidden" name="nombre_pelicula" value="<?= $reserva['nombre_pelicula'] ?>">
                <input type="hidden" name="fecha_formateada" value="<?= $reserva['fecha_formateada'] ?>">
                <input type="hidden" name="hora_formateada" value="<?= $reserva['hora_formateada'] ?>">
                <input type="hidden" name="nombre_sala" value="<?= $reserva['nombre_sala'] ?>">
                <input type="hidden" name="formato_sala" value="<?= $reserva['formato_sala'] ?>">
                <input type="hidden" name="precio_unitario" value="<?= $reserva['precio_unitario'] ?>">
                <input type="hidden" name="id_pelicula" value="<?= $reserva['id_pelicula'] ?>">

                <button type="submit" class="btn-pagar">
                    <i class="fas fa-lock"></i> Pagar RD$<?= number_format($total, 2) ?>
                </button>
            </form>
        </div>
    </div>
</body>

</html>