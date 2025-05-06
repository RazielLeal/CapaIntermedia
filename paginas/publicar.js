document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('publicarForm');
    const selectCat = document.getElementById('categoria');
    const btnCrearCategoria = document.getElementById('btnCrearCategoria');
    const nombreCategoria = document.getElementById('nombreCategoria');
    const descripcionCategoria = document.getElementById('descripcionCategoria');

    // 1) Cargar categorías dinámicamente
    function cargarCategorias() {
        fetch('obtenerCategorias.php')
            .then(response => response.json())
            .then(cats => {
                selectCat.innerHTML = '';
                cats.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.ID;
                    opt.textContent = cat.Nombre;
                    selectCat.appendChild(opt);
                });
                toggleRequiredFields();
            })
            .catch(err => {
                console.error('Error cargando categorías:', err);
                alert('No se pudieron cargar las categorías.');
            });
    }
    cargarCategorias();

    // 2) Manejar creación de categorías
    btnCrearCategoria.addEventListener('click', function() {
        const nombre = nombreCategoria.value.trim();
        const desc = descripcionCategoria.value.trim();

        if (!nombre || !desc) {
            alert('Por favor, complete ambos campos para crear una categoría.');
            return;
        }

        const formData = new FormData();
        formData.append('nombreCategoria', nombre);
        formData.append('descripcionCategoria', desc);

        fetch('crearCategoria.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Error al crear categoría');
            
            // Limpiar campos y actualizar lista
            nombreCategoria.value = '';
            descripcionCategoria.value = '';
            cargarCategorias();
            alert('Categoría creada exitosamente!');
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message);
        });
    });

    // 3) Validación de campos requeridos
    function toggleRequiredFields() {
        if (selectCat.options.length > 0) {
            selectCat.required = true;
            nombreCategoria.required = false;
            descripcionCategoria.required = false;
        } else {
            selectCat.required = false;
            nombreCategoria.required = true;
            descripcionCategoria.required = true;
        }
    }

    // 4) Manejar envío del formulario principal
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Validación de tamaño de imágenes
        const maxSize = 5 * 1024 * 1024;
        const fotoPrincipal = document.getElementById('fotoPrincipal').files[0];
        const fotoExtra1 = document.getElementById('fotoExtra1').files[0];
        const fotoExtra2 = document.getElementById('fotoExtra2').files[0];
        
        if (fotoPrincipal && fotoPrincipal.size > maxSize) {
            alert('La imagen principal es demasiado grande (máximo 5MB)');
            return;
        }
        if (fotoExtra1 && fotoExtra1.size > maxSize) {
            alert('La imagen extra 1 es demasiado grande (máximo 5MB)');
            return;
        }
        if (fotoExtra2 && fotoExtra2.size > maxSize) {
            alert('La imagen extra 2 es demasiado grande (máximo 5MB)');
            return;
        }

        // Desactivar botón
        const btnSubmit = document.getElementById('btnpublicar');
        btnSubmit.disabled = true;

        // Enviar formulario
        const formData = new FormData(form);
        
        fetch('publicar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(result => {
            if (result.success) {
                alert('Producto publicado exitosamente');
                window.location.href = 'mainvendedor.html'; 
            } else {
                throw new Error(result.error || 'Error desconocido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al publicar: ' + error.message);
        })
        .finally(() => {
            btnSubmit.disabled = false;
        });
    });

    // Event listeners para validación dinámica
    selectCat.addEventListener('change', toggleRequiredFields);
    nombreCategoria.addEventListener('input', toggleRequiredFields);
    descripcionCategoria.addEventListener('input', toggleRequiredFields);
    toggleRequiredFields(); // Estado inicial
});