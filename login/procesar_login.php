<?php
session_start();

// Incluir conexión a la base de datos
// Asegúrate de que 'conexion.php' inicialice $conn correctamente (sqlsrv_connect) o defina variables necesarias.
require_once '../conexion.php';  // Ajusta la ruta según tu proyecto

// Función para redireccionar con mensaje (tipo: 'error' o 'success')
function redirectWithMessage($page, $type, $message) {
    $encoded_message = urlencode($message);
    header("Location: {$page}?{$type}={$encoded_message}");
    exit();
}

// Verificar que la conexión SQL Server exista ($conn)
if (!isset($conn) || $conn === false) {
    // Si tu conexión falla, redirigir con mensaje
    error_log("Login: Conexión a base de datos no disponible");
    redirectWithMessage('login.html', 'error', 'Error de conexión. Inténtalo más tarde.');
}

// Solo procesar si es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('login.html', 'error', 'Acceso no autorizado');
}

// Obtener y sanitizar datos
$correo = isset($_POST['correo']) ? trim(strtolower($_POST['correo'])) : '';
$contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';

// Validaciones básicas
if ($correo === '' || $contrasena === '') {
    redirectWithMessage('login.html', 'error', 'Por favor completa todos los campos');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirectWithMessage('login.html', 'error', 'Formato de correo inválido');
}

// Preparar consulta para buscar usuario
$sql = "SELECT id_usuario, nombre, correo, rol, contrasena_hash 
        FROM usuarios 
        WHERE correo = ?";

// Preparar statement
$stmt = sqlsrv_prepare($conn, $sql, array(&$correo));
if ($stmt === false) {
    error_log("Login: Error al preparar consulta: " . print_r(sqlsrv_errors(), true));
    redirectWithMessage('login.html', 'error', 'Error interno. Inténtalo nuevamente.');
}

// Ejecutar
if (!sqlsrv_execute($stmt)) {
    error_log("Login: Error al ejecutar consulta: " . print_r(sqlsrv_errors(), true));
    redirectWithMessage('login.html', 'error', 'Error interno. Inténtalo nuevamente.');
}

// Obtener resultado
$usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);

if (!$usuario) {
    // Usuario no existe
    redirectWithMessage('login.html', 'error', 'Correo o contraseña incorrectos');
}

// Verificar contraseña
$hash_almacenado = $usuario['contrasena_hash'] ?? '';
if (!password_verify($contrasena, $hash_almacenado)) {
    // Contraseña equivocada
    redirectWithMessage('login.html', 'error', 'Correo o contraseña incorrectos');
}

// Contraseña correcta: iniciar sesión
session_regenerate_id(true);  // Previene fijación de sesión

// Guardar datos en la sesión
$_SESSION['usuario_id'] = $usuario['id_usuario'];
$_SESSION['usuario_nombre'] = $usuario['nombre'];
$_SESSION['usuario_correo'] = $usuario['correo'];
$_SESSION['usuario_rol'] = $usuario['rol'];
$_SESSION['login_time'] = time();

// Registrar último acceso (opcional)
try {
    $sqlUpdate = "UPDATE usuarios SET ultimo_acceso = GETDATE() WHERE id_usuario = ?";
    $paramId = $usuario['id_usuario'];
    $stmtUpd = sqlsrv_prepare($conn, $sqlUpdate, array(&$paramId));
    if ($stmtUpd) {
        sqlsrv_execute($stmtUpd);
        sqlsrv_free_stmt($stmtUpd);
    } else {
        error_log("Login: Error al preparar actualización de último acceso: " . print_r(sqlsrv_errors(), true));
    }
} catch (Exception $e) {
    error_log("Login: Excepción al registrar último acceso: " . $e->getMessage());
}

// Redireccionar según rol
switch ($usuario['rol']) {
    case 'ADMIN':
        redirectWithMessage('../panel_administrativo.php', 'success', 'Bienvenido ' . $usuario['nombre']);
        break;
    case 'CAJERO':
        redirectWithMessage('cajero/dashboard.php', 'success', 'Bienvenido ' . $usuario['nombre']);
        break;
    case 'CLIENTE':
        redirectWithMessage('../cartelera.php', 'success', 'Bienvenido ' . $usuario['nombre']);
        break;
    default:
        // Rol inesperado
        redirectWithMessage('cartelera.php', 'success', 'Bienvenido ' . $usuario['nombre']);
        break;
}

// Cerrar conexión
sqlsrv_close($conn);
exit();
