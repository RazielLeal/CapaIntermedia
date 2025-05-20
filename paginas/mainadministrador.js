document.addEventListener('DOMContentLoaded', function() {
    const userRole = sessionStorage.getItem('userRole');
    if (userRole !== 'Admin') {
        alert('Acceso denegado. Solo administradores pueden acceder a esta página.');
        window.location.href = 'index.html';
    }

    fetch('obtener_estadisticas.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Estadísticas cargadas:', data);
            }
        })
        .catch(error => {
            console.error('Error al cargar estadísticas:', error);
        });
});

function gestionarUsuarios() {
    window.location.href = 'gestion_usuarios.html';
}

function gestionarProductos() {
    window.location.href = 'gestion_productos.html';
}

function verReportes() {
    window.location.href = 'reportes.html';
}

function configurarSistema() {
    window.location.href = 'configuracion.html';
}