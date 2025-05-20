

const VENDEDOR_API_URL = 'vendedor_api.php';
const PRODUCTOS_PER_PAGE = 8; 
let currentPage = 1;
let totalPages = 0;

async function loadCategories() {
    try {
        const userId = sessionStorage.getItem('userId');
        if (!userId) {
            console.error('No se encontró el ID de usuario para cargar categorías.');
            return;
        }

        const response = await fetch(`${VENDEDOR_API_URL}?action=getCategories`, {
            headers: { 'Authorization': `Bearer ${userId}` }
        });
        const data = await response.json();

        if (data.success) {
            const salesCategoryFilter = document.getElementById('filter-category-sales');
            const productsCategoryFilter = document.getElementById('filter-category-products');
            
            salesCategoryFilter.innerHTML = '<option value="">Todas</option>';
            productsCategoryFilter.innerHTML = '<option value="">Todas</option>';

            data.categories.forEach(category => {
                const optionSales = document.createElement('option');
                optionSales.value = category.ID;
                optionSales.textContent = category.Nombre;
                salesCategoryFilter.appendChild(optionSales);

                const optionProducts = document.createElement('option');
                optionProducts.value = category.ID;
                optionProducts.textContent = category.Nombre;
                productsCategoryFilter.appendChild(optionProducts);
            });
        } else {
            console.error('Error al cargar categorías:', data.error);
        }
    } catch (error) {
        console.error('Error en la solicitud de categorías:', error);
    }
}

async function loadSalesData() {
    const userId = sessionStorage.getItem('userId');
    if (!userId) {
        alert('No se encontró el ID de usuario. Por favor, inicie sesión de nuevo.');
        return;
    }

    const startDate = document.getElementById('filter-start-date').value;
    const endDate = document.getElementById('filter-end-date').value;
    const categoryId = document.getElementById('filter-category-sales').value;

    try {
        // Cargar Consulta Agrupada
        const responseGrouped = await fetch(`${VENDEDOR_API_URL}?action=getGroupedSales&startDate=${startDate}&endDate=${endDate}&categoryId=${categoryId}`, {
            headers: { 'Authorization': `Bearer ${userId}` }
        });
        const dataGrouped = await responseGrouped.json();
        renderGroupedSales(dataGrouped);

        currentPage = 1; 
        await loadDetailedSales();

    } catch (error) {
        console.error('Error al cargar datos de ventas:', error);
        document.getElementById('grouped-sales-results').innerHTML = '<p>Error al cargar las ventas agrupadas.</p>';
        document.getElementById('detailed-sales-products').innerHTML = '<p>Error al cargar los productos vendidos.</p>';
    }
}

function renderGroupedSales(data) {
    const container = document.getElementById('grouped-sales-results');
    container.innerHTML = ''; 

    if (data.success && data.groupedSales && data.groupedSales.length > 0) {
        let tableHTML = '<table><thead><tr><th>Mes - Año</th><th>Categoría</th><th>Productos Vendidos</th><th>Cantidad Total</th><th>Ingreso Total</th></tr></thead><tbody>';
        data.groupedSales.forEach(row => {
            tableHTML += `
                <tr>
                    <td>${row.MesAnio}</td>
                    <td>${row.CategoriaNombre}</td>
                    <td>${row.NumeroProductosVendidos}</td>
                    <td>${row.CantidadTotalVendida}</td>
                    <td>$${parseFloat(row.IngresoTotal).toFixed(2)}</td>
                </tr>
            `;
        });
        tableHTML += '</tbody></table>';
        container.innerHTML = tableHTML;
    } else {
        container.innerHTML = '<p>No hay datos de ventas agrupadas para los filtros seleccionados.</p>';
    }
}

async function loadDetailedSales() {
    const userId = sessionStorage.getItem('userId');
    const startDate = document.getElementById('filter-start-date').value;
    const endDate = document.getElementById('filter-end-date').value;
    const categoryId = document.getElementById('filter-category-sales').value;

    try {
        const responseDetailed = await fetch(`${VENDEDOR_API_URL}?action=getDetailedSalesProducts&startDate=${startDate}&endDate=${endDate}&categoryId=${categoryId}&page=${currentPage}&limit=${PRODUCTOS_PER_PAGE}`, {
            headers: { 'Authorization': `Bearer ${userId}` }
        });
        const dataDetailed = await responseDetailed.json();
        renderDetailedSalesProducts(dataDetailed);
        renderPagination(dataDetailed.totalPages);

    } catch (error) {
        console.error('Error al cargar productos vendidos:', error);
        document.getElementById('detailed-sales-products').innerHTML = '<p>Error al cargar los productos vendidos.</p>';
    }
}

function renderDetailedSalesProducts(data) {
    const container = document.getElementById('detailed-sales-products');
    container.innerHTML = ''; 

    if (data.success && data.products && data.products.length > 0) {
        data.products.forEach(product => {
            const card = document.createElement('div');
            card.classList.add('sold-product-card');
            card.dataset.productId = product.ProductoID; 
            card.innerHTML = `
                <img src="data:image/jpeg;base64,${product.FotoPrincipal || ''}" alt="${product.Nombre}">
                <div class="sold-product-card-info">
                    <h3>${product.Nombre}</h3>
                    <p>Categoría: ${product.CategoriaNombre}</p>
                    <p>Precio Venta: $${parseFloat(product.Precio).toFixed(2)}</p>
                    <p>Stock Actual: ${product.Stock}</p>
                    <p>Total Vendido (Histórico): ${product.Vendidos}</p>
                </div>
            `;
            card.addEventListener('click', () => openProductDetailsModal(product.ProductoID, product.Nombre));
            container.appendChild(card);
        });
    } else {
        container.innerHTML = '<p>No hay productos vendidos para los filtros seleccionados.</p>';
    }
}

function renderPagination(total) {
    const paginationContainer = document.getElementById('detailed-sales-pagination');
    paginationContainer.innerHTML = '';
    totalPages = total;

    if (totalPages > 1) {
        const prevButton = document.createElement('button');
        prevButton.textContent = '<';
        prevButton.disabled = currentPage === 1;
        prevButton.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadDetailedSales();
            }
        });
        paginationContainer.appendChild(prevButton);

        // Números de página
        for (let i = 1; i <= totalPages; i++) {
            const pageButton = document.createElement('button');
            pageButton.textContent = i;
            pageButton.classList.toggle('active', i === currentPage);
            pageButton.addEventListener('click', () => {
                currentPage = i;
                loadDetailedSales();
            });
            paginationContainer.appendChild(pageButton);
        }

        const nextButton = document.createElement('button');
        nextButton.textContent = '>';
        nextButton.disabled = currentPage === totalPages;
        nextButton.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                loadDetailedSales();
            }
        });
        paginationContainer.appendChild(nextButton);
    }
}

async function openProductDetailsModal(productId, productName) {
    const modal = document.getElementById('product-details-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const detailsContent = document.getElementById('product-details-content');
    
    modalProductName.textContent = `Detalles de Venta: ${productName}`;
    detailsContent.innerHTML = '<p>Cargando detalles...</p>';
    modal.style.display = 'block';

    const userId = sessionStorage.getItem('userId');
    if (!userId) {
        console.error('No se encontró el ID de usuario para cargar detalles de venta.');
        return;
    }

    try {
        const response = await fetch(`${VENDEDOR_API_URL}?action=getProductSaleDetails&productId=${productId}`, {
            headers: { 'Authorization': `Bearer ${userId}` }
        });
        const data = await response.json();

        detailsContent.innerHTML = ''; // Limpiar contenido anterior
        if (data.success && data.salesDetails && data.salesDetails.length > 0) {
            data.salesDetails.forEach(sale => {
                const saleEntry = document.createElement('div');
                saleEntry.classList.add('sale-entry');
                saleEntry.innerHTML = `
                    <p><strong>Fecha y Hora:</strong> ${new Date(sale.Fecha).toLocaleString()}</p>
                    <p><strong>Cantidad Vendida:</strong> ${sale.Cantidad}</p>
                    <p><strong>Precio Total de Venta:</strong> $${parseFloat(sale.PrecioTotal).toFixed(2)}</p>
                    <p><strong>Calificación:</strong> ${sale.Calificacion ? sale.Calificacion + ' / 5' : 'N/A'}</p>
                    <p><strong>Comentario:</strong> ${sale.Comentario || 'Sin comentario'}</p>
                `;
                detailsContent.appendChild(saleEntry);
            });
        } else {
            detailsContent.innerHTML = '<p>No se encontraron detalles de ventas para este producto.</p>';
        }
    } catch (error) {
        console.error('Error al cargar los detalles de venta del producto:', error);
        detailsContent.innerHTML = '<p>Error al cargar los detalles de venta.</p>';
    }
}

async function loadCurrentProducts() {
    const userId = sessionStorage.getItem('userId');
    if (!userId) {
        alert('No se encontró el ID de usuario. Por favor, inicie sesión de nuevo.');
        return;
    }

    const categoryId = document.getElementById('filter-category-products').value;

    try {
        const response = await fetch(`${VENDEDOR_API_URL}?action=getCurrentProducts&categoryId=${categoryId}`, {
            headers: { 'Authorization': `Bearer ${userId}` }
        });
        const data = await response.json();
        renderCurrentProducts(data);
    } catch (error) {
        console.error('Error al cargar productos actuales:', error);
        document.getElementById('current-products-list').innerHTML = '<p>Error al cargar los productos actuales.</p>';
    }
}

function renderCurrentProducts(data) {
    const container = document.getElementById('current-products-list');
    container.innerHTML = ''; 

    if (data.success && data.products && data.products.length > 0) {
        data.products.forEach(product => {
            const card = document.createElement('div');
            card.classList.add('product-item-card');
            const stockClass = product.Stock <= 5 ? 'low-stock' : ''; 
            card.innerHTML = `
                <img src="data:image/jpeg;base64,${product.FotoPrincipal || ''}" alt="${product.Nombre}">
                <h3>${product.Nombre}</h3>
                <p>Categoría: ${product.CategoriaNombre}</p>
                <p>Precio: $${parseFloat(product.Precio).toFixed(2)}</p>
                <p class="stock-info ${stockClass}">Stock: ${product.Stock}</p>
            `;
            container.appendChild(card);
        });
    } else {
        container.innerHTML = '<p>No tienes productos publicados o no hay productos para los filtros seleccionados.</p>';
    }
}
