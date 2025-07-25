<?php
ob_start();
header('Content-Type: application/json');
require_once 'funciones_bd.php';

$result = listarPeliculas();
ob_clean();
echo json_encode($result, JSON_UNESCAPED_UNICODE);


$serverName = "localhost,1433";
$connectionOptions = array("Database" => "Cine", "UID" => "sa", "PWD" => "123456", "CharacterSet" => "UTF-8");
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) { ob_clean(); echo json_encode(['error' => 'Error de conexión']); exit; }
$sql = "SELECT titulo, duracion_min, clasificacion, genero, fecha_estreno, imagen_url FROM peliculas";
$stmt = sqlsrv_query($conn, $sql);
if (!$stmt) { ob_clean(); echo json_encode(['error' => 'Error en consulta']); exit; }
$peliculas = [];
while ($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $fecha = '';
    if (isset($fila['fecha_estreno']) && $fila['fecha_estreno']) {
        if (is_object($fila['fecha_estreno'])) {
            $fecha = $fila['fecha_estreno']->format('Y-m-d');
        } else {
            $fecha = $fila['fecha_estreno'];
        }
    }
    $peliculas[] = [
        'titulo' => $fila['titulo'] ?? '',
        'duracion' => ($fila['duracion_min'] ?? 0) . ' min',
        'clasificacion' => $fila['clasificacion'] ?? '',
        'genero' => $fila['genero'] ?? '',
        'fecha' => $fecha,
        'imagen' => $fila['imagen_url'] ?? ''
    ];
}
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
ob_clean();
echo json_encode($peliculas, JSON_UNESCAPED_UNICODE);