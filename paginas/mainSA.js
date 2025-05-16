document.addEventListener('DOMContentLoaded', function() {
    const cerrarSesionBtn = document.getElementById('cerrar-sesion-sa');

    if (cerrarSesionBtn) {
        cerrarSesionBtn.addEventListener('click', function() {
            sessionStorage.removeItem('username');
            window.location.href = 'index.html';
        });
    }
});