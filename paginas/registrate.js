document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registroForm');
    
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(form);

        fetch('registrate.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Registro exitoso');
                window.location.href = 'index.html'; 
            } else {
                alert('Error en el registro: ' + result.error);
            }
        })
        .catch(error => {
            console.error('Error en la solicitud:', error);
            alert('Hubo un problema con la conexión.');
        });
    });
});
