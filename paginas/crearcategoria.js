document.addEventListener('DOMContentLoaded', function() {
    const categoriaForm = document.getElementById('categoriaForm');

    categoriaForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const nombre = document.getElementById('nombreCategoria').value.trim();
        const descripcion = document.getElementById('descripcionCategoria').value.trim();
        
        if (!nombre || !descripcion) {
            alert('Por favor complete todos los campos obligatorios');
            return;
        }

        const formData = new FormData(categoriaForm);
        
        fetch('crear_categoria.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('Categoría Creada con Éxito');
                window.location.href = 'mainadministrador.html';
            } else {
                throw new Error(data.error || 'Error desconocido al crear categoría');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    });
});