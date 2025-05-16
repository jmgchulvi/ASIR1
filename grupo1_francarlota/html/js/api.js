// js/api.js
// Centraliza la función para interactuar con la API

// La URL base de tu API. Ajusta si tu configuración de servidor es diferente.
const API_URL = '/api';

/**
 * Muestra un mensaje de estado en la página usando clases CSS personalizadas.
 * @param {string} message El texto del mensaje.
 * @param {string} type El tipo de mensaje ('success' o 'error').
 */
function showStatusMessage(message, type) {
    const statusElement = document.getElementById('status-message');
    if (statusElement) {
        statusElement.textContent = message;
        // Limpia clases de color/tipo anteriores y añade las nuestras
        statusElement.className = 'section status-message'; // Empieza con base 'section' y nuestra clase base
        if (type === 'success') {
            statusElement.classList.add('success'); // Añade nuestra clase 'success'
        } else if (type === 'error') {
            statusElement.classList.add('error'); // Añade nuestra clase 'error'
        }
        // La lógica display: block/none se maneja en showSection o aquí si #status-message está fuera
        // Si #status-message SIEMPRE está en una sección manejada por showSection,
        // showSection se encargará de mostrarla/ocultarla.
        // Si está fuera, necesitamos asegurarnos de mostrarla explícitamente aquí.
        // Como en los HTML la pusimos DENTRO de .container y antes de .main-layout,
        // que no es gestionado por showSection, debemos mostrarla aquí.
        statusElement.style.display = 'block';
    } else {
        // Fallback simple si el elemento no existe
        alert(`${type.toUpperCase()}: ${message}`);
        console.log(`Status [${type}]: ${message}`);
    }
}

/**
 * Oculta el mensaje de estado.
 * Si #status-message está fuera de las secciones manejadas por showSection,
 * esta función debe ser llamada explícitamente cuando se cambie de vista
 * o se quiera borrar el mensaje.
 */
function hideStatusMessage() {
    const statusElement = document.getElementById('status-message');
    if (statusElement) {
        statusElement.style.display = 'none';
    }
}


/**
 * Función genérica para hacer peticiones fetch a la API.
 * ... (el resto de la función fetchData queda igual) ...
 */
async function fetchData(endpoint, method = 'GET', data = null) {
    const url = `${API_URL}${endpoint}`;
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            // Otros encabezados si son necesarios (ej: Authorization)
        },
    };

    if (data !== null && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        options.body = JSON.stringify(data);
    }

    console.log(`Workspaceing: ${method} ${url}`, data);

    try {
        const response = await fetch(url, options);
        const responseClone = response.clone();

        try {
            const result = await response.json();

            if (!response.ok) {
                console.error('API Error Response:', response.status, result);
                showStatusMessage(`Error ${response.status}: ${result.message || result.error || 'Error desconocido'}`, 'error');
                return { error: result.message || result.error || `API Error: ${response.status}`, statusCode: response.status };
            }

            console.log('API Success Response:', response.status, result);
            if (method !== 'GET') {
                showStatusMessage(`Operación ${method} exitosa!`, 'success');
            } else {
                // Para GET, normalmente solo mostramos los datos
                hideStatusMessage(); // Asegurar que se ocultan mensajes anteriores
            }
            return result;

        } catch (jsonError) {
            const text = await responseClone.text();
            console.error('JSON Parse Error or Non-JSON Response:', jsonError, 'Raw Text:', text);

            const errorMessage = `Server Error (Non-JSON Response): ${response.status} - ${text.substring(0, 200)}...`;
            showStatusMessage(errorMessage, 'error');
            return { error: errorMessage, statusCode: response.status };
        }

    } catch (networkError) {
        console.error('Network or Fetch Setup Error:', networkError);
        const errorMessage = `Network Error: ${networkError.message}. Asegúrate de que el backend esté corriendo.`;
        showStatusMessage(errorMessage, 'error');
        return { error: errorMessage, statusCode: 0 };
    }
}