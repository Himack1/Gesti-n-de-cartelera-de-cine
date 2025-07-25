<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo contiene la configuración de conexión a la base de datos

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_correo']) || empty($_SESSION['usuario_correo'])) {
    header("Location: login\login.html");
    exit();
}


$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

$usuario_email = $_SESSION['usuario_correo'];
$usuario_nombre = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';

// Consulta para obtener el historial de compras del usuario
$sql = "SELECT * FROM Historial_Compras 
        WHERE usuario_email = ? 
        ORDER BY fecha_compra DESC";

$params = array($usuario_email);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$historial = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $historial[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras - Cinema</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2d3748;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header p {
            color: #718096;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .clock-icon {
            animation: tick 2s infinite;
        }

        @keyframes tick {
            0%, 50%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(6deg); }
            75% { transform: rotate(-6deg); }
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #4299e1;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
        }

        .back-btn:hover {
            background: #3182ce;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.4);
            text-decoration: none;
            color: white;
        }

        .historial-grid {
            display: grid;
            gap: 25px;
        }

        .compra-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .compra-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .compra-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pelicula-info h3 {
            color: #2d3748;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .codigo-confirmacion {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .detalles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detalle-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: rgba(247, 250, 252, 0.8);
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .detalle-icon {
            width: 24px;
            height: 24px;
            color: #667eea;
        }

        .detalle-content h4 {
            color: #4a5568;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .detalle-content p {
            color: #2d3748;
            font-weight: 600;
            font-size: 1rem;
        }

        .asientos {
            margin: 20px 0;
            padding: 20px;
            background: rgba(247, 250, 252, 0.8);
            border-radius: 12px;
            border-left: 4px solid #ed8936;
        }

        .asientos h4 {
            color: #ed8936;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .asientos-lista {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .asiento-tag {
            background: #ed8936;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .combos-section {
            margin: 20px 0;
            padding: 20px;
            background: rgba(247, 250, 252, 0.8);
            border-radius: 12px;
            border-left: 4px solid #9f7aea;
        }

        .combos-section h4 {
            color: #9f7aea;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .combo-item {
            background: white;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 3px solid #9f7aea;
        }

        .total-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-top: 20px;
        }

        .total-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            text-align: center;
        }

        .total-item h4 {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .total-item p {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .estado {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .estado.completada {
            background: rgba(72, 187, 120, 0.2);
            color: #38a169;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            color: #a0aec0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #4a5568;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #718096;
            font-size: 1.1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .compra-card {
                padding: 20px;
            }
            
            .detalles-grid {
                grid-template-columns: 1fr;
            }
            
            .total-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="perfil.php" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Volver al Perfil
        </a>

        <div class="header">
            <h1>
                <svg class="clock-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="#667eea" stroke-width="2"/>
                    <polyline points="12,6 12,12 16,14" stroke="#667eea" stroke-width="2"/>
                </svg>
                Historial de Compras
            </h1>
            <p>Bienvenido/a <?php echo htmlspecialchars($usuario_nombre); ?> - Aquí puedes ver todas tus compras anteriores</p>
        </div>

        <div class="historial-grid">
            <?php if (empty($historial)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 11H15M9 15H15M21 5V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h3>No tienes compras registradas</h3>
                    <p>Cuando realices tu primera compra, aparecerá aquí tu historial completo.</p>
                </div>
            <?php else: ?>
                <?php foreach ($historial as $compra): ?>
                    <div class="compra-card">
                        <div class="compra-header">
                            <div class="pelicula-info">
                                <h3><?php echo htmlspecialchars($compra['pelicula_titulo']); ?></h3>
                                <div class="estado completada">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <?php echo ucfirst($compra['estado']); ?>
                                </div>
                            </div>
                            <div class="codigo-confirmacion">
                                #<?php echo htmlspecialchars($compra['codigo_confirmacion']); ?>
                            </div>
                        </div>

                        <div class="detalles-grid">
                            <div class="detalle-item">
                                <svg class="detalle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/>
                                    <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <div class="detalle-content">
                                    <h4>Fecha Función</h4>
                                    <p><?php echo htmlspecialchars($compra['fecha_funcion']); ?></p>
                                </div>
                            </div>

                            <div class="detalle-item">
                                <svg class="detalle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                    <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <div class="detalle-content">
                                    <h4>Hora</h4>
                                    <p><?php echo htmlspecialchars($compra['hora_funcion']); ?></p>
                                </div>
                            </div>

                            <div class="detalle-item">
                                <svg class="detalle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M21 16V8C20.9996 7.64927 20.9071 7.30481 20.7315 7.00116C20.556 6.69751 20.3037 6.44536 20 6.27L13 2.27C12.696 2.09446 12.3511 2.00205 12 2.00205C11.6489 2.00205 11.304 2.09446 11 2.27L4 6.27C3.69626 6.44536 3.44398 6.69751 3.26846 7.00116C3.09294 7.30481 3.00036 7.64927 3 8V16C3.00036 16.3507 3.09294 16.6952 3.26846 16.9988C3.44398 17.3025 3.69626 17.5546 4 17.73L11 21.73C11.304 21.9055 11.6489 21.9979 12 21.9979C12.3511 21.9979 12.696 21.9055 13 21.73L20 17.73C20.3037 17.5546 20.556 17.3025 20.7315 16.9988C20.9071 16.6952 20.9996 16.3507 21 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div class="detalle-content">
                                    <h4>Sala</h4>
                                    <p><?php echo htmlspecialchars($compra['sala']); ?></p>
                                </div>
                            </div>

                            <div class="detalle-item">
                                <svg class="detalle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M16 11V7C16 5.93913 15.5786 4.92172 14.8284 4.17157C14.0783 3.42143 13.0609 3 12 3C10.9391 3 9.92172 3.42143 9.17157 4.17157C8.42143 4.92172 8 5.93913 8 7V11M5 11H19L20 21H4L5 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div class="detalle-content">
                                    <h4>Entradas</h4>
                                    <p><?php echo $compra['cantidad_entradas']; ?> tickets</p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($compra['asientos_reservados'])): ?>
                            <div class="asientos">
                                <h4>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M19 9V6C19 5.46957 18.7893 4.96086 18.4142 4.58579C18.0391 4.21071 17.5304 4 17 4H7C6.46957 4 5.96086 4.21071 5.58579 4.58579C5.21071 4.96086 5 5.46957 5 6V9C5 9.53043 5.21071 10.0391 5.58579 10.4142C5.96086 10.7893 6.46957 11 7 11H17C17.5304 11 18.0391 10.7893 18.4142 10.4142C18.7893 10.0391 19 9.53043 19 9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M7 11V17C7 17.5304 7.21071 18.0391 7.58579 18.4142C7.96086 18.7893 8.46957 19 9 19H15C15.5304 19 16.0391 18.7893 16.4142 18.4142C16.7893 18.0391 17 17.5304 17 17V11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Asientos Reservados
                                </h4>
                                <div class="asientos-lista">
                                    <?php 
                                    $asientos = explode(',', $compra['asientos_reservados']);
                                    foreach ($asientos as $asiento): 
                                    ?>
                                        <span class="asiento-tag"><?php echo trim(htmlspecialchars($asiento)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($compra['combos_info'])): ?>
                            <div class="combos-section">
                                <h4>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18 8H22L20 21H4L2 8H6M18 8V6C18 4.89543 17.1046 4 16 4H8C6.89543 4 6 4.89543 6 6V8M18 8H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Combos Incluidos
                                </h4>
                                <?php 
                                $combos = json_decode($compra['combos_info'], true);
                                if ($combos && is_array($combos)): 
                                    foreach ($combos as $combo):
                                ?>
                                    <div class="combo-item">
                                        <strong><?php echo htmlspecialchars($combo['nombre'] ?? 'Combo'); ?></strong>
                                        <?php if (isset($combo['precio'])): ?>
                                            - $<?php echo number_format($combo['precio'], 2); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <div class="combo-item"><?php echo htmlspecialchars($compra['combos_info']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="total-section">
                            <div class="total-grid">
                                <div class="total-item">
                                    <h4>Subtotal</h4>
                                    <p>$<?php echo number_format($compra['subtotal'], 2); ?></p>
                                </div>
                                <div class="total-item">
                                    <h4>Impuestos</h4>
                                    <p>$<?php echo number_format($compra['impuestos'], 2); ?></p>
                                </div>
                                <div class="total-item">
                                    <h4>Total Pagado</h4>
                                    <p>$<?php echo number_format($compra['total'], 2); ?></p>
                                </div>
                                <div class="total-item">
                                    <h4>Fecha Compra</h4>
                                    <p><?php echo $compra['fecha_compra']->format('d/m/Y H:i'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>