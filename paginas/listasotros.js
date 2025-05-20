document.addEventListener('DOMContentLoaded', function() {
    const userNicknameElement = document.getElementById('user-nickname');
    const profilePhotoElement = document.getElementById('profile-photo');
    const listasContainer = document.getElementById('Listas');
    
    const urlParams = new URLSearchParams(window.location.search);
    const userId = urlParams.get('userId');

    if (!userId) {
        alert('Error: No se proporcionó un ID de usuario para ver sus listas.');
        window.location.href = 'main.html';
        return;
    }

    fetch(`get_user_profile.php?id=${encodeURIComponent(userId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.user) {
                userNicknameElement.innerHTML = `Listas de: ${data.user.nickname || 'Usuario'} <button class="botones" id="back-to-profile-btn" onclick="window.history.back()">Regresar al Perfil</button>`;
                if (data.user.photo) {
                    profilePhotoElement.src = 'data:image/jpeg;base64,' + data.user.photo;
                }
            } else {
                userNicknameElement.innerHTML = `Listas de Usuario Desconocido <button class="botones" id="back-to-profile-btn" onclick="window.history.back()">Regresar al Perfil</button>`;
                console.error('Error al cargar nickname/foto del otro usuario:', data.error || 'desconocido');
            }
        })
        .catch(error => {
            userNicknameElement.innerHTML = `Listas de Usuario <button class="botones" id="back-to-profile-btn" onclick="window.history.back()">Regresar al Perfil</button>`;
            console.error('Error al cargar nickname/foto del otro usuario:', error);
        });

    cargarListasUsuario(userId);
    function cargarListasUsuario(userId) {
        fetch(`get_listas.php?user_id=${userId}&public_only=true`) 
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    listasContainer.innerHTML = ''; 
                    
                    if (data.listas.length === 0) {
                        listasContainer.innerHTML = '<p>Este usuario no tiene listas públicas creadas.</p>';
                        return;
                    }
                    
                    data.listas.forEach(lista => {
                        const listaElement = document.createElement('div');
                        listaElement.className = 'producto';
                        
                        let imagenHTML = '';
                        if (lista.primerProductoImagen) {
                            imagenHTML = `<img src="${lista.primerProductoImagen.startsWith('data:image') ? lista.primerProductoImagen : 'data:image/jpeg;base64,' + lista.primerProductoImagen}" alt="${lista.Nombre}" class="imagen-producto">`;
                        } else {
                            imagenHTML = `<div class="lista-vacia-icono"><i class="fas fa-list"></i></div>`;
                        }
                        
                        listaElement.innerHTML = `
                            ${imagenHTML}
                            <div class="lista-info">
                                <h3>${lista.Nombre}</h3>
                                <p>${lista.Descripcion || 'Sin descripción'}</p>
                                <p><strong>Estado:</strong> ${lista.Status}</p>
                                <p><strong>Productos:</strong> ${lista.cantidadProductos || 0}</p>
                            </div>
                        `;
                        
                        listaElement.addEventListener('click', function() {
                            window.location.href = `listadetalleotros.html?id=${lista.ID}&userId=${userId}`; 
                        });
                        
                        listasContainer.appendChild(listaElement);
                    });
                } else {
                    console.error('Error al cargar listas:', data.error);
                    listasContainer.innerHTML = `<p>Error al cargar las listas: ${data.error || 'Desconocido'}</p>`;
                }
            })
            .catch(error => {
                console.error('Error en la solicitud fetch para cargar listas:', error);
                listasContainer.innerHTML = '<p>Error de conexión al cargar las listas.</p>';
            });
    }
});