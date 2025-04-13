document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const userNameElement = document.getElementById('user-name');
    const userEmailElement = document.getElementById('user-email');
    const registerDateElement = document.getElementById('register-date');
    const profilePhoto = document.getElementById('profile-photo');
    const cerrarBtn = document.getElementById('cerrar-btn');
    
    // Obtener el usuario de sessionStorage
    const username = sessionStorage.getItem('username');
    
    if (!username) {
        alert('No hay usuario registrado en la sesión.');
        window.location.href = 'index.html';
        return;
    }
    
    // Cargar datos del usuario desde el servidor
    fetch(`login.php?user=${encodeURIComponent(username)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mostrar datos del usuario
                userNameElement.textContent = data.username || 'Usuario';
                userEmailElement.textContent = `Email: ${data.email || 'No disponible'}`;
                registerDateElement.textContent = `Fecha de Registro: ${data.register_date || 'No disponible'}`;
                
                // Mostrar foto de perfil si existe
                if (data.photo) {
                    profilePhoto.src = 'data:image/jpeg;base64,' + data.photo;
                } else {
                    profilePhoto.src = 'avatar.png'; // Imagen por defecto
                }
            } else {
                throw new Error(data.message || 'Error al cargar el usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('No se pudo cargar la información del usuario: ' + error.message);
        });
    
    // Manejar cierre de sesión
    cerrarBtn.addEventListener('click', function() {
        sessionStorage.removeItem('username');
        window.location.href = 'index.html';
    });
    
    // Filtros de productos (opcional)
    const categoriaSelect = document.getElementById('categoria');
    const fechaSelect = document.getElementById('fecha');
    const productos = document.querySelectorAll('.producto');
    
    function filtrarProductos() {
        const categoria = categoriaSelect.value;
        const fecha = fechaSelect.value;
        
        productos.forEach(producto => {
            const productoFecha = producto.getAttribute('data-fecha');
            const productoCategoria = producto.getAttribute('data-categoria') || '';
            
            let mostrar = true;
            
            if (categoria && !productoCategoria.includes(categoria)) {
                mostrar = false;
            }
            
            if (fecha === 'ultimos' && productoFecha > '2024-01-01') {
                mostrar = false;
            }
            
            producto.style.display = mostrar ? 'block' : 'none';
        });
    }
    
    categoriaSelect.addEventListener('change', filtrarProductos);
    fechaSelect.addEventListener('change', filtrarProductos);
});