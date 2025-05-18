document.addEventListener('DOMContentLoaded', () => {
  // Elementos del DOM
  const nameEl = document.getElementById('user-name');
  const emailEl = document.getElementById('user-email');
  const regEl = document.getElementById('register-date');
  const photoEl = document.getElementById('profile-photo');
  const closeBtn = document.getElementById('cerrar-btn');
  const prodEl = document.getElementById('productos');
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  const pageInfo = document.getElementById('page-info');
  const statusFilter = document.getElementById('filtro-status');
  const categoriaFilter = document.getElementById('filtro-categoria');
  const ordenFilter = document.getElementById('filtro-orden');
  

  // Variables de paginación
  let currentPage = 1;
  const productsPerPage = 6;
  let allProducts = [];
  let totalPages = 1;
  let categorias = [];
  let currentFilters = {
    status: 'todos',
    categoria: 'todas',
    orden: 'recientes'
  };

  // Verificar sesión
  const username = sessionStorage.getItem('username');
  if (!username) {
    console.error('No hay sesión activa');
    alert('No hay sesión activa. Redirigiendo al login...');
    return location.href = 'index.html';
  }

  // Inicializar
  init();

  async function init() {
    try {
      await loadCategorias();
      await loadUserData();
    } catch (error) {
      console.error('Error en inicialización:', error);
      showError(`Error inicial: ${error.message}`);
    }
  }

  async function loadUserData() {
    try {
      const userUrl = buildUrl('login.php', { user: username });
      const userData = await fetchData(userUrl);
      
      if (!userData.id) {
        throw new Error('ID de usuario no recibido');
      }

      displayUserInfo(userData);
      await loadProducts(userData.id);
    } catch (error) {
      console.error('Error cargando datos usuario:', error);
      throw error;
    }
  }

  async function loadCategorias() {
    try {
      const url = 'obtenerCategorias.php';
      categorias = await fetchData(url);
      populateCategoriaFilter();
    } catch (error) {
      console.error('Error cargando categorías:', error);
      // Continuamos aunque falle, mostrando solo "Todas las categorías"
    }
  }

  function buildUrl(base, params = {}) {
    const query = Object.entries(params)
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');
    return query ? `${base}?${query}` : base;
  }

  async function fetchData(url) {
    try {
      console.log(`Fetching: ${url}`);
      const response = await fetch(url);
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error(`HTTP ${response.status}: ${errorText || 'Error desconocido'}`);
      }
      
      const data = await response.json();
      
      if (data.error) {
        throw new Error(data.error);
      }
      
      return data;
    } catch (error) {
      console.error(`Error en fetch a ${url}:`, error);
      throw error;
    }
  }

  async function loadProducts(userId) {
    try {
        
        // Construir URL sin parámetros undefined
        const params = new URLSearchParams();
        params.append('user_id', userId);
        
        if (currentFilters.status !== 'todos') {
            params.append('status', currentFilters.status);
        }
        if (currentFilters.categoria !== 'todas') {
            params.append('categoria', currentFilters.categoria);
        }
        params.append('orden', currentFilters.orden);

        const url = `obtener_productos_filtrados.php?${params.toString()}`;

        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error en datos de productos');
        }

        allProducts = data.productos || [];
        console.log(`Productos recibidos:`, allProducts); // Depuración
        
        totalPages = Math.ceil(allProducts.length / productsPerPage);
        currentPage = 1;
        renderProducts();
    } catch (error) {
        console.error('Error cargando productos:', error);
        showError(`Error al cargar productos: ${error.message}`);
    }
}

  function displayUserInfo(data) {
    nameEl.textContent = data.username || 'Usuario';
    emailEl.textContent = `Email: ${data.email || 'No disponible'}`;
    emailEl.dataset.userId = data.id;
    regEl.textContent = `Registro: ${data.register_date || 'Fecha desconocida'}`;
    
    if (data.photo) {
      photoEl.src = `data:image/jpeg;base64,${data.photo}`;
    } else {
      photoEl.src = 'avatar.png';
    }
  }

  function populateCategoriaFilter() {
    categoriaFilter.innerHTML = '<option value="todas">Todas las categorías</option>';
    
    if (categorias && categorias.length > 0) {
      categorias.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.ID;
        option.textContent = cat.Nombre;
        categoriaFilter.appendChild(option);
      });
    }
  }

  function renderProducts() {
    console.log(`Renderizando productos (página ${currentPage} de ${totalPages})`);
    prodEl.innerHTML = '';

    if (!allProducts || allProducts.length === 0) {
      prodEl.innerHTML = '<p class="no-products">No hay productos publicados.</p>';
      updatePaginationControls();
      return;
    }

    const startIndex = (currentPage - 1) * productsPerPage;
    const endIndex = Math.min(startIndex + productsPerPage, allProducts.length);
    const productsToShow = allProducts.slice(startIndex, endIndex);

    prodEl.innerHTML = "";
    productsToShow.forEach(p => {
      try {
        const price = safeConvertPrice(p.Precio);
        const productDiv = document.createElement('div');
        productDiv.className = 'producto-simple';

        productDiv.innerHTML = `
          <h3>${escapeHtml(p.Nombre) || 'Sin nombre'}</h3>
          <div class="producto-imagen">
            ${p.FotoPrincipal ? 
              `<img src="data:image/jpeg;base64,${p.FotoPrincipal}" alt="${escapeHtml(p.Nombre)}" />` : 
              '<p class="sin-imagen">Sin imagen</p>'}
          </div>
          <p class="precio">${formatCurrency(price)}</p>
          <small>Estado: ${p.Status || 'Desconocido'}</small>
          <small>Stock: ${p.Stock}</small>

            ${p.Status !== "Eliminado" ?

          `<form class="agregarstock" method="post" action="actualizarproducto.php">
            <button type="submit" name="btnagregar" class="botones btnagregar">Agregar stock</button>
            <button type="button" class="botones btnmenos" > - </button>
            <input type="number" name="cantidadstock" class="cantidadstock" value="0"/>
            <button type="button" class="botones btnmas"> + </button>
          

             <button type="submit" name="btneliminar" class="botones btneliminar">Eliminar producto</button>`
            : ""
            }


            <input type="hidden" name="id_producto" value="${p.ID}"/>

          </form>
          `

        ;
        

        prodEl.appendChild(productDiv);

        const btnMenos = productDiv.querySelector(".btnmenos");
        const btnMas = productDiv.querySelector(".btnmas");
        const inputCantidad = productDiv.querySelector(".cantidadstock");

        btnMenos.addEventListener("click", function (event) {
          event.preventDefault();
          let valorActual = parseInt(inputCantidad.value, 10);
          if (valorActual > 0) {
            inputCantidad.value = valorActual - 1;
          }
        });
    
        btnMas.addEventListener("click", function (event) {
          event.preventDefault();
          let valorActual = parseInt(inputCantidad.value, 10);
          console.log(`btnMas de producto ${p.Nombre} disparado. Valor actual: ${valorActual}`);
  
          inputCantidad.value = valorActual + 1;
        });
    
    
      } catch (e) {
        console.error('Error renderizando producto:', p, e);
      }
    });




    

    updatePaginationControls();
  }

  function updatePaginationControls() {
    totalPages = Math.ceil(allProducts.length / productsPerPage);
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
    pageInfo.textContent = `Página ${currentPage} de ${totalPages}`;
    document.querySelector('.paginacion').style.display = totalPages <= 1 ? 'none' : 'flex';
  }

  function safeConvertPrice(price) {
    if (price === null || price === undefined || isNaN(price)) return 0;
    return parseFloat(price);
  }

  function formatCurrency(amount) {
    if (isNaN(amount)) return 'Precio no disponible';
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 2
    }).format(amount);
  }

  function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function showError(msg) {
    console.error('Mostrando error:', msg);
    prodEl.innerHTML = `<p class="error">Error: ${escapeHtml(msg)}</p>`;
    document.querySelector('.paginacion').style.display = 'none';
  }

  function logout() {
    sessionStorage.clear();
    location.href = 'index.html';
  }

  // Event listeners
  closeBtn.addEventListener('click', logout);
  
  prevBtn.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      renderProducts();
    }
  });
  
  nextBtn.addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      renderProducts();
    }
  });

  statusFilter.addEventListener('change', async (e) => {
    currentFilters.status = e.target.value;
    await reloadProducts();
  });

  categoriaFilter.addEventListener('change', async (e) => {
    currentFilters.categoria = e.target.value;
    await reloadProducts();
  });

  ordenFilter.addEventListener('change', async (e) => {
    currentFilters.orden = e.target.value;
    await reloadProducts();
  });

  async function reloadProducts() {
    const userId = emailEl.dataset.userId;
    if (userId) {
      console.log('Recargando productos con filtros:', currentFilters);
      await loadProducts(userId);
    }
  }


  const notificacion = document.getElementById('notificacion');
  
    function verificarMensajesPendientes() {
        fetch('get_mensajes.php')
            .then(response => response.text()) // Obtener la respuesta como texto
            .then(text => {
                return JSON.parse(text); // Intentar convertirlo a JSON
            })
            .then(data => {
                console.log("Arreglo de mensajes:", data.mensajes); // Ver JSON limpio
                if (!data.success || !data.mensajes) {
                    throw new Error(data.message || "Error al obtener mensajes.");
                }

                // Mostrar número de mensajes no leídos
                const cantidadMensajes = data.mensajes.length;
                if (cantidadMensajes > 0) {
                    notificacion.textContent = cantidadMensajes;
                    notificacion.classList.remove('hidden'); // Mostrar la burbuja de notificación
                } else {
                    notificacion.classList.add('hidden'); // Ocultar si no hay mensajes
                }
            })
            .catch(error => console.error('Error al obtener mensajes:', error));
    }

    // Llamar a la función al cargar la página
    verificarMensajesPendientes();
    const mensajesBtn = document.getElementById('mensajes-btn');
    const chatPendientesBody = document.getElementById('chat-pendientes-body');
    const chatPendientesModal = document.getElementById('chat-pendientes-modal');
    const chatPendientesClose = document.getElementById('chat-pendientes-close');

    const chatModal = document.getElementById('chat-modal');
    const chatTitle = document.getElementById('chat-title');
    const chatUsuario = document.getElementById('chat-usuario');
    const chatBody = document.getElementById('chat-body');
    const chatClose = document.getElementById('chat-close');

    mensajesBtn.addEventListener('click', function () {
        chatPendientesModal.classList.add('visible');

        // Obtener chats pendientes
        fetch('get_chats_pendientes.php')
            .then(response => response.json())
            .then(data => {
                chatPendientesBody.innerHTML = '';
                if (data.chats.length === 0) {
                    chatPendientesBody.innerHTML = '<p style="color:#888;text-align:center;">No hay chats pendientes.</p>';
                    return;
                }

                data.chats.forEach(chat => {
                    const chatElement = document.createElement('div');
                    chatElement.className = 'chat-pendiente';
                    chatElement.dataset.chatId = chat.id_chat;
                    chatElement.innerHTML = `
                        <div style="padding: 10px; border-bottom: 1px solid #ddd;">
                            <strong>${chat.nombre_usuario}</strong>
                            <p style="color: #666;">Último mensaje: ${chat.ultimo_mensaje}</p>
                        </div>
                    `;
                    chatPendientesBody.appendChild(chatElement);
                });

                // Agregar evento para abrir el chat
                document.querySelectorAll('.chat-pendiente').forEach(element => {
                    element.addEventListener('click', function () {
                        const chatId = this.dataset.chatId;

                        abrirChat(chatId);
                    });
                });
            })
            .catch(error => console.error('Error al obtener chats:', error));
    });

    function abrirChat(chatId) {
        chatModal.classList.add('visible');
        chatModal.classList.remove('hidden');
        chatBody.innerHTML = '<p style="color:#888;text-align:center;margin-top:30px;">Cargando mensajes...</p>';
        chatBody.dataset.chatId = chatId;

        let refreshInterval;
        refreshInterval = setInterval(refreshChatMessages, 300);
        // Obtener detalles del producto y asignar `productId` al dataset
        // Obtener detalles del producto asociado al chat
        fetch(`get_producto_chat.php?id_chat=${chatId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    chatBody.dataset.productId = data.producto.id; // ✅ Guardar el ID del producto correctamente
                } else {
                    console.error("Error al obtener detalles del producto:", data.error);
                }
            })
          .catch(error => console.error('Error al obtener detalles del producto:', error));


        // Obtener mensajes del chat
        fetch(`get_mensajes_chat.php?id_chat=${chatId}`)
            .then(response => response.json())
            .then(data => {
                chatBody.innerHTML = ''; // Limpiar contenido previo
                if (data.mensajes.length === 0) {
                    chatBody.innerHTML = '<p style="color:#888;text-align:center;">No hay mensajes aún.</p>';
                    return;
                }

                
            })
            .catch(error => console.error('Error al obtener mensajes:', error));
    }

    function refreshChatMessages() {
        const chatId = chatBody.dataset.chatId; // Asegúrate que este valor se asigna al abrir el chat.
        if (!chatId) return;
            
        fetch(`get_mensajes_chat.php?id_chat=${chatId}`)
            .then(response => response.json())
            .then(data => {
              chatBody.innerHTML = '';
              if (data.mensajes.length === 0) {
                chatBody.innerHTML = '<p style="color:#888;text-align:center;">No hay mensajes aún.</p>';
                return;
              } else {
                  data.mensajes.forEach(msg => {
                    const mensajeElement = document.createElement('div');
                    mensajeElement.className = `chat-message ${msg.es_usuario ? 'user' : ''}`;
                    mensajeElement.textContent = msg.mensaje;
                    chatBody.appendChild(mensajeElement);
                  });  
                }

            })
            .catch(error => console.error('Error al refrescar mensajes:', error));
    }

    // Cerrar modal de chats
    chatPendientesClose.addEventListener('click', function () {
        chatPendientesModal.classList.remove('visible');
    });


    // Cerrar modal de chat
    chatClose.addEventListener('click', function () {
        chatModal.classList.remove('visible');
    });

    function sendMessage(chatId, mensaje) {
      if (!chatId) {
        console.error("Error: chatId no está definido.");
        return;
      }
      if (!mensaje) return;

      fetch('enviar_mensaje.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id_chat=${encodeURIComponent(chatId)}&mensaje=${encodeURIComponent(mensaje)}`
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Actualizar el chat (asumiendo que #chat-body es donde se muestran los mensajes)
              const chatBody = document.getElementById('chat-body');
              const mensajeElement = document.createElement('div');
              mensajeElement.className = 'chat-message user';
              mensajeElement.textContent = mensaje;
              chatBody.appendChild(mensajeElement);
              chatBody.scrollTop = chatBody.scrollHeight;
          } else {
              alert(data.message);
          }
      })
      .catch(error => console.error('Error al enviar mensaje:', error));
    }

    document.getElementById('chat-send').addEventListener('click', function () {
      const chatInput = document.getElementById('chat-input');
      const chatBody = document.getElementById('chat-body');
      const mensaje = chatInput.value.trim();
      const chatId = chatBody.dataset.chatId; // Obtener el ID del chat activo

      if (!mensaje) return;

      // Llamada a función común para enviar el mensaje
      sendMessage(chatId, mensaje);
      chatInput.value = '';
   });

// Obtener elementos del modal de cotización
const cotizacionModal = document.getElementById('cotizacion-modal');
const cotizacionClose = document.getElementById('cotizacion-close');
const cotizacionProducto = document.getElementById('cotizacion-producto');
const cotizacionPrecio = document.getElementById('cotizacion-precio');
const cotizacionDetalles = document.getElementById('cotizacion-detalles');
const cotizacionEnviar = document.getElementById('cotizacion-enviar');
const cotizacionBtn = document.getElementById('cotizacion-btn');

// Mostrar el modal cuando se hace clic en el botón de cotización
cotizacionBtn.addEventListener('click', function() {
    const productId = chatBody.dataset.productId; // Usar el ID del producto guardado

    // Obtén y muestra el contenedor
    const container = document.querySelector('.modal-cotizacion-container');
    if (container) {
      container.classList.remove('hidden');
    }
    // Luego, muéstrale al modal
    const cotizacionModal = document.getElementById('cotizacion-modal');
    cotizacionModal.classList.remove('hidden');
    cotizacionModal.classList.add('visible');
     // Asegúrate que este valor se asigna al abrir el chat.

    if (!productId) {
        console.error("Error: No se encontró el ID del producto.");
        return;
    }

    // Obtener los detalles del producto
    fetch(`get_product_details.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cotizacionProducto.textContent = data.product.nombre;
                cotizacionModal.classList.add('visible');
            } else {
                console.error("Error al obtener detalles del producto:", data.error);
            }
        })
        .catch(error => console.error('Error al obtener detalles del producto:', error));
});

// Cerrar el modal de cotización
cotizacionClose.addEventListener('click', function() {
  // Ocultar el modal de cotización
  const cotizacionModal = document.getElementById('cotizacion-modal');
  cotizacionModal.classList.remove('visible');
  cotizacionModal.classList.add('hidden');
  
  // Ocultar también el contenedor del modal
  const container = document.querySelector('.modal-cotizacion-container');
  if (container) {
    container.classList.add('hidden');
  }
});

function appendChatMessage(sender, text, isUser) {
    const chatBody = document.getElementById('chat-body');
    if (!chatBody) return;
    
    const mensajeElement = document.createElement('div');
    mensajeElement.classList.add('chat-message');
    if (isUser) {
        mensajeElement.classList.add('user');
    }
    mensajeElement.textContent = text;
    chatBody.appendChild(mensajeElement);
    chatBody.scrollTop = chatBody.scrollHeight;
}

// Enviar la cotización como mensaje
cotizacionEnviar.addEventListener('click', function() {
    const chatBody = document.getElementById('chat-body');
    const chatId = chatBody.dataset.chatId; // Asegurar que haya un chat activo
    
    if (!chatId) {
        console.error("Error: No hay un chat activo.");
        return;
    }

    const cotizacionProducto = document.getElementById('cotizacion-producto').textContent;
    const cotizacionPrecio = parseFloat(document.getElementById('cotizacion-precio').value);
    const cotizacionDetalles = document.getElementById('cotizacion-detalles').value.trim();

    if (isNaN(cotizacionPrecio) || cotizacionPrecio <= 0) {
        alert("El precio debe ser un número positivo.");
        return;
    }
    if (!cotizacionDetalles) {
        alert("Debes ingresar los detalles de la cotización.");
        return;
    }

    // Construir el mensaje con los datos de cotización
    const mensajeCotizacion = `Cotización para ${cotizacionProducto}:
    Precio: $${cotizacionPrecio.toFixed(2)}
    Detalles: ${cotizacionDetalles}`;

    // Usar la función común para enviar el mensaje
    sendMessage(chatId, mensajeCotizacion);

    // Cerrar el modal de cotización (agrega las clases o estilos que uses para ocultarlo)
    document.getElementById('cotizacion-modal').classList.remove('visible');
    document.querySelector('.modal-cotizacion-container').classList.add('hidden');
});

});

