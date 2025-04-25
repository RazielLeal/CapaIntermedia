document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('publicarForm');
    const selectCat = document.getElementById('categoria');

    // → 1) Cargar categorías dinámicamente
    fetch('obtenerCategorias.php')
        .then(response => response.json())
        .then(cats => {
            cats.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat.ID;
                opt.textContent = cat.Nombre;
                selectCat.appendChild(opt);
            });
        })
        .catch(err => {
            console.error('Error cargando categorías:', err);
            alert('No se pudieron cargar las categorías.');
        });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Validar tamaño máximo de imágenes
        const maxSize = 5 * 1024 * 1024; // 5MB
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

        // Desactivar botón para evitar dobles envíos
        document.getElementById('btnpublicar').disabled = true;

        const formData = new FormData(form);

        fetch('publicar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                alert('Producto publicado exitosamente');
                window.location.href = 'mainvendedor.html'; 
            } else {
                alert('Error al publicar: ' + (result.error || 'Desconocido'));
                document.getElementById('btnpublicar').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error en la solicitud:', error);
            alert('Hubo un problema con la conexión: ' + error.message);
            document.getElementById('btnpublicar').disabled = false;
        });
    });
});
