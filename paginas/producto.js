document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');            
    if (!productId) {
        alert('Producto no encontrado');
        window.location.href = 'main.html';
        return;
    }            
    fetch(`get_product_details.php?id=${productId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            document.getElementById('producto-titulo').textContent = product.nombre;
            if(product.tipo==='Cotizacion'){
                document.getElementById('producto-precio').textContent = 'Este producto es una cotización. Por favor, contacta al vendedor para más detalles.';
                document.getElementById('producto-descripcion').textContent = product.descripcion;
                document.getElementById('btn-contactar-vendedor').hidden = false;
            } else {
                document.getElementById('producto-descripcion').textContent = product.descripcion;
                document.getElementById('producto-precio').textContent = `$${product.precio}`;
                document.getElementById('btn-añadir-carrito').hidden = false;
            }
            document.getElementById('producto-vendidos').textContent = `${product.vendidos} vendidos`;
            const categoriasContainer = document.getElementById('categorias-container');
            categoriasContainer.innerHTML = '';
            if (product.categorias && product.categorias.length > 0) {
                product.categorias.forEach(categoria => {                                
                    const span = document.createElement('span');
                    span.className = 'producto-categoria';
                    span.textContent = categoria;
                    categoriasContainer.appendChild(span);
                });
            } else if (product.categoria) {
                const span = document.createElement('span');
                span.className = 'producto-categoria';
                span.textContent = product.categoria;
                categoriasContainer.appendChild(span);
            }
            const stockElement = document.getElementById('producto-stock');
            if (product.stock > 0) {
                stockElement.textContent = `Disponibles: ${product.stock}`;
                stockElement.classList.add('disponible');
                document.getElementById('btn-añadir-carrito').disabled = false;
            } else {
                stockElement.textContent = 'AGOTADO';
                stockElement.classList.add('agotado');
                document.getElementById('btn-añadir-carrito').disabled = true;
            }
            if (product.imagenPrincipal) {
                document.getElementById('imagen-principal').src = product.imagenPrincipal;
            }
            const miniaturasContainer = document.getElementById('miniaturas-container');
            miniaturasContainer.innerHTML = '';
            if (product.imagenPrincipal) {
                const miniaturaPrincipal = document.createElement('img');
                miniaturaPrincipal.className = 'miniatura seleccionada';
                miniaturaPrincipal.src = product.imagenPrincipal;
                miniaturaPrincipal.alt = 'Imagen principal';
                miniaturaPrincipal.onclick = () => {
                    document.getElementById('imagen-principal').style.display = 'block';
                    document.getElementById('video-reproductor').style.display = 'none';
                    document.querySelectorAll('.miniatura').forEach(m => m.classList.remove('seleccionada'));
                    miniaturaPrincipal.classList.add('seleccionada');
                };
                miniaturasContainer.appendChild(miniaturaPrincipal);
            }

            [product.imagenExtra1, product.imagenExtra2].forEach((imagen, index) => {
                if (imagen) {
                    const miniatura = document.createElement('img');
                    miniatura.className = 'miniatura';
                    miniatura.src = imagen;
                    miniatura.alt = `Imagen extra ${index + 1}`;
                    miniatura.onclick = () => {
                        document.getElementById('imagen-principal').src = imagen;
                        document.getElementById('imagen-principal').style.display = 'block';
                        document.getElementById('video-reproductor').style.display = 'none';
                        document.querySelectorAll('.miniatura').forEach(m => m.classList.remove('seleccionada'));
                        miniatura.classList.add('seleccionada');
                    };
                    miniaturasContainer.appendChild(miniatura);
                }
            });

            if (product.video) {
                const miniaturaVideo = document.createElement('div');
                miniaturaVideo.className = 'miniatura miniatura-video';
                miniaturaVideo.onclick = () => {
                    const videoPlayer = document.getElementById('video-reproductor');
                    videoPlayer.src = product.video;
                    videoPlayer.style.display = 'block';
                    document.getElementById('imagen-principal').style.display = 'none';
                    document.querySelectorAll('.miniatura').forEach(m => m.classList.remove('seleccionada'));
                    miniaturaVideo.classList.add('seleccionada');
                    videoPlayer.play();
                };
                miniaturasContainer.appendChild(miniaturaVideo);
            }

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
                const productId = new URLSearchParams(window.location.search).get('id');
                if (!username || !productId) {
                    alert('Error al cargar listas');
                    return;
                }
                fetch(`get_user_lists.php?username=${encodeURIComponent(username)}&productId=${productId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const container = document.getElementById('listas-container');
                            container.innerHTML = '';
                            if (data.lists.length === 0) {
                                container.innerHTML = '<p>No tienes listas creadas. Crea una lista desde tu perfil.</p>';
                                return;
                            }
                            data.lists.forEach(lista => {
                                const listItem = document.createElement('div');
                                listItem.className = 'lista-checkbox';
                                listItem.innerHTML = `
                                    <input type="checkbox" id="lista-${lista.ID}" ${lista.enLista ? 'checked' : ''}>
                                    <label for="lista-${lista.ID}">${lista.Nombre} (${lista.Status})</label>
                                `;
                                container.appendChild(listItem);
                            });
                        } else {
                            alert('Error al cargar listas: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cargar listas');
                    });
            }

            function saveListChanges() {
                const username = sessionStorage.getItem('username');
                const productId = new URLSearchParams(window.location.search).get('id');
                const checkboxes = document.querySelectorAll('#listas-container input[type="checkbox"]');
                if (!username || !productId) {
                    alert('Error al guardar cambios');
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
                        productId: productId,
                        changes: changes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeListasModal();
                    } else {
                        alert('Error al guardar cambios: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al guardar cambios');
                });
            }

            document.getElementById('btn-agregar-lista').addEventListener('click', openListasModal);
        }
    });
    
    const chatModal = document.getElementById('chat-modal');
    const chatCloseBtn = document.getElementById('chat-modal-close');
    const chatBody = document.getElementById('chat-modal-body');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const btnContactarVendedor = document.getElementById('btn-contactar-vendedor');

   // Al abrir:
    btnContactarVendedor.addEventListener('click', function() {
        fetch('crear_chat_mensaje.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `productId=${encodeURIComponent(productId)}`
        })
        .then(response => response.text())
        .then(text => {
            console.log("Respuesta del servidor:", text);
            return JSON.parse(text); 
        })
        .then(data => {
            if (data.success || !data.success) {
                chatModal.classList.remove('hidden');
                chatModal.classList.add('visible');
                chatModal.dataset.productId=productId;

                setTimeout(() => chatInput.focus(), 200);
                let refreshInterval;
                refreshInterval = setInterval(refreshChatMessages, 3000);

                const chatId = data.chat_id;
                chatBody.dataset.chatId =  data.chat_id;
                refreshChatMessages();

                if (!chatId) {
                    console.error('No se devolvió chat_id en la respuesta del servidor.');
                    return;
                }
            fetch(`get_mensajes_chat.php?id_chat=${chatId}`)
                .then(response => response.json())
                .then(messageData => {
                    chatBody.innerHTML = '';   // Limpia los mensajes previos
                    if (messageData.success) {
                        if (messageData.mensajes.length === 0) {
                            chatBody.innerHTML = '<div style="color:#888;text-align:center;margin-top:30px;">No hay mensajes aún.</div>';
                        } else {
                            messageData.mensajes.forEach(msg => {
                                appendChatMessage(
                                    msg.es_usuario ? 'Tú' : 'Soporte',
                                    msg.mensaje,
                                    msg.es_usuario
                                );
                            });
                        }
                    } else {
                        chatBody.innerHTML = '<div style="color:red;text-align:center;">Error al cargar mensajes.</div>';
                    }
                })
                .catch(error => console.error('Error al obtener mensajes:', error));
        } else {
            console.error(data.message);
        }
                
            
        })
        .catch(error => console.error('Error al iniciar chat:', error));
    });


    if (chatCloseBtn) {
        chatCloseBtn.onclick = () => {
            chatModal.classList.remove('visible');
            chatModal.classList.add('hidden');
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
                console.error("El chatId no está definido.");
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
                    refreshChatMessages();
                    appendChatMessage('Tú', msg, true);
                    // Limpia el campo de entrada
                    chatInput.value = '';
                }else{
                    alert(data.message);
                }
                    
        })
        .catch(error =>  console.error('Error al enviar mensaje:', error));
    }

    function appendChatMessage(sender, text, isUser) {
        if (!chatBody) return;        
        const msgDiv = document.createElement('div');
        msgDiv.style.margin = '8px 0';
        msgDiv.style.textAlign = isUser ? 'right' : 'left';
        msgDiv.innerHTML = `<span style="display:inline-block;max-width:80%;background:${isUser ? 'var(--principal,#1976d2)' : '#e3eafc'};color:${isUser ? '#fff' : '#222'};padding:7px 12px;border-radius:10px;margin:${isUser ? '0 0 0 auto' : '0 auto 0 0'};font-size:1em;">${text}</span>`;
        chatBody.appendChild(msgDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function refreshChatMessages() {
        const chatId = chatBody.dataset.chatId; // Asegúrate que este valor se asigna al abrir el chat.
        if (!chatId) return;
            
        fetch(`get_mensajes_chat.php?id_chat=${chatId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Vacía el contenedor y vuelve a agregar todos los mensajes.
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
                    } else {
                        console.error("Error al refrescar mensajes:", data.message);
                    }
            })
            .catch(error => console.error('Error al refrescar mensajes:', error));
    }


    chatCloseBtn.onclick = () => {
        clearInterval(refreshInterval); // Detiene el refresco periódico
        chatModal.classList.remove('visible');
        chatModal.classList.add('hidden');
    };

});