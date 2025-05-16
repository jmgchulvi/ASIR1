<?php

// Asegúrate de que Database.php y el archivo con sendResponse/sendError estén incluidos
require_once __DIR__ . '/../Database.php';
// Si sendResponse/sendError están en otro archivo (ej: helpers.php), inclúyelo:
// require_once __DIR__ . '/../helpers.php'; // Asegúrate de que sendResponse/sendError están aquí


class UserController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
        // Opcional: Configurar mysqli para lanzar excepciones en lugar de solo errores
        // Esto permitiría usar try/catch para excepciones mysqli_sql_exception
        // $this->conn->set_exception_handler(false); // No, esto desactiva el manejador por defecto
        // $this->conn->options(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Activar reportes como excepciones (requiere PHP 5.3+)
    }

    // --- Método GET (Lista Todos) ---
    /**
     * Obtiene una lista de todos los usuarios.
     */
    public function getAll() {
        $sql = "SELECT user_id, name, email, created_at, updated_at FROM users";
        $result = $this->conn->query($sql);

        $users = [];
        if ($result) {
             if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
            }
            sendResponse($users); // Enviar respuesta JSON (puede ser un array vacío)
        } else {
             sendError("Error al obtener usuarios: " . $this->conn->error, 500);
        }
    }

    // --- Método GET (Uno por ID) ---
    /**
     * Obtiene los detalles de un usuario por su ID.
     *
     * @param int|string $id El ID del usuario.
     */
    public function getOne($id) {
        // Validar que el ID es un número positivo
        if (!is_numeric($id) || $id <= 0) {
            sendError("ID de usuario inválido", 400);
            return;
        }

        $sql = "SELECT user_id, name, email, created_at, updated_at FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             sendError("Error preparando la consulta: " . $this->conn->error, 500);
             return;
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
             sendError("Error ejecutando la consulta: " . $stmt->error, 500);
             return;
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            sendResponse($user);
        } else {
            sendError("Usuario no encontrado", 404);
        }
    }

    // --- Método POST (Crear) ---
    /**
     * Crea un nuevo usuario a partir de los datos recibidos por POST.
     */
    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['name'], $data['email'])) {
            sendError("Datos incompletos: faltan name o email", 400);
            return;
        }

        $name = $this->conn->real_escape_string($data['name']);
        $email = $this->conn->real_escape_string($data['email']);

         // Validar formato de email (opcional, pero recomendado también en backend)
         // if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         //     sendError("Formato de email inválido", 400);
         //     return;
         // }


        // Verificar si el email ya existe (asumimos que email es único en la tabla o debería serlo)
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = $this->conn->prepare($check_sql);
        if (!$check_stmt) {
            error_log("Error preparando check email: " . $this->conn->error);
            sendError("Error interno del servidor", 500); return;
        }
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            sendError("El email ya está registrado.", 409); // Conflict
            return;
        }


        $sql = "INSERT INTO users (name, email, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             sendError("Error preparando la consulta de creación: " . $this->conn->error, 500);
             return;
        }

        $stmt->bind_param("ss", $name, $email);

        if ($stmt->execute()) {
            $new_user_id = $this->conn->insert_id;
            sendResponse(["message" => "Usuario creado con éxito", "user_id" => $new_user_id], 201);
        } else {
             sendError("Error al crear el usuario", 500, $stmt->error);
        }
    }

    // --- Método PUT (Actualizar) ---
    /**
     * Actualiza un usuario existente por su ID.
     *
     * @param int|string $id El ID del usuario a actualizar.
     */
    public function update($id) {
        // Validar que el ID es un número positivo
        if (!is_numeric($id) || $id <= 0) {
            sendError("ID de usuario inválido", 400);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data)) {
            sendError("No hay datos para actualizar", 400);
            return;
        }

        $set_clauses = [];
        $params = [];
        $param_types = "";

        $updatable_fields = ['name', 'email']; // Campos permitidos para actualizar

        foreach ($data as $key => $value) {
            if (!in_array($key, $updatable_fields)) {
                 // Ignorar o dar error si campo no es actualizable
                 continue;
            }

            // Validar formato de email si se está actualizando el email
            if ($key === 'email' && $value !== null && $value !== "") { // Permitir email vacío si se quiere borrar? No es común, email suele ser NOT NULL.
                 // if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                 //     sendError("Formato de email inválido", 400);
                 //     return;
                 // }

                // Verificar si el nuevo email ya existe para otro usuario
                 $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
                 $check_stmt = $this->conn->prepare($check_sql);
                 if (!$check_stmt) { error_log("Error preparando check email update: " . $this->conn->error); sendError("Error interno del servidor", 500); return; }
                 $check_stmt->bind_param("si", $value, $id);
                 $check_stmt->execute();
                 $check_result = $check_stmt->get_result();
                 if ($check_result->num_rows > 0) {
                     sendError("El email ya está registrado por otro usuario.", 409); // Conflict
                     return;
                 }
            }

            // Añadir al SET
            $set_clauses[] = "`" . $key . "` = ?";
            $params[] = $this->conn->real_escape_string($value); // Asumiendo que son strings (name, email)
            $param_types .= "s";
        }

        // Añadir la actualización de updated_at
        $set_clauses[] = "updated_at = NOW()";

        if (empty($set_clauses)) {
             // Esto no debería pasar si $data no estaba vacía y los campos son actualizables
             // Pero si solo se envía un campo no actualizable, podría llegar aquí.
             sendError("No hay datos válidos para actualizar", 400);
             return;
        }

        $sql = "UPDATE users SET " . implode(", ", $set_clauses) . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            sendError("Error preparando la consulta de actualización: " . $this->conn->error, 500);
            return;
        }

        // Añadir el ID del usuario al final de los parámetros
        $params[] = (int)$id;
        $param_types .= "i"; // El ID es un entero

        // Bind de parámetros dinámico
        $bind_args = array();
        $bind_args[] = $param_types;
        foreach ($params as $key => $value) {
            $bind_args[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_args);


        if ($stmt->execute()) {
             // affected_rows > 0 significa que se hizo un cambio
             // affected_rows == 0 significa que el ID existe, pero los datos eran los mismos
             // En ambos casos, la operación fue "exitosa" desde la perspectiva de que el estado deseado se alcanzó.
             // Si el ID no existiera, execute() podría fallar o affected_rows sería 0 si no se chequea antes.
             // Una SELECT previa podría verificar la existencia, pero añade complejidad.
             // Confiamos en el 404 si affected_rows es 0 después de un DELETE, pero para PUT, 200 es más común si el ID existe.
             // Si affected_rows es 0, la incidencia existe pero no se cambió nada. Podríamos devolver 200 con un mensaje diferente.
             // O mantener 200 y que el cliente compruebe el mensaje.
             // sendResponse(["message" => "Usuario actualizado con éxito"], 200); // Simple, siempre 200 si execute no falla y ID válido
             // Alternativa que verifica si hubo cambio real:
             if ($stmt->affected_rows > 0) {
                 sendResponse(["message" => "Usuario actualizado con éxito"], 200);
             } else {
                 // Si affected_rows es 0, el usuario existe pero no se cambiaron campos.
                 // Podríamos enviar 200 con otro mensaje o 404 si queremos indicar que no se afectó ninguna fila.
                 // Mantengamos 200 con mensaje de éxito si el ID era válido.
                 // sendResponse(["message" => "Usuario encontrado, pero no se realizaron cambios (los datos eran idénticos o no se enviaron campos válidos)."], 200);
                 // O enviar 404 si no se afectaron filas (más común en algunas APIs para PUT/DELETE):
                 // sendError("Usuario no encontrado o no se realizaron cambios", 404); // Esto es si no existe O datos idénticos
                 // Pero para PUT, si existe y datos idénticos, 200 es aceptable. Si no existe, 404.
                 // Para saber si no existe sin SELECT previa, podemos hacer el 404 aquí.
                  $check_exist_sql = "SELECT user_id FROM users WHERE user_id = ?";
                  $check_exist_stmt = $this->conn->prepare($check_exist_sql);
                  if ($check_exist_stmt) {
                      $check_exist_stmt->bind_param("i", $id);
                      $check_exist_stmt->execute();
                      if ($check_exist_stmt->get_result()->num_rows === 0) {
                          sendError("Usuario no encontrado", 404); // Si no existe
                          return;
                      }
                  } // Si no se pudo chequear existencia, seguimos asumiendo que el error 0 affected rows significa datos idénticos
                 sendResponse(["message" => "Usuario actualizado con éxito (sin cambios efectivos)"], 200); // Si existe pero 0 affected rows
             }


        } else {
             // Error en la ejecución (ej: email duplicado si la restricción UNIQUE no está en BD y la manejamos por código)
             // El error SQL en $stmt->error dará más detalles
             sendError("Error al actualizar el usuario", 500, $stmt->error);
        }
    }

    // --- Método DELETE (Eliminar) ---
    /**
     * Elimina un usuario por su ID.
     * **Maneja el error de restricción de clave foránea si el usuario tiene incidencias.**
     *
     * @param int|string $id El ID del usuario a eliminar.
     */
    public function delete($id) {
        // Validar que el ID es un número positivo
        if (!is_numeric($id) || $id <= 0) {
            sendError("ID de usuario inválido", 400);
            return;
        }

        // Usar prepared statement para la eliminación
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
             // Loggear error
             error_log("Error preparando consulta DELETE user: " . $this->conn->error);
             sendError("Error interno del servidor", 500);
             return;
        }

        $stmt->bind_param("i", $id); // Bind del ID como entero

        // *******************************************************************
        // MODIFICACIÓN CRUCIAL: Intentar ejecutar y capturar el error de FK
        // *******************************************************************
        if ($stmt->execute()) {
            // La ejecución SQL fue exitosa. Ahora verificamos si alguna fila fue afectada.
            if ($stmt->affected_rows > 0) {
                // Se eliminó una fila
                sendResponse(["message" => "Usuario eliminado con éxito"], 200);
            } else {
                // 0 filas afectadas. Esto significa que el ID no existía en la tabla.
                sendError("Usuario no encontrado", 404);
            }
        } else {
            // **Error en la ejecución SQL.** Aquí es donde manejamos el error específico de clave foránea.
            // MySQL error code 1451 es "Cannot delete or update a parent row: a foreign key constraint fails"
            if ($this->conn->errno == 1451) {
                 // Error de restricción de clave foránea
                 sendError("Error al eliminar el usuario: Tiene incidencias asociadas y no se puede eliminar.", 409); // 409 Conflict
            } else {
                 // Otro tipo de error de ejecución SQL (permisos, sintaxis, etc.)
                 // Loggear el error detallado del statement
                 error_log("Error ejecutando consulta DELETE user: " . $stmt->error . " (errno: " . $this->conn->errno . ")");
                 sendError("Error al eliminar el usuario", 500, $stmt->error); // Enviar un error 500 general
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