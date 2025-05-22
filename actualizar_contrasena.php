<?php
require_once 'conexion.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (empty($data['email']) || empty($data['codigo']) || empty($data['nueva_contrasena'])) {
        throw new Exception("Faltan datos obligatorios.");
    }

    $email = $conn->real_escape_string($data['email']);
    $codigo = $conn->real_escape_string($data['codigo']);
    $nuevaContrasena = $data['nueva_contrasena'];

    // Verificar que el código exista, coincida con el email y no haya expirado
    $sql = "SELECT contrasena, token_expiracion FROM usuarios WHERE email = ? AND token_recuperacion = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        throw new Exception("Código inválido o no coincide con el correo.");
    }

    $fila = $resultado->fetch_assoc();

    if (strtotime($fila['token_expiracion']) < time()) {
        throw new Exception("El código ha expirado.");
    }

    // Verificar que la nueva contraseña no sea igual a la anterior
    if (password_verify($nuevaContrasena, $fila['contrasena'])) {
        throw new Exception("La nueva contraseña no puede ser igual a la anterior.");
    }

    // Hashear la nueva contraseña
    $hash = password_hash($nuevaContrasena, PASSWORD_BCRYPT);

    // Actualizar la contraseña y eliminar el token anterior
    $update = "UPDATE usuarios SET contrasena = ?, token_recuperacion = NULL, token_expiracion = NULL WHERE email = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("ss", $hash, $email);
    $stmt->execute();

    echo json_encode(['mensaje' => 'Contraseña actualizada correctamente']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
