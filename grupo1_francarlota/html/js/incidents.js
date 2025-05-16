// js/incidents.js
// Lógica específica para la página de gestión de incidencias

document.addEventListener('DOMContentLoaded', () => {
    // Elementos de la interfaz
    const listIncidentsBtn = document.getElementById('listIncidentsBtn');
    const incidentsTableBody = document.querySelector('#incidents-table tbody');

    // Botón para mostrar el formulario de creación
    const showCreateIncidentFormBtn = document.getElementById('showCreateIncidentFormBtn');

    const createIncidentForm = document.getElementById('createIncidentForm');
    // Referencia al SELECT de solicitante en el formulario de creación
    const createRequestorSelect = document.getElementById('create_requestor_select');

    const updateIncidentForm = document.getElementById('updateIncidentForm');
    const updateIncidentIdInput = document.getElementById('update_incident_id');
    // Referencia al SELECT de asignado en el formulario de actualización
    const updateAssignedToSelect = document.getElementById('update_assigned_to_select');

    const incidentDetailsSection = document.getElementById('incident-details-section');
    const incidentDetailsContent = document.getElementById('incidentDetailsContent');
    const hideDetailsBtn = document.getElementById('hideDetailsBtn');

    // *********************************************************
    // ELEMENTO Y ESTADO DEL FILTRO
    // *********************************************************
    const toggleFilterBtn = document.getElementById('toggleFilterBtn');
    let currentFilter = 'unsolved'; // Estado inicial: mostrar no solucionadas

    // Secciones principales para mostrar/ocultar
    const sections = {
        list: document.getElementById('list-incidents-section'),
        details: incidentDetailsSection,
        create: document.getElementById('create-incident-section'),
        update: document.getElementById('update-incident-section'),
        // delete: document.getElementById('delete-incident-section'),
    };

    /**
     * Muestra una sección específica y oculta las otras secciones principales.
     * @param {string} sectionId El ID de la sección a mostrar ('list', 'details', 'create', 'update').
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

        // Lógica adicional para mostrar/ocultar botón "Ocultar Detalles"
        if (sectionId === 'details') {
            hideDetailsBtn.style.display = 'inline-block';
        } else {
            hideDetailsBtn.style.display = 'none';
        }

        // Ocultar mensajes de estado al cambiar de sección
        hideStatusMessage();
    }


    // --- Funciones para cargar y renderizar datos de Incidencias ---

    /**
     * Carga las incidencias desde la API según el filtro actual.
     */
    async function loadIncidents() { // Ya no necesita argumento, usa currentFilter
        incidentsTableBody.innerHTML = '<tr><td colspan="7">Cargando incidencias...</td></tr>'; // Mensaje de carga
        // Construye la URL con el parámetro de filtro si no es 'unsolved' (el default del backend)
        const endpoint = (currentFilter === 'all') ? '/incidents?filter=all' : '/incidents';
        const data = await fetchData(endpoint, 'GET'); // Usa la función fetchData de api.js

        if (data && data.error) {
            incidentsTableBody.innerHTML = `<tr><td colspan="7" style="color:red;">Error al cargar incidencias: ${data.error.message || data.error}</td></tr>`;
        } else if (Array.isArray(data)) {
            renderIncidents(data);
        } else {
            incidentsTableBody.innerHTML = `<tr><td colspan="7" style="color:orange;">Respuesta de API inesperada al cargar incidencias.</td></tr>`;
            console.warn("Unexpected API response for /incidents:", endpoint, data);
        }
        showSection('list'); // Después de cargar las incidencias, siempre volvemos a mostrar la lista
    }

    /**
     * Rellena el cuerpo de la tabla de incidencias con los datos proporcionados.
     * @param {Array<object>} incidents Lista de objetos incidencia.
     */
    function renderIncidents(incidents) {
        incidentsTableBody.innerHTML = ''; // Limpiar contenido actual de la tabla

        if (incidents.length === 0) {
            incidentsTableBody.innerHTML = '<tr><td colspan="7">No hay incidencias registradas.</td></tr>';
            return;
        }

        // Crear una fila de tabla (<tr>) por cada incidencia
        incidents.forEach(incident => {
            const row = incidentsTableBody.insertRow();
            row.setAttribute('data-id', incident.incident_id); // Almacenar el ID en la fila para fácil acceso
            row.innerHTML = `
                <td>${incident.incident_id}</td>
                <td>${incident.title}</td>
                <td>${incident.status_name || 'N/A'}</td>
                <td>${incident.requestor_name || 'N/A'}</td>
                <td>${incident.assigned_to_name || 'Sin asignar'}</td>
                <td>${incident.reported_at ? new Date(incident.reported_at + 'Z').toLocaleString() : 'N/A'}</td>
                <td class="actions-container">
                    <button class="view-btn btn-secondary btn-sm" data-id="${incident.incident_id}">Ver</button>
                    <button class="edit-btn btn-warning btn-sm" data-id="${incident.incident_id}">Editar</button>
                    <button class="delete-btn btn-danger btn-sm" data-id="${incident.incident_id}">Eliminar</button>
                </td>
            `;
        });
    }

    /**
     * Carga y muestra los detalles de una incidencia específica.
     * @param {number|string} id ID de la incidencia a ver.
     */
    async function viewIncident(id) {
        incidentDetailsContent.textContent = 'Cargando detalles...';
        incidentDetailsContent.style.color = 'initial';

        const data = await fetchData(`/incidents/${id}`, 'GET');

        if (data && data.error) {
            incidentDetailsContent.textContent = `Error al cargar detalles: ${data.error.message || data.error}`;
            incidentDetailsContent.style.color = 'red';
        } else {
            incidentDetailsContent.textContent = JSON.stringify(data, null, 2);
            incidentDetailsContent.style.color = 'initial';
        }
        showSection('details');
    }


    // --- Funciones para cargar y poblar los Selects de Usuarios ---

    /**
     * Carga la lista de usuarios desde la API y puebla los selects de solicitantes y asignados.
     */
    async function loadUsersForSelects() {
        // Deshabilitar temporalmente y añadir opción de carga a AMBOS selects si existen
        if (createRequestorSelect) {
            createRequestorSelect.disabled = true;
            createRequestorSelect.innerHTML = '<option value="">Cargando usuarios...</option>';
        }
        if (updateAssignedToSelect) {
            updateAssignedToSelect.disabled = true;
            updateAssignedToSelect.innerHTML = '<option value="">Cargando usuarios...</option>';
        }


        const data = await fetchData('/users', 'GET'); // Reutiliza fetchData

        if (data && data.error) {
            // Mostrar error en ambos selects si existen
            if (createRequestorSelect) createRequestorSelect.innerHTML = '<option value="">Error al cargar</option>';
            if (updateAssignedToSelect) updateAssignedToSelect.innerHTML = '<option value="">Error al cargar</option>';
            console.error('Error loading users for selects:', data.error);
        } else if (Array.isArray(data)) {
            // Poblar ambos selects si existen
            if (createRequestorSelect) populateUserSelect(createRequestorSelect, data, "-- Seleccionar Solicitante --");
            if (updateAssignedToSelect) populateUserSelect(updateAssignedToSelect, data, "-- Sin asignar --"); // Texto diferente para la opción NULL
        } else {
            // Manejar respuesta inesperada para ambos selects si existen
            if (createRequestorSelect) createRequestorSelect.innerHTML = '<option value="">Error de datos</option>';
            if (updateAssignedToSelect) updateAssignedToSelect.innerHTML = '<option value="">Error de datos</option>';
            console.warn("Unexpected API response for /users during select load:", data);
        }

        // Habilitar ambos selects al finalizar
        if (createRequestorSelect) createRequestorSelect.disabled = false;
        if (updateAssignedToSelect) updateAssignedToSelect.disabled = false;
    }

    /**
     * Puebla un elemento select dado con una lista de usuarios.
     * @param {HTMLSelectElement} selectElement El elemento <select> a poblar.
     * @param {Array<object>} users Lista de objetos usuario { user_id, name, ... }.
     * @param {string} nullOptionText Texto para la opción que representa NULL (ej: "-- Sin asignar --").
     */
    function populateUserSelect(selectElement, users, nullOptionText) {
        if (!selectElement) return; // Salir si el elemento no es válido

        selectElement.innerHTML = ''; // Limpiar opciones existentes

        // Añadir la opción que representa NULL/vacío
        const nullOption = document.createElement('option');
        nullOption.value = ""; // Valor vacío para mapear a NULL en el backend si se envía así
        nullOption.textContent = nullOptionText;
        selectElement.appendChild(nullOption);

        // Añadir una opción por cada usuario
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.user_id; // El valor enviado será el ID del usuario
            option.textContent = `${user.name} (ID: ${user.user_id})`; // El texto visible en el desplegable
            selectElement.appendChild(option);
        });
    }


    // --- Manejo de Formularios ---

    // Listener para el envío del formulario de Creación de Incidencia
    createIncidentForm.addEventListener('submit', async function(event) {
        event.preventDefault(); // Detener el envío tradicional del formulario

        // Recoger datos del formulario usando FormData
        const formData = new FormData(this);
        const data = {};
        data.title = formData.get('title');
        data.description = formData.get('description');
        data.category = formData.get('category');
        // Si affected_asset está vacío, enviar null; de lo contrario, enviar el valor
        data.affected_asset = formData.get('affected_asset') || null;

        // Obtener valor del SELECT de Solicitantes y validar
        const requestorId = parseInt(formData.get('requestor_id'));

        if (isNaN(requestorId) || requestorId <= 0) {
            showStatusMessage("Por favor, selecciona un solicitante válido de la lista.", 'error');
            return;
        }
        data.requestor_id = requestorId;

        const result = await fetchData('/incidents', 'POST', data);

        if (result && result.error) {
            // El error ya se muestra en showStatusMessage dentro de fetchData
            // alert('Error al crear: ' + result.error.message); // Evitar doble alerta/mensaje
        } else if (result && result.incident_id) {
            // El mensaje de éxito ya se muestra en showStatusMessage
            alert('Incidencia creada con ID: ' + result.incident_id); // Mantener alerta para visibilidad
            this.reset(); // Limpiar formulario después de éxito
            loadIncidents(); // Recargar la lista para ver la nueva incidencia (esto llama a showSection('list'))
        } else {
            showStatusMessage('Respuesta inesperada al crear incidencia.', 'error');
            console.warn("Unexpected successful API response for POST /incidents:", result);
        }
    });

    // Listener para el botón Reset/Cancelar en formulario de creación
    createIncidentForm.addEventListener('reset', function(event) {
        showSection('list'); // Volver a la lista al cancelar
    });


    // Listener para el envío del formulario de Actualización
    updateIncidentForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        const incidentId = updateIncidentIdInput.value;
        // Validar ID de la URL
        if (!incidentId || parseInt(incidentId) <= 0) {
            showStatusMessage("ID de incidencia para actualizar inválido.", 'error');
            return;
        }

        const formData = new FormData(this);
        const dataToUpdate = {}; // Objeto para enviar solo los campos que tienen valor

        // Recorrer FormData para construir el objeto de datos a enviar
        for (const [key, value] of formData.entries()) {
            // Ignorar el ID del formulario de actualización, ya va en la URL
            if (key === 'incident_id') {
                continue;
            }

            // *********************************************************
            // MANEJO DEL VALOR DEL SELECT DE ASIGNADO (assigned_to_id)
            // *********************************************************
            if (key === 'assigned_to_id') {
                if (value === "") {
                    // Si se seleccionó la opción "Sin asignar" (valor vacío)
                    dataToUpdate[key] = null; // Enviar explícitamente NULL
                    console.log("Sending assigned_to_id as NULL");
                } else {
                    // Si se seleccionó un usuario (valor es un ID)
                    const numValue = parseInt(value);
                    if (isNaN(numValue) || numValue <= 0) {
                        showStatusMessage(`ID de usuario asignado inválido seleccionado: "${value}".`, 'error');
                        return; // Detener el envío
                    }
                    dataToUpdate[key] = numValue;
                    console.log(`Sending assigned_to_id as ${numValue}`);
                }
                continue; // Ya procesado, pasar al siguiente campo
            }


            // --- Lógica para otros campos opcionales que pueden ser NULL (pero no assigned_to_id ya manejado) ---
            const otherNullableFields = ['affected_asset', 'in_progress_details', 'resolution_comments']; // Excluimos assigned_to_id aquí
            if (otherNullableFields.includes(key) && value === "") {
                dataToUpdate[key] = null;
                continue;
            }

            // --- Lógica para el campo de estado (status) ---
            if (key === 'status') {
                if (value !== "") { // No enviar si se seleccionó "-- No Cambiar --"
                    dataToUpdate[key] = value; // Envía el nombre del estado
                }
                continue;
            }

            // --- Lógica para otros campos (title, description, category) ---
            if (value !== "") { // No enviar si el campo de texto está vacío
                dataToUpdate[key] = value;
            }
            // Si value es "" y no está en otherNullableFields O si es 'status' con value="", simplemente se omite del objeto dataToUpdate
        }

        // Verificar si realmente hay datos para actualizar
        const hasData = Object.keys(dataToUpdate).length > 0;

        if (!hasData) {
            showStatusMessage("No hay datos válidos para actualizar.", 'error');
            return;
        }

        console.log(`Enviando PUT para incidencia ${incidentId}:`, dataToUpdate);

        const result = await fetchData(`/incidents/${incidentId}`, 'PUT', dataToUpdate);

        if (result && result.error) {
            // Error ya mostrado
        } else {
            alert('Incidencia actualizada.');
            loadIncidents(); // Recargar lista
            this.reset(); // Limpiar formulario
        }
    });

    // Listener para el botón Reset/Cancelar en formulario de actualización
    updateIncidentForm.addEventListener('reset', function(event) {
        showSection('list'); // Volver a la lista
        hideStatusMessage(); // Ocultar mensajes
    });


    // --- Manejo de Botones de Acción en la Tabla (Delegación de Eventos) ---
    incidentsTableBody.addEventListener('click', async function(event) {
        const target = event.target; // El elemento exacto donde se hizo clic
        // Buscamos el botón más cercano que sea 'view-btn', 'edit-btn', o 'delete-btn'
        const button = target.closest('.view-btn, .edit-btn, .delete-btn');

        if (!button) return; // Si no se hizo clic en uno de esos botones, salir

        const incidentId = button.getAttribute('data-id'); // Obtener el ID del data-id del botón

        if (!incidentId) {
            console.error("Botón clicado sin data-id");
            return;
        }

        // Lógica basada en la clase del botón clicado
        if (button.classList.contains('view-btn')) {
            viewIncident(incidentId);
        } else if (button.classList.contains('edit-btn')) {
            showStatusMessage(`Cargando datos de incidencia ${incidentId} para editar...`, 'success');
            const incidentData = await fetchData(`/incidents/${incidentId}`, 'GET');

            if (incidentData && incidentData.error) {
                // El error ya se muestra
            } else if (incidentData) {
                // Rellenar el formulario de actualización
                document.getElementById('update_incident_id').value = incidentData.incident_id;
                document.getElementById('update_title').value = incidentData.title || '';
                document.getElementById('update_description').value = incidentData.description || '';
                document.getElementById('update_category').value = incidentData.category || '';
                document.getElementById('update_affected_asset').value = incidentData.affected_asset || '';
                document.getElementById('update_in_progress_details').value = incidentData.in_progress_details || '';
                document.getElementById('update_resolution_comments').value = incidentData.resolution_comments || '';


                // Seleccionar el estado correcto en el select de estado
                const statusSelect = document.getElementById('update_status');
                let statusFound = false;
                for (const option of statusSelect.options) {
                    if (option.value === incidentData.status_name) {
                        option.selected = true;
                        statusFound = true;
                        break;
                    }
                }
                if (!statusFound) {
                    statusSelect.value = ""; // Seleccionar "No Cambiar" o la primera
                }

                // *********************************************************
                // SELECCIONAR EL USUARIO ASIGNADO CORRECTO EN EL SELECT
                // *********************************************************
                const assignedToSelect = document.getElementById('update_assigned_to_select');
                if (assignedToSelect) {
                    let assignedUserFound = false;
                    // Si assigned_to_id es null, seleccionamos la opción "Sin asignar" (value="")
                    if (incidentData.assigned_to_id === null || incidentData.assigned_to_id === undefined) { // También manejar undefined por si acaso
                        assignedToSelect.value = "";
                        assignedUserFound = true; // Marcar como encontrado (la opción null)
                    } else {
                        // Si hay un ID, buscar la opción correspondiente
                        for (const option of assignedToSelect.options) {
                            // Comparamos el valor del option (string) con el ID del usuario (number)
                            // Es seguro convertir ambos a string para la comparación
                            if (String(option.value) === String(incidentData.assigned_to_id)) {
                                option.selected = true;
                                assignedUserFound = true;
                                break;
                            }
                        }
                    }

                    // Si el usuario asignado no se encontró en las opciones (ej: usuario eliminado de la BD)
                    // Para simplificar, si no se encuentra, seleccionamos "Sin asignar".
                    if (!assignedUserFound) {
                        assignedToSelect.value = ""; // Vuelve a seleccionar la opción NULL
                        console.warn(`Usuario asignado con ID ${incidentData.assigned_to_id} no encontrado en la lista actual. Seleccionando "Sin asignar".`);
                    }

                    // Asegurarse de que el select esté habilitado (si loadUsersForSelects lo deshabilita al cargar)
                    assignedToSelect.disabled = false;

                } else {
                    console.error("Elemento select 'update_assigned_to_select' no encontrado para rellenar.");
                }


                showSection('update'); // Mostrar la sección del formulario de actualización
            } else {
                showStatusMessage('Respuesta inesperada al cargar datos para editar.', 'error');
                console.warn("Unexpected successful API response for GET /incidents/{id} for edit:", incidentData);
            }

        } else if (button.classList.contains('delete-btn')) {
            // Botón "Eliminar"
            if (confirm(`¿Estás seguro de eliminar la incidencia con ID ${incidentId}?`)) {
                const result = await fetchData(`/incidents/${incidentId}`, 'DELETE');
                if (result && result.error) {
                    // El error ya se muestra
                } else {
                    alert('Incidencia eliminada.');
                    loadIncidents(); // Recargar lista
                }
            }
        }
    });

    // Listener para el botón "Ocultar Detalles"
    hideDetailsBtn.addEventListener('click', function() {
        showSection('list'); // Volver a la lista
        incidentDetailsContent.textContent = 'Selecciona una incidencia para ver sus detalles.';
        incidentDetailsContent.style.color = 'initial';
    });

    // Listener para el botón "Crear Nueva Incidencia" (para mostrar el formulario)
    if (showCreateIncidentFormBtn) {
        showCreateIncidentFormBtn.addEventListener('click', () => {
            createIncidentForm.reset(); // Limpiar formulario antes de mostrar
            showSection('create'); // Mostrar la sección del formulario de creación
            // Asegurarse de que el select de solicitantes esté habilitado y con la opción por defecto seleccionada
            if (createRequestorSelect) {
                createRequestorSelect.disabled = false;
                createRequestorSelect.value = ""; // Asegurar que la opción "-- Seleccionar" está seleccionada
                // loadUsersForSelects ya se llama al inicio, así que las opciones ya deberían estar ahí
            } else {
                console.error("Elemento select 'create_requestor_select' no encontrado.");
            }
        });
    } else {
        console.error("Elemento 'showCreateIncidentFormBtn' no encontrado en el HTML.");
    }

    // *********************************************************
    // LISTENER PARA EL BOTÓN DE TOGGLE DE FILTRO
    // *********************************************************
    if (toggleFilterBtn) {
        toggleFilterBtn.addEventListener('click', () => {
            // Cambia el estado del filtro
            currentFilter = (currentFilter === 'unsolved') ? 'all' : 'unsolved';

            // Cambia el texto del botón
            toggleFilterBtn.textContent = (currentFilter === 'all') ? 'Mostrar No Solucionadas' : 'Mostrar Todas';
            // Opcional: cambiar color/clase del botón si tienes estilos para ello (ej: btn-info para "Todas")
            // toggleFilterBtn.classList.toggle('btn-secondary');
            // toggleFilterBtn.classList.toggle('btn-info');

            // Recarga la lista de incidencias con el nuevo filtro
            loadIncidents(); // Llama a la función que usa currentFilter
        });
    } else {
        console.error("Elemento botón 'toggleFilterBtn' no encontrado en el HTML.");
    }


    // --- Inicialización al cargar la página ---
    // Cargar las incidencias (por defecto no solucionadas) Y los usuarios para los selects
    loadIncidents(); // Carga incidencias y muestra la lista (llama a showSection('list'))
    loadUsersForSelects(); // Carga usuarios para los selects de solicitante (creación) y asignado (actualización)

});