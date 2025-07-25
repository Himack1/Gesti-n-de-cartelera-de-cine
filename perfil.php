<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login/login.html");
    exit();
}

// Incluir archivo de conexión
include 'conexion.php';

// Variables de mensajes
$error_msg = '';
$success_msg = '';

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
    
    // Validar campos
    if (empty($contrasena_actual) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
        $error_msg = 'Todos los campos son obligatorios.';
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        $error_msg = 'Las nuevas contraseñas no coinciden.';
    } elseif (strlen($nueva_contrasena) < 6) {
        $error_msg = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        // Obtener contraseña actual de la base de datos
        $tsql = "SELECT contrasena_hash FROM usuarios WHERE id_usuario = ?";
        $params = array($usuario_id);
        $stmt = sqlsrv_query($conn, $tsql, $params);
        
        if ($stmt === false) {
            $error_msg = 'Error al verificar la contraseña actual.';
        } else {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $hash_actual = $row['contrasena_hash'];
            
            // Verificar contraseña actual
            if (!password_verify($contrasena_actual, $hash_actual)) {
                $error_msg = 'La contraseña actual es incorrecta.';
            } 
            // Verificar que la nueva contraseña no sea igual a la actual
            elseif (password_verify($nueva_contrasena, $hash_actual)) {
                $error_msg = 'La nueva contraseña no puede ser igual a la actual.';
            } else {
                // Generar nuevo hash y actualizar
                $nuevo_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
                $tsql_update = "UPDATE usuarios SET contrasena_hash = ? WHERE id_usuario = ?";
                $params_update = array($nuevo_hash, $usuario_id);
                $stmt_update = sqlsrv_query($conn, $tsql_update, $params_update);
                
                if ($stmt_update === false) {
                    $error_msg = 'Error al actualizar la contraseña.';
                } else {
                    $success_msg = 'Contraseña actualizada correctamente.';
                }
            }
        }
    }
}

$nombre = $_SESSION['usuario_nombre'];
$correo = $_SESSION['usuario_correo'];
$rol = $_SESSION['usuario_rol'];



?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Perfil de Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
            background-size: 400% 400%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #333;
            font-size: 2.2em;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }

        .logo .cinema-text {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            color: #666;
            font-size: 0.9em;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 1);
        }

        .form-group input::placeholder {
            color: #999;
            font-weight: 400;
        }

        .password-input-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1.1em;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .toggle-password:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .register-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .divider {
            text-align: center;
            margin: 30px 0 25px;
            position: relative;
            color: #666;
            font-size: 0.9em;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ddd, transparent);
        }

        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 20px;
            position: relative;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95em;
            position: relative;
            transition: all 0.3s ease;
        }

        .login-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        .login-link a:hover::after {
            width: 100%;
        }

        .login-link a:hover {
            color: #764ba2;
        }

        .terms-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 25px;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .terms-checkbox input[type="checkbox"] {
            width: auto !important;
            margin: 0;
            accent-color: #667eea;
        }

        .terms-checkbox label {
            margin: 0;
            color: #555;
            font-weight: 400;
            cursor: pointer;
        }

        .terms-checkbox a {
            color: #667eea;
            text-decoration: none;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: rgba(255, 82, 82, 0.1);
            color: #ff5252;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9em;
            margin-bottom: 20px;
            border-left: 4px solid #ff5252;
            display: none;
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9em;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
            display: none;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 25px;
                margin: 10px;
            }

            .logo h1 {
                font-size: 1.8em;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

/*Historial*/

.historial-btn-container {
    display: flex;
    justify-content: center;
    margin: 25px 0;
}

.btn-historial {
    display: inline-flex;
    align-items: center;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    border: none;
    cursor: pointer;
}

.btn-historial:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    text-decoration: none;
    color: white;
}

.btn-historial:active {
    transform: translateY(0);
}

.btn-historial svg {
    animation: tick 2s infinite;
}

@keyframes tick {
    0%, 50%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(6deg); }
    75% { transform: rotate(-6deg); }
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

<div class="cartelera-btn-container">
    <a href="cartelera.php" class="btn-cartelera">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
            <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
            <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
            <circle cx="8" cy="9" r="2" stroke="currentColor" stroke-width="2"/>
            <path d="M14 9L16 11L20 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Ver Cartelera</span>
    </a>
</div>

    <div class="register-container">
        <div class="logo">
            <h1>Bienvenido, <span class="cinema-text"><?php echo htmlspecialchars($nombre); ?></span></h1>
            <p>Este es tu perfil de usuario</p>
        </div>

        <div class="form-group">
            <label>Nombre:</label>
            <input type="text" value="<?php echo htmlspecialchars($nombre); ?>" readonly>
        </div>

        <div class="form-group">
            <label>Correo electrónico:</label>
            <input type="email" value="<?php echo htmlspecialchars($correo); ?>" readonly>
        </div>

        <div class="form-group">
            <label>Rol:</label>
            <input type="text" value="<?php echo htmlspecialchars($rol); ?>" readonly>
        </div>

 <div class="change-password-form">
            <h3 class="change-password-title">Cambiar Contraseña</h3>
            
            <?php if (!empty($error_msg)): ?>
                <div class="error-message" style="display: block;"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
                <div class="success-message" style="display: block;"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Contraseña Actual:</label>
                    <div class="password-input-container">
                        <input type="password" name="contrasena_actual" placeholder="Ingresa tu contraseña actual" required>
                        <button type="button" class="toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nueva Contraseña:</label>
                    <div class="password-input-container">
                        <input type="password" name="nueva_contrasena" placeholder="Ingresa tu nueva contraseña" required>
                        <button type="button" class="toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña:</label>
                    <div class="password-input-container">
                        <input type="password" name="confirmar_contrasena" placeholder="Confirma tu nueva contraseña" required>
                        <button type="button" class="toggle-password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="cambiar_contrasena" class="register-btn">
                    Cambiar Contraseña
                </button>
            </form>
        </div>

        <div class="historial-btn-container" style="margin: 20px 0;">
    <a href="historial_compras.php" class="btn-historial">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 10px;">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
        </svg>
        Ver Historial de Compras
    </a>
</div>

        <?php if ($rol === 'ADMIN'): ?>
            <div class="form-group" style="margin-top: 30px;">
                <a href="panel_administrativo.php" class="register-btn" style="text-decoration: none; text-align: center; display: block;">
                    Panel Administrativo
                </a>
            </div>
        <?php endif; ?>

<?php if ($rol === 'ADMIN' || $rol === 'CAJERO'): ?>
            <div class="form-group" style="margin-top: 30px;">
                <a href="reportes_y_estadisticas.php" class="register-btn" style="text-decoration: none; text-align: center; display: block;">
                    Reportes y Estadísticas
                </a>
            </div>
        <?php endif; ?>

        <div class="login-link">
            <a href="login/logout.php">Cerrar sesión</a>
        </div>
    </div>
     <script>
        // Función para mostrar/ocultar contraseña
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-password');
            
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.parentElement.querySelector('input');
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
        });
    </script>
</body>

</html>