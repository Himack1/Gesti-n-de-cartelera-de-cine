# 🎬 Sistema de Gestión de Cartelera y Reservas de Cine

Plataforma web para la exhibición de películas en cartelera, reserva de butacas en tiempo real, venta de combos de dulcería, portal de clientes y panel administrativo con estadísticas avanzadas de taquilla. Desarrollada con **PHP** y **Microsoft SQL Server**.

---

## 🚀 Funcionalidades Destacadas

### 🍿 1. Cartelera Interactiva
- Vista en cuadrícula interactiva con pósters en alta resolución, sinopsis, duración, clasificación (PG-13, R, etc.) y fecha de estreno.
- Filtros dinámicos por género, clasificación y búsqueda en tiempo real con JavaScript.

### 🎟️ 2. Flujo Completo de Reserva y Boletos
- **Detalle de la película:** Selección de fecha, sala (2D, 3D, IMAX/VIP) y tanda horaria.
- **Selección de combos:** Posibilidad de añadir combos de palomitas, bebidas y golosinas a la orden.
- **Procesamiento de pago:** Desglose con subtotal, cálculo de impuestos y confirmación con código de reserva único.
- **Historial de compras:** Panel donde el cliente puede consultar y descargar sus reservaciones anteriores.

### 👤 3. Autenticación y Perfil de Usuario
- Módulos independientes de inicio de sesión (`login/login.html`) y registro de nuevos clientes (`login/registro.html`).
- Validación de sesiones activas, protección de rutas y actualización de datos personales.

### ⚙️ 4. Panel de Control Administrativo
- Gestión CRUD de películas: agregar nuevas películas, actualizar pósters, estados en cartelera y horarios.
- Configuración de salas, tandas y precios de admisión.

### 📊 5. Reportes y Analíticas de Taquilla
- Métricas clave en tiempo real: ingresos totales, boletos vendidos, promedio de consumo por cliente y ocupación de salas.
- Gráficos interactivos con **Chart.js** (películas más taquilleras, ventas por día de la semana y mix de productos).
- Exportación de datos de taquilla a formato tabular (CSV / Excel).

---

## 🛠️ Stack Tecnológico

- **Backend:** PHP 8.x (Conexión nativa con SQL Server mediante controlador `sqlsrv` y PDO).
- **Base de Datos:** Microsoft SQL Server (Base de datos: `Cine`).
- **Frontend:** HTML5, CSS3 moderno con efectos Glassmorphism, JavaScript nativo (ES6+), Chart.js y FontAwesome.
- **Servidor Web:** Apache 2.4 (WampServer).

---

## 💻 Instalación y Configuración

1. **Clonar el proyecto en tu directorio web:**
   ```bash
   cd C:\wamp64\www\
   git clone https://github.com/Himack1/Gesti-n-de-cartelera-de-cine.git
   ```

2. **Configuración de Base de Datos:**
   - Asegúrate de tener **Microsoft SQL Server** en ejecución con la base de datos `Cine`.
   - Revisa las credenciales de conexión en [`conexion.php`](conexion.php):
     ```php
     $serverName = "localhost,1433";
     $connectionOptions = array(
         "Database" => "Cine",
         "UID" => "sa",
         "PWD" => "123456",
         "CharacterSet" => "UTF-8"
     );
     ```

3. **Ejecutar el Sistema:**
   - Abre WampServer y accede en tu navegador a:
     ```text
     http://localhost/Proyecto_Cine/cartelera.php
     ```

---

## 👨‍💻 Autor

Desarrollado por **Himack1**  
GitHub: [@Himack1](https://github.com/Himack1)
