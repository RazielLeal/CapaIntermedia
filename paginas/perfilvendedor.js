document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const userNameElement = document.getElementById('user-name');
    const userEmailElement = document.getElementById('user-email');
    const registerDateElement = document.getElementById('register-date');
    const profilePhoto = document.getElementById('profile-photo');
    const cerrarBtn = document.getElementById('cerrar-btn');
    const productosContainer = document.getElementById('productos');
    const paginacionContainer = document.getElementById('paginacion');
    
    // Variables de estado
    let currentPage = 1;
    const itemsPerPage = 4;
    let totalProducts = 0;
    let userId = null;
    const username = sessionStorage.getItem('username');

    // Validar sesión
    if (!username) {
        alert('No hay sesión activa. Redirigiendo al login...');
        window.location.href = 'index.html';
        return;
    }

    // Cargar datos del usuario
    fetch(`login.php?user=${encodeURIComponent(username)}`)
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.success && data.id) {
                displayUserData(data);
                userId = data.id;
                loadProducts(userId, currentPage);
            } else {
                throw new Error(data.error || 'Error al cargar datos de usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error al cargar datos de usuario', error);
        });

    // Mostrar datos del usuario
    function displayUserData(userData) {
        userNameElement.textContent = userData.username || 'Usuario';
        userEmailElement.textContent = `Email: ${userData.email || 'No disponible'}`;
        registerDateElement.textContent = `Registro: ${userData.register_date || 'No disponible'}`;
        
        if (userData.photo) {
            profilePhoto.src = `data:image/jpeg;base64,${userData.photo}`;
        }
    }

    // Cargar productos del vendedor
    function loadProducts(userId, page) {
        fetch(`getproducts.php?userId=${userId}&page=${page}&perPage=${itemsPerPage}`)
            .then(response => {
                if (!response.ok) throw new Error('Error al cargar productos');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    renderProducts(data.products);
                    totalProducts = data.total;
                    updatePagination();
                } else {
                    throw new Error(data.error || 'Error en los datos de productos');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error al cargar productos', error);
            });
    }

    // Mostrar productos en el grid
    function renderProducts(products) {
        productosContainer.innerHTML = '';
        
        if (products.length === 0) {
            productosContainer.innerHTML = '<p class="no-products">No se encontraron productos.</p>';
            return;
        }

        products.forEach(product => {
            const productElement = document.createElement('div');
            productElement.className = 'producto';
            
            productElement.innerHTML = `
                <div class="producto-imagen">
                    ${product.imagen ? 
                        `<img src="${product.imagen}" alt="${product.nombre}" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\'sin-imagen\'>Imagen no disponible</div>'">` : 
                        '<div class="sin-imagen">Sin imagen</div>'}
                </div>
                <div class="producto-info">
                    <h3>${product.nombre}</h3>
                    <p class="precio">$${product.precio}</p>
                    <p class="categoria">${product.categoria}</p>
                    ${product.descripcion ? `<p class="descripcion">${product.descripcion}</p>` : ''}
                </div>
            `;
            
            productosContainer.appendChild(productElement);
        });
    }

    // Actualizar controles de paginación
    function updatePagination() {
        paginacionContainer.innerHTML = '';
        const totalPages = Math.ceil(totalProducts / itemsPerPage);

        if (totalPages <= 1) return;

        // Botón Anterior
        const prevButton = document.createElement('button');
        prevButton.textContent = '«';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadProducts(userId, currentPage);
            }
        });
        paginacionContainer.appendChild(prevButton);

        // Botones de página
        for (let i = 1; i <= totalPages; i++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = i;
            if (i === currentPage) pageButton.classList.add('active');
            pageButton.addEventListener('click', () => {
                currentPage = i;
                loadProducts(userId, currentPage);
            });
            paginacionContainer.appendChild(pageButton);
        }

        // Botón Siguiente
        const nextButton = document.createElement('button');
        nextButton.textContent = '»';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                loadProducts(userId, currentPage);
            }
        });
        paginacionContainer.appendChild(nextButton);
    }

    // Mostrar mensaje de error
    function showError(message, error) {
        productosContainer.innerHTML = `
            <div class="error-message">
                <h4>${message}</h4>
                <p>${error.message}</p>
                <button onclick="location.reload()">Reintentar</button>
            </div>
        `;
    }

    // Event Listeners
    cerrarBtn.addEventListener('click', () => {
        sessionStorage.clear();
        window.location.href = 'index.html';
    });

    // Filtros
    document.getElementById('categoria').addEventListener('change', function() {
        currentPage = 1;
        loadProducts(userId, currentPage);
    });

    document.getElementById('fecha').addEventListener('change', function() {
        currentPage = 1;
        loadProducts(userId, currentPage);
    });
});