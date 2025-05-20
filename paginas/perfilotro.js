document.addEventListener('DOMContentLoaded', function() {
    const userNameElement = document.getElementById('user-name');
    const userEmailElement = document.getElementById('user-email');
    const registerDateElement = document.getElementById('register-date');
    const userRoleElement = document.getElementById('user-role');
    const profilePhoto = document.getElementById('profile-photo');
    const privateMessage = document.getElementById('private-message');
    const listasBtn = document.getElementById('listas-btn');
    const productosContainer = document.getElementById('productos-container');

    function getUserIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('id');
    }

    const userId = getUserIdFromUrl();

    if (!userId) {
        alert('Error: No se proporcionó un ID de usuario en la URL.');
        window.location.href = 'main.html';
        return;
    }

    const fetchUrl = `get_user_profile.php?id=${encodeURIComponent(userId)}`;
    
    fetch(fetchUrl)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Error en la respuesta del servidor (${response.status}): ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log("DEBUG: Datos recibidos del servidor:", data);

            if (data.success) {
                if (data.is_private) {
                    mostrarPerfilPrivado(data);
                } else {
                    mostrarPerfilPublico(data);
                    cargarProductosVendedor(userId);
                }
            } else {
                throw new Error(data.error || 'Error al cargar el perfil del usuario');
            }
        })
        .catch(error => {
            manejarErrorPerfil(error);
        });

    function mostrarPerfilPrivado(data) {
        userNameElement.textContent = data.nickname || 'Usuario Privado';
        profilePhoto.src = data.photo ? 'data:image/jpeg;base64,' + data.photo : 'avatar.png';
        userEmailElement.style.display = 'none';
        registerDateElement.style.display = 'none';
        userRoleElement.style.display = 'none';
        privateMessage.style.display = 'block';
        listasBtn.style.display = 'none';
        productosContainer.style.display = 'none'; 
    }

    function mostrarPerfilPublico(data) {
        userNameElement.textContent = data.nickname || 'Usuario';
        userEmailElement.textContent = `Email: ${data.email || 'No disponible'}`;
        registerDateElement.textContent = `Fecha de Registro: ${data.register_date || 'No disponible'}`;
        userRoleElement.textContent = `Rol: ${data.rol || 'No disponible'}`;
        profilePhoto.src = data.photo ? 'data:image/jpeg;base64,' + data.photo : 'avatar.png';
        
        privateMessage.style.display = 'none';
        userEmailElement.style.display = 'block';
        registerDateElement.style.display = 'block';
        userRoleElement.style.display = 'block';
        listasBtn.style.display = 'block';
        listasBtn.dataset.userId = data.user_id;

        if (data.rol === 'Vendedor' || data.rol === 'Admin') {
            productosContainer.parentElement.style.display = 'block';
        } else {
            productosContainer.parentElement.style.display = 'none';
        }
    }

    function cargarProductosVendedor(userId) {
        fetch(`get_user_products.php?user_id=${encodeURIComponent(userId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        throw new Error('Respuesta no válida del servidor');
                    }
                });
            })
            .then(productsData => {
                if (productsData.success) {
                    if (productsData.products.length > 0) {
                        renderizarProductos(productsData.products);
                    } else {
                        productosContainer.innerHTML = '<p class="no-productos">Este usuario no tiene productos en venta.</p>';
                    }
                } else {
                    throw new Error(productsData.error || 'Error al obtener productos');
                }
            })
            .catch(error => {
                console.error('Error al cargar productos:', error);
                productosContainer.innerHTML = `<p class="error-productos">Error al cargar productos: ${error.message}</p>`;
            });
    }

    function renderizarProductos(productos) {
        productosContainer.innerHTML = '';

        productos.forEach(producto => {
            const productCard = document.createElement('div');
            productCard.className = 'producto-card';
            productCard.innerHTML = `
                <img src="${producto.FotoPrincipal ? 'data:image/jpeg;base64,' + producto.FotoPrincipal : 'avatar2.png'}" 
                     class="producto-img" 
                     alt="${producto.Nombre}">
                <div class="producto-info">
                    <h3>${producto.Nombre}</h3>
                    <div class="producto-precio">$${parseFloat(producto.Precio).toFixed(2)}</div>
                    <div class="producto-stock ${producto.Stock <= 0 ? 'agotado' : ''}">
                        ${producto.Stock <= 0 ? 'Agotado' : `Disponibles: ${producto.Stock}`}
                    </div>
                </div>
            `;
            
            productCard.addEventListener('click', () => {
                window.location.href = `producto.html?id=${producto.ID}`;
            });

            productosContainer.appendChild(productCard);
        });
    }

    function manejarErrorPerfil(error) {
        console.error('Error en la solicitud fetch o procesamiento de datos:', error);
        alert('No se pudo cargar la información del usuario: ' + error.message);

        userNameElement.textContent = 'Error al cargar perfil';
        profilePhoto.src = 'avatar.png';
        userEmailElement.style.display = 'none';
        registerDateElement.style.display = 'none';
        userRoleElement.style.display = 'none';
        privateMessage.style.display = 'none';
        listasBtn.style.display = 'none';
        productosContainer.style.display = 'none';
    }

    listasBtn.addEventListener('click', function(event) {
        event.preventDefault();
        const userIdForLists = listasBtn.dataset.userId;
        if (userIdForLists) {
            window.location.href = `listasotros.html?userId=${userIdForLists}`;
        } else {
            alert('No se pudo obtener el ID del usuario para ver sus listas. Inténtalo de nuevo.');
        }
    });
});