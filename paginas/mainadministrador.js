document.addEventListener('DOMContentLoaded', function() {
    // Verificar rol de administrador
    const userRole = sessionStorage.getItem('userRole');
    if (userRole !== 'Admin') {
        alert('Acceso denegado. Solo administradores pueden acceder a esta página.');
        window.location.href = 'index.html';
    }

    // Cargar estadísticas del dashboard
    fetch('obtener_estadisticas.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar el dashboard con las estadísticas
                console.log('Estadísticas cargadas:', data);
            }
        })
        .catch(error => {
            console.error('Error al cargar estadísticas:', error);
        });
});

// Función para gestionar usuarios
function gestionarUsuarios() {
    window.location.href = 'gestion_usuarios.html';
}

// Función para gestionar productos
function gestionarProductos() {
    window.location.href = 'gestion_productos.html';
}

// Función para ver reportes
function verReportes() {
    window.location.href = 'reportes.html';
}

// Función para configurar sistema
function configurarSistema() {
    window.location.href = 'configuracion.html';
}