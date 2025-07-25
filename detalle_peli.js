// Función para abrir el trailer
function openTrailer(trailerUrl) {
    const modal = document.getElementById('trailerModal');
    const iframe = document.getElementById('trailerFrame');
    
    // Convertir URL de YouTube si es necesario
    let embedUrl = convertToEmbedUrl(trailerUrl);
    
    iframe.src = embedUrl;
    modal.style.display = 'block';
    
    // Prevenir scroll del body cuando el modal está abierto
    document.body.style.overflow = 'hidden';
}

// Función para cerrar el trailer
function closeTrailer() {
    const modal = document.getElementById('trailerModal');
    const iframe = document.getElementById('trailerFrame');
    
    modal.style.display = 'none';
    iframe.src = '';
    
    // Restaurar scroll del body
    document.body.style.overflow = 'auto';
}

// Función para convertir URL de YouTube a embed
function convertToEmbedUrl(url) {
    if (!url) return '';
    
    // Si ya es una URL de embed, devolverla tal como está
    if (url.includes('embed')) {
        return url;
    }
    
    // Extraer el ID del video de diferentes formatos de URL de YouTube
    let videoId = '';
    
    if (url.includes('youtube.com/watch?v=')) {
        videoId = url.split('v=')[1].split('&')[0];
    } else if (url.includes('youtu.be/')) {
        videoId = url.split('youtu.be/')[1].split('?')[0];
    } else if (url.includes('youtube.com/v/')) {
        videoId = url.split('v/')[1].split('?')[0];
    }
    
    if (videoId) {
        return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
    }
    
    return url;
}

// Función para reservar boletos
function bookTicket(funcionId) {
    if (confirm('¿Deseas continuar con la reserva de este horario?')) {
        // Corregido "funcion" (no "function")
        window.location.href = `procesar_reserva.php?funcion=${funcionId}`;
    }
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('trailerModal');
    if (event.target === modal) {
        closeTrailer();
    }
}

// Cerrar modal con la tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTrailer();
    }
});

// Animaciones de entrada
document.addEventListener('DOMContentLoaded', function() {
    // Animación para las tarjetas de horarios
    const timeCards = document.querySelectorAll('.time-card');
    timeCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animación para la información de la película
    const movieInfo = document.querySelector('.movie-info');
    if (movieInfo) {
        movieInfo.style.opacity = '0';
        movieInfo.style.transform = 'translateX(30px)';
        
        setTimeout(() => {
            movieInfo.style.transition = 'all 0.8s ease';
            movieInfo.style.opacity = '1';
            movieInfo.style.transform = 'translateX(0)';
        }, 300);
    }
    
    // Animación para el poster
    const poster = document.querySelector('.poster-img');
    if (poster) {
        poster.style.opacity = '0';
        poster.style.transform = 'scale(0.9)';
        
        setTimeout(() => {
            poster.style.transition = 'all 0.8s ease';
            poster.style.opacity = '1';
            poster.style.transform = 'scale(1)';
        }, 100);
    }
});

// Función para formatear precios
function formatPrice(price) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP'
    }).format(price);
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Estilos para la notificación
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 10px;
        color: white;
        font-weight: bold;
        z-index: 1001;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    // Colores según el tipo
    switch(type) {
        case 'success':
            notification.style.background = 'linear-gradient(45deg, #4ecdc4, #44a08d)';
            break;
        case 'error':
            notification.style.background = 'linear-gradient(45deg, #ff4757, #ff6b7a)';
            break;
        default:
            notification.style.background = 'linear-gradient(45deg, #ffd700, #ffed4a)';
            notification.style.color = '#333';
    }
    
    document.body.appendChild(notification);
    
    // Mostrar notificación
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Ocultar notificación después de 3 segundos
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Función para validar URLs de YouTube
function isValidYouTubeUrl(url) {
    const youtubeRegex = /^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/)|youtu\.be\/)[\w-]+/;
    return youtubeRegex.test(url);
}

// Función para compartir película en redes sociales
function shareMovie(title, url) {
    if (navigator.share) {
        navigator.share({
            title: `${title} - Caribbean Cinemas`,
            url: window.location.href
        });
    } else {
        // Fallback: copiar URL al portapapeles
        navigator.clipboard.writeText(window.location.href).then(() => {
            showNotification('Enlace copiado al portapapeles', 'success');
        });
    }
}