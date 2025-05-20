document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');

    const productoTitulo = document.getElementById('producto-titulo');
    const productoDescripcion = document.getElementById('producto-descripcion');
    const productoPrecio = document.getElementById('producto-precio');
    const productoStock = document.getElementById('producto-stock');
    const productoVendidos = document.getElementById('producto-vendidos');
    const categoriasContainer = document.getElementById('categorias-container');
    const imagenPrincipalElement = document.getElementById('imagen-principal');
    const videoReproductorElement = document.getElementById('video-reproductor');
    const miniaturasContainer = document.getElementById('miniaturas-container');
    const btnAddCarrito = document.getElementById('btn-añadir-carrito');
    const btnContactarVendedor = document.getElementById('btn-contactar-vendedor');
    const btnAgregarLista = document.getElementById('btn-agregar-lista');

    const calificacionPromedioDiv = document.getElementById('calificacion-promedio');
    const promedioEstrellasSpan = document.getElementById('promedio-estrellas');
    const promedioValorSpan = document.getElementById('promedio-valor');
    const listaComentariosDiv = document.getElementById('lista-comentarios');
    const noComentariosMensaje = document.getElementById('no-comentarios-mensaje');

    const chatModal = document.getElementById('chat-modal');
    const chatCloseBtn = document.getElementById('chat-modal-close');
    const chatBody = document.getElementById('chat-modal-body');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    let refreshInterval; 

    window.openCart = function() {
        console.log('Abriendo el carrito...');
        window.location.href = 'carrito.html';
    };

    function openListasModal() {
        document.getElementById('listas-overlay').style.display = 'block';
        document.getElementById('listas-modal').style.display = 'block';
        loadUserLists();
    }

    window.closeListasModal = function() {
        document.getElementById('listas-overlay').style.display = 'none';
        document.getElementById('listas-modal').style.display = 'none';
    }

    function loadUserLists() {
        const username = sessionStorage.getItem('username');
        const currentProductId = new URLSearchParams(window.location.search).get('id');
        if (!username || !currentProductId) {
            alert('Error: Sesión de usuario no encontrada o ID de producto no válido.');
            return;
        }

        fetch(`get_user_lists.php?username=${encodeURIComponent(username)}&productId=${currentProductId}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('listas-container');
                container.innerHTML = '';

                if (data.success) {
                    if (data.lists.length === 0) {
                        container.innerHTML = '<p>No tienes listas creadas. Crea una lista desde tu perfil.</p>';
                    } else {
                        data.lists.forEach(lista => {
                            const listItem = document.createElement('div');
                            listItem.className = 'lista-checkbox';
                            listItem.innerHTML = `
                                <input type="checkbox" id="lista-${lista.ID}" ${lista.enLista ? 'checked' : ''}>
                                <label for="lista-${lista.ID}">${lista.Nombre} (${lista.Status})</label>
                            `;
                            container.appendChild(listItem);
                        });
                    }
                } else {
                    alert('Error al cargar listas: ' + (data.error || 'Desconocido'));
                }
            })
            .catch(error => {
                console.error('Error al cargar listas:', error);
                alert('Error al cargar listas. Inténtalo de nuevo.');
            });
    }

    window.saveListChanges = function() {
        const username = sessionStorage.getItem('username');
        const currentProductId = new URLSearchParams(window.location.search).get('id');
        const checkboxes = document.querySelectorAll('#listas-container input[type="checkbox"]');

        if (!username || !currentProductId) {
            alert('Error: Sesión de usuario no encontrada o ID de producto no válido para guardar cambios.');
            return;
        }

        const changes = Array.from(checkboxes).map(checkbox => {
            const listId = checkbox.id.split('-')[1];
            return {
                listId: listId,
                checked: checkbox.checked
            };
        });

        fetch('update_product_lists.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                productId: currentProductId,
                changes: changes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeListasModal();
            } else {
                alert('Error al guardar cambios: ' + (data.message || data.error || 'Desconocido'));
            }
        })
        .catch(error => {
            console.error('Error al guardar cambios:', error);
            alert('Error al guardar cambios en las listas. Inténtalo de nuevo.');
        });
    }
    if (btnAgregarLista) {
        btnAgregarLista.addEventListener('click', openListasModal);
    }

    if (!productId) {
        alert('Producto no encontrado. Redirigiendo a la página principal.');
        window.location.href = 'main.html';
        return;
    }

    fetch(`get_product_details.php?id=${productId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.product) {
                const product = data.product;

                productoTitulo.textContent = product.nombre;
                productoDescripcion.textContent = product.descripcion;
                productoVendidos.textContent = `${product.vendidos} vendidos`;

                if (product.calificacion_promedio > 0) {
                    const fullStars = '★'.repeat(Math.round(product.calificacion_promedio));
                    const emptyStars = '☆'.repeat(10 - Math.round(product.calificacion_promedio)); // Ajustar a tu sistema de 10 estrellas
                    promedioEstrellasSpan.textContent = fullStars + emptyStars;
                    promedioValorSpan.textContent = ` (${product.calificacion_promedio}/10)`;
                    calificacionPromedioDiv.style.display = 'block';
                } else {
                    calificacionPromedioDiv.style.display = 'none'; 
                }

                if (product.tipo === 'Cotizacion') {
                    productoPrecio.textContent = 'Este producto es una cotización. Por favor, contacta al vendedor para más detalles.';
                    btnContactarVendedor.hidden = false;
                    btnAddCarrito.hidden = true;
                } else {
                    productoPrecio.textContent = `$${parseFloat(product.precio).toFixed(2)}`;
                    btnAddCarrito.hidden = false;
                    btnContactarVendedor.hidden = true;
                }

                categoriasContainer.innerHTML = '';
                const categorias = product.categorias && Array.isArray(product.categorias) ? product.categorias : (product.categoria ? [product.categoria] : []);

                if (categorias.length > 0) {
                    categorias.forEach(categoria => {
                        const span = document.createElement('span');
                        span.className = 'producto-categoria';
                        span.textContent = categoria;
                        categoriasContainer.appendChild(span);
                    });
                } else {
                    categoriasContainer.innerHTML = '<span class="producto-categoria">Sin Categoría</span>';
                }

                if (product.stock > 0) {
                    productoStock.textContent = `Disponibles: ${product.stock}`;
                    productoStock.classList.remove('agotado');
                    productoStock.classList.add('disponible');
                    btnAddCarrito.disabled = false;
                } else {
                    productoStock.textContent = 'AGOTADO';
                    productoStock.classList.remove('disponible');
                    productoStock.classList.add('agotado');
                    btnAddCarrito.disabled = true;
                }

                function selectMedia(type, src, miniaturaElement) {
                    document.querySelectorAll('.miniatura').forEach(m => m.classList.remove('seleccionada'));
                    miniaturaElement.classList.add('seleccionada');

                    if (type === 'image') {
                        imagenPrincipalElement.src = src;
                        imagenPrincipalElement.style.display = 'block';
                        videoReproductorElement.style.display = 'none';
                        videoReproductorElement.pause();
                    } else if (type === 'video') {
                        videoReproductorElement.src = src;
                        videoReproductorElement.style.display = 'block';
                        imagenPrincipalElement.style.display = 'none';
                        videoReproductorElement.play();
                    }
                }

                if (product.imagenPrincipal) {
                    const miniaturaPrincipal = document.createElement('img');
                    miniaturaPrincipal.className = 'miniatura';
                    miniaturaPrincipal.src = product.imagenPrincipal;
                    miniaturaPrincipal.alt = 'Imagen principal';
                    miniaturaPrincipal.onclick = () => selectMedia('image', product.imagenPrincipal, miniaturaPrincipal);
                    miniaturasContainer.appendChild(miniaturaPrincipal);
                    selectMedia('image', product.imagenPrincipal, miniaturaPrincipal); // Establecer la imagen principal por defecto
                }

                [product.imagenExtra1, product.imagenExtra2].forEach((imagen, index) => {
                    if (imagen) {
                        const miniatura = document.createElement('img');
                        miniatura.className = 'miniatura';
                        miniatura.src = imagen;
                        miniatura.alt = `Imagen extra ${index + 1}`;
                        miniatura.onclick = () => selectMedia('image', imagen, miniatura);
                        miniaturasContainer.appendChild(miniatura);
                    }
                });

                if (product.video) {
                    const miniaturaVideo = document.createElement('div');
                    miniaturaVideo.className = 'miniatura miniatura-video';
                    miniaturaVideo.textContent = '▶️';
                    miniaturaVideo.style.cssText = 'display:flex; justify-content:center; align-items:center; font-size:2em; background-color:#f0f0f0; cursor:pointer;';
                    miniaturaVideo.onclick = () => selectMedia('video', product.video, miniaturaVideo);
                    miniaturasContainer.appendChild(miniaturaVideo);
                }

                listaComentariosDiv.innerHTML = ''; 
                if (product.reseñas && product.reseñas.length > 0) {
                    noComentariosMensaje.style.display = 'none';
                    product.reseñas.forEach(review => {
                        const reviewItemDiv = document.createElement('div');
                        reviewItemDiv.classList.add('review-item');
                        reviewItemDiv.innerHTML = `
                            <p><strong>${review.autor || 'Anónimo'}</strong> <span class="review-stars">${'★'.repeat(review.calificacion)}${'☆'.repeat(10 - review.calificacion)}</span> (${review.calificacion}/10)</p>
                            <p class="review-comment">"${review.comentario}"</p>
                        `;
                        listaComentariosDiv.appendChild(reviewItemDiv);
                    });
                } else {
                    noComentariosMensaje.style.display = 'block';
                }

            } else {
                alert('No se pudieron cargar los detalles del producto: ' + (data.message || 'Error desconocido'));
                window.location.href = 'main.html';
            }
        })
        .catch(error => {
            console.error('Error al obtener detalles del producto:', error);
            alert('Hubo un problema al cargar el producto. Inténtalo más tarde.');
            window.location.href = 'main.html';
        });

    if (btnContactarVendedor) {
        btnContactarVendedor.addEventListener('click', function() {
            fetch('crear_chat_mensaje.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `productId=${encodeURIComponent(productId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    chatModal.classList.remove('hidden');
                    chatModal.classList.add('visible');
                    chatModal.dataset.productId = productId;
                    chatBody.dataset.chatId = data.chat_id;

                    const chatHeader = document.getElementById('chat-modal-header');
                    fetch(`get_product_details.php?id=${productId}`)
                        .then(response => response.json())
                        .then(productData => {
                            if (productData.success && productData.product && productData.product.vendedor) {
                                chatHeader.textContent = 'Chat con: ' + productData.product.vendedor;
                            } else {
                                chatHeader.textContent = 'Chat de Soporte';
                            }
                        })
                        .catch(error => console.error('Error al obtener nombre del vendedor:', error));

                    setTimeout(() => chatInput.focus(), 200);

                    clearInterval(refreshInterval);
                    refreshInterval = setInterval(refreshChatMessages, 3000);

                    refreshChatMessages();
                } else {
                    alert('Error al iniciar el chat: ' + (data.message || 'Desconocido'));
                    console.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error al iniciar chat:', error);
                alert('Hubo un problema al iniciar el chat. Inténtalo más tarde.');
            });
        });
    }

    if (chatCloseBtn) {
        chatCloseBtn.onclick = () => {
            clearInterval(refreshInterval);
            chatModal.classList.remove('visible');
            chatModal.classList.add('hidden');
            chatBody.innerHTML = '<div style="color:#888;text-align:center;margin-top:30px;">¡Hola! ¿En qué podemos ayudarte?</div>';
        };
    }

    if (chatSend) chatSend.onclick = sendChatMessage;
    if (chatInput) {
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') sendChatMessage();
        });
    }

    function sendChatMessage() {
        if (!chatInput) return;
        const msg = chatInput.value.trim();
        if (!msg) return;

        const chatId = chatBody.dataset.chatId;

        if (!chatId) {
            console.error("El chatId no está definido para enviar el mensaje.");
            alert("No se pudo enviar el mensaje. Inténtalo de nuevo.");
            return;
        }

        fetch('enviar_mensaje.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id_chat=${encodeURIComponent(chatId)}&mensaje=${encodeURIComponent(msg)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chatInput.value = '';
                refreshChatMessages();
            } else {
                alert('Error al enviar mensaje: ' + (data.message || 'Desconocido'));
                console.error('Error al enviar mensaje:', data.message);
            }
        })
        .catch(error => {
            console.error('Error de red al enviar mensaje:', error);
            alert('Error de conexión al enviar el mensaje.');
        });
    }

    function appendChatMessage(sender, text, isUser) {
        if (!chatBody) return;
        const msgDiv = document.createElement('div');
        msgDiv.style.margin = '8px 0';
        msgDiv.style.textAlign = isUser ? 'right' : 'left';
        msgDiv.innerHTML = `<span style="display:inline-block;max-width:80%;background:${isUser ? 'var(--principal,#1976d2)' : '#e3eafc'};color:${isUser ? '#fff' : '#222'};padding:7px 12px;border-radius:10px;margin:${isUser ? '0 0 0 auto' : '0 auto 0 0'};font-size:1em;word-wrap:break-word;">${text}</span>`;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function refreshChatMessages() {
        const chatId = chatBody.dataset.chatId;
        if (!chatId || chatModal.classList.contains('hidden')) {
            return;
        }

        fetch(`get_mensajes_chat.php?id_chat=${chatId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const currentScrollPos = chatBody.scrollTop;
                    const maxScrollPos = chatBody.scrollHeight - chatBody.clientHeight;
                    const isScrolledToBottom = (maxScrollPos - currentScrollPos <= 20);

                    chatBody.innerHTML = '';

                    if (data.mensajes.length === 0) {
                        chatBody.innerHTML = '<div style="color:#888;text-align:center;margin-top:30px;">No hay mensajes aún.</div>';
                    } else {
                        data.mensajes.forEach(msg => {
                            appendChatMessage(
                                msg.es_usuario ? 'Tú' : 'Soporte',
                                msg.mensaje,
                                msg.es_usuario
                            );
                        });
                    }

                    if (isScrolledToBottom) {
                        chatBody.scrollTop = chatBody.scrollHeight;
                    }
                } else {
                    console.error("Error al refrescar mensajes:", data.message);
                }
            })
            .catch(error => console.error('Error al refrescar mensajes:', error));
    }

    if (btnAddCarrito) {
        btnAddCarrito.addEventListener('click', function() {
            const username = sessionStorage.getItem('username');
            if (!username) {
                alert('Debes iniciar sesión para añadir productos al carrito.');
                return;
            }

            fetch('carrito_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    productId: productId,
                    cantidad: 1
                })
            })
            .then(response => {
                return response.text().then(text => {
                    console.log("Respuesta cruda del servidor al añadir al carrito:", text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error("Respuesta del servidor no es JSON válido: " + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('Producto añadido al carrito con éxito!');
                } else {
                    alert('Error al añadir al carrito: ' + (data.error || data.message || 'Desconocido'));
                    console.error('Error al añadir al carrito:', data.error || data.message);
                }
            })
            .catch(error => {
                console.error('Error de red al añadir al carrito:', error);
                alert('Error de conexión al añadir el producto al carrito. Revisa la consola para más detalles.');
            });
        });
    }
});