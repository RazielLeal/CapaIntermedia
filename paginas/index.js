document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const loginButton = document.querySelector('#btnis a');
    const contLP = document.querySelector('.ContLP');
    const popupIScont = document.querySelector('.popupIScont');
    const overlay = document.querySelector('.overlay');
    const cancelISbtn = document.querySelector('#btnformsalir');
    const checkboxContainer = document.getElementById('mostrarcontracont');
    const inputcontra = document.getElementById('inputcontra');
    const cambiartexto = document.getElementById('cambiartexto');
    const btnformis = document.getElementById('btnformis');
    const inputcorreo = document.getElementById('inputcorreo');
    const productosContainer = document.getElementById('productosContainer');

    // Cargar productos al iniciar
    cargarProductosAleatorios();

    // Función para cargar productos aleatorios
    function cargarProductosAleatorios() {
        fetch('get_random_products.php')
            .then(response => {
                if (!response.ok) throw new Error('Error al cargar productos');
                return response.json();
            })
            .then(productos => {
                productosContainer.innerHTML = '';
                
                if (productos.length === 0) {
                    productosContainer.innerHTML = '<div class="producto-loading">No se encontraron productos</div>';
                    return;
                }

                productos.forEach(producto => {
                    const productoElement = document.createElement('div');
                    productoElement.className = 'product-card';
                    productoElement.innerHTML = `
                        <div class="product-image-container">
                            <img class="product-image" src="${producto.Foto || 'img/placeholder.jpg'}" alt="${producto.Nombre}">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">${producto.Nombre}</h3>
                            <div class="product-price">$${parseFloat(producto.Precio).toFixed(2)}</div>
                        </div>
                    `;
                    productosContainer.appendChild(productoElement);
                });
            })
            .catch(error => {
                console.error('Error:', error);
                productosContainer.innerHTML = '<div class="producto-loading">Error al cargar los productos. Intente más tarde.</div>';
            });
    }

    // Mostrar popup de inicio de sesión
    loginButton.addEventListener('click', function(event) {
        event.preventDefault();
        contLP.classList.add('blur');
        popupIScont.classList.add('show-popup');
        overlay.classList.add('show');
    });

    // Cerrar popup
    function closepopup() {
        overlay.classList.remove('show');
        popupIScont.classList.remove('show-popup');
        contLP.classList.remove('blur');
    }

    overlay.addEventListener('click', closepopup);
    cancelISbtn.addEventListener('click', closepopup);

    // Mostrar/Ocultar contraseña
    checkboxContainer.addEventListener('click', function() {
        const isChecked = checkboxContainer.classList.toggle('checked');
        inputcontra.type = isChecked ? 'text' : 'password';
        cambiartexto.textContent = isChecked ? 'Ocultar contraseña' : 'Mostrar contraseña';
    });

    // Manejo del login
    btnformis.addEventListener('click', function() {
        const correo = inputcorreo.value.trim();
        const contrasena = inputcontra.value.trim();

        if (!correo || !contrasena) {
            alert('Por favor, completa todos los campos.');
            return;
        }

        fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `correo=${encodeURIComponent(correo)}&contrasena=${encodeURIComponent(contrasena)}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        // Reemplaza la parte del login en index.js con esto:
.then(data => {
    if (data.success) {
        sessionStorage.setItem('username', data.username);
        sessionStorage.setItem('userRole', data.role);
        sessionStorage.setItem('userPhoto', data.photo || '');

        if (data.role === 'Vendedor') {
            window.location.href = 'perfilvendedor.html';
        } else if (data.role === 'Admin') {
            window.location.href = 'mainadministrador.html';
        } else if(data.role === 'Usuario'){
            window.location.href = 'main.html';
        }else{
            window.location.href = 'mainSA.html'
        }
    } else {
        alert(data.error || 'Credenciales incorrectas');
    }
})
        .catch(error => {
            console.error('Error:', error);
            alert('Error al conectar con el servidor. Intenta más tarde.');
        });
    });
});