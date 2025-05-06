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
  const productsPerPage = 4;
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
      console.log('Cargando datos del usuario...');
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
      console.log('Cargando categorías...');
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
        console.log(`Cargando productos para usuario ${userId}...`);
        
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
        console.log('URL completa:', url);

        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Datos recibidos:', data); // Depuración

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
});