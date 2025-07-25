<?php
// funciones_reportes.php - Archivo de funciones complementarias para reportes
include_once 'conexion.php'; // Asegúrate de que este archivo contiene la configuración de conexión a la base de datos


class ReportesManager {
    private $conn;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    // Calcular ocupación porcentual de salas
    public function calcularOcupacionSala($sala, $fecha_inicio, $fecha_fin, $capacidad_sala = 100) {
        $query = "
            SELECT 
                COUNT(DISTINCT fecha_funcion, hora_funcion) as total_funciones,
                SUM(cantidad_entradas) as entradas_vendidas,
                AVG(CAST(cantidad_entradas AS FLOAT)) as promedio_entradas_por_funcion
            FROM Historial_Compras 
            WHERE sala = ? 
            AND fecha_compra BETWEEN ? AND ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$sala, $fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $ocupacion_porcentual = 0;
        if ($resultado['total_funciones'] > 0) {
            $ocupacion_porcentual = ($resultado['promedio_entradas_por_funcion'] / $capacidad_sala) * 100;
        }
        
        return [
            'total_funciones' => $resultado['total_funciones'],
            'entradas_vendidas' => $resultado['entradas_vendidas'],
            'ocupacion_porcentual' => round($ocupacion_porcentual, 2),
            'promedio_entradas' => round($resultado['promedio_entradas_por_funcion'], 2)
        ];
    }
    
    // Análisis de tendencias de ventas
    public function analizarTendencias($fecha_inicio, $fecha_fin) {
        $query = "
            WITH VentasDiarias AS (
                SELECT 
                    CAST(fecha_compra AS DATE) as fecha,
                    SUM(total) as ingresos_dia,
                    COUNT(*) as transacciones_dia,
                    SUM(cantidad_entradas) as entradas_dia
                FROM Historial_Compras 
                WHERE fecha_compra BETWEEN ? AND ?
                GROUP BY CAST(fecha_compra AS DATE)
            ),
            TendenciasCTE AS (
                SELECT 
                    fecha,
                    ingresos_dia,
                    LAG(ingresos_dia) OVER (ORDER BY fecha) as ingresos_anterior,
                    transacciones_dia,
                    entradas_dia
                FROM VentasDiarias
            )
            SELECT 
                fecha,
                ingresos_dia,
                ingresos_anterior,
                CASE 
                    WHEN ingresos_anterior IS NULL THEN 0
                    WHEN ingresos_anterior = 0 THEN 100
                    ELSE ROUND(((ingresos_dia - ingresos_anterior) / ingresos_anterior) * 100, 2)
                END as crecimiento_porcentual,
                transacciones_dia,
                entradas_dia
            FROM TendenciasCTE
            ORDER BY fecha";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Reporte de horarios más rentables
    public function horariosRentables($fecha_inicio, $fecha_fin) {
        $query = "
            SELECT 
                hora_funcion,
                COUNT(*) as total_funciones,
                SUM(total) as ingresos_totales,
                SUM(cantidad_entradas) as entradas_vendidas,
                AVG(total) as ticket_promedio,
                CASE 
                    WHEN CAST(SUBSTRING(hora_funcion, 1, 2) AS INT) < 12 THEN 'Matinée'
                    WHEN CAST(SUBSTRING(hora_funcion, 1, 2) AS INT) < 18 THEN 'Tarde'
                    ELSE 'Noche'
                END as franja_horaria
            FROM Historial_Compras 
            WHERE fecha_compra BETWEEN ? AND ?
            GROUP BY hora_funcion
            ORDER BY ingresos_totales DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Análisis de combos más vendidos por película
    public function combosPorPelicula($fecha_inicio, $fecha_fin) {
        $query = "
            SELECT 
                hc.pelicula_titulo,
                rc.nombre_combo,
                SUM(rc.cantidad) as cantidad_total,
                SUM(rc.subtotal) as ingresos_combo,
                AVG(rc.precio_unitario) as precio_promedio,
                COUNT(DISTINCT hc.id_reserva) as reservas_con_combo
            FROM Reserva_Combos rc
            INNER JOIN Reservas r ON rc.id_reserva = r.id_reserva
            INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
            WHERE hc.fecha_compra BETWEEN ? AND ?
            GROUP BY hc.pelicula_titulo, rc.nombre_combo
            ORDER BY hc.pelicula_titulo, cantidad_total DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Generar reporte PDF (requiere biblioteca TCPDF o similar)
    public function generarReportePDF($datos, $titulo, $fecha_inicio, $fecha_fin) {
        // Esta función requeriría una biblioteca como TCPDF
        // Aquí está la estructura básica
        /*
        require_once('tcpdf/tcpdf.php');
        
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $titulo, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, "Período: $fecha_inicio - $fecha_fin", 0, 1, 'C');
        
        // Agregar datos en formato tabla
        foreach($datos as $fila) {
            // Procesar y agregar cada fila
        }
        
        return $pdf->Output('S'); // Retorna el PDF como string
        */
        
        return "Funcionalidad PDF requiere biblioteca TCPDF";
    }
    
    // Comparación entre períodos
    public function compararPeriodos($fecha_inicio_1, $fecha_fin_1, $fecha_inicio_2, $fecha_fin_2) {
        $query = "
            WITH Periodo1 AS (
                SELECT 
                    COUNT(*) as transacciones,
                    SUM(total) as ingresos,
                    SUM(cantidad_entradas) as entradas,
                    AVG(total) as ticket_promedio
                FROM Historial_Compras 
                WHERE fecha_compra BETWEEN ? AND ?
            ),
            Periodo2 AS (
                SELECT 
                    COUNT(*) as transacciones,
                    SUM(total) as ingresos,
                    SUM(cantidad_entradas) as entradas,
                    AVG(total) as ticket_promedio
                FROM Historial_Compras 
                WHERE fecha_compra BETWEEN ? AND ?
            )
            SELECT 
                p1.transacciones as transacciones_p1,
                p1.ingresos as ingresos_p1,
                p1.entradas as entradas_p1,
                p1.ticket_promedio as ticket_promedio_p1,
                p2.transacciones as transacciones_p2,
                p2.ingresos as ingresos_p2,
                p2.entradas as entradas_p2,
                p2.ticket_promedio as ticket_promedio_p2,
                CASE 
                    WHEN p1.ingresos = 0 THEN 0
                    ELSE ROUND(((p2.ingresos - p1.ingresos) / p1.ingresos) * 100, 2)
                END as crecimiento_ingresos,
                CASE 
                    WHEN p1.transacciones = 0 THEN 0
                    ELSE ROUND(((p2.transacciones - p1.transacciones) / CAST(p1.transacciones AS FLOAT)) * 100, 2)
                END as crecimiento_transacciones
            FROM Periodo1 p1 CROSS JOIN Periodo2 p2";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $fecha_inicio_1 . ' 00:00:00', $fecha_fin_1 . ' 23:59:59',
            $fecha_inicio_2 . ' 00:00:00', $fecha_fin_2 . ' 23:59:59'
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Predicción simple basada en tendencias (muy básica)
    public function prediccionVentas($dias_futuros, $fecha_inicio, $fecha_fin) {
        // Calcular promedio de ventas del período
        $query = "
            SELECT 
                AVG(CAST(ingresos_dia AS FLOAT)) as promedio_diario,
                STDEV(ingresos_dia) as desviacion_estandar
            FROM (
                SELECT 
                    SUM(total) as ingresos_dia
                FROM Historial_Compras 
                WHERE fecha_compra BETWEEN ? AND ?
                GROUP BY CAST(fecha_compra AS DATE)
            ) as ventas_diarias";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59']);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $proyeccion = [];
        $promedio = $resultado['promedio_diario'] ?? 0;
        
        for ($i = 1; $i <= $dias_futuros; $i++) {
            $fecha_futura = date('Y-m-d', strtotime("+$i days"));
            $proyeccion[] = [
                'fecha' => $fecha_futura,
                'ingreso_proyectado' => round($promedio, 2),
                'rango_minimo' => round($promedio * 0.8, 2),
                'rango_maximo' => round($promedio * 1.2, 2)
            ];
        }
        
        return $proyeccion;
    }
    
    // Análisis de asientos más populares
    public function asientosPopulares($fecha_inicio, $fecha_fin, $sala = null) {
        $query = "
            SELECT 
                ra.asiento,
                " . ($sala ? "hc.sala," : "") . "
                COUNT(*) as veces_reservado,
                AVG(ra.precio) as precio_promedio,
                SUM(ra.precio) as ingresos_asiento
            FROM Reserva_Asientos ra
            INNER JOIN Reservas r ON ra.id_reserva = r.id_reserva
            INNER JOIN Historial_Compras hc ON r.id_reserva = hc.id_reserva
            WHERE hc.fecha_compra BETWEEN ? AND ?";
        
        $params = [$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59'];
        
        if ($sala) {
            $query .= " AND hc.sala = ?";
            $params[] = $sala;
        }
        
        $query .= "
            GROUP BY ra.asiento" . ($sala ? ", hc.sala" : "") . "
            ORDER BY veces_reservado DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Reporte de clientes frecuentes
    public function clientesFrecuentes($fecha_inicio, $fecha_fin, $limite = 20) {
        $query = "
            SELECT 
                usuario_email,
                usuario_nombre,
                COUNT(*) as total_compras,
                SUM(total) as gasto_total,
                AVG(total) as gasto_promedio,
                SUM(cantidad_entradas) as entradas_compradas,
                MIN(fecha_compra) as primera_compra,
                MAX(fecha_compra) as ultima_compra
            FROM Historial_Compras 
            WHERE fecha_compra BETWEEN ? AND ?
            GROUP BY usuario_email, usuario_nombre
            HAVING COUNT(*) > 1
            ORDER BY gasto_total DESC
            OFFSET 0 ROWS FETCH NEXT ? ROWS ONLY";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59', $limite]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Funciones de utilidad para formateo
class ReportesUtils {
    
    public static function formatearMoneda($cantidad) {
        return '$' . number_format($cantidad, 2);
    }
    
    public static function formatearPorcentaje($valor) {
        return number_format($valor, 2) . '%';
    }
    
    public static function calcularCrecimiento($valor_actual, $valor_anterior) {
        if ($valor_anterior == 0) return $valor_actual > 0 ? 100 : 0;
        return (($valor_actual - $valor_anterior) / $valor_anterior) * 100;
    }
    
    public static function generarColoresGrafico($cantidad) {
        $colores_base = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c',
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#667eea', '#764ba2'
        ];
        
        $colores = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $colores[] = $colores_base[$i % count($colores_base)];
        }
        
        return $colores;
    }
    
    public static function exportarJSON($datos, $nombre_archivo) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.json"');
        echo json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function exportarXML($datos, $nombre_archivo, $elemento_raiz = 'reporte') {
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.xml"');
        
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><$elemento_raiz></$elemento_raiz>");
        
        function array_to_xml($data, &$xml) {
            foreach($data as $key => $value) {
                if(is_numeric($key)){
                    $key = 'item' . $key;
                }
                if(is_array($value)) {
                    $subnode = $xml->addChild($key);
                    array_to_xml($value, $subnode);
                } else {
                    $xml->addChild($key, htmlspecialchars($value));
                }
            }
        }
        
        array_to_xml($datos, $xml);
        echo $xml->asXML();
        exit;
    }
}

// Configuraciones por defecto del sistema
class ConfigReportes {
    const CAPACIDAD_SALA_DEFAULT = 100;
    const DIAS_PROYECCION_DEFAULT = 30;
    const LIMITE_TOP_PELICULAS = 10;
    const LIMITE_CLIENTES_FRECUENTES = 20;
    
    // Colores para gráficos
    const COLORES_PRINCIPALES = [
        'primario' => '#667eea',
        'secundario' => '#764ba2',
        'exito' => '#28a745',
        'peligro' => '#dc3545',
        'advertencia' => '#ffc107',
        'info' => '#17a2b8'
    ];
    
    // Configuración de franjas horarias
    const FRANJAS_HORARIAS = [
        'matinee' => ['inicio' => 0, 'fin' => 12, 'nombre' => 'Matinée'],
        'tarde' => ['inicio' => 12, 'fin' => 18, 'nombre' => 'Tarde'],
        'noche' => ['inicio' => 18, 'fin' => 24, 'nombre' => 'Noche']
    ];
}
?>