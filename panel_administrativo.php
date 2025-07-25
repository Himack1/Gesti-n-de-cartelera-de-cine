<?php
/*// Al inicio del archivo panel_administrativo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Al inicio de panel_administrativo.php para depuración
error_log("Método: " . $_SERVER['REQUEST_METHOD']);
error_log("GET: " . print_r($_GET, true));
error_log("POST: " . print_r($_POST, true));
error_log("Acción: " . ($_GET['accion'] ?? 'N/A'));*/



session_start();
include 'conexion.php';
include 'funciones_bd.php';

// Verificar que el usuario es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'ADMIN') {
    header('Location: ../index.php');
    exit;
}

// Obtener estadísticas para el dashboard
$total_peliculas = obtenerTotalRegistros('peliculas');
$total_salas = obtenerTotalRegistros('salas');
$total_funciones = obtenerTotalRegistros('funciones');
$total_usuarios = obtenerTotalRegistros('usuarios');

// Obtener actividades recientes
$actividades = obtenerActividadesRecientes(4); // Últimas 4 actividades

// Reemplaza todo el bloque de procesamiento inicial con:
$accion = $_GET['accion'] ?? '';
$tabla = $_GET['tabla'] ?? '';

// Procesar tanto GET como POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $accion === 'eliminar') {
    switch ($tabla) {
        case 'peliculas':
            procesarPelicula($accion);
            break;
        case 'salas':
            procesarSala($accion);
            break;
        case 'funciones':
            procesarFuncion($accion);
            break;
        case 'usuarios':
            procesarUsuario($accion);
            break;
        case 'configuracion':
            guardarConfiguracion();
            break;
    }

    // Redirección general después de procesar
    $seccion_actual = $_GET['seccion'] ?? 'dashboard';
    header("Location: panel_administrativo.php?seccion=$seccion_actual");
    exit;
}

// Funciones de procesamiento
function procesarPelicula($accion)
{
    global $conn;

    $id = $_REQUEST['id'] ?? 0;
    $titulo = trim($_POST['titulo'] ?? '');

    // CONVERTIR A ENTERO (FIX CRÍTICO)
    $duracion = (int)($_POST['duracion_min'] ?? 0);

    $clasificacion = $_POST['clasificacion'] ?? '';
    $genero = trim($_POST['genero'] ?? '');
    $fecha_estreno = $_POST['fecha_estreno'] ?? '';
    $imagen_url = trim($_POST['imagen_url'] ?? '');
    $trailer_url = trim($_POST['trailer_url'] ?? '');
    $formato = $_POST['formato'] ?? '2D';


    if ($accion === 'crear') {
        $tsql = "INSERT INTO peliculas (titulo, duracion_min, clasificacion, genero, fecha_estreno, imagen_url, trailer_url, formato) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$titulo, $duracion, $clasificacion, $genero, $fecha_estreno, $imagen_url, $trailer_url, $formato];
    } elseif ($accion === 'editar' && $id > 0) {
        $tsql = "UPDATE peliculas 
                 SET titulo = ?, duracion_min = ?, clasificacion = ?, genero = ?, fecha_estreno = ?, 
                     imagen_url = ?, trailer_url = ?, formato = ?
                 WHERE id_pelicula = ?";
        $params = [$titulo, $duracion, $clasificacion, $genero, $fecha_estreno, $imagen_url, $trailer_url, $formato, $id];
    } elseif ($accion === 'eliminar' && $id > 0) {
        $tsql = "DELETE FROM peliculas WHERE id_pelicula = ?";
        $params = [$id];
    }
 

    if (isset($tsql)) {
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $_SESSION['error'] = "Error al procesar: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['exito'] = "Operación realizada correctamente";
           
            // Registrar actividad
            if ($accion === 'crear') {
                registrarActividad('pelicula_creada', "Película '$titulo' añadida");
            } elseif ($accion === 'editar') {
                registrarActividad('pelicula_editada', "Película '$titulo' actualizada");
            } elseif ($accion === 'eliminar') {
                registrarActividad('pelicula_eliminada', "Película con ID $id eliminada");
            }


        }

        // Agregar redirección
        $seccion_actual = $_REQUEST['seccion'] ?? 'dashboard';
        header("Location: panel_administrativo.php?seccion=$seccion_actual");
        exit;
    }
}

function procesarSala($accion)
{
    global $conn;

    $id = $_REQUEST['id'] ?? 0;
    $nombre = $_POST['nombre_sala'] ?? '';
    $capacidad = $_POST['capacidad'] ?? 0;
    $formato = $_POST['formato'] ?? '';

    if ($accion === 'crear') {
        $tsql = "INSERT INTO salas (nombre_sala, capacidad, formato) 
                 VALUES (?, ?, ?)";
        $params = [$nombre, $capacidad, $formato];
    } elseif ($accion === 'editar' && $id > 0) {
        $tsql = "UPDATE salas 
                 SET nombre_sala = ?, capacidad = ?, formato = ?
                 WHERE id_sala = ?";
        $params = [$nombre, $capacidad, $formato, $id];
    } elseif ($accion === 'eliminar' && $id > 0) {
        $tsql = "DELETE FROM salas WHERE id_sala = ?";
        $params = [$id];
    }



    if (isset($tsql)) {
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $_SESSION['error'] = "Error al procesar: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['exito'] = "Operación realizada correctamente";

            // Registrar actividad
            if ($accion === 'crear') {
                registrarActividad('sala_creada', "Sala '$nombre' añadida");
            } elseif ($accion === 'editar') {
                registrarActividad('sala_editada', "Sala '$nombre' actualizada");
            } elseif ($accion === 'eliminar') {
                registrarActividad('sala_eliminada', "Sala con ID $id eliminada");
            }

        }

        // Agregar redirección
        $seccion_actual = $_REQUEST['seccion'] ?? 'dashboard';
        header("Location: panel_administrativo.php?seccion=$seccion_actual");
        exit;
    }
}

function procesarFuncion($accion)
{
    global $conn;

    $id = $_REQUEST['id'] ?? 0;
    $pelicula_id = $_POST['id_pelicula'] ?? 0;
    $sala_id = $_POST['id_sala'] ?? 0;
    $fecha_hora = $_POST['fecha_hora'] ?? '';
    $precio = $_POST['precio'] ?? 0;

    if ($accion === 'crear') {
        // Obtener nombre de película para almacenarlo
        $pelicula = obtenerPeliculaPorId($pelicula_id);
        $nombre_pelicula = $pelicula['titulo'] ?? '';

        $tsql = "INSERT INTO funciones (id_pelicula, id_sala, fecha_hora, precio, nombre_pelicula) 
                 VALUES (?, ?, ?, ?, ?)";
        $params = [$pelicula_id, $sala_id, $fecha_hora, $precio, $nombre_pelicula];
    } elseif ($accion === 'editar' && $id > 0) {
        $pelicula = obtenerPeliculaPorId($pelicula_id);
        $nombre_pelicula = $pelicula['titulo'] ?? '';

        $tsql = "UPDATE funciones 
                 SET id_pelicula = ?, id_sala = ?, fecha_hora = ?, precio = ?, nombre_pelicula = ?
                 WHERE id_funcion = ?";
        $params = [$pelicula_id, $sala_id, $fecha_hora, $precio, $nombre_pelicula, $id];
    } elseif ($accion === 'eliminar' && $id > 0) {
        $tsql = "DELETE FROM funciones WHERE id_funcion = ?";
        $params = [$id];
    }


    
    if (isset($tsql)) {
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $_SESSION['error'] = "Error al procesar: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['exito'] = "Operación realizada correctamente";

            // Registrar actividad
            if ($accion === 'crear') {
                registrarActividad('funcion_creada', "Función de película '$nombre_pelicula' añadida");
            } elseif ($accion === 'editar') {
                registrarActividad('funcion_editada', "Función de película '$nombre_pelicula' actualizada");
            } elseif ($accion === 'eliminar') {
                registrarActividad('funcion_eliminada', "Función de película con ID $id eliminada");
            }
        }

        // Agregar redirección
        $seccion_actual = $_REQUEST['seccion'] ?? 'dashboard';
        header("Location: panel_administrativo.php?seccion=$seccion_actual");
        exit;
    }
}

function procesarUsuario($accion)
{
    global $conn;

    $id = $_REQUEST['id'] ?? 0;
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $rol = $_POST['rol'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    // Verificar duplicado de correo
    $tsql_check = "SELECT COUNT(*) AS total FROM usuarios WHERE correo = ? AND id_usuario != ?";
    $params_check = [$correo, $id];
    $stmt_check = sqlsrv_query($conn, $tsql_check, $params_check);

    if ($stmt_check && sqlsrv_fetch($stmt_check)) {
        $total = sqlsrv_get_field($stmt_check, 0);

        if ($total > 0) {
            $_SESSION['error'] = "El correo electrónico ya está registrado";
            $seccion_actual = $_REQUEST['seccion'] ?? 'usuarios';
            header("Location: panel_administrativo.php?seccion=$seccion_actual");
            exit;
        }
    }

    $tsql = null;
    $params = [];

    if ($accion === 'crear') {
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $tsql = "INSERT INTO usuarios (nombre, correo, rol, contrasena_hash) 
                 VALUES (?, ?, ?, ?)";
        $params = [$nombre, $correo, $rol, $hash];
    } elseif ($accion === 'editar' && $id > 0) {
        if (!empty($contrasena)) {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $tsql = "UPDATE usuarios 
                     SET nombre = ?, correo = ?, rol = ?, contrasena_hash = ?
                     WHERE id_usuario = ?";
            $params = [$nombre, $correo, $rol, $hash, $id];
        } else {
            $tsql = "UPDATE usuarios 
                     SET nombre = ?, correo = ?, rol = ?
                     WHERE id_usuario = ?";
            $params = [$nombre, $correo, $rol, $id];
        }
    } elseif ($accion === 'eliminar' && $id > 0) {
        $tsql = "DELETE FROM usuarios WHERE id_usuario = ?";
        $params = [$id];
    } else {
        // Manejar casos no válidos
        $_SESSION['error'] = "Acción no válida o ID faltante";
        $seccion_actual = $_REQUEST['seccion'] ?? 'dashboard';
        header("Location: panel_administrativo.php?seccion=$seccion_actual");
        exit;
    }

    // Ejecutar la consulta solo si se definió
    if ($tsql !== null) {
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt === false) {
            $_SESSION['error'] = "Error al procesar usuario: " . print_r(sqlsrv_errors(), true);
        } else {
            $mensaje_accion = '';
            switch ($accion) {
                case 'crear':
                    $mensaje_accion = 'creado';
                    break;
                case 'editar':
                    $mensaje_accion = 'actualizado';
                    break;
                case 'eliminar':
                    $mensaje_accion = 'eliminado';
                    break;
            }
            $_SESSION['exito'] = "Usuario $mensaje_accion correctamente";

            // Registrar actividad
            if ($accion === 'crear') {
                registrarActividad('usuario_creado', "Usuario '$nombre' añadido");
            } elseif ($accion === 'editar') {
                registrarActividad('usuario_editado', "Usuario '$nombre' actualizado");
            } elseif ($accion === 'eliminar') {
                registrarActividad('usuario_eliminado', "Usuario con ID $id eliminado");
            }
        }
    }

    


    // Redirección
    $seccion_actual = $_REQUEST['seccion'] ?? 'dashboard';
    header("Location: panel_administrativo.php?seccion=$seccion_actual");
    exit;
}

function guardarConfiguracion()
{
    // Aquí implementarías la lógica para guardar configuraciones
    // Ejemplo: precio base, impuestos, etc.
    $_SESSION['exito'] = "Configuración actualizada correctamente";

    // Registrar actividad
    registrarActividad('configuracion_actualizada', "Configuración actualizada");
}

function obtenerTotalRegistros($tabla)
{
    global $conn;

    $tsql = "SELECT COUNT(*) AS total FROM $tabla";
    $stmt = sqlsrv_query($conn, $tsql);

    if ($stmt === false) {
        return 0;
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return $row['total'] ?? 0;
}

function verificarUsuarioEnBD($id)
{
    $conn = obtenerConexion();
    $tsql = "SELECT COUNT(*) as total FROM usuarios WHERE id_usuario = ?";
    $params = [$id];
    $stmt = sqlsrv_query($conn, $tsql, $params);

    if ($stmt === false) {
        return "Error: " . print_r(sqlsrv_errors(), true);
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    return "Usuario ID $id existe: " . ($row['total'] > 0 ? 'Sí' : 'No');
}

// Función para obtener actividades recientes
function obtenerActividadesRecientes($limite = 5)
{
    global $conn;

    $tsql = "SELECT TOP $limite * FROM actividades ORDER BY fecha_hora DESC";
    $stmt = sqlsrv_query($conn, $tsql);
    $actividades = [];

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $actividades[] = $row;
        }
    }

    return $actividades;
}

function registrarActividad($tipo, $descripcion)
{
    global $conn;

    $id_usuario = $_SESSION['usuario_id'];
    $fecha_hora = date('Y-m-d H:i:s');

    $tsql = "INSERT INTO actividades (id_usuario, tipo, descripcion, fecha_hora) 
             VALUES (?, ?, ?, ?)";
    $params = array($id_usuario, $tipo, $descripcion, $fecha_hora);
    sqlsrv_query($conn, $tsql, $params);
}

function obtenerNombreUsuario($id_usuario)
{
    global $conn;

    $tsql = "SELECT nombre FROM usuarios WHERE id_usuario = ?";
    $params = array($id_usuario);
    $stmt = sqlsrv_query($conn, $tsql, $params);

    if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['nombre'];
    }

    return 'Usuario desconocido';
}

// luego en la sección de edición:
if ($accion === 'editar' && isset($_GET['id'])) {
    $id_usuario = (int)$_GET['id'];
    //echo "<div class='alert alert-info'>" . verificarUsuarioEnBD($id_usuario) . "</div>";
}

// obtener datos para mostrar
$seccion = $_GET['seccion'] ?? 'dashboard';
$peliculas = obtenerTodosRegistros('peliculas');
$salas = obtenerTodosRegistros('salas');
$usuarios = obtenerTodosRegistros('usuarios');

// obtener funciones con detalles
$funciones = [];
$tsql = "SELECT f.*, p.titulo AS nombre_pelicula, s.nombre_sala 
         FROM funciones f
         JOIN peliculas p ON f.id_pelicula = p.id_pelicula
         JOIN salas s ON f.id_sala = s.id_sala
         ORDER BY f.fecha_hora DESC";
$stmt = sqlsrv_query($conn, $tsql);
if ($stmt !== false) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $funciones[] = $row;
    }
}

function obtenerTodosRegistros($tabla)
{
    global $conn;

    $tsql = "SELECT * FROM $tabla";
    $stmt = sqlsrv_query($conn, $tsql);
    $resultados = [];

    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            //convertir objetos DateTime a strings
            foreach ($row as $key => $value) {
                if ($value instanceof DateTime) {
                    $row[$key] = $value->format('Y-m-d H:i:s');
                }
            }

            //manejo especial para usuarios sin fecha_registro
            if ($tabla === 'usuarios' && empty($row['fecha_registro'])) {
                $row['fecha_registro'] = date('Y-m-d H:i:s'); // Fecha actual como default
            }

            $resultados[] = $row;
        }
    }

    return $resultados;
}

function formatearFechaHora($fecha_hora)
{
    if ($fecha_hora instanceof DateTime) {
        return $fecha_hora->format('d/m/Y H:i');
    } elseif (is_string($fecha_hora)) {
        return date('d/m/Y H:i', strtotime($fecha_hora));
    }
    return $fecha_hora;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Caribbean Cinemas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3c72;
            --secondary: #2a5298;
            --accent: #4ecdc4;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow-x: hidden;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid var(--accent);
        }

        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .topbar {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent);
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border: none;
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }

        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--accent);
        }

        .stats-card .number {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary);
        }

        .stats-card .label {
            color: var(--dark);
            font-size: 0.9rem;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .badge-admin {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }

        .badge-cajero {
            background: linear-gradient(45deg, #ff9800, #ff5722);
            color: white;
        }

        .badge-cliente {
            background: linear-gradient(45deg, #4caf50, #8bc34a);
            color: white;
        }

        .modal-header {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
        }

        .pagination .page-link {
            color: var(--primary);
        }

        .pagination .page-link:hover {
            background: rgba(30, 60, 114, 0.1);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.25);
        }

        .alert {
            border-radius: 8px;
        }

        .search-container {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-input {
            padding-left: 35px;
        }

        .filter-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-item {
            flex: 1;
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h4 class="text-center mb-0">
                    <i class="fas fa-film"></i> Caribbean Cinemas
                </h4>
                <div class="text-center mt-2 text-white" style="font-size: 0.9rem;">
                    Panel Administrativo
                </div>
            </div>

            <ul class="sidebar-menu">
                <li>
                    <a href="?seccion=dashboard" class="<?= $seccion === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="?seccion=peliculas" class="<?= $seccion === 'peliculas' ? 'active' : '' ?>">
                        <i class="fas fa-film"></i> Películas
                    </a>
                </li>
                <li>
                    <a href="?seccion=salas" class="<?= $seccion === 'salas' ? 'active' : '' ?>">
                        <i class="fas fa-theater-masks"></i> Salas
                    </a>
                </li>
                <li>
                    <a href="?seccion=funciones" class="<?= $seccion === 'funciones' ? 'active' : '' ?>">
                        <i class="fas fa-clock"></i> Funciones
                    </a>
                </li>
                <li>
                    <a href="?seccion=usuarios" class="<?= $seccion === 'usuarios' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> Usuarios
                    </a>
                </li>
                <li>
                    <a href="?seccion=configuracion" class="<?= $seccion === 'configuracion' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                </li>
                <li>
                    <a href="cartelera.php">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h3 class="mb-0">Panel de Administración</h3>
                <div class="user-info">
                    <div class="user-avatar"><?= substr($_SESSION['usuario_nombre'], 0, 1) ?></div>
                    <div>
                        <div class="fw-bold"><?= $_SESSION['usuario_nombre'] ?> (Administrador)</div>
                        <div class="text-muted small">Último acceso: <?= date('d/m/Y H:i') ?></div>
                    </div>
                </div>
            </div>

            <!-- Mensajes de éxito/error -->
            <?php if (isset($_SESSION['exito'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['exito'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['exito']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section <?= $seccion === 'dashboard' ? 'active' : '' ?>">
                <h4 class="section-title">Resumen del Sistema</h4>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-film"></i>
                            <div class="number"><?= $total_peliculas ?></div>
                            <div class="label">Películas Activas</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-theater-masks"></i>
                            <div class="number"><?= $total_salas ?></div>
                            <div class="label">Salas Disponibles</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-ticket-alt"></i>
                            <div class="number"><?= $total_funciones ?></div>
                            <div class="label">Funciones Programadas</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <i class="fas fa-users"></i>
                            <div class="number"><?= $total_usuarios ?></div>
                            <div class="label">Usuarios Registrados</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <span>Próximas Funciones</span>
                                <a href="?seccion=funciones&tabla=funciones&accion=crear" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus"></i> Nueva Función
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Película</th>
                                                <th>Sala</th>
                                                <th>Fecha y Hora</th>
                                                <th>Precio</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($funciones, 0, 4) as $funcion): ?>
                                                <tr>
                                                    <td><?= $funcion['nombre_pelicula'] ?></td>
                                                    <td><?= $funcion['nombre_sala'] ?></td>
                                                    <td><?= formatearFechaHora($funcion['fecha_hora']) ?></td>
                                                    <td>RD$<?= number_format($funcion['precio'], 2) ?></td>
                                                    <td>
                                                        <a href="?seccion=funciones&tabla=funciones&accion=editar&id=<?= $funcion['id_funcion'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?seccion=funciones&tabla=funciones&accion=eliminar&id=<?= $funcion['id_funcion'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar esta función?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!---- Actividad Reciente Section -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Actividad Reciente</span>
                                <small class="text-muted">Actualizado: <?= date('H:i') ?></small>
                            </div>
                            <div class="card-body">
                                <?php if (empty($actividades)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                                        <p>No hay actividades recientes</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($actividades as $actividad): ?>
                                        <div class="d-flex mb-3">
                                            <?php
                                            // Determinar icono y color según el tipo de actividad
                                            $icono = '';
                                            $color = '';

                                            switch ($actividad['tipo']) {
                                                case 'pelicula_creada':
                                                case 'pelicula_editada':
                                                case 'pelicula_eliminada':
                                                    $icono = 'fas fa-film';
                                                    $color = 'primary';
                                                    break;
                                                case 'sala_creada':
                                                case 'sala_editada':
                                                case 'sala_eliminada':
                                                    $icono = 'fas fa-theater-masks';
                                                    $color = 'info';
                                                    break;
                                                case 'funcion_creada':
                                                case 'funcion_editada':
                                                case 'funcion_eliminada':
                                                    $icono = 'fas fa-ticket-alt';
                                                    $color = 'success';
                                                    break;
                                                case 'usuario_creado':
                                                case 'usuario_editado':
                                                case 'usuario_eliminado':
                                                    $icono = 'fas fa-user';
                                                    $color = 'warning';
                                                    break;
                                                case 'configuracion_actualizada':
                                                    $icono = 'fas fa-cog';
                                                    $color = 'danger';
                                                    break;
                                                default:
                                                    $icono = 'fas fa-info-circle';
                                                    $color = 'secondary';
                                            }
                                            ?>

                                            <div class="bg-<?= $color ?> text-white rounded-circle p-2 me-3">
                                                <i class="<?= $icono ?>"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($actividad['descripcion']) ?></div>
                                                <div class="text-muted small">
                                                    Por <?= obtenerNombreUsuario($actividad['id_usuario']) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= formatearFechaHora($actividad['fecha_hora']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- End of Actividad Reciente Section -->
                </div>
            </div>

            <!-- Películas Section -->
            <div id="peliculas" class="content-section <?= $seccion === 'peliculas' ? 'active' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title">Gestión de Películas</h4>
                    <a href="?seccion=peliculas&accion=crear" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Película
                    </a>
                </div>

                <?php if ($accion === 'crear' || $accion === 'editar'): ?>
                    <!-- Formulario de Película -->
                    <div class="card">
                        <div class="card-header">
                            <span><?= $accion === 'crear' ? 'Agregar Nueva Película' : 'Editar Película' ?></span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="?seccion=peliculas&tabla=peliculas&accion=<?= $accion ?>">
                                <?php
                                $pelicula = [];
                                if ($accion === 'editar' && isset($_GET['id'])) {
                                    $pelicula = obtenerPeliculaPorId($_GET['id']);
                                }
                                ?>
                                <input type="hidden" name="id" value="<?= $pelicula['id_pelicula'] ?? '' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Título</label>
                                            <input type="text" class="form-control" name="titulo"
                                                value="<?= $pelicula['titulo'] ?? '' ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Duración (minutos)</label>
                                            <input type="number" class="form-control" name="duracion_min"
                                                value="<?= $pelicula['duracion_min'] ?? '' ?>" min="1" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Clasificación</label>
                                            <select class="form-select" name="clasificacion" required>
                                                <option value="G" <?= ($pelicula['clasificacion'] ?? '') === 'G' ? 'selected' : '' ?>>G - General</option>
                                                <option value="PG" <?= ($pelicula['clasificacion'] ?? '') === 'PG' ? 'selected' : '' ?>>PG - Guía Parental</option>
                                                <option value="PG-13" <?= ($pelicula['clasificacion'] ?? '') === 'PG-13' ? 'selected' : '' ?>>PG-13 - Mayores de 13</option>
                                                <option value="R" <?= ($pelicula['clasificacion'] ?? '') === 'R' ? 'selected' : '' ?>>R - Restringido</option>
                                                <option value="NC-17" <?= ($pelicula['clasificacion'] ?? '') === 'NC-17' ? 'selected' : '' ?>>NC-17 - Solo adultos</option>
                                            </select>
                                        </div>

                                        <!-- Nuevo campo para formato -->
                                        <div class="mb-3">
                                            <label class="form-label">Formato</label>
                                            <select class="form-select" name="formato" required>
                                                <option value="2D" <?= ($pelicula['formato'] ?? '') === '2D' ? 'selected' : '' ?>>2D</option>
                                                <option value="3D" <?= ($pelicula['formato'] ?? '') === '3D' ? 'selected' : '' ?>>3D</option>
                                                <option value="IMAX" <?= ($pelicula['formato'] ?? '') === 'IMAX' ? 'selected' : '' ?>>IMAX</option>
                                                <option value="4DX" <?= ($pelicula['formato'] ?? '') === '4DX' ? 'selected' : '' ?>>4DX</option>
                                                <option value="VIP" <?= ($pelicula['formato'] ?? '') === 'VIP' ? 'selected' : '' ?>>VIP</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Género</label>
                                            <input type="text" class="form-control" name="genero"
                                                value="<?= $pelicula['genero'] ?? '' ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Fecha de Estreno</label>
                                            <input type="date" class="form-control" name="fecha_estreno"
                                                value="<?= $pelicula['fecha_estreno'] ?? '' ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">URL de la Imagen</label>
                                            <input type="text" class="form-control" name="imagen_url"
                                                value="<?= $pelicula['imagen_url'] ?? '' ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">URL del Tráiler</label>
                                            <input type="url" class="form-control" name="trailer_url"
                                                value="<?= $pelicula['trailer_url'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Guardar Película</button>
                                <a href="?seccion=peliculas" class="btn btn-secondary mt-3">Cancelar</a>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!$accion || $accion === 'listar'): ?>
                        <!-- Listado de Películas -->
                        <div class="card">
                            <div class="card-header">
                                <span>Listado de Películas</span>
                                <div class="d-flex search-container">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" id="buscar-pelicula" class="form-control form-control-sm search-input" placeholder="Buscar película...">
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabla-peliculas">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Título</th>
                                                <th>Duración</th>
                                                <th>Clasificación</th>
                                                <th>Género</th>
                                                <th>Formato</th> <!-- Nueva columna para formato -->
                                                <th>Estreno</th>
                                                <th>Tráiler</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($peliculas as $pelicula): ?>
                                                <tr>
                                                    <td><?= $pelicula['id_pelicula'] ?></td>
                                                    <td><?= $pelicula['titulo'] ?></td>
                                                    <td><?= $pelicula['duracion_min'] ?> min</td>
                                                    <td><?= $pelicula['clasificacion'] ?></td>
                                                    <td><?= $pelicula['genero'] ?></td>
                                                    <td><?= $pelicula['formato'] ?></td> <!-- Mostrar formato -->
                                                    <td>
                                                        <?php
                                                        if (isset($pelicula['fecha_estreno'])) {
                                                            if ($pelicula['fecha_estreno'] instanceof DateTime) {
                                                                echo $pelicula['fecha_estreno']->format('d/m/Y');
                                                            } else {
                                                                echo date('d/m/Y', strtotime($pelicula['fecha_estreno']));
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($pelicula['trailer_url'])): ?>
                                                            <a href="<?= $pelicula['trailer_url'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                                <i class="fab fa-youtube"></i> Ver
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="?seccion=peliculas&tabla=peliculas&accion=editar&id=<?= $pelicula['id_pelicula'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?seccion=peliculas&tabla=peliculas&accion=eliminar&id=<?= $pelicula['id_pelicula'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar esta película?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        </div>

                        <!-- Fin de la tabla de películas -->
                    <?php endif; ?>
            </div>

            <!--salas Section -->
            <div id="salas" class="content-section <?= $seccion === 'salas' ? 'active' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title">Gestión de Salas</h4>
                    <a href="?seccion=salas&accion=crear" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Sala
                    </a>
                </div>

                <?php if ($accion === 'crear' || $accion === 'editar'): ?>
                    <!--formulario de Sala -->
                    <div class="card">
                        <div class="card-header">
                            <span><?= $accion === 'crear' ? 'Agregar Nueva Sala' : 'Editar Sala' ?></span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="?seccion=salas&tabla=salas&accion=<?= $accion ?>">
                                <?php
                                $sala = [];
                                if ($accion === 'editar' && isset($_GET['id'])) {
                                    $sala = obtenerSalaPorId($_GET['id']);
                                }
                                ?>
                                <input type="hidden" name="id" value="<?= $sala['id_sala'] ?? '' ?>">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la Sala</label>
                                    <input type="text" class="form-control" name="nombre_sala"
                                        value="<?= $sala['nombre_sala'] ?? '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Capacidad</label>
                                    <input type="number" class="form-control" name="capacidad"
                                        value="<?= $sala['capacidad'] ?? '' ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Formato</label>
                                    <select class="form-select" name="formato" required>
                                        <option value="2D" <?= ($sala['formato'] ?? '') === '2D' ? 'selected' : '' ?>>2D</option>
                                        <option value="3D" <?= ($sala['formato'] ?? '') === '3D' ? 'selected' : '' ?>>3D</option>
                                        <option value="4DX" <?= ($sala['formato'] ?? '') === '4DX' ? 'selected' : '' ?>>4DX</option>
                                        <option value="IMAX" <?= ($sala['formato'] ?? '') === 'IMAX' ? 'selected' : '' ?>>IMAX</option>
                                        <option value="VIP" <?= ($sala['formato'] ?? '') === 'VIP' ? 'selected' : '' ?>>VIP</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Guardar Sala</button>
                                <a href="?seccion=salas" class="btn btn-secondary mt-3">Cancelar</a>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!--listado de Salas -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <span>Listado de Salas</span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Capacidad</th>
                                                    <th>Formato</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($salas as $sala): ?>
                                                    <tr>
                                                        <td><?= $sala['id_sala'] ?></td>
                                                        <td><?= $sala['nombre_sala'] ?></td>
                                                        <td><?= $sala['capacidad'] ?></td>
                                                        <td><?= $sala['formato'] ?></td>
                                                        <td>
                                                            <a href="?seccion=salas&tabla=salas&accion=editar&id=<?= $sala['id_sala'] ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="?seccion=salas&tabla=salas&accion=eliminar&id=<?= $sala['id_sala'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar esta sala?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- funciones Section -->
            <div id="funciones" class="content-section <?= $seccion === 'funciones' ? 'active' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title">Gestión de Funciones</h4>
                    <a href="?seccion=funciones&tabla=funciones&accion=crear" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Función
                    </a>
                </div>

                <?php if ($accion === 'crear' || $accion === 'editar'): ?>
                    <!--formulario de Función -->
                    <div class="card">
                        <div class="card-header">
                            <span><?= $accion === 'crear' ? 'Programar Nueva Función' : 'Editar Función' ?></span>
                        </div>
                        <div class="card-body">
                            <form method="post" action="?seccion=funciones&tabla=funciones&accion=<?= $accion ?>">
                                <?php
                                $funcion = [];
                                if ($accion === 'editar' && isset($_GET['id'])) {
                                    $funcion = obtenerFuncionPorId($_GET['id']);
                                }
                                ?>
                                <input type="hidden" name="id" value="<?= $funcion['id_funcion'] ?? '' ?>">
                                <div class="mb-3">
                                    <label class="form-label">Película</label>
                                    <select class="form-select" name="id_pelicula" required>
                                        <option value="">Seleccionar película</option>
                                        <?php foreach ($peliculas as $pelicula): ?>
                                            <option value="<?= $pelicula['id_pelicula'] ?>"
                                                <?= ($funcion['id_pelicula'] ?? '') == $pelicula['id_pelicula'] ? 'selected' : '' ?>>
                                                <?= $pelicula['titulo'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Sala</label>
                                    <select class="form-select" name="id_sala" required>
                                        <option value="">Seleccionar sala</option>
                                        <?php foreach ($salas as $sala): ?>
                                            <option value="<?= $sala['id_sala'] ?>"
                                                <?= ($funcion['id_sala'] ?? '') == $sala['id_sala'] ? 'selected' : '' ?>>
                                                <?= $sala['nombre_sala'] ?> (<?= $sala['formato'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha</label>
                                            <input type="date" class="form-control" name="fecha_hora_date"
                                                value="<?= isset($funcion['fecha_hora']) ? date('Y-m-d', strtotime($funcion['fecha_hora'])) : '' ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Hora</label>
                                            <input type="time" class="form-control" name="fecha_hora_time"
                                                value="<?= isset($funcion['fecha_hora']) ? date('H:i', strtotime($funcion['fecha_hora'])) : '' ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="fecha_hora" id="fecha_hora_completa">

                                <div class="mb-3">
                                    <label class="form-label">Precio (RD$)</label>
                                    <input type="number" class="form-control" name="precio" step="0.01"
                                        value="<?= $funcion['precio'] ?? '' ?>" required>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">Guardar Función</button>
                                <a href="?seccion=funciones" class="btn btn-secondary mt-3">Cancelar</a>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!$accion || $accion === 'listar'): ?>
                        <!--listado de Funciones -->
                        <div class="card">
                            <div class="card-header">
                                <span>Programación de Funciones</span>
                                <div class="filter-container">
                                    <div class="filter-item">
                                        <label class="me-2 mb-0">Sala:</label>
                                        <select class="form-select form-select-sm" id="filtro-sala">
                                            <option value="">Todas las salas</option>
                                            <?php foreach ($salas as $sala): ?>
                                                <option value="<?= $sala['nombre_sala'] ?>"><?= $sala['nombre_sala'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-item">
                                        <label class="me-2 mb-0">Fecha:</label>
                                        <input type="date" class="form-control form-control-sm" id="filtro-fecha">
                                    </div>
                                    <div class="filter-item">
                                        <label class="me-2 mb-0">Película:</label>
                                        <input type="text" class="form-control form-control-sm" id="filtro-pelicula" placeholder="Buscar película...">
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabla-funciones">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Película</th>
                                                <th>Sala</th>
                                                <th>Fecha y Hora</th>
                                                <th>Precio</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($funciones as $funcion): ?>
                                                <tr>
                                                    <td><?= $funcion['id_funcion'] ?></td>
                                                    <td><?= $funcion['nombre_pelicula'] ?></td>
                                                    <td><?= $funcion['nombre_sala'] ?></td>
                                                    <td><?= formatearFechaHora($funcion['fecha_hora']) ?></td>
                                                    <td>RD$<?= number_format($funcion['precio'], 2) ?></td>
                                                    <td>
                                                        <a href="?seccion=funciones&tabla=funciones&accion=editar&id=<?= $funcion['id_funcion'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?seccion=funciones&tabla=funciones&accion=eliminar&id=<?= $funcion['id_funcion'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar esta función?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div><!-- Fin de la tabla de funciones -->
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!--usuarios Section -->

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error'] ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            <div id="usuarios" class="content-section <?= $seccion === 'usuarios' ? 'active' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="section-title">Gestión de Usuarios</h4>
                    <a href="?seccion=usuarios&accion=crear" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Usuario
                    </a>
                </div>

                <?php if ($accion === 'crear' || $accion === 'editar'): ?>
                    <!--formulario de Usuario -->
                    <div class="card">
                        <div class="card-header">
                            <span><?= $accion === 'crear' ? 'Agregar Nuevo Usuario' : 'Editar Usuario' ?></span>
                        </div>
                        <div class="card-body">
                            <?php
                            $usuario = [];
                            $id_usuario = 0;

                            if ($accion === 'editar' && isset($_GET['id'])) {
                                $id_usuario = (int)$_GET['id'];

                                //obtener usuario
                                $usuario = obtenerUsuarioPorId($id_usuario);

                                //depuración
                                echo "<script>console.log('Datos usuario:', " . json_encode($usuario) . ");</script>";
                                error_log("Datos usuario (ID=$id_usuario): " . print_r($usuario, true));

                                if (!$usuario) {
                                    echo "<div class='alert alert-danger'>No se encontró el usuario con ID $id_usuario</div>";
                                }
                            }
                            ?>

                            <?php if (!isset($usuario['id_usuario']) && $accion === 'editar'): ?>
                                <div class="alert alert-danger">
                                    Error: No se pudieron cargar los datos del usuario.
                                    <?php if ($id_usuario > 0): ?>
                                        <br>ID: <?= $id_usuario ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <form method="post" action="?seccion=usuarios&tabla=usuarios&accion=<?= $accion ?>">
                                    <input type="hidden" name="id" value="<?= $usuario['id_usuario'] ?? '' ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Nombre Completo</label>
                                        <input type="text" class="form-control" name="nombre"
                                            value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" name="correo"
                                            value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Rol</label>
                                        <select class="form-select" name="rol" required>
                                            <option value="ADMIN" <?= ($usuario['rol'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>ADMIN</option>
                                            <option value="CAJERO" <?= ($usuario['rol'] ?? '') === 'CAJERO' ? 'selected' : '' ?>>CAJERO</option>
                                            <option value="CLIENTE" <?= ($usuario['rol'] ?? '') === 'CLIENTE' ? 'selected' : '' ?>>CLIENTE</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" name="contrasena" <?= $accion === 'crear' ? 'required' : '' ?>>
                                        <?php if ($accion === 'editar'): ?>
                                            <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($accion === 'editar' && isset($usuario['fecha_registro'])): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Fecha de Registro</label>
                                            <input type="text" class="form-control"
                                                value="<?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?>"
                                                readonly>
                                        </div>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-primary mt-3">Guardar Usuario</button>
                                    <a href="?seccion=usuarios" class="btn btn-secondary mt-3">Cancelar</a>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if (!$accion || $accion === 'listar'): ?>
                        <!--listado de Usuarios -->
                        <div class="card">
                            <div class="card-header">
                                <span>Usuarios del Sistema</span>
                                <div class="d-flex search-container">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" id="buscar-usuario" class="form-control form-control-sm search-input" placeholder="Buscar usuario...">
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabla-usuarios">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Correo</th>
                                                <th>Rol</th>
                                                <th>Registro</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <tr>
                                                    <td><?= $usuario['id_usuario'] ?></td>
                                                    <td><?= $usuario['nombre'] ?></td>
                                                    <td><?= $usuario['correo'] ?></td>
                                                    <td>
                                                        <span class="badge 
                <?= $usuario['rol'] === 'ADMIN' ? 'badge-admin' : '' ?>
                <?= $usuario['rol'] === 'CAJERO' ? 'badge-cajero' : '' ?>
                <?= $usuario['rol'] === 'CLIENTE' ? 'badge-cliente' : '' ?>">
                                                            <?= $usuario['rol'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($usuario['fecha_registro'])) {
                                                            if ($usuario['fecha_registro'] instanceof DateTime) {
                                                                echo $usuario['fecha_registro']->format('d/m/Y');
                                                            } else {
                                                                echo date('d/m/Y', strtotime($usuario['fecha_registro']));
                                                            }
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="?seccion=usuarios&tabla=usuarios&accion=editar&id=<?= $usuario['id_usuario'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?seccion=usuarios&tabla=usuarios&accion=eliminar&id=<?= $usuario['id_usuario'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!--configuración Section -->
            <div id="configuracion" class="content-section <?= $seccion === 'configuracion' ? 'active' : '' ?>">
                <h4 class="section-title">Configuración del Sistema</h4>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <span>Precios por Formato</span>
                            </div>
                            <div class="card-body">
                                <form method="post" action="?seccion=configuracion&tabla=configuracion">
                                    <div class="mb-3">
                                        <label class="form-label">Precio Base (2D)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RD$</span>
                                            <input type="number" class="form-control" name="precio_base" value="300.00" step="0.01">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Recargo 3D</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RD$</span>
                                            <input type="number" class="form-control" name="recargo_3d" value="50.00" step="0.01">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Recargo IMAX</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RD$</span>
                                            <input type="number" class="form-control" name="recargo_imax" value="150.00" step="0.01">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Recargo 4DX</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RD$</span>
                                            <input type="number" class="form-control" name="recargo_4dx" value="250.00" step="0.01">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Recargo VIP</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RD$</span>
                                            <input type="number" class="form-control" name="recargo_vip" value="100.00" step="0.01">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Precios
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span>Configuración General</span>
                            </div>
                            <div class="card-body">
                                <form method="post" action="?seccion=configuracion&tabla=configuracion">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre del Cine</label>
                                        <input type="text" class="form-control" name="nombre_cine" value="Caribbean Cinemas">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Impuesto (%)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="impuesto" value="18.00" step="0.01">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tiempo Máximo de Reserva</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="tiempo_reserva" value="30">
                                            <span class="input-group-text">minutos</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Horario de Atención</label>
                                        <input type="text" class="form-control" name="horario" value="10:00 AM - 11:00 PM">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Política de Cancelación</label>
                                        <textarea class="form-control" name="politica_cancelacion" rows="3">Las reservas pueden cancelarse hasta 1 hora antes de la función.</textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Configuración
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        //combinar fecha y hora para funciones
        document.addEventListener('DOMContentLoaded', function() {
            const formFuncion = document.querySelector('form[action*="funciones"]');
            if (formFuncion) {
                formFuncion.addEventListener('submit', function(e) {
                    const fecha = document.querySelector('input[name="fecha_hora_date"]').value;
                    const hora = document.querySelector('input[name="fecha_hora_time"]').value;
                    document.getElementById('fecha_hora_completa').value = `${fecha} ${hora}`;
                });
            }

            //función de búsqueda para películas
            const buscarPelicula = document.getElementById('buscar-pelicula');
            if (buscarPelicula) {
                buscarPelicula.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const table = document.getElementById('tabla-peliculas');
                    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                    for (let row of rows) {
                        const cells = row.getElementsByTagName('td');
                        //buscamos en título (columna 1) y género (columna 4)
                        const titulo = cells[1].textContent.toLowerCase();
                        const genero = cells[4].textContent.toLowerCase();

                        if (titulo.includes(searchTerm) || genero.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            }

            //función de búsqueda para usuarios
            const buscarUsuario = document.getElementById('buscar-usuario');
            if (buscarUsuario) {
                buscarUsuario.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const table = document.getElementById('tabla-usuarios');
                    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                    for (let row of rows) {
                        const cells = row.getElementsByTagName('td');
                        //buscamos en nombre (columna 1) y correo (columna 2)
                        const nombre = cells[1].textContent.toLowerCase();
                        const correo = cells[2].textContent.toLowerCase();

                        if (nombre.includes(searchTerm) || correo.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            }

            //filtros para funciones
            const filtroSala = document.getElementById('filtro-sala');
            const filtroFecha = document.getElementById('filtro-fecha');
            const filtroPelicula = document.getElementById('filtro-pelicula');

            const aplicarFiltrosFunciones = function() {
                const salaValue = filtroSala.value.toLowerCase();
                const fechaValue = filtroFecha.value;
                const peliculaValue = filtroPelicula.value.toLowerCase();

                const table = document.getElementById('tabla-funciones');
                const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                for (let row of rows) {
                    const cells = row.getElementsByTagName('td');
                    //obtenemos los valores de cada columna
                    const pelicula = cells[1].textContent.toLowerCase();
                    const sala = cells[2].textContent.toLowerCase();
                    const fechaHora = cells[3].textContent;

                    //convertimos la fecha de la tabla a formato año-mes-días para comparar
                    const fechaParts = fechaHora.split(' ');
                    const fechaTabla = fechaParts[0].split('/').reverse().join('-');


                    let matchSala = salaValue === '' || sala.includes(salaValue);
                    let matchFecha = fechaValue === '' || fechaTabla === fechaValue;
                    let matchPelicula = peliculaValue === '' || pelicula.includes(peliculaValue);


                    row.style.display = (matchSala && matchFecha && matchPelicula) ? '' : 'none';
                }
            };


            if (filtroSala) filtroSala.addEventListener('change', aplicarFiltrosFunciones);
            if (filtroFecha) filtroFecha.addEventListener('change', aplicarFiltrosFunciones);
            if (filtroPelicula) filtroPelicula.addEventListener('input', aplicarFiltrosFunciones);
        });
    </script>
</body>

</html>