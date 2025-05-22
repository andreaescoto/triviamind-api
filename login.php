<?php
require_once 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Obtener los datos crudos del cuerpo de la solicitud
    $json = file_get_contents("php://input");
    if (!$json) {
        throw new Exception("No se recibió ninguna entrada JSON.");
    }

    // Decodificar el JSON recibido
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error en el formato JSON: " . json_last_error_msg());
    }

    // Verificar que email y contraseña estén presentes
    if (empty($data['email']) || empty($data['contrasena'])) {
        throw new Exception("Faltan campos requeridos: email o contraseña.");
    }

    $email = $conn->real_escape_string($data['email']);
    $contrasena = $data['contrasena'];

    // Consultar si el usuario existe
    $sql = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        throw new Exception("Usuario no encontrado.");
    }

    $usuario = $resultado->fetch_assoc();

    // Verificar la contraseña
    if (!password_verify($contrasena, $usuario['contrasena'])) {
        throw new Exception("Contraseña incorrecta.");
    }

    // Devolver los campos directamente (no dentro de un objeto 'usuario')
    echo json_encode([
        'mensaje' => 'Login exitoso',
        'usuario_id' => $usuario['id'],
        'nombre' => $usuario['nombre'] ?? 'Usuario',  // Valor predeterminado si es NULL
        'apellido' => $usuario['apellido'] ?? '',     // Valor predeterminado si es NULL
        'email' => $usuario['email'],
        'puntos_totales' => $usuario['puntos_totales'] ?? 0,
        'fecha_registro' => $usuario['fecha_registro'],
        'proveedor' => $usuario['proveedor'],
        'foto_url' => $usuario['foto_url']
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
?>