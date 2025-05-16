// js/users.js
// Lógica específica para la página de gestión de usuarios

document.addEventListener('DOMContentLoaded', () => {
    // Elementos de la interfaz
    const listUsersBtn = document.getElementById('listUsersBtn');
    const usersTableBody = document.querySelector('#users-table tbody'); // Asegúrate de que este selector coincida con tu HTML

    const showCreateUserFormBtn = document.getElementById('showCreateUserFormBtn');
    const cancelCreateUserBtn = document.getElementById('cancelCreateUserBtn');
    const createUserForm = document.getElementById('createUserForm');

    const cancelUpdateUserBtn = document.getElementById('cancelUpdateUserBtn');
    const updateUserForm = document.getElementById('updateUserForm');
    const updateUserIdInput = document.getElementById('update_user_id');


    // Secciones para mostrar/ocultar
    const sections = {
        list: document.getElementById('list-users-section'),
        create: document.getElementById('create-user-section'),
        update: document.getElementById('update-user-section'),
    };

     /**
      * Muestra una sección específica y oculta las otras secciones principales.
      * @param {string} sectionId El ID de la sección a mostrar ('list', 'create', 'update').
      */
     function showSection(sectionId) {
         // Ocultar todas las secciones
         for (const key in sections) {
             if (sections[key]) {
                 sections[key].style.display = 'none';
             }
         }

         // Mostrar la sección solicitada
          const sectionToShow = sections[sectionId];
         if (sectionToShow) {
             sectionToShow.style.display = 'block';
         } else {
              console.error(`Sección con ID "${sectionId}" no encontrada.`);
         }

          // Ocultar mensajes de estado al cambiar de sección
          hideStatusMessage();
     }


    // --- Funciones para cargar y renderizar datos ---

    /**
     * Carga todos los usuarios desde la API y actualiza la tabla.
     */
    async function loadUsers() {
        if (usersTableBody) { // Asegurarse de que el elemento existe
            usersTableBody.innerHTML = '<tr><td colspan="5">Cargando usuarios...</td></tr>'; // Mensaje de carga
        }
        const data = await fetchData('/users', 'GET'); // Usa la función fetchData de api.js

        if (data && data.error) {
             if (usersTableBody) {
                 usersTableBody.innerHTML = `<tr><td colspan="5" style="color:red;">Error al cargar usuarios: ${data.error.message || data.error}</td></tr>`;
             }
        } else if (Array.isArray(data)) {
            renderUsers(data);
        } else {
             if (usersTableBody) {
                 usersTableBody.innerHTML = `<tr><td colspan="5" style="color:orange;">Respuesta de API inesperada.</td></tr>`;
             }
             console.warn("Unexpected API response for /users:", data);
        }
         showSection('list'); // Asegurarse de que la lista esté visible después de cargar
    }

    /**
     * Rellena el cuerpo de la tabla de usuarios con los datos proporcionados.
     * @param {Array<object>} users Lista de objetos usuario.
     */
    function renderUsers(users) {
        if (!usersTableBody) return; // Salir si el elemento no existe

        usersTableBody.innerHTML = ''; // Limpiar tabla

        if (users.length === 0) {
            usersTableBody.innerHTML = '<tr><td colspan="5">No hay usuarios registrados.</td></tr>';
            return;
        }

        users.forEach(user => {
            const row = usersTableBody.insertRow();
             // Agregar data-id para referenciar al usuario
            row.setAttribute('data-id', user.user_id);
            row.innerHTML = `
                <td>${user.user_id}</td>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td>${user.created_at ? new Date(user.created_at + 'Z').toLocaleString() : 'N/A'}</td>
                <td class="actions">
                    <button class="edit-btn btn-warning btn-sm" data-id="${user.user_id}">Editar</button>
                    <button class="delete-btn btn-danger btn-sm" data-id="${user.user_id}">Eliminar</button>
                </td>
            `;
        });
    }


    // --- Manejo de Formularios ---

    // Formulario de Creación de Usuario
    if (createUserForm) { // Verificar que el formulario exista
        createUserForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            const data = {};
            data.name = formData.get('name');
            data.email = formData.get('email');

            // Validaciones básicas en frontend
            if (!data.name || !data.email) {
                 showStatusMessage("Nombre e email son campos requeridos.", 'error');
                 return;
            }
             // Validación básica de formato de email en frontend (JavaScript)
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Expresión regular simple para formato email
             if (!emailRegex.test(data.email)) {
                  showStatusMessage("Formato de email inválido.", 'error');
                  return;
             }


            const result = await fetchData('/users', 'POST', data);

            if (result && result.error) {
                // Error ya mostrado por fetchData
            } else if (result && result.user_id) {
                 // Éxito ya mostrado por fetchData
                 alert('Usuario creado con ID: ' + result.user_id); // Mantener alerta para visibilidad
                this.reset(); // Limpiar formulario
                loadUsers(); // Recargar lista (esto llama a showSection('list'))
            } else {
                 showStatusMessage('Respuesta inesperada al crear usuario.', 'error');
                 console.warn("Unexpected successful API response for POST /users:", result);
            }
        });
    } else {
         console.error("Formulario 'createUserForm' no encontrado.");
    }


    // Formulario de Actualización de Usuario
    if (updateUserForm) { // Verificar que el formulario exista
        updateUserForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const userId = updateUserIdInput.value;
             if (!userId || parseInt(userId) <= 0) {
                 showStatusMessage("ID de usuario para actualizar inválido.", 'error');
                 return;
             }

            const formData = new FormData(this);
            const dataToUpdate = {};
            let hasData = false; // Bandera para saber si hay algo que actualizar

            const name = formData.get('name');
             if (name !== "") { // Solo incluir si no está vacío
                 dataToUpdate.name = name;
                 hasData = true;
             }

            const email = formData.get('email');
            if (email !== "") { // Solo incluir si no está vacío
                 // **************************************************
                 // CORRECCIÓN: Usar validación JavaScript de email
                 // **************************************************
                 const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Expresión regular simple para formato email
                 if (!emailRegex.test(email)) {
                     showStatusMessage("Formato de email inválido.", 'error');
                     return; // Detener el envío si el email no tiene formato válido
                 }
                 // **************************************************
                 dataToUpdate.email = email;
                 hasData = true;
            }

            if (!hasData) {
                showStatusMessage("No hay datos válidos para actualizar.", 'error');
                return;
            }

            console.log(`Enviando PUT para usuario ${userId}:`, dataToUpdate);

            const result = await fetchData(`/users/${userId}`, 'PUT', dataToUpdate);

            if (result && result.error) {
                 // Error ya mostrado
            } else {
                // Éxito ya mostrado
                alert('Usuario actualizado.'); // Alerta para visibilidad
                loadUsers(); // Recargar lista (esto llama a showSection('list'))
                this.reset(); // Limpiar formulario
            }
        });
    } else {
         console.error("Formulario 'updateUserForm' no encontrado.");
    }


    // --- Manejo de Botones en la Tabla ---
    if (usersTableBody) { // Verificar que la tabla exista
        usersTableBody.addEventListener('click', async function(event) {
            const target = event.target;
            const button = target.closest('.edit-btn, .delete-btn'); // Delegación en botones de editar y eliminar

            if (!button) return; // Si no se hizo clic en uno de esos botones, salir

            const userId = button.getAttribute('data-id'); // Obtener el ID del data-id del botón

            if (!userId) {
                console.error("Botón clicado sin data-id");
                return;
            }

            if (button.classList.contains('edit-btn')) {
                // Botón "Editar"
                 showStatusMessage(`Cargando datos de usuario ${userId} para editar...`, 'success');
                 const userData = await fetchData(`/users/${userId}`, 'GET');

                 if (userData && userData.error) {
                     // Error ya mostrado
                 } else if (userData) {
                     // Rellenar el formulario de actualización
                     document.getElementById('update_user_id').value = userData.user_id;
                     document.getElementById('update_user_name').value = userData.name || '';
                     document.getElementById('update_user_email').value = userData.email || '';

                     showSection('update'); // Mostrar la sección del formulario de actualización
                 } else {
                      showStatusMessage('Respuesta inesperada al cargar datos para editar.', 'error');
                      console.warn("Unexpected successful API response for GET /users/{id} for edit:", userData);
                 }


            } else if (button.classList.contains('delete-btn')) {
                // Botón "Eliminar"
                if (confirm(`¿Estás seguro de eliminar al usuario con ID ${userId}? Esta acción puede fallar si el usuario tiene incidencias asociadas.`)) {
                    const result = await fetchData(`/users/${userId}`, 'DELETE');
                    if (result && result.error) {
                         // Error ya mostrado (incluirá error de FK si aplica)
                    } else {
                         // Éxito ya mostrado
                         alert('Usuario eliminado.'); // Alerta para visibilidad
                         loadUsers(); // Recargar lista
                    }
                }
            }
        });
    } else {
         console.error("Elemento 'usersTableBody' no encontrado.");
    }


    // --- Listeners de Botones para mostrar/ocultar secciones ---

    // Botón "Crear Nuevo Usuario"
    if (showCreateUserFormBtn) {
        showCreateUserFormBtn.addEventListener('click', () => {
            if (createUserForm) createUserForm.reset(); // Limpiar formulario
            showSection('create'); // Mostrar sección crear
        });
    } else {
         console.error("Botón 'showCreateUserFormBtn' no encontrado.");
    }


    // Botones "Cancelar" en formularios
    if (cancelCreateUserBtn) {
        cancelCreateUserBtn.addEventListener('click', () => {
            showSection('list'); // Volver a lista
             if (createUserForm) createUserForm.reset(); // Limpiar formulario
        });
    } else {
         console.warn("Botón 'cancelCreateUserBtn' no encontrado."); // No es un error crítico si no existe
    }

     if (cancelUpdateUserBtn) {
        cancelUpdateUserBtn.addEventListener('click', () => {
            showSection('list'); // Volver a lista
             if (updateUserForm) updateUserForm.reset(); // Limpiar formulario
        });
    } else {
        console.warn("Botón 'cancelUpdateUserBtn' no encontrado."); // No es un error crítico
    }

     // Botón "Actualizar Listado" - ya definido en HTML
     if (listUsersBtn) {
          listUsersBtn.addEventListener('click', () => {
              loadUsers(); // Vuelve a cargar la lista
          });
     } else {
          console.error("Botón 'listUsersBtn' no encontrado.");
     }


    // --- Inicialización ---
    // Cargar la lista de usuarios al cargar la página
    loadUsers();
});