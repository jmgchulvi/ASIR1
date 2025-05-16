<?php

// Asegúrate de que Database.php y el archivo con sendResponse/sendError estén incluidos
require_once __DIR__ . '/../Database.php';
// Si sendResponse/sendError están en otro archivo (ej: helpers.php), inclúyelo:
// require_once __DIR__ . '/../helpers.php';


class IncidentController {
    private $db;
    private $conn;
    private $statusMap = []; // Cache para mapear nombres de estado a IDs

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    // --- Helper para obtener el ID de un estado ---
    /**
     * Obtiene el ID de un estado dado su nombre.
     * Usa un cache interno para evitar múltiples consultas para el mismo estado.
     *
     * @param string $statusName Nombre del estado (ej: 'Pendiente')
     * @return int|null El status_id o null si no se encuentra el estado.
     */
    private function getStatusIdByName($statusName) {
        if (isset($this->statusMap[$statusName])) {
            return $this->statusMap[$statusName];
        }

        $sql = "SELECT status_id FROM incident_statuses WHERE status_name = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             // Loggear error si la preparación falla (no enviar directo al usuario en prod)
             error_log("Error preparando consulta getStatusIdByName: " . $this->conn->error);
             return null;
        }

        $stmt->bind_param("s", $statusName);

        if (!$stmt->execute()) {
             // Loggear error si la ejecución falla
             error_log("Error ejecutando consulta getStatusIdByName: " . $stmt->error);
             return null;
        }

        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $this->statusMap[$statusName] = $row['status_id']; // Cachear resultado
            return $row['status_id'];
        }

        return null; // Estado no encontrado
    }

    // --- Método GET (Lista o Filtrada) ---
    /**
     * Obtener todas las incidencias.
     * Permite filtrar por incidencias 'no solucionadas' (por defecto) o 'all'.
     *
     * @param string|null $filter 'all' para mostrar todas, cualquier otro valor o null para no solucionadas/cerradas.
     */
    public function getAll($filter = null) {
        // Join con users y incident_statuses para obtener nombres y nombres de estado
        $sql = "SELECT
                    i.incident_id,
                    i.title,
                    i.description,
                    i.category,
                    i.affected_asset,
                    i.requestor_id,
                    i.reported_at,
                    i.status_id,
                    s.status_name,
                    i.in_progress_details,
                    i.assigned_to_id,
                    i.assigned_at,
                    i.resolved_at,
                    i.resolution_comments,
                    r.name AS requestor_name,
                    a.name AS assigned_to_name
                FROM
                    incidents i
                JOIN
                    users r ON i.requestor_id = r.user_id
                JOIN
                    incident_statuses s ON i.status_id = s.status_id
                LEFT JOIN
                    users a ON i.assigned_to_id = a.user_id";

        $where_clauses = [];
        $params = []; // Array para parámetros de prepared statement
        $param_types = ""; // String para tipos de parámetros ('i', 's', etc.)

        // Status IDs para 'Solucionado' (3) y 'Cerrado' (4).
        // **IMPORTANTE:** Verifica que estos IDs coincidan con tu tabla incident_statuses.
        $solved_status_id = 3;
        $closed_status_id = 4;

        // Aplicar filtro a menos que se pida explícitamente 'all'
        if ($filter !== 'all') {
            // Mostrar incidencias donde el status_id NO esté en (Solucionado, Cerrado)
            $where_clauses[] = "i.status_id NOT IN (?, ?)";
            $params[] = $solved_status_id; // Añadir ID 3 a parámetros
            $params[] = $closed_status_id; // Añadir ID 4 a parámetros
            $param_types .= "ii"; // Son dos parámetros enteros ('i' 'i')
        }

        // Si hay cláusulas WHERE, añadirlas a la consulta SQL
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses); // Combinar múltiples cláusulas si las hubiera
        }

        // Ordenar por fecha de reporte descendente por defecto
        $sql .= " ORDER BY i.reported_at DESC";

        // --- Ejecutar la consulta ---
        $result = null;
        if (empty($params)) {
             // No hay parámetros para bind_param, ejecutar consulta directa
            $result = $this->conn->query($sql);
        } else {
            // Usar prepared statement si hay parámetros (para el filtro)
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                sendError("Error preparando la consulta de filtro: " . $this->conn->error, 500);
                return;
            }

             // Bind de parámetros dinámico usando call_user_func_array
             // Requiere que los parámetros se pasen por referencia.
             // NOTA: Este enfoque es más complejo que bind_param directo si solo hay 1-2 params.
             // Para 2 parámetros fijos como aquí, $stmt->bind_param($param_types, $params[0], $params[1]); sería más simple.
             // Mantenemos call_user_func_array por si se añaden más filtros dinámicos en el futuro.
             $bind_args = array();
             $bind_args[] = $param_types; // El primer argumento es el string de tipos
             foreach ($params as $key => $value) {
                 $bind_args[] = &$params[$key]; // Los argumentos siguientes son las variables por referencia
             }
             // error_log("DEBUG: bind_args for getAll: " . print_r($bind_args, true)); // Debugging
             call_user_func_array(array($stmt, 'bind_param'), $bind_args);


            if (!$stmt->execute()) {
                 sendError("Error ejecutando la consulta de filtro: " . $stmt->error, 500);
                 return;
            }
            $result = $stmt->get_result(); // Obtener el resultado del prepared statement
        }


        $incidents = [];
        // Verificar si la consulta fue exitosa antes de procesar resultados
        if ($result) {
             if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Añadir cada fila al array de incidencias
                    $incidents[] = $row;
                }
            }
            sendResponse($incidents); // Enviar respuesta JSON con el array de incidencias (puede estar vacío)
        } else {
             // Manejar error en la ejecución de la consulta (query() o get_result())
             sendError("Error en la consulta de base de datos: " . ($this->conn->error ? $this->conn->error : (isset($stmt) ? $stmt->error : 'Error de consulta desconocido')), 500);
        }
    }

    // --- Método GET (Uno por ID) ---
    /**
     * Obtiene los detalles de una incidencia por su ID.
     *
     * @param int|string $id El ID de la incidencia.
     */
    public function getOne($id) {
         // Validar que el ID es un número positivo
         if (!is_numeric($id) || $id <= 0) {
             sendError("ID de incidencia inválido", 400);
             return;
         }

        // Consulta para obtener una incidencia específica, uniendo con users y status
        $sql = "SELECT
                    i.incident_id,
                    i.title,
                    i.description,
                    i.category,
                    i.affected_asset,
                    i.requestor_id,
                    i.reported_at,
                    i.status_id,
                    s.status_name,
                    i.in_progress_details,
                    i.assigned_to_id,
                    i.assigned_at,
                    i.resolved_at,
                    i.resolution_comments,
                    r.name AS requestor_name,
                    a.name AS assigned_to_name
                FROM
                    incidents i
                JOIN
                    users r ON i.requestor_id = r.user_id
                JOIN
                    incident_statuses s ON i.status_id = s.status_id
                LEFT JOIN
                    users a ON i.assigned_to_id = a.user_id
                WHERE i.incident_id = ?"; // Filtrar por ID

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             sendError("Error preparando la consulta: " . $this->conn->error, 500);
             return;
        }

        $stmt->bind_param("i", $id); // Bind del ID como entero

        if (!$stmt->execute()) {
             sendError("Error ejecutando la consulta: " . $stmt->error, 500);
             return;
        }

        $result = $stmt->get_result(); // Obtener el resultado

        if ($result->num_rows > 0) {
            // Incidencia encontrada, enviar los datos de la primera fila
            $incident = $result->fetch_assoc();
            sendResponse($incident);
        } else {
            // Incidencia no encontrada
            sendError("Incidencia no encontrada", 404);
        }
    }

    // --- Método POST (Crear) ---
    /**
     * Crea una nueva incidencia a partir de los datos recibidos por POST.
     */
    public function create() {
        // Leer el cuerpo de la petición HTTP (debe ser JSON)
        $data = json_decode(file_get_contents("php://input"), true);

        // Validar datos requeridos
        if (!isset($data['title'], $data['description'], $data['category'], $data['requestor_id'])) {
            sendError("Datos incompletos: faltan title, description, category o requestor_id", 400);
            return;
        }

         // Validar que requestor_id es un número positivo
         if (!is_numeric($data['requestor_id']) || $data['requestor_id'] <= 0) {
              sendError("ID de solicitante inválido", 400);
              return;
         }


        $title = $this->conn->real_escape_string($data['title']); // Escapar para seguridad
        $description = $this->conn->real_escape_string($data['description']);
        $category = $this->conn->real_escape_string($data['category']);
        $requestor_id = (int)$data['requestor_id'];
        // affected_asset es opcional, puede ser null o string
        $affected_asset = isset($data['affected_asset']) ? ($data['affected_asset'] === null ? null : $this->conn->real_escape_string($data['affected_asset'])) : null;

        // El estado inicial es 'Pendiente' (asumimos status_id = 1, verifica tu BD)
        $initial_status_id = 1; // ID para el estado 'Pendiente'

        // Usar prepared statement para la inserción
        $sql = "INSERT INTO incidents (title, description, category, affected_asset, requestor_id, status_id, reported_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())"; // NOW() para la fecha actual en BD

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             sendError("Error preparando la consulta de creación: " . $this->conn->error, 500);
             return;
        }

        // Bind de parámetros: s=string, i=integer. affected_asset puede ser null, necesita 's' si no es null.
        // Para bind_param, null se puede pasar como valor para tipos string.
        $stmt->bind_param("ssssii", $title, $description, $category, $affected_asset, $requestor_id, $initial_status_id);


        if ($stmt->execute()) {
            // Inserción exitosa, obtener el ID recién creado
            $new_incident_id = $this->conn->insert_id;
            sendResponse(["message" => "Incidencia creada con éxito", "incident_id" => $new_incident_id], 201);
        } else {
             // Error en la ejecución (ej: requestor_id no existe en tabla users)
             // El error SQL en $stmt->error dará más detalles
             if ($this->conn->errno == 1452) { // Código de error para FK constraint fails (aprox)
                  sendError("Error al crear la incidencia: El solicitante (requestor_id) no existe.", 409); // Conflict
             } else {
                  sendError("Error al crear la incidencia", 500, $stmt->error); // Incluir error de BD
             }
        }
    }

    // --- Método PUT (Actualizar) ---
    /**
     * Actualiza una incidencia existente por su ID.
     *
     * @param int|string $id El ID de la incidencia a actualizar.
     */
    public function update($id) {
        // Validar que el ID es un número positivo
        if (!is_numeric($id) || $id <= 0) {
            sendError("ID de incidencia inválido", 400);
            return;
        }

        // Leer el cuerpo de la petición HTTP
        $data = json_decode(file_get_contents("php://input"), true);

        // Verificar si hay datos para actualizar
        if (empty($data)) {
            sendError("No hay datos para actualizar", 400);
            return;
        }

        // Construir la consulta UPDATE dinámicamente
        $set_clauses = [];
        $params = [];
        $param_types = "";
        $update_status_id = null; // Para guardar el nuevo ID de estado si se actualiza el estado
        $update_assigned_to_id = null; // Para guardar el nuevo ID de asignado si se actualiza el asignado
        $update_assigned_at = false; // Bandera para saber si assigned_at debe actualizarse
        $update_resolved_at = false; // Bandera para saber si resolved_at debe actualizarse


        // Iterar sobre los datos recibidos para construir la consulta
        foreach ($data as $key => $value) {
            // Validar que la clave es un campo actualizable (lista blanca)
            $updatable_fields = ['title', 'description', 'category', 'affected_asset', 'status', 'in_progress_details', 'assigned_to_id', 'resolution_comments'];
            if (!in_array($key, $updatable_fields)) {
                // Ignorar campos no actualizables o enviar error 400 si se desea ser estricto
                // sendError("Campo no actualizable: " . $key, 400); return;
                continue;
            }

            // --- Manejo especial para el campo 'status' (nombre del estado) ---
            if ($key === 'status') {
                // Si el valor no es null/vacío, intentar obtener el ID del estado
                if ($value !== null && $value !== "") {
                    $status_id = $this->getStatusIdByName($value);
                    if ($status_id === null) {
                        sendError("Error al actualizar la incidencia: El estado '" . $value . "' no es válido.", 409); // Conflict si estado no existe
                        return;
                    }
                    // Si el estado es 'Solucionado' o 'Cerrado', marcamos para actualizar resolved_at
                    // NOTA: Asumiendo 3=Solucionado, 4=Cerrado. Ajustar IDs si es necesario.
                    if ($status_id == 3 || $status_id == 4) {
                         $update_resolved_at = true;
                    } else {
                         // Si cambia a un estado NO solucionado/cerrado, establecemos resolved_at a NULL
                         $set_clauses[] = "resolved_at = NULL";
                    }

                    $set_clauses[] = "status_id = ?"; // Añadir al SET
                    $params[] = $status_id; // Añadir el ID del estado a los parámetros
                    $param_types .= "i"; // Es un parámetro entero
                    $update_status_id = $status_id; // Guardar el nuevo ID de estado

                }
                continue; // Pasar al siguiente campo de los datos recibidos

            } // Fin manejo 'status'


            // --- Manejo especial para el campo 'assigned_to_id' ---
            if ($key === 'assigned_to_id') {
                 // El valor puede ser un ID numérico, null, o "" (del select 'Sin asignar')
                 if ($value === null || $value === "") {
                      // Si es null o cadena vacía, queremos establecerlo a NULL en la BD
                      $set_clauses[] = "assigned_to_id = NULL";
                      // Si se asigna a NULL y antes estaba asignado, marcamos para actualizar assigned_at a NULL
                      // Para saber si antes estaba asignado, necesitaríamos consultar el estado actual,
                      // lo cual complica este loop. Una alternativa es simplemente establecer assigned_at a NULL
                      // siempre que assigned_to_id se establezca a NULL.
                      $set_clauses[] = "assigned_at = NULL";
                      $update_assigned_to_id = null; // Guardar el nuevo ID de asignado (null)

                 } else {
                      // Si el valor no es null/vacío, debe ser un ID de usuario numérico positivo
                      $assigned_id = (int)$value; // Convertir a entero
                      if (!is_numeric($value) || $assigned_id <= 0) {
                          sendError("ID de usuario asignado inválido", 400); // Error si no es un número positivo
                          return;
                      }
                      // Opcional: Verificar si el user_id existe en la tabla users (puede hacerse con JOIN en la consulta UPDATE o una consulta SELECT previa)
                      // Para simplificar, confiamos en la restricción de clave foránea de la BD si existe.

                      $set_clauses[] = "assigned_to_id = ?"; // Añadir al SET
                      $params[] = $assigned_id; // Añadir el ID del usuario a los parámetros
                      $param_types .= "i"; // Es un parámetro entero
                       $update_assigned_to_id = $assigned_id; // Guardar el nuevo ID de asignado

                      // Si se asigna a un usuario (y antes estaba NULL o asignado a otro), actualizamos assigned_at
                       // Similar a resolved_at, para saber si "antes estaba NULL" necesitaríamos consultar.
                       // Una alternativa más simple es actualizar assigned_at SIEMPRE que assigned_to_id cambie a un valor NO NULL.
                       // Sin embargo, para ser más precisos, solo deberíamos actualizarlo si el assigned_to_id ANTERIOR era NULL.
                       // Para este nivel de API, es aceptable actualizarlo siempre que se cambie a un valor no null.
                       // O podemos añadir un campo 'assigned_at' en el JSON de entrada si el frontend controla cuándo actualizarlo.
                       // La solución más robusta aquí es actualizar assigned_at = NOW() si el status_id pasa a 'En Proceso' (2).
                       // Y ponerlo a NULL si el status cambia a Pendiente (1), Solucionado (3), Cerrado (4), Cancelado (5), Re-abierto (6) ?
                       // Esto depende de la lógica de negocio exacta.
                       // Por ahora, basemos la actualización de assigned_at en el CAMBIO de assigned_to_id a un valor NO NULL:
                        $update_assigned_at = true; // Marcamos para actualizar assigned_at si se asigna a alguien

                 } // Fin else (value no es null/vacío)

                continue; // Pasar al siguiente campo de los datos recibidos

            } // Fin manejo 'assigned_to_id'


            // --- Manejo para otros campos (title, description, category, affected_asset, in_progress_details, resolution_comments) ---
            // Estos campos son strings. affected_asset, in_progress_details, resolution_comments pueden ser NULL.

            // Si el valor es null, lo añadimos directamente al SET.
            if ($value === null) {
                 $set_clauses[] = "`" . $key . "` = NULL";
                 // No se añade a params ni param_types en este caso
            } else {
                // Si el valor no es null, se trata como string.
                // Asegurarse de que el campo sea realmente uno de los nullable_fields si el valor es null
                $nullable_fields_check = ['affected_asset', 'in_progress_details', 'resolution_comments'];
                // Si el campo NO es uno de los nullable_fields y el valor es null, esto sería un error de diseño o validación.
                // Pero nuestra lista $updatable_fields ya filtra los campos.
                // Simplemente añadimos el campo y su valor (string) a la consulta y parámetros.
                $set_clauses[] = "`" . $key . "` = ?"; // Añadir al SET
                $params[] = $this->conn->real_escape_string($value); // Añadir el valor escapado a los parámetros
                $param_types .= "s"; // Es un parámetro string
            }

        } // Fin foreach ($data as $key => $value)


        // Añadir actualización de timestamps especiales si las banderas están activas
        if ($update_resolved_at) {
            $set_clauses[] = "resolved_at = NOW()"; // Usar NOW() para la fecha actual de resolución
            // Si el estado se mueve a resuelto, también podría considerarse poner assigned_at a NULL,
            // o dejarlo como estaba al resolver. Depende de la lógica. Por ahora, solo resolved_at.
            // Opcional: $set_clauses[] = "assigned_at = NULL";
        }

        // Si el estado se mueve a un estado NO solucionado/cerrado, resolved_at ya se puso a NULL arriba.

         // Si update_assigned_at es true (se asignó a un usuario) Y el estado actual NO es 'En Proceso' (2),
         // podríamos querer cambiar el estado a 'En Proceso' y actualizar assigned_at.
         // Esto es lógica de negocio más compleja que requeriría consultar el estado ANTERIOR.
         // Para simplificar: actualizamos assigned_at = NOW() si el campo assigned_to_id se envió con un valor NO NULL.
         // Ya lo manejamos en el loop.

         // Si el estado cambia a 'En Proceso' (ID 2), actualizamos assigned_at = NOW(), incluso si assigned_to_id no se envió en el mismo PUT.
         // Esto es más robusto: la fecha de asignación depende del estado.
         // Necesitamos saber el estado ANTERIOR o asumir que si el estado enviado es 'En Proceso', actualizamos assigned_at.
         // Vamos a hacerlo simple: si el estado *enviado* es 'En Proceso', actualizamos assigned_at = NOW().
         // Necesitamos el ID del estado 'En Proceso'. Asumimos ID = 2.
         $en_proceso_status_id = 2; // **IMPORTANTE: Verifica en tu BD**

         // Si se actualizó el status_id AHORA a 'En Proceso', actualizamos assigned_at = NOW()
         if ($update_status_id !== null && $update_status_id == $en_proceso_status_id) {
             // Solo añadir si no se añadió ya arriba al poner assigned_to_id a NULL
             if (!in_array("assigned_at = NULL", $set_clauses)) {
                 $set_clauses[] = "assigned_at = NOW()";
             }
         } else if ($update_status_id !== null && $update_status_id != $en_proceso_status_id) {
              // Si el estado cambió a algo DIFERENTE de En Proceso, ponemos assigned_at a NULL (si no es NULL ya)
              // Esto puede ser demasiado agresivo dependiendo de la lógica. Quizás solo si pasa de En Proceso a otro estado.
              // Dejemos la lógica simple: assigned_at se pone a NOW() cuando el estado entra a En Proceso.
              // No lo establecemos a NULL automáticamente al salir, a menos que assigned_to_id pase a NULL.
         }


        // Si no hay cláusulas SET construidas, significa que no se enviaron campos válidos
        if (empty($set_clauses)) {
             sendError("No hay datos válidos para actualizar", 400);
             return;
        }

        // Montar la consulta SQL final
        $sql = "UPDATE incidents SET " . implode(", ", $set_clauses) . " WHERE incident_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            sendError("Error preparando la consulta de actualización: " . $this->conn->error, 500);
            return;
        }

        // Añadir el ID de la incidencia al final de los parámetros para el WHERE clause
        $params[] = (int)$id;
        $param_types .= "i"; // El ID es un entero

        // Bind de parámetros dinámico
        $bind_args = array();
        $bind_args[] = $param_types;
        foreach ($params as $key => $value) {
            $bind_args[] = &$params[$key];
        }
        // error_log("DEBUG: bind_args for update: " . print_r($bind_args, true)); // Debugging
        call_user_func_array(array($stmt, 'bind_param'), $bind_args);


        if ($stmt->execute()) {
            // Verificar si se actualizó alguna fila
            if ($stmt->affected_rows > 0) {
                sendResponse(["message" => "Incidencia actualizada con éxito"], 200);
            } else {
                 // 0 filas afectadas. Podría ser que el ID no existe o los datos enviados eran idénticos a los actuales.
                 // Una respuesta 404 o 200 con mensaje específico son opciones.
                 // Optamos por 404 si no se afectaron filas, asumiendo que el ID no existía o ya estaba exactamente igual.
                 // Si el ID existe y los datos eran iguales, la base de datos reporta 0 filas afectadas.
                 // Para distinguir, necesitaríamos una SELECT previa, lo cual añade complejidad.
                 // Un 404 es razonable si el usuario esperaba un cambio y no ocurrió.
                 sendError("Incidencia no encontrada o no se realizaron cambios", 404);
            }
        } else {
             // Error en la ejecución
             // Verificar si es un error de FK (ej: assigned_to_id no existe)
             if ($this->conn->errno == 1452) {
                  sendError("Error al actualizar la incidencia: El usuario asignado (assigned_to_id) no existe.", 409); // Conflict
             } else {
                  sendError("Error al actualizar la incidencia", 500, $stmt->error); // Incluir error de BD
             }
        }
    }

    // --- Método DELETE (Eliminar) ---
    /**
     * Elimina una incidencia por su ID.
     *
     * @param int|string $id El ID de la incidencia a eliminar.
     */
    public function delete($id) {
        // Validar que el ID es un número positivo
        if (!is_numeric($id) || $id <= 0) {
            sendError("ID de incidencia inválido", 400);
            return;
        }

        // Usar prepared statement para la eliminación
        $sql = "DELETE FROM incidents WHERE incident_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             sendError("Error preparando la consulta de eliminación: " . $this->conn->error, 500);
             return;
        }

        $stmt->bind_param("i", $id); // Bind del ID como entero

        if ($stmt->execute()) {
            // Verificar si se eliminó alguna fila
            if ($stmt->affected_rows > 0) {
                sendResponse(["message" => "Incidencia eliminada con éxito"], 200);
            } else {
                // 0 filas afectadas. Esto generalmente significa que el ID no existía.
                sendError("Incidencia no encontrada", 404);
            }
        } else {
            // Error en la ejecución (ej: restricción de clave foránea si otras tablas referencian incidencias, aunque no en este diseño simple)
             // El error SQL en $stmt->error dará más detalles
             // Código de error 1451 es para FK constraint fails (aprox)
             if ($this->conn->errno == 1451) {
                  sendError("Error al eliminar la incidencia: Existen dependencias que impiden la eliminación.", 409); // Conflict
             } else {
                  sendError("Error al eliminar la incidencia", 500, $stmt->error); // Incluir error de BD
             }
        }
    }


    // --- Destructor ---
    public function __destruct() {
        // Cerrar la conexión a la base de datos al destruir el objeto
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>