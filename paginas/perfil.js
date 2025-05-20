document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const userNameElement = document.getElementById('user-name');
    const userEmailElement = document.getElementById('user-email');
    const registerDateElement = document.getElementById('register-date');
    const profilePhoto = document.getElementById('profile-photo');
    const cerrarBtn = document.getElementById('cerrar-btn');
    const listaComprasDiv = document.getElementById('lista-compras');
    const noComprasMensaje = document.getElementById('no-compras-mensaje');
    const reviewModal = document.getElementById('review-modal');
    const reviewProductName = document.getElementById('review-product-name');
    const reviewPurchaseId = document.getElementById('review-purchase-id');
    const reviewProductId = document.getElementById('review-product-id');
    const reviewRatingInput = document.getElementById('review-rating');
    const reviewCommentInput = document.getElementById('review-comment');
    const ratingStarsContainer = document.getElementById('rating-stars-container');
    
    // Variables globales
    let purchasesData = [];
    const username = sessionStorage.getItem('username');

    // Verificar sesión
    if (!username) {
        window.location.href = 'index.html';
        return;
    }

    // Inicializar
    loadUserData();
    setupEventListeners();

    function loadUserData() {
        fetch(`login.php?user=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProfileInfo(data);
                    loadPurchases();
                } else {
                    throw new Error(data.error || 'Error cargando datos');
                }
            })
            .catch(error => handleError(error));
    }

    function updateProfileInfo(userData) {
        userNameElement.textContent = userData.username;
        userEmailElement.textContent = `Email: ${userData.email}`;
        registerDateElement.textContent = `Registro: ${userData.register_date}`;
        if (userData.photo) {
            profilePhoto.src = `data:image/jpeg;base64,${userData.photo}`;
        }
    }

    function loadPurchases() {
        fetch(`compras_api.php?action=get_purchases&username=${encodeURIComponent(username)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    purchasesData = data.purchases;
                    renderPurchases();
                    setupFilters();
                } else {
                    showNoPurchases();
                }
            })
            .catch(error => handleError(error));
    }

    function renderPurchases() {
        listaComprasDiv.innerHTML = '';
        noComprasMensaje.style.display = 'none';

        purchasesData.forEach(purchase => {
            const compraDiv = document.createElement('div');
            compraDiv.className = 'compra-item';
            compraDiv.dataset.fecha = new Date(purchase.purchase_date).getTime();
            compraDiv.dataset.categorias = purchase.categories?.toLowerCase() || '';

            compraDiv.innerHTML = `
                <h4>${purchase.product_name}</h4>
                <div class="categorias-producto">
                    ${getCategoryTags(purchase.categories)}
                </div>
                <p>Cantidad: ${purchase.quantity}</p>
                <p>Total: $${parseFloat(purchase.total_price).toFixed(2)}</p>
                <p>Fecha: ${new Date(purchase.purchase_date).toLocaleString()}</p>
                ${getReviewSection(purchase)}
                <button class="${getReviewButtonClass(purchase)}" 
                    onclick="openReviewModal(
                        ${purchase.purchase_id},
                        ${purchase.product_id},
                        '${escapeString(purchase.product_name)}',
                        ${purchase.existing_rating || 0},
                        '${escapeString(purchase.existing_comment || '')}'
                    )">
                    ${getReviewButtonText(purchase)}
                </button>
            `;
            listaComprasDiv.appendChild(compraDiv);
        });
    }

    function getCategoryTags(categories) {
        return categories ? 
            categories.split(', ')
                .map(cat => `<span class="categoria-tag">${cat}</span>`)
                .join('') : 
            '<span class="categoria-tag">Sin categoría</span>';
    }

    function getReviewSection(purchase) {
    let reviewHtml = '';
    
    if (purchase.existing_rating) {
        reviewHtml += `
            <div class="reseña-existente">
                <p>Calificación: 
                    ${'★'.repeat(purchase.existing_rating)}
                    ${'☆'.repeat(10 - purchase.existing_rating)}
                    (${purchase.existing_rating}/10)
                </p>`;
        
        if (purchase.existing_comment && purchase.existing_comment.trim() !== '') {
            reviewHtml += `<p class="comentario-reseña">"${purchase.existing_comment}"</p>`;
        }
        
        reviewHtml += `</div>`;
    }
    
    return reviewHtml;
}

    function getReviewButtonClass(purchase) {
        return purchase.existing_rating ? 'botones editar-btn' : 'botones';
    }

    function getReviewButtonText(purchase) {
        return purchase.existing_rating ? 'Editar Reseña' : 'Añadir Reseña';
    }

    function setupFilters() {
        populateCategoryFilter();
        document.querySelectorAll('.filtro-input').forEach(input => {
            input.addEventListener('input', aplicarFiltros);
        });
    }

    function populateCategoryFilter() {
        const categories = new Set();
        purchasesData.forEach(purchase => {
            if (purchase.categories) {
                purchase.categories.split(', ').forEach(cat => categories.add(cat));
            }
        });
        
        const filtroCategoria = document.getElementById('filtro-categoria');
        filtroCategoria.innerHTML = '<option value="">Todas</option>';
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            filtroCategoria.appendChild(option);
        });
    }

    window.aplicarFiltros = function() {
        const fechaDesde = document.getElementById('filtro-fecha-desde').value;
        const fechaHasta = document.getElementById('filtro-fecha-hasta').value;
        const categoria = document.getElementById('filtro-categoria').value.toLowerCase();

        document.querySelectorAll('.compra-item').forEach(item => {
            const itemFecha = parseInt(item.dataset.fecha);
            const itemCats = item.dataset.categorias;
            
            const cumpleFecha = (
                (!fechaDesde || itemFecha >= new Date(fechaDesde).getTime()) &&
                (!fechaHasta || itemFecha <= new Date(fechaHasta).getTime())
            );
            
            const cumpleCategoria = !categoria || itemCats.includes(categoria);
            
            item.style.display = (cumpleFecha && cumpleCategoria) ? 'block' : 'none';
        });
    };

    window.resetFilters = function() {
        document.getElementById('filtro-fecha-desde').value = '';
        document.getElementById('filtro-fecha-hasta').value = '';
        document.getElementById('filtro-categoria').value = '';
        aplicarFiltros();
    };

    window.openReviewModal = function(purchaseId, productId, productName, existingRating = 0, existingComment = '') {
        reviewPurchaseId.value = purchaseId;
        reviewProductId.value = productId;
        reviewProductName.textContent = productName;
        reviewRatingInput.value = existingRating;
        reviewCommentInput.value = existingComment;
        updateStarDisplay(existingRating);
        reviewModal.style.display = 'block';
    };

    window.closeReviewModal = function() {
        reviewModal.style.display = 'none';
    };

    window.submitReview = function() {
        const reviewData = {
            purchase_id: reviewPurchaseId.value,
            rating: reviewRatingInput.value,
            comment: reviewCommentInput.value.trim()
        };

        fetch('compras_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + username
            },
            body: JSON.stringify({
                action: 'add_review',
                ...reviewData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeReviewModal();
                loadPurchases();
            } else {
                throw new Error(data.error || 'Error del servidor');
            }
        })
        .catch(error => handleError(error));
    };

    function updateStarDisplay(rating) {
        const stars = ratingStarsContainer.querySelectorAll('.star');
        stars.forEach((star, index) => {
            star.style.color = index < rating ? '#ffc107' : '#ccc';
        });
    }

    function setupEventListeners() {
        cerrarBtn.addEventListener('click', () => {
            sessionStorage.clear();
            window.location.href = 'index.html';
        });

        ratingStarsContainer.addEventListener('click', e => {
            if (e.target.classList.contains('star')) {
                reviewRatingInput.value = e.target.dataset.value;
                updateStarDisplay(e.target.dataset.value);
            }
        });
    }

    function escapeString(str) {
        return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function showNoPurchases() {
        listaComprasDiv.innerHTML = '';
        noComprasMensaje.style.display = 'block';
    }

    function handleError(error) {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    }
});