<?php
// Permitir CORS para las solicitudes desde la app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';

try {
    // Leer el cuerpo JSON crudo del POST
    $json = file_get_contents("php://input");

    if (!$json) {
        throw new Exception("No se recibió ninguna entrada JSON.");
    }

    // Decodificar el JSON
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error en el formato JSON: " . json_last_error_msg());
    }

    // Validar que los campos estén presentes y no vacíos
    if (
        empty($data['nombre']) ||
        empty($data['apellido']) ||
        empty($data['email']) ||
        empty($data['contrasena'])
    ) {
        throw new Exception("Faltan campos requeridos: nombre, apellido, email o contraseña.");
    }

    // Limpiar los datos
    $nombre = trim($data['nombre']);
    $apellido = trim($data['apellido']);
    $email = trim($data['email']);
    $contrasena = $data['contrasena'];

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El formato del email no es válido.");
    }

    // Verificar si el email ya existe
    $sql_check = "SELECT id FROM usuarios WHERE email = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        throw new Exception("El correo ya está registrado.");
    }

    // Hash de la contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_BCRYPT);

    // Insertar nuevo usuario
    $sql = "INSERT INTO usuarios (nombre, apellido, email, contrasena, fecha_registro, puntos_totales) 
            VALUES (?, ?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nombre, $apellido, $email, $contrasena_hash);
    
    if ($stmt->execute()) {
        $usuario_id = $conn->insert_id;
        
        // Respuesta exitosa
        echo json_encode([
            'mensaje' => 'Usuario registrado exitosamente',
            'usuario_id' => $usuario_id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email
        ]);
    } else {
        throw new Exception("Error al registrar el usuario.");
    }

    $stmt->close();
    $stmt_check->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>