const contenedor = document.getElementById("cartelera");
const loading = document.getElementById("loading");
let peliculas = [];

function mostrarLoading(mostrar) {
  if (loading) {
    loading.style.display = mostrar ? "block" : "none";
  }
}

function mostrarPeliculas(peliculasAMostrar) {
  contenedor.innerHTML = "";
  
  if (peliculasAMostrar.length === 0) {
    contenedor.innerHTML = '<div class="sin-resultados"><p>No se encontraron películas que coincidan con los filtros.</p></div>';
    return;
  }

  peliculasAMostrar.forEach(pelicula => {
    const card = document.createElement("div");
    card.className = "pelicula";
    card.innerHTML = `
      <div class="pelicula-imagen">
        ${crearElementoImagen(pelicula.imagen, pelicula.titulo)}
      </div>
      <div class="pelicula-info">
        <h2>${pelicula.titulo}</h2>
        <p><strong>Duración:</strong> ${pelicula.duracion}</p>
        <p><strong>Clasificación:</strong> ${pelicula.clasificacion}</p>
        <p><strong>Género:</strong> ${formatearGenero(pelicula.genero)}</p>
        <p><strong>Estreno:</strong> ${formatearFecha(pelicula.fecha)}</p>
        <a class="boton" href="#" onclick="verDetalles(${pelicula.id_pelicula || pelicula.id}, '${pelicula.titulo}'); return false;">Ver más</a>
      </div>
    `;
    contenedor.appendChild(card);
  });
}

// FUNCIÓN CORREGIDA - Reemplaza la función verDetalles en cartelera.js
function verDetalles(idPelicula, titulo) {
  console.log("verDetalles llamada con:", { idPelicula, titulo, tipo: typeof idPelicula });
  
  // Convertir idPelicula a número si es string
  const id = parseInt(idPelicula);
  
  // Verificar que tenemos un ID válido o un título
  if ((!id || isNaN(id) || id <= 0) && !titulo) {
    console.error('Error: No se puede acceder a los detalles - ID inválido y sin título');
    alert('Error: No se puede acceder a los detalles de la película');
    return;
  }
  
  // Construir la URL
  let url = 'detalle_pelicula.php';
  if (id && !isNaN(id) && id > 0) {
    // Preferir ID si está disponible y es válido
    url = `detalle_pelicula.php?id=${id}`;
    console.log("Usando ID para redirección:", id);
  } else if (titulo && titulo.trim() !== '') {
    // Usar título como alternativa
    url = `detalle_pelicula.php?titulo=${encodeURIComponent(titulo.trim())}`;
    console.log("Usando título para redirección:", titulo);
  } else {
    console.error('Error: Ni ID ni título son válidos');
    alert('Error: No se pueden cargar los detalles de la película');
    return;
  }
  
  console.log("Redirigiendo a:", url);
  
  // Redirigir a la página de detalles
  try {
    window.location.href = url;
  } catch (error) {
    console.error('Error en la redirección:', error);
    alert('Error al cargar la página de detalles');
  }
}

// Función mejorada para manejar imágenes
function crearElementoImagen(urlImagen, titulo) {
  // Si la URL es obviamente falsa o inválida, usar directamente la imagen por defecto
  if (!urlImagen || urlImagen === '' || esProbablementeUrlFalsa(urlImagen)) {
    return `<div class="imagen-placeholder">
              <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21,15 16,10 5,21"/>
              </svg>
              <span>Sin imagen</span>
            </div>`;
  }
  
  // Para URLs que podrían ser válidas, usar img con manejo de errores mejorado
  return `<img src="${urlImagen}" alt="${titulo}" onload="this.style.opacity='1'" onerror="manejarErrorImagen(this)" style="opacity:0;transition:opacity 0.3s;">`;
}

// Función para detectar URLs probablemente falsas
function esProbablementeUrlFalsa(url) {
  const patronesFalsos = [
    /^https?:\/\/fake/i,
    /^https?:\/\/ejemplo/i,
    /^https?:\/\/test/i,
    /^https?:\/\/placeholder/i,
    /\.(fake|test|ejemplo)$/i,
    /fake-image/i,
    /placeholder/i
  ];
  
  return patronesFalsos.some(patron => patron.test(url));
}

// Función global para manejar errores de imagen (evita ciclos infinitos)
function manejarErrorImagen(img) {
  // Prevenir múltiples ejecuciones del onerror
  if (img.dataset.errorHandled) {
    return;
  }
  img.dataset.errorHandled = "true";
  
  // Crear elemento de reemplazo
  const placeholder = document.createElement('div');
  placeholder.className = 'imagen-placeholder';
  placeholder.innerHTML = `
    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
      <circle cx="8.5" cy="8.5" r="1.5"/>
      <polyline points="21,15 16,10 5,21"/>
    </svg>
    <span>Imagen no disponible</span>
  `;
  
  // Reemplazar la imagen con el placeholder
  img.parentNode.replaceChild(placeholder, img);
}

function formatearGenero(genero) {
  const generos = {
    'accion': 'Acción',
    'aventura': 'Aventura',
    'animacion': 'Animación',
    'comedia': 'Comedia',
    'crimen': 'Crimen',
    'drama': 'Drama',
    'fantasia': 'Fantasía',
    'ciencia-ficcion': 'Ciencia Ficción',
    'misterio': 'Misterio',
    'thriller': 'Thriller',
    'terror': 'Terror'
  };
  return generos[genero] || genero;
}

function formatearFecha(fecha) {
  try {
    const date = new Date(fecha);
    return date.toLocaleDateString('es-ES', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  } catch (error) {
    return fecha;
  }
}

function filtrarPeliculas() {
  const nombre = document.getElementById("buscarPelicula").value.trim().toLowerCase();
  const genero = document.getElementById("genero").value;
  const fecha = document.getElementById("fecha").value;
  
  const peliculasFiltradas = peliculas.filter(pelicula => {
    const cumpleNombre = nombre === "" || pelicula.titulo.toLowerCase().includes(nombre);
    const cumpleGenero = genero === "" || pelicula.genero === genero;
    const cumpleFecha = fecha === "" || new Date(pelicula.fecha) >= new Date(fecha);
    return cumpleNombre && cumpleGenero && cumpleFecha;
  });
  
  mostrarPeliculas(peliculasFiltradas);
}

function limpiarFiltros() {
  document.getElementById("buscarPelicula").value = "";
  document.getElementById("genero").value = "";
  document.getElementById("fecha").value = "";
  mostrarPeliculas(peliculas);
}

async function cargarPeliculas() {
  try {
    mostrarLoading(true);
    
    console.log("Iniciando carga de películas...");
    const response = await fetch("obtener_peliculas.php");
    
    console.log("Respuesta recibida:", {
      status: response.status,
      statusText: response.statusText,
      contentType: response.headers.get("content-type")
    });
    
    if (!response.ok) {
      throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
    }
    
    // Verificar el tipo de contenido
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const responseText = await response.text();
      console.error("Respuesta no es JSON:", responseText);
      throw new Error(`El servidor devolvió ${contentType || 'contenido desconocido'} en lugar de JSON. Respuesta: ${responseText.substring(0, 200)}...`);
    }
    
    // Obtener el texto de la respuesta para diagnóstico
    const responseText = await response.text();
    console.log("Respuesta cruda del servidor:", responseText);
    
    // Intentar parsear como JSON
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error("Error al parsear JSON:", parseError);
      console.error("Contenido que causó el error:", responseText);
      throw new Error(`Error al parsear JSON: ${parseError.message}. Contenido recibido: ${responseText.substring(0, 200)}...`);
    }
    
    if (data.error) {
      throw new Error(data.error);
    }
    
    console.log("Películas cargadas exitosamente:", data);
    peliculas = data;
    mostrarPeliculas(peliculas);
    
  } catch (error) {
    console.error("Error completo al cargar películas:", error);
    contenedor.innerHTML = `
      <div class="error-mensaje" style="padding: 20px; background: #fee; border: 1px solid #fcc; border-radius: 5px; margin: 10px 0;">
        <h3 style="color: #c33; margin: 0 0 10px 0;">❌ Error al cargar la cartelera</h3>
        <p style="margin: 5px 0;"><strong>Error:</strong> ${error.message}</p>
        <p style="margin: 5px 0; font-size: 0.9em; color: #666;">
          Revisa la consola del navegador (F12) para más detalles.
        </p>
        <button onclick="cargarPeliculas()" class="boton" style="margin-top: 10px; padding: 8px 16px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;">
          Reintentar
        </button>
      </div>
    `;
  } finally {
    mostrarLoading(false);
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const formFiltro = document.getElementById("form-filtro");
  
  // Verificar que el elemento existe antes de manipularlo
  if (formFiltro) {
    // Agregar botón de limpiar filtros
    const botonLimpiar = document.createElement("button");
    botonLimpiar.type = "button";
    botonLimpiar.textContent = "Limpiar Filtros";
    botonLimpiar.className = "boton-limpiar";
    botonLimpiar.onclick = limpiarFiltros;
    formFiltro.appendChild(botonLimpiar);
    
    // Event listeners para filtros
    formFiltro.addEventListener("submit", function (e) {
      e.preventDefault();
      filtrarPeliculas();
    });
    
    // Verificar que los elementos existen antes de agregar listeners
    const buscarInput = document.getElementById("buscarPelicula");
    const generoSelect = document.getElementById("genero");
    const fechaInput = document.getElementById("fecha");
    
    if (buscarInput) {
      buscarInput.addEventListener("input", filtrarPeliculas);
    }
    if (generoSelect) {
      generoSelect.addEventListener("change", filtrarPeliculas);
    }
    if (fechaInput) {
      fechaInput.addEventListener("change", filtrarPeliculas);
    }
  }
  
  // Cargar películas al iniciar
  cargarPeliculas();
});