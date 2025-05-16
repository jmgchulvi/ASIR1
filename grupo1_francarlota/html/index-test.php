<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de API de Incidencias</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        pre { background-color: #f4f4f4; padding: 15px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        form { margin-bottom: 30px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9; }
        form h2 { margin-top: 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], textarea, select {
            width: calc(100% - 22px); /* Ajustar ancho considerando padding y borde */
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Incluye padding y borde en el ancho */
        }
         textarea { height: 80px; resize: vertical;}
        button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            margin-right: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result-section { margin-top: 20px; }
        .result-section h2 { border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Prueba de API de Gestión de Incidencias</h1>

    <div class="result-section">
        <h2>Información General</h2>
        <p>Nota: Asegúrate de que tu contenedor MySQL esté corriendo y de haber creado al menos un usuario en la tabla `users` (con un `user_id`) para poder crear y asignar incidencias.</p>
         <p>Puedes insertar un usuario de ejemplo en la base de datos así:</p>
         <pre>
USE fixit; -- Asegúrate de usar el nombre correcto de tu base de datos
INSERT INTO users (name, email) VALUES ('Usuario de Prueba', 'prueba@ejemplo.com');
-- Ejecuta SELECT * FROM users; para ver el user_id generado.
         </pre>
    </div>


    <form id="createIncidentForm">
        <h2>Crear Nueva Incidencia (POST /api/incidents)</h2>
        <label for="create_title">Título:</label>
        <input type="text" id="create_title" name="title" required>

        <label for="create_description">Descripción:</label>
        <textarea id="create_description" name="description" required></textarea>

        <label for="create_category">Categoría:</label>
        <input type="text" id="create_category" name="category" value="General" required>

        <label for="create_affected_asset">Activo Afectado (Opcional):</label>
        <input type="text" id="create_affected_asset" name="affected_asset">

        <label for="create_requestor_id">ID Solicitante (Ver lista de usuarios):</label>
        <input type="number" id="create_requestor_id" name="requestor_id" required min="1">

        <button type="submit">Crear Incidencia</button>
    </form>

    <div class="result-section">
        <h2>Listar Incidencias (GET /api/incidents)</h2>
        <button id="listIncidentsBtn">Listar Todas</button>
        <pre id="incidentsList"></pre>
    </div>

    <div class="result-section">
        <h2>Listar Usuarios (GET /api/users)</h2>
        <button id="listUsersBtn">Listar Usuarios</button>
        <pre id="usersList"></pre>
    </div>

    <div class="result-section">
        <h2>Obtener Incidencia por ID (GET /api/incidents/{id})</h2>
        <label for="getIncidentId">ID de Incidencia:</label>
        <input type="number" id="getIncidentId" min="1">
        <button id="getIncidentBtn">Obtener</button>
        <pre id="incidentDetails"></pre>
    </div>

    <form id="updateIncidentForm">
        <h2>Actualizar Incidencia (PUT /api/incidents/{id})</h2>
        <label for="update_incident_id">ID Incidencia:</label>
        <input type="number" id="update_incident_id" name="incident_id" required min="1">

        <label for="update_title">Título (Opcional):</label>
        <input type="text" id="update_title" name="title">

        <label for="update_description">Descripción (Opcional):</label>
        <textarea id="update_description" name="description"></textarea>

        <label for="update_category">Categoría (Opcional):</label>
        <input type="text" id="update_category" name="category">

         <label for="update_affected_asset">Activo Afectado (Opcional - dejar vacío para NULL):</label>
        <input type="text" id="update_affected_asset" name="affected_asset">


        <label for="update_status">Estado (Opcional):</label>
        <select id="update_status" name="status">
            <option value="">-- No Cambiar --</option>
            <option value="Pendiente">Pendiente</option>
            <option value="En Proceso">En Proceso</option>
            <option value="Solucionado">Solucionado</option>
            <option value="Cerrado">Cerrado</option>
            <option value="Re-abierto">Re-abierto</option>
            <option value="Cancelado">Cancelado</option>
        </select>

        <label for="update_assigned_to_id">ID Asignado (Opcional - dejar vacío para NULL):</label>
        <input type="number" id="update_assigned_to_id" name="assigned_to_id" min="1">

         <label for="update_in_progress_details">Detalles En Proceso (Opcional - dejar vacío para NULL):</label>
         <textarea id="update_in_progress_details" name="in_progress_details"></textarea>

         <label for="update_resolution_comments">Comentarios Solución (Opcional - dejar vacío para NULL):</label>
         <textarea id="update_resolution_comments" name="resolution_comments"></textarea>

        <button type="submit">Actualizar Incidencia</button>
    </form>

    <div class="result-section">
        <h2>Eliminar Incidencia (DELETE /api/incidents/{id})</h2>
        <label for="deleteIncidentId">ID de Incidencia:</label>
        <input type="number" id="deleteIncidentId" min="1">
        <button id="deleteIncidentBtn">Eliminar</button>
        <pre id="deleteResult"></pre>
    </div>


    <script>
        // La URL base de tu API. Ajusta si tu configuración de servidor es diferente.
        const API_URL = '/api';

        // Función genérica para hacer peticiones fetch a la API
        async function fetchData(endpoint, method = 'GET', data = null) {
            const url = `${API_URL}${endpoint}`;
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    // Otros encabezados si son necesarios (ej: Authorization)
                },
            };
            if (data !== null) { // Envía body solo si data no es null
                options.body = JSON.stringify(data);
            }

            console.log(`Workspaceing: ${method} ${url}`, data); // Log de la petición

            try {
                const response = await fetch(url, options);
                // Intenta clonar la respuesta para poder leer el cuerpo dos veces si es necesario
                const responseClone = response.clone();

                try {
                    // Primero intenta parsear como JSON
                    const result = await response.json();

                    if (!response.ok) {
                        console.error('API Error Response:', response.status, result);
                        // Devolver el mensaje de error de la API si está disponible, o un genérico
                        return { error: result.message || result.error || `API Error: ${response.status}` };
                    }

                    console.log('API Success Response:', response.status, result);
                    return result;

                } catch (jsonError) {
                    // Si falla el parseo JSON, lee el cuerpo como texto
                    const text = await responseClone.text();
                    console.error('JSON Parse Error or Non-JSON Response:', jsonError, 'Raw Text:', text);
                    // Si la respuesta no fue exitosa (ej: error 500 con salida de PHP), muestra el texto sin procesar
                    if (!response.ok) {
                         return { error: `Server Error (Non-JSON Response): ${response.status} - ${text.substring(0, 200)}...` }; // Limitar longitud
                    }
                     // Si fue exitosa pero no JSON, devuelve un error de formato
                    return { error: `Invalid API Response (Not JSON): ${text.substring(0, 200)}...` };
                }

            } catch (networkError) {
                console.error('Network or Fetch Setup Error:', networkError);
                return { error: `Network Error: ${networkError.message}` };
            }
        }

        // Función para mostrar resultados en elementos <pre>
        function displayResult(elementId, data) {
            document.getElementById(elementId).textContent = JSON.stringify(data, null, 2);
        }

        function displayError(elementId, error) {
             document.getElementById(elementId).textContent = 'Error: ' + (error.error || error);
             document.getElementById(elementId).style.color = 'red';
        }


        // --- Crear Incidencia ---
        document.getElementById('createIncidentForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const data = {};
            // Convertir FormData a un objeto plano. Asegurar tipos correctos.
            data.title = formData.get('title');
            data.description = formData.get('description');
            data.category = formData.get('category');
            data.affected_asset = formData.get('affected_asset') || null; // Campo opcional, si vacío enviar NULL
            data.requestor_id = parseInt(formData.get('requestor_id')); // Asegurarse de que sea número

             // Validar que requestor_id sea un número válido
             if (isNaN(data.requestor_id) || data.requestor_id <= 0) {
                 alert("Por favor, introduce un ID de solicitante válido (un número positivo).");
                 return;
             }


            const result = await fetchData('/incidents', 'POST', data);

            if (result && result.error) {
                alert('Error al crear: ' + result.error);
                displayError('incidentsList', result); // Mostrar error en alguna parte si quieres
            } else if (result && result.incident_id) {
                alert('Incidencia creada con ID: ' + result.incident_id);
                this.reset(); // Limpiar formulario después de éxito
                 // Opcional: listar incidencias de nuevo para ver la nueva
                 // document.getElementById('listIncidentsBtn').click();
            } else {
                 // Manejar respuesta inesperada
                 alert('Respuesta inesperada al crear incidencia.');
                 displayResult('incidentsList', result); // Mostrar respuesta inesperada
            }
        });

        // --- Listar Incidencias ---
        document.getElementById('listIncidentsBtn').addEventListener('click', async function() {
            document.getElementById('incidentsList').style.color = 'initial'; // Reset color
            const data = await fetchData('/incidents');
             if (data && data.error) {
                 displayError('incidentsList', data);
             } else {
                displayResult('incidentsList', data);
             }
        });

        // --- Listar Usuarios ---
        document.getElementById('listUsersBtn').addEventListener('click', async function() {
             document.getElementById('usersList').style.color = 'initial'; // Reset color
            const data = await fetchData('/users');
             if (data && data.error) {
                 displayError('usersList', data);
             } else {
                 displayResult('usersList', data);
             }
        });


        // --- Obtener Incidencia por ID ---
        document.getElementById('getIncidentBtn').addEventListener('click', async function() {
            document.getElementById('incidentDetails').style.color = 'initial'; // Reset color
            const incidentId = document.getElementById('getIncidentId').value;
            if (!incidentId || parseInt(incidentId) <= 0) {
                alert("Por favor, introduce un ID de incidencia válido (un número positivo).");
                return;
            }
            const data = await fetchData(`/incidents/${incidentId}`);
             if (data && data.error) {
                 displayError('incidentDetails', data);
             } else {
                 displayResult('incidentDetails', data);
             }
        });

        // --- Actualizar Incidencia ---
        document.getElementById('updateIncidentForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const incidentId = document.getElementById('update_incident_id').value;
            if (!incidentId || parseInt(incidentId) <= 0) {
                alert("Por favor, introduce un ID de incidencia válido para actualizar.");
                return;
            }

            const formData = new FormData(this);
            const dataToUpdate = {};
            let hasData = false; // Bandera para saber si hay algún campo para actualizar

            // Recorrer FormData para construir el objeto de datos
            for (const [key, value] of formData.entries()) {
                // Ignorar el ID del formulario de actualización, ya va en la URL
                if (key === 'incident_id') {
                    continue;
                }

                // --- Lógica para campos opcionales que pueden ser NULL ---
                // Si el campo está en esta lista Y su valor es una cadena vacía, lo enviamos como NULL
                const nullableFields = ['affected_asset', 'in_progress_details', 'assigned_to_id', 'resolution_comments'];
                if (nullableFields.includes(key) && value === "") {
                     dataToUpdate[key] = null;
                     hasData = true; // Considerar NULL como dato para actualizar
                     continue; // Pasar al siguiente campo
                }

                // --- Lógica para campos numéricos ---
                if (key === 'assigned_to_id' && value !== "") { // assigned_to_id ya se manejó arriba si es vacío
                     const numValue = parseInt(value);
                     if (isNaN(numValue) || numValue <= 0) {
                         alert(`ID de usuario asignado inválido: "${value}". Debe ser un número positivo o dejar vacío.`);
                         return; // Detener el envío
                     }
                     dataToUpdate[key] = numValue;
                     hasData = true;
                     continue;
                }

                // --- Lógica para el campo de estado (status) ---
                if (key === 'status') {
                    // Si el valor del select NO es la opción "No Cambiar" (valor vacío)
                    if (value !== "") {
                         dataToUpdate[key] = value; // Envía el nombre del estado como string (el backend lo convierte a ID)
                         hasData = true;
                    }
                    continue; // Ya procesado, pasar al siguiente campo
                }

                // --- Lógica para otros campos (title, description, category) ---
                // Si el valor no es una cadena vacía, incluirlo
                if (value !== "") {
                    dataToUpdate[key] = value;
                    hasData = true;
                }

                 // Si value es "" y no está en nullableFields, simplemente se omite del objeto dataToUpdate,
                 // lo cual es correcto, ya que el backend ignorará los campos no enviados.
            }

            // Verificar si realmente hay datos para actualizar
            if (!hasData) {
                alert("No hay datos válidos para actualizar.");
                return;
            }

            console.log("Data to send for update:", dataToUpdate); // Ver los datos finales a enviar

            const result = await fetchData(`/incidents/${incidentId}`, 'PUT', dataToUpdate);
            document.getElementById('incidentDetails').style.color = 'initial'; // Reset color
             if (result && result.error) {
                 alert('Error al actualizar: ' + result.error);
                 displayError('incidentDetails', result); // Mostrar error cerca de detalles
             } else {
                 alert('Incidencia actualizada.');
                 // Opcional: recargar los detalles o la lista después de actualizar con éxito
                 // document.getElementById('getIncidentId').value = incidentId; // Rellenar ID en "Obtener"
                 // document.getElementById('getIncidentBtn').click(); // Cargar detalles actualizados
             }
        });


        // --- Eliminar Incidencia ---
        document.getElementById('deleteIncidentBtn').addEventListener('click', async function() {
             document.getElementById('deleteResult').style.color = 'initial'; // Reset color
            const incidentId = document.getElementById('deleteIncidentId').value;
            if (!incidentId || parseInt(incidentId) <= 0) {
                alert("Por favor, introduce un ID de incidencia válido para eliminar.");
                return;
            }

            if (confirm(`¿Estás seguro de eliminar la incidencia con ID ${incidentId}?`)) {
                 const result = await fetchData(`/incidents/${incidentId}`, 'DELETE');
                 if (result && result.error) {
                      alert('Error al eliminar: ' + result.error);
                     displayError('deleteResult', result);
                 } else {
                      alert('Incidencia eliminada.');
                      displayResult('deleteResult', result); // Mostrar mensaje de éxito
                 }
            }
        });

         // --- Cargar lista de usuarios y incidencias al cargar la página (opcional) ---
         // window.addEventListener('load', () => {
         //      document.getElementById('listUsersBtn').click();
         //      document.getElementById('listIncidentsBtn').click();
         // });


    </script>
</body>
</html>