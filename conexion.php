<?php
// Configuración de conexión
$serverName = "localhost,1433"; // Ajusta según tu servidor
$connectionOptions = array(
    "Database" => "Cine",
    "UID" => "sa",
    "PWD" => "123456",
    "CharacterSet" => "UTF-8"
);

// Establecer conexión
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Verificar conexión
if ($conn === false) {
    // Obtener errores detallados
    $errors = sqlsrv_errors();
    
    // Log para debugging (NO mostrar en pantalla)
    error_log("Error de conexión SQL Server: " . print_r($errors, true));
    
    // NO USAR ECHO AQUÍ - Solo log
    // Los errores se manejan en el archivo que use esta conexión
    
    $conn = false; // Asegurar que $conn sea false
} else {
    // ✅ Conexión exitosa - NO mostrar mensaje HTML
    // Solo registrar en log si es necesario
    error_log("Conexión exitosa a la base de datos 'Cine'");
}

// COMENTAR O ELIMINAR LA FUNCIÓN DE PRUEBA QUE HACE ECHO
/*
function probarConexion($conn) {
    $sql = "SELECT COUNT(*) as total FROM peliculas";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        error_log("Error en consulta: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    error_log("Total de películas en la BD: " . $row['total']);
    
    sqlsrv_free_stmt($stmt);
    return true;
}

// NO ejecutar la prueba aquí
// if ($conn) {
//     probarConexion($conn);
// }
*/

// NO cerrar con ?> 