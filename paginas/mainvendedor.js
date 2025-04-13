document.getElementById("registroForm").addEventListener("submit", function (event) {
    event.preventDefault();

    let nombre = document.getElementById("nombre").value.trim();
    let apellidoP = document.getElementById("apellidoP").value.trim();
    let apellidoM = document.getElementById("apellidoM").value.trim();
    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();
    let username = document.getElementById("username").value.trim();
    let nacimiento = document.getElementById("nacimiento").value;
    let foto = document.getElementById("foto").files[0];
    let generoSeleccionado = document.querySelector('input[name="genero"]:checked');

    if (!nombre || !apellidoP || !apellidoM || !email || !password || !username || !nacimiento) {
        alert("Rellena todos los campos");
        return;
    }

    if (!generoSeleccionado) {
        alert("Selecciona un género");
        return;
    }

    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert("Ingresa un correo electrónico válido.");
        return;
    }

    if (password.length < 8) {
        alert("La contraseña debe tener al menos 8 caracteres.");
        return;
    }

    let formData = new FormData();
    formData.append("nombre", nombre);
    formData.append("apellidoP", apellidoP);
    formData.append("apellidoM", apellidoM);
    formData.append("email", email);
    formData.append("password", password);
    formData.append("username", username);
    formData.append("nacimiento", nacimiento);
    formData.append("genero", generoSeleccionado.value);

    if (foto) {
        formData.append("foto", foto);
    }


});
