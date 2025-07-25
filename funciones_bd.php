<?php
// funciones_bd.php

// 1. Función para obtener la conexión usando sqlsrv_connect
function obtenerConexion() {
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }
    $serverName = "localhost,1433";
    $connectionOptions = [
        "Database"     => "Cine",
        "UID"          => "sa",
        "PWD"          => "123456",
        "CharacterSet" => "UTF-8",
        "ReturnDatesAsStrings" => true // Para evitar problemas con fechas
    ];
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        error_log("Error de conexión SQL Server: " . print_r(sqlsrv_errors(), true));
        return null;
    }
     if ($conn === false) {
        // Añadir este mensaje detallado
        die("Error de conexión: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}

// 2. Función para obtener listado de películas (similar a obtener_peliculas.php)
function listarPeliculas(): array {
    $conn = obtenerConexion();
    if (!$conn) {
        return ['error' => 'Error de conexión'];
    }
    $sql = "SELECT id_pelicula, titulo, duracion_min, clasificacion, genero, fecha_estreno, imagen_url 
            FROM dbo.peliculas";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        error_log("Error en consulta listarPeliculas: " . print_r(sqlsrv_errors(), true));
        return ['error' => 'Error en consulta'];
    }
    $peliculas = [];
    while ($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Formatear fecha a YYYY-MM-DD
        $fecha = '';
        if (isset($fila['fecha_estreno']) && $fila['fecha_estreno']) {
            if ($fila['fecha_estreno'] instanceof DateTime) {
                $fecha = $fila['fecha_estreno']->format('Y-m-d');
            } elseif (is_string($fila['fecha_estreno'])) {
                $ts = strtotime($fila['fecha_estreno']);
                if ($ts !== false) {
                    $fecha = date('Y-m-d', $ts);
                } else {
                    $fecha = $fila['fecha_estreno'];
                }
            }
        }
        $peliculas[] = [
            'id_pelicula'   => isset($fila['id_pelicula']) ? (int)$fila['id_pelicula'] : null,
            'titulo'        => $fila['titulo'] ?? '',
            'duracion'      => isset($fila['duracion_min']) ? ($fila['duracion_min'] . ' min') : '',
            'clasificacion' => $fila['clasificacion'] ?? '',
            'genero'        => $fila['genero'] ?? '',
            'fecha'         => $fecha,
            'imagen'        => $fila['imagen_url'] ?? ''
        ];
    }
    sqlsrv_free_stmt($stmt);
    return $peliculas;
}

// 3. Función para obtener una sola película por ID o título
function obtenerPelicula(?int $id = null, ?string $titulo = null): ?array {
    $conn = obtenerConexion();
    if (!$conn) {
        return null;
    }
    $pelicula = null;
    if ($id !== null && is_numeric($id) && (int)$id > 0) {
        $id_pelicula = (int)$id;
        $tsql = "SELECT * FROM dbo.peliculas WHERE id_pelicula = ?";
        $params = [$id_pelicula];
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt !== false) {
            $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
        } else {
            error_log("obtenerPelicula ID error: " . print_r(sqlsrv_errors(), true));
        }
    }
    if (!$pelicula && $titulo !== null && trim($titulo) !== '') {
        $t = trim($titulo);
        $tsql = "SELECT TOP 1 * FROM dbo.peliculas WHERE titulo = ? OR titulo LIKE ?";
        $params = [$t, '%' . $t . '%'];
        $stmt = sqlsrv_query($conn, $tsql, $params);
        if ($stmt !== false) {
            $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
        } else {
            error_log("obtenerPelicula título error: " . print_r(sqlsrv_errors(), true));
        }
    }
    return $pelicula ?: null;
}

// 4. Función para obtener funciones de una película
function obtenerFuncionesPorPelicula(int $id_pelicula): array
{
    $conn = obtenerConexion();
    if (!$conn) {
        return [];
    }
    $tsql = "
    SELECT f.id_funcion, f.fecha_hora, f.precio,
           s.nombre_sala, s.formato AS formato_sala
    FROM dbo.funciones f
    INNER JOIN dbo.salas s ON f.id_sala = s.id_sala
    WHERE f.id_pelicula = ? 
    ORDER BY f.fecha_hora ASC
    ";

    $params = [$id_pelicula];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    $funciones = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Formatear fechas:
            if (!empty($row['fecha_hora'])) {
                try {
                    if ($row['fecha_hora'] instanceof DateTime) {
                        $dt = $row['fecha_hora'];
                    } else {
                        $dt = new DateTime($row['fecha_hora']);
                    }
                    $row['fecha_formateada'] = $dt->format('d/m/Y');
                    $row['hora_formateada']  = $dt->format('H:i');
                } catch (Exception $e) {
                    error_log("Error formateando fecha_hora: " . $e->getMessage());
                    $row['fecha_formateada'] = '';
                    $row['hora_formateada']  = '';
                }
            } else {
                $row['fecha_formateada'] = '';
                $row['hora_formateada']  = '';
            }

            $funciones[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    } else {
        error_log("obtenerFuncionesPorPelicula error: " . print_r(sqlsrv_errors(), true));
    }
    return $funciones;
}

// 5. Función para obtener detalles de una función por ID
function obtenerFuncionPorId(int $id_funcion): ?array {
    $conn = obtenerConexion();
    

      if (!$conn) {
        $_SESSION['error'] = "Error de conexión a la base de datos";
        return null;
    }
    
    
   $tsql = "SELECT f.id_funcion, f.fecha_hora, f.precio, 
                p.titulo AS nombre_pelicula,
                p.id_pelicula,  
                s.nombre_sala, s.formato AS formato_sala
         FROM dbo.funciones f
         INNER JOIN dbo.peliculas p ON f.id_pelicula = p.id_pelicula
         INNER JOIN dbo.salas s ON f.id_sala = s.id_sala
         WHERE f.id_funcion = ?";

    $params = [$id_funcion];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error en consulta: ";
        foreach ($errors as $error) {
            $errorMsg .= "SQLSTATE: " . $error['SQLSTATE'] . " | ";
            $errorMsg .= "Código: " . $error['code'] . " | ";
            $errorMsg .= "Mensaje: " . $error['message'];
        }
        $_SESSION['error'] = $errorMsg;
        return null;
    }
    
    
    $funcion = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
     if (!$funcion) {
        $_SESSION['error'] = "No se encontró función con ID: $id_funcion";
        return null;
    }

    // Formatear fechas de manera más robusta
    if (isset($funcion['fecha_hora'])) {
        if ($funcion['fecha_hora'] instanceof DateTime) {
            $fechaHora = $funcion['fecha_hora'];
        } else {
            try {
                $fechaHora = new DateTime($funcion['fecha_hora']);
            } catch (Exception $e) {
                error_log("Error al crear DateTime: " . $e->getMessage());
                $fechaHora = false;
            }
        }
        
        if ($fechaHora) {
            $funcion['fecha_formateada'] = $fechaHora->format('d/m/Y');
            $funcion['hora_formateada'] = $fechaHora->format('H:i');
        } else {
            $funcion['fecha_formateada'] = 'N/A';
            $funcion['hora_formateada'] = 'N/A';
        }
    } else {
        $funcion['fecha_formateada'] = 'N/A';
        $funcion['hora_formateada'] = 'N/A';
    }
    
    // Asegurar que todos los campos necesarios existan
    $camposRequeridos = ['nombre_pelicula', 'nombre_sala', 'formato_sala', 'precio'];
    foreach ($camposRequeridos as $campo) {
        if (!isset($funcion[$campo])) {
            $funcion[$campo] = 'N/A';
        }
    }

    if (!$funcion) {
        // Limpiar el error en sesión si no se encuentra
        unset($_SESSION['error']);
        return null;
    }
    
    return $funcion;
}

// 6. Función para obtener asientos reservados para una función
/*function obtenerAsientosReservados(int $id_funcion): array {
    $conn = obtenerConexion();
    
    
    $tsql = "SELECT CONCAT(a.fila, a.numero) AS asiento 
             FROM dbo.detalle_venta dv
             INNER JOIN dbo.asientos a ON dv.id_asiento = a.id_asiento
             WHERE dv.id_funcion = ?";
    
    $params = [$id_funcion];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    $reservados = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $reservados[] = $row['asiento'];
        }
        sqlsrv_free_stmt($stmt);
    } else {
        error_log("Error en obtenerAsientosReservados: " . print_r(sqlsrv_errors(), true));
    }
    
    return $reservados;
}*/

// 7. Función para guardar una reserva completa
function guardarReserva($id_usuario, $id_funcion, $asientos, $combos, $total, $datos_cliente) {
    $conn = obtenerConexion();
    if (!$conn) {
        return false;
    }
    
    // Obtener detalles de la función para el precio
    $funcion = obtenerFuncionPorId($id_funcion);
    if (!$funcion) {
        error_log("Error: No se pudo obtener la función con ID $id_funcion");
        return false;
    }
    $precio_asiento = $funcion['precio'];
    
    try {
        // Iniciar transacción
        sqlsrv_begin_transaction($conn);
        
        // 1. Insertar en ventas
        $tsql_venta = "INSERT INTO ventas (id_usuario, fecha_venta, monto_total) 
                       VALUES (?, GETDATE(), ?)";
        $params_venta = [$id_usuario, $total];
        $stmt_venta = sqlsrv_query($conn, $tsql_venta, $params_venta);
        
        if ($stmt_venta === false) {
            throw new Exception("Error al guardar venta: " . print_r(sqlsrv_errors(), true));
        }
        
        // Obtener ID de la venta
        $tsql_id = "SELECT SCOPE_IDENTITY() AS id_venta";
        $stmt_id = sqlsrv_query($conn, $tsql_id);
        if ($stmt_id === false) {
            throw new Exception("Error al obtener ID de venta: " . print_r(sqlsrv_errors(), true));
        }
        
        $row = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
        $id_venta = $row['id_venta'];
        
        // 2. Guardar asientos (detalle_venta)
        foreach ($asientos as $asiento) {
            // Primero necesitas obtener el id_asiento basado en fila y número
            $fila = substr($asiento, 0, 1); // Ej: 'A' de 'A1'
            $numero = substr($asiento, 1);  // Ej: '1' de 'A1'
            
            $tsql_asiento = "SELECT id_asiento FROM asientos 
                             WHERE id_sala = (
                                 SELECT id_sala FROM funciones WHERE id_funcion = ?
                             )
                             AND fila = ? AND numero = ?";
            
            $params_asiento = [$id_funcion, $fila, $numero];
            $stmt_asiento = sqlsrv_query($conn, $tsql_asiento, $params_asiento);
            
            if ($stmt_asiento === false) {
                throw new Exception("Error al buscar asiento: " . print_r(sqlsrv_errors(), true));
            }
            
            if ($row_asiento = sqlsrv_fetch_array($stmt_asiento, SQLSRV_FETCH_ASSOC)) {
                $id_asiento = $row_asiento['id_asiento'];
                
                $tsql_detalle = "INSERT INTO detalle_venta (id_venta, id_funcion, id_asiento, precio_unitario) 
                                 VALUES (?, ?, ?, ?)";
                $params_detalle = [$id_venta, $id_funcion, $id_asiento, $precio_asiento];
                $stmt_detalle = sqlsrv_query($conn, $tsql_detalle, $params_detalle);
                
                if ($stmt_detalle === false) {
                    throw new Exception("Error al guardar detalle venta: " . print_r(sqlsrv_errors(), true));
                }
                
                // Actualizar estado del asiento
                $tsql_update = "UPDATE asientos SET estado = 'reservado' 
                                WHERE id_asiento = ?";
                $stmt_update = sqlsrv_query($conn, $tsql_update, [$id_asiento]);
                
                if ($stmt_update === false) {
                    throw new Exception("Error al actualizar asiento: " . print_r(sqlsrv_errors(), true));
                }
            } else {
                throw new Exception("Asiento no encontrado: $asiento");
            }
        }
        
        // 3. Guardar combos (venta_snack)
        foreach ($combos as $id_combo => $cantidad) {
            if ($cantidad > 0) {
                // Obtener precio del snack/combo
                $tsql_snack = "SELECT precio FROM snack_bar WHERE id_producto = ?";
                $stmt_snack = sqlsrv_query($conn, $tsql_snack, [$id_combo]);
                
                if ($stmt_snack === false) {
                    throw new Exception("Error al obtener precio del combo: " . print_r(sqlsrv_errors(), true));
                }
                
                if ($row_snack = sqlsrv_fetch_array($stmt_snack, SQLSRV_FETCH_ASSOC)) {
                    $precio = $row_snack['precio'];
                    $subtotal = $cantidad * $precio;
                    
                    $tsql_venta_snack = "INSERT INTO venta_snack (id_venta, id_producto, cantidad, subtotal) 
                                         VALUES (?, ?, ?, ?)";
                    $params_venta_snack = [$id_venta, $id_combo, $cantidad, $subtotal];
                    $stmt_venta_snack = sqlsrv_query($conn, $tsql_venta_snack, $params_venta_snack);
                    
                    if ($stmt_venta_snack === false) {
                        throw new Exception("Error al guardar venta_snack: " . print_r(sqlsrv_errors(), true));
                    }
                    
                    // Actualizar stock
                    $tsql_update_stock = "UPDATE snack_bar SET stock_actual = stock_actual - ? 
                                          WHERE id_producto = ?";
                    $stmt_update_stock = sqlsrv_query($conn, $tsql_update_stock, [$cantidad, $id_combo]);
                    
                    if ($stmt_update_stock === false) {
                        throw new Exception("Error al actualizar stock: " . print_r(sqlsrv_errors(), true));
                    }
                } else {
                    throw new Exception("Combo no encontrado: $id_combo");
                }
            }
        }
        
        // Confirmar la transacción
        sqlsrv_commit($conn);
        return $id_venta;
        
    } catch (Exception $e) {
        // Revertir la transacción en caso de error
        sqlsrv_rollback($conn);
        error_log($e->getMessage());
        return false;
    }
}

function obtenerPeliculaPorId($id_pelicula) {
    $conn = obtenerConexion();
    if (!$conn) {
        return null;
    }
    
    // Añadir los campos faltantes: genero y fecha_estreno
    $tsql = "SELECT id_pelicula, titulo, imagen_url, duracion_min, clasificacion, 
                    trailer_url, formato, genero, fecha_estreno 
             FROM peliculas 
             WHERE id_pelicula = ?";
    
    $params = [$id_pelicula];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    if ($stmt === false) {
        error_log("Error al obtener película: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    
    $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $pelicula;
}

function obtenerSalaPorId($id) {
    $conn = obtenerConexion();
    if (!$conn) return null;
    
    $tsql = "SELECT * FROM salas WHERE id_sala = ?";
    $params = [$id];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    if ($stmt === false) {
        error_log("Error en obtenerSalaPorId: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function obtenerUsuarioPorId($id) {
    $conn = obtenerConexion();
    if (!$conn) {
        error_log("Error de conexión en obtenerUsuarioPorId para ID: $id");
        return null;
    }
    
    $tsql = "SELECT id_usuario, nombre, correo, rol, fecha_registro 
             FROM usuarios 
             WHERE id_usuario = ?";
    
    $params = [$id];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMsg = "Error SQL para ID $id: ";
        foreach ($errors as $error) {
            $errorMsg .= "[SQLSTATE: ".$error['SQLSTATE']."] [Code: ".$error['code']."] ".$error['message'];
        }
        error_log($errorMsg);
        return null;
    }
    
    $usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    // Convertir DateTime a string si es necesario
    if ($usuario && isset($usuario['fecha_registro']) && $usuario['fecha_registro'] instanceof DateTime) {
        $usuario['fecha_registro'] = $usuario['fecha_registro']->format('Y-m-d H:i:s');
    }
    
    return $usuario;
}

function obtenerDetallesPelicula(int $id_pelicula): array {
    $conn = obtenerConexion();
    if (!$conn) return [];
    
    $tsql = "SELECT titulo, imagen_url, duracion_min, clasificacion 
             FROM peliculas 
             WHERE id_pelicula = ?";
    
    $params = [$id_pelicula];
    $stmt = sqlsrv_query($conn, $tsql, $params);
    
    if ($stmt === false) {
        error_log("Error al obtener detalles de película: " . print_r(sqlsrv_errors(), true));
        return [];
    }
    
    $pelicula = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    return $pelicula ?: [];
}

function limpiarErroresSesion() {
    if (isset($_SESSION['error'])) {
        unset($_SESSION['error']);
    }
}



 

