<?php
// Debug: Log información de la petición
error_log("Método recibido: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Headers: " . print_r(getallheaders(), true));

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir el archivo de conexión - ajusta la ruta según tu estructura
require_once '../conexion.php'; // Si conexion.php está en la carpeta padre
// O usa la ruta completa si está en otra ubicación:
// require_once __DIR__ . '/../conexion.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Método recibido: ' . $_SERVER['REQUEST_METHOD'],
        'debug_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'No definido'
        ]
    ]);
    exit;
}

// Obtener y validar datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';
$rol = trim($_POST['rol'] ?? 'CLIENTE');

// Validaciones básicas
$errores = [];

if (empty($nombre)) {
    $errores[] = 'El nombre es requerido';
} elseif (strlen($nombre) > 100) {
    $errores[] = 'El nombre no puede exceder 100 caracteres';
}

if (empty($correo)) {
    $errores[] = 'El correo es requerido';
} elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo no tiene un formato válido';
} elseif (strlen($correo) > 100) {
    $errores[] = 'El correo no puede exceder 100 caracteres';
}

if (empty($contrasena)) {
    $errores[] = 'La contraseña es requerida';
} elseif (strlen($contrasena) < 6) {
    $errores[] = 'La contraseña debe tener al menos 6 caracteres';
}

if (!in_array($rol, ['ADMIN', 'CAJERO', 'CLIENTE'])) {
    $errores[] = 'Rol no válido';
}

// Si hay errores, retornarlos
if (!empty($errores)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errores)
    ]);
    exit;
}

// Verificar que la conexión esté disponible
if ($conn === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

try {
    // Verificar si el correo ya existe
    $checkEmailQuery = "SELECT COUNT(*) as count FROM usuarios WHERE correo = ?";
    $checkParams = array($correo);
    $checkStmt = sqlsrv_query($conn, $checkEmailQuery, $checkParams);
    
    if ($checkStmt === false) {
        throw new Exception('Error al verificar el correo');
    }
    
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    
    if ($row['count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El correo electrónico ya está registrado'
        ]);
        sqlsrv_free_stmt($checkStmt);
        exit;
    }
    
    sqlsrv_free_stmt($checkStmt);
    
    // Encriptar la contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
    
    // Insertar el nuevo usuario
    $insertQuery = "INSERT INTO usuarios (nombre, correo, rol, contrasena_hash) VALUES (?, ?, ?, ?)";
    $insertParams = array($nombre, $correo, $rol, $contrasena_hash);
    
    $insertStmt = sqlsrv_query($conn, $insertQuery, $insertParams);
    
    if ($insertStmt === false) {
        $errors = sqlsrv_errors();
        
        // Verificar si es error de correo duplicado
        $isDuplicateEmail = false;
        foreach ($errors as $error) {
            if (strpos($error['message'], 'UNIQUE') !== false || 
                strpos($error['message'], 'duplicate') !== false ||
                $error['code'] == 2627) {
                $isDuplicateEmail = true;
                break;
            }
        }
        
        if ($isDuplicateEmail) {
            echo json_encode([
                'success' => false,
                'message' => 'El correo electrónico ya está registrado'
            ]);
        } else {
            // Log del error para debugging
            error_log("Error SQL Server: " . print_r($errors, true));
            echo json_encode([
                'success' => false,
                'message' => 'Error al registrar el usuario. Intenta nuevamente.'
            ]);
        }
        exit;
    }
    
    // Obtener el ID del usuario recién creado
    $getIdQuery = "SELECT SCOPE_IDENTITY() as new_id";
    $getIdStmt = sqlsrv_query($conn, $getIdQuery);
    $userId = null;
    
    if ($getIdStmt !== false) {
        $idRow = sqlsrv_fetch_array($getIdStmt, SQLSRV_FETCH_ASSOC);
        $userId = $idRow['new_id'];
        sqlsrv_free_stmt($getIdStmt);
    }
    
    sqlsrv_free_stmt($insertStmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'user_id' => $userId
    ]);
    
} catch(Exception $e) {
    // Log del error para debugging
    error_log("Error en registro: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor. Intenta nuevamente.'
    ]);
}

// Cerrar la conexión
sqlsrv_close($conn);
?>


