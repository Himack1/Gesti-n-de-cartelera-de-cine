<?php
session_start();
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cartelera - Caribbean Cinemas</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

  <header>
    <h1>Cartelera</h1>
    <?php if ($nombreUsuario): ?>
      <button class="btn-iniciar-sesion" onclick="location.href='perfil.php'">
        <svg class="icono-usuario" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        <?php echo htmlspecialchars($nombreUsuario); ?>
      </button>
    <?php else: ?>
      <button class="btn-iniciar-sesion" onclick="location.href='login/login.html'">
        <svg class="icono-usuario" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        Iniciar Sesión
      </button>
    <?php endif; ?>
  </header>

  <section class="filtro-cartelera">
    <h2>Buscar Película</h2>
    <form id="form-filtro">
      <input type="text" id="buscarPelicula" placeholder="Ej: Galaxy Guardians..." />

      <select id="genero">
        <option value="">Todos los géneros</option>
        <option value="accion">Acción</option>
        <option value="aventura">Aventura</option>
        <option value="animacion">Animación</option>
        <option value="comedia">Comedia</option>
        <option value="crimen">Crimen</option>
        <option value="drama">Drama</option>
        <option value="fantasia">Fantasía</option>
        <option value="ciencia-ficcion">Ciencia Ficción</option>
        <option value="misterio">Misterio</option>
        <option value="thriller">Thriller</option>
        <option value="terror">Terror</option>
      </select>

      <input type="date" id="fecha" />
      <button type="submit">Filtrar</button>
    </form>
  </section>

  <!-- Indicador de carga -->
  <div id="loading" class="loading" style="display: none;">
    <p>Cargando películas...</p>
  </div>

  <main class="cartelera" id="cartelera"></main>

  <script src="cartelera.js?v=1.6"></script>

</body>
</html>

<style>

/* Filtros */
.filtro-cartelera {
  background-color: #f8f8f8;
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 25px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  max-width: 800px;
  margin-left: auto;
  margin-right: auto;
  text-align: center;
}

.filtro-cartelera h2 {
  margin-bottom: 15px;
}

.filtro-cartelera form {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

.filtro-cartelera input,
.filtro-cartelera select,
.filtro-cartelera button {
  padding: 10px;
  font-size: 16px;
  border-radius: 6px;
  border: 1px solid #ccc;
}

.filtro-cartelera button {
  background-color: #1a73e8;
  color: white;
  border: none;
  cursor: pointer;
  transition: background 0.3s ease;
}

.filtro-cartelera button:hover {
  background-color: #155ab6;
}

/* Filtros */

/*iniciar sesion */ 
/* Estilos para el header */
header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

header h1 {
  margin: 0;
  font-size: 2rem;
  font-weight: bold;
}

/* Estilos para el botón de iniciar sesión */
.btn-iniciar-sesion {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 25px;
  color: white;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

.btn-iniciar-sesion:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-iniciar-sesion:active {
  transform: translateY(0);
}

/* Ícono de usuario */
.icono-usuario {
  width: 20px;
  height: 20px;
  stroke: currentColor;
}

/* Responsive */
@media (max-width: 768px) {
  header {
    padding: 1rem;
  }
  
  header h1 {
    font-size: 1.5rem;
  }
  
  .btn-iniciar-sesion {
    padding: 8px 16px;
    font-size: 12px;
  }
  
  .icono-usuario {
    width: 16px;
    height: 16px;
  }
}
/*iniciar sesion */ 
body {
  margin: 0;
  font-family: Arial, sans-serif;
  background-color: #f2f2f2;
}

header {
  background-color: #001f3f;
  color: white;
  padding: 1rem;
  text-align: center;
}

h1 {
  margin: 0;
}

.cartelera {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
  padding: 2rem;
}

.pelicula {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: transform 0.2s;
}

.pelicula:hover {
  transform: scale(1.03);
}

.pelicula img {
  width: 100%;
  height: 300px;
  object-fit: cover;
}

.pelicula-info {
  padding: 1rem;
}

.pelicula-info h2 {
  font-size: 18px;
  margin: 0 0 0.5rem;
}

.pelicula-info p {
  margin: 0.3rem 0;
  color: #555;
}

.boton {
  display: inline-block;
  margin-top: 0.8rem;
  background-color: #0074D9;
  color: white;
  padding: 0.5rem 1rem;
  text-decoration: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
}

/* Estilos adicionales para mejorar la cartelera */

/* Loading indicator */
.loading {
  text-align: center;
  padding: 2rem;
  font-size: 1.2rem;
  color: #666;
}

/* Sin resultados */
.sin-resultados {
  text-align: center;
  padding: 3rem;
  background-color: #f8f9fa;
  border-radius: 10px;
  margin: 2rem;
}

.sin-resultados p {
  font-size: 1.2rem;
  color: #6c757d;
  margin: 0;
}

/* Error message */
.error-mensaje {
  text-align: center;
  padding: 2rem;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 10px;
  margin: 2rem;
  color: #721c24;
}

.error-mensaje p {
  margin: 0.5rem 0;
}

.error-mensaje button {
  margin-top: 1rem;
}

/* Mejoras para las tarjetas de películas */
.pelicula {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.pelicula:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.pelicula-imagen {
  position: relative;
  overflow: hidden;
}

.pelicula img {
  width: 100%;
  height: 350px;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.pelicula:hover img {
  transform: scale(1.05);
}

.pelicula-info {
  padding: 1.5rem;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.pelicula-info h2 {
  font-size: 1.25rem;
  margin: 0 0 1rem;
  color: #2c3e50;
  font-weight: 600;
  line-height: 1.3;
}

.pelicula-info p {
  margin: 0.4rem 0;
  color: #555;
  font-size: 0.9rem;
  line-height: 1.4;
}

.pelicula-info p strong {
  color: #2c3e50;
  font-weight: 600;
}

/* Botón mejorado */
.boton {
  display: inline-block;
  margin-top: auto;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.75rem 1.5rem;
  text-decoration: none;
  border-radius: 25px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: center;
  border: none;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.boton:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

/* Botón limpiar filtros */
.boton-limpiar {
  background-color: #6c757d;
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  transition: background-color 0.3s ease;
}

.boton-limpiar:hover {
  background-color: #5a6268;
}

/* Responsive improvements */
@media (max-width: 768px) {
  .cartelera {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    padding: 1rem;
    gap: 1rem;
  }
  
  .filtro-cartelera {
    margin: 1rem;
    padding: 1rem;
  }
  
  .filtro-cartelera form {
    flex-direction: column;
    align-items: stretch;
  }
  
  .filtro-cartelera input,
  .filtro-cartelera select,
  .filtro-cartelera button {
    margin-bottom: 0.5rem;
  }
  
  .pelicula img {
    height: 280px;
  }
  
  .pelicula-info {
    padding: 1rem;
  }
  
  .pelicula-info h2 {
    font-size: 1.1rem;
  }
}

@media (max-width: 480px) {
  .cartelera {
    grid-template-columns: 1fr;
  }
  
  .pelicula img {
    height: 250px;
  }
}

/* Estilos para el placeholder de imágenes */
.imagen-placeholder {
  width: 100%;
  height: 300px; /* Ajusta según el tamaño que necesites */
  background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 2px dashed #ccc;
  border-radius: 8px;
  color: #666;
  font-size: 14px;
  transition: all 0.3s ease;
}

.imagen-placeholder:hover {
  background: linear-gradient(135deg, #ececec 0%, #d7d7d7 100%);
  border-color: #999;
}

.imagen-placeholder svg {
  margin-bottom: 10px;
  opacity: 0.6;
}

.imagen-placeholder span {
  font-weight: 500;
  text-align: center;
}

/* Mejorar el contenedor de imagen */
.pelicula-imagen {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
  background: #f8f8f8;
}

.pelicula-imagen img {
  width: 100%;
  height: 300px; /* Misma altura que el placeholder */
  object-fit: cover;
  display: block;
}

/* Animación suave para cuando las imágenes sí cargan */
.pelicula-imagen img[style*="opacity:1"] {
  transition: opacity 0.3s ease-in-out;
}

/* Estilo adicional para las cards de película */
.pelicula {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.pelicula:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

</style>
