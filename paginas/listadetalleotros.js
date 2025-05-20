document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const listaId = urlParams.get('id');
    const userId = urlParams.get('userId'); 

    if (!listaId || !userId) {
        alert('Lista o Usuario no especificado.');
        window.history.back();
        return;
    }

    fetch(`get_lista_detalle.php?id=${listaId}&user_id=${userId}&public_only=true`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { 
                    console.error('Error HTTP en la respuesta del servidor:', response.status, text);
                    throw new Error(`Error en la respuesta del servidor (${response.status}): ${text}`); 
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('lista-nombre').textContent = data.lista.Nombre;
                document.getElementById('lista-status').textContent = `Visibilidad: ${data.lista.Status}`;
                document.getElementById('lista-descripcion').textContent = data.lista.Descripcion || 'Sin descripción';
                
                const container = document.getElementById('productos-lista');
                if (data.productos.length === 0) {
                    container.innerHTML = `
                        <div class="empty-list">
                            <div class="lista-vacia-icono" style="margin: 0 auto;">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <p>Esta lista está vacía</p>
                        </div>
                    `;
                    return;
                }
                
                data.productos.forEach(producto => {
                    const productoDiv = document.createElement('div');
                    productoDiv.className = 'producto producto-lista';
                    productoDiv.innerHTML = `
                        ${producto.FotoPrincipal ? 
                            `<img src="data:image/jpeg;base64,${producto.FotoPrincipal}" class="imagen-producto">` : 
                            `<div class="lista-vacia-icono"><i class="fas fa-box"></i></div>`
                        }
                        <div class="producto-precio">$${parseFloat(producto.Precio).toFixed(2)}</div>
                        <div class="lista-info">
                            <h3>${producto.Nombre}</h3>
                            <p>${producto.Descripcion?.substring(0, 50) || ''}...</p>
                        </div>
                    `;
                    
                    productoDiv.addEventListener('click', () => {
                        window.location.href = `producto.html?id=${producto.ID}`;
                    });
                    
                    container.appendChild(productoDiv);
                });
            } else {
                alert('Error: ' + data.error);
                window.history.back();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar la lista. Puede que sea privada o no exista.');
            window.history.back();
        });
});