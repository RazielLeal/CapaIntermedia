document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editarForm');
    const cambiarContrasenaBtn = document.getElementById('cambiarContrasena');
    const contrasenaForm = document.getElementById('contrasenaForm');
    const guardarContrasenaBtn = document.getElementById('guardarContrasena');
    
    // Cargar datos actuales del usuario
    cargarDatosUsuario();
    
    // Mostrar/ocultar formulario de contraseña
    cambiarContrasenaBtn.addEventListener('click', function() {
        contrasenaForm.style.display = contrasenaForm.style.display === 'none' ? 'block' : 'none';
    });
    
    // Manejar el envío del formulario principal
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        actualizarPerfil();
    });
    
    // Manejar el cambio de contraseña
    guardarContrasenaBtn.addEventListener('click', function() {
        cambiarContrasena();
    });
});

function cargarDatosUsuario() {
    const username = sessionStorage.getItem('username');
    
    if (!username) {
        alert('No hay usuario registrado en la sesión.');
        window.location.href = 'index.html';
        return;
    }
    
    fetch(`obtener_usuario.php?username=${encodeURIComponent(username)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Rellenar campos con los datos actuales
                if (data.nombre) document.getElementById('nombre').value = data.nombre;
                if (data.apellidoPaterno) document.getElementById('apellidoP').value = data.apellidoPaterno;
                if (data.apellidoMaterno) document.getElementById('apellidoM').value = data.apellidoMaterno;
                if (data.nickname) document.getElementById('username').value = data.nickname;
                
                // Seleccionar género
                if (data.genero) {
                    const generoRadios = document.getElementsByName('genero');
                    for (let radio of generoRadios) {
                        if (radio.value === data.genero) {
                            radio.checked = true;
                            break;
                        }
                    }
                }
            } else {
                throw new Error(data.message || 'Error al cargar los datos del usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos del usuario: ' + error.message);
        });
}

function actualizarPerfil() {
    const form = document.getElementById('editarForm');
    const formData = new FormData(form);
    const username = sessionStorage.getItem('username');
    
    // Agregar el username actual para identificarlo en la BD
    formData.append('current_username', username);
    
    fetch('actualizar_perfil.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Perfil actualizado correctamente');
            if (data.new_username) {
                sessionStorage.setItem('username', data.new_username);
            }
            window.location.href = 'perfil.html';
        } else {
            throw new Error(data.message || 'Error al actualizar el perfil');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el perfil: ' + error.message);
    });
}

function cambiarContrasena() {
    const contrasenaActual = document.getElementById('contrasena_actual').value;
    const nuevaContrasena = document.getElementById('nueva_contrasena').value;
    const confirmarContrasena = document.getElementById('confirmar_contrasena').value;
    const username = sessionStorage.getItem('username');
    
    if (!contrasenaActual || !nuevaContrasena || !confirmarContrasena) {
        alert('Todos los campos son obligatorios');
        return;
    }
    
    if (nuevaContrasena !== confirmarContrasena) {
        alert('Las contraseñas nuevas no coinciden');
        return;
    }
    
    const data = {
        username: username,
        contrasena_actual: contrasenaActual,
        nueva_contrasena: nuevaContrasena
    };
    
    fetch('cambiar_contrasena.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Contraseña cambiada correctamente');
            document.getElementById('contrasenaForm').style.display = 'none';
            // Limpiar campos
            document.getElementById('contrasena_actual').value = '';
            document.getElementById('nueva_contrasena').value = '';
            document.getElementById('confirmar_contrasena').value = '';
        } else {
            throw new Error(data.message || 'Error al cambiar la contraseña');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cambiar la contraseña: ' + error.message);
    });
}