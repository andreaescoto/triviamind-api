<?php
require_once 'conexion.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');

try {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (empty($data['email'])) {
        throw new Exception("Email requerido.");
    }

    $email = $conn->real_escape_string($data['email']);

    // Verificar si existe el usuario
    $sql = "SELECT id, nombre FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        throw new Exception("No se encontró ningún usuario con ese correo.");
    }

    $usuario = $resultado->fetch_assoc();

    // Generar código de 6 dígitos
    $codigo = random_int(100000, 999999);
    $expira = date('Y-m-d H:i:s', time() + 3600); // 1 hora

    // Guardar el código en la base de datos
    $update = "UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE email = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("sss", $codigo, $expira, $email);
    $stmt->execute();

    // Configurar PHPMailer
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = '8bfbc8001@smtp-brevo.com'; // Correo de la app
    $mail->Password = 'xsmtpsib-3d5cba6164a8b04c6e921b5cb7bd415b4510b35696ec6ce70967606e19a52765-37z9vKIOjtRV0mSU'; // Clave de aplicación BREVO
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('triviamind.app@gmail.com', 'TriviaMind');
    $mail->addAddress($email, $usuario['nombre']);

    $mail->Subject = '=?UTF-8?B?' . base64_encode('Tu código para recuperar contraseña - TriviaMind') . '?=';
    $mail->isHTML(true);

    // HTML del mensaje
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <p>Hola ' . htmlspecialchars($usuario['nombre']) . ',</p>
            <p>Recibimos una solicitud para restablecer tu contraseña. Tu código de acceso temporal es:</p>
            <div style="font-size: 36px; font-weight: bold; color: #2d89ef; text-align: center; margin: 20px 0;">' . $codigo . '</div>
            <p>Este código es válido por 1 hora. Por seguridad, no compartas este código con nadie.</p>
            <p>Si no fuiste tú quien lo solicitó, puedes ignorar este correo sin problemas.</p>
            <br>
            <p style="font-size: 12px; color: #999;">TriviaMind App · triviamind.app@gmail.com</p>
        </div>
    ';

    // Texto alternativo
    $mail->AltBody = 'Hola ' . $usuario['nombre'] . ', tu código para recuperar tu contraseña en TriviaMind es: ' . $codigo . '. Este código es válido por 1 hora. Si no lo solicitaste, puedes ignorar este mensaje.';

    // Encabezados adicionales
    $mail->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
    $mail->addCustomHeader('X-Priority', '3'); // Normal

    $mail->send();

    echo json_encode(['mensaje' => 'Código enviado por correo']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

