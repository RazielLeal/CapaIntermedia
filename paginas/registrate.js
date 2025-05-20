document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registroForm');
    const rolUsuarioRadio = document.getElementById('rol-usuario');
    const rolVendedorRadio = document.getElementById('rol-vendedor');
    const estatusOptionsDiv = document.getElementById('estatus-options');
    const estatusPublicoRadio = document.getElementById('estatus-publico');

    function toggleEstatusOptions() {
        if (rolUsuarioRadio.checked) {
            estatusOptionsDiv.style.display = 'block';
        } else {
            estatusOptionsDiv.style.display = 'none'; 
            estatusPublicoRadio.checked = true; 
        }
    }

    rolUsuarioRadio.addEventListener('change', toggleEstatusOptions);
    rolVendedorRadio.addEventListener('change', toggleEstatusOptions);

    toggleEstatusOptions();

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