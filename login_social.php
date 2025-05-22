<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Habilita logs para depuración
error_log("====== INICIO DE LOGIN SOCIAL ======");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener datos de la petición
$raw_data = file_get_contents("php://input");
error_log("Datos recibidos: " . $raw_data);

// Parsear JSON
$data = json_decode($raw_data, true);

// Si hay un error en el JSON, registrarlo
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Error al procesar JSON: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
    exit();
}

// Validar que se recibieron todos los datos necesarios
if (!isset($data['email']) || !isset($data['proveedor']) || !isset($data['proveedor_id'])) {
    error_log("Faltan datos requeridos");
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos requeridos']);
    exit();
}

$email = trim($data['email']);
$nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
$apellido = isset($data['apellido']) ? trim($data['apellido']) : '';
$proveedor = trim($data['proveedor']);
$proveedor_id = trim($data['proveedor_id']);
$foto_url = isset($data['foto_url']) ? trim($data['foto_url']) : '';

error_log("Datos procesados: email=$email, nombre=$nombre, apellido=$apellido, proveedor=$proveedor, proveedor_id=$proveedor_id, foto_url=$foto_url");

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Email inválido: $email");
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit();
}

// Conexión a la base de datos
try {
    error_log("Conectando a la base de datos...");
    $conexion = new mysqli("mysql.webcindario.com", "triviamind", "triviamindpass", "triviamind");
    
    if ($conexion->connect_error) {
        throw new Exception("Error de conexión: " . $conexion->connect_error);
    }
    
    error_log("Conexión exitosa");
    $conexion->set_charset("utf8");
    
    // Verificar si el usuario ya existe por email
    error_log("Verificando si el usuario existe...");
    $stmt = $conexion->prepare("SELECT id, nombre, apellido, proveedor_id, foto_url FROM usuarios WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar consulta: " . $conexion->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows > 0) {
        error_log("Usuario encontrado");
        // El usuario ya existe
        $usuario = $resultado->fetch_assoc();
        
        // Verificar si es el mismo proveedor
        if ($usuario['proveedor_id'] != null && $usuario['proveedor_id'] != $proveedor_id) {
            error_log("Email registrado con otro proveedor");
            http_response_code(400);
            echo json_encode([
                'error' => 'Este email ya está registrado con otro método de inicio de sesión'
            ]);
            exit();
        }
        
        // Impedir el registro con un correo ya existente
        if (isset($data['es_registro']) && $data['es_registro'] === true) {
            error_log("Intento de registro con email ya existente");
            http_response_code(400);
            echo json_encode([
                'error' => 'Este correo ya está registrado. Por favor, usa otro correo o inicia sesión.'
            ]);
            exit();
        }
        
        // Actualizar el proveedor_id y foto_url si no estaban establecidos o han cambiado
        $actualizar = false;
        $campos_actualizacion = [];
        $tipos = "";
        $valores = [];
        
        if ($usuario['proveedor_id'] == null || $usuario['proveedor_id'] != $proveedor_id) {
            $campos_actualizacion[] = "proveedor_id = ?";
            $campos_actualizacion[] = "proveedor = ?";
            $tipos .= "ss";
            $valores[] = $proveedor_id;
            $valores[] = $proveedor;
            $actualizar = true;
        }
        
        // Actualizar foto_url si se proporcionó una nueva y es diferente
        if (!empty($foto_url) && $foto_url != $usuario['foto_url']) {
            $campos_actualizacion[] = "foto_url = ?";
            $tipos .= "s";
            $valores[] = $foto_url;
            $actualizar = true;
            error_log("Actualizando foto_url: $foto_url");
        }
        
        if ($actualizar) {
            // Añadir el ID del usuario para la condición WHERE
            $tipos .= "i";
            $valores[] = $usuario['id'];
            
            $sql = "UPDATE usuarios SET " . implode(", ", $campos_actualizacion) . " WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            if (!$stmt_update) {
                throw new Exception("Error al preparar actualización: " . $conexion->error);
            }
            
            $stmt_update->bind_param($tipos, ...$valores);
            $stmt_update->execute();
            $stmt_update->close();
            
            // Recuperar la foto_url actualizada
            if (!empty($foto_url) && $foto_url != $usuario['foto_url']) {
                $usuario['foto_url'] = $foto_url;
            }
        }
        
        // Login exitoso
        error_log("Login exitoso para usuario existente");
        $response = [
            'mensaje' => 'Login exitoso',
            'usuario_id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'email' => $email,
            'es_nuevo' => false,
            'foto_url' => $usuario['foto_url'] ?? $foto_url
        ];
        
        error_log("Respuesta: " . json_encode($response));
        http_response_code(200);
        echo json_encode($response);
        
    } else {
        error_log("Usuario no encontrado, creando cuenta nueva");
        // Usuario nuevo, crear cuenta
        $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, apellido, email, proveedor_id, proveedor, foto_url, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt_insert) {
            throw new Exception("Error al preparar inserción: " . $conexion->error);
        }
        
        $stmt_insert->bind_param("ssssss", $nombre, $apellido, $email, $proveedor_id, $proveedor, $foto_url);
        
        if ($stmt_insert->execute()) {
            $nuevo_usuario_id = $conexion->insert_id;
            error_log("Usuario creado con ID: $nuevo_usuario_id");
            
            $response = [
                'mensaje' => '¡Registro exitoso! Bienvenido a TriviaMind',
                'usuario_id' => $nuevo_usuario_id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'es_nuevo' => true,
                'foto_url' => $foto_url
            ];
            
            error_log("Respuesta: " . json_encode($response));
            http_response_code(200);
            echo json_encode($response);
        } else {
            throw new Exception("Error al crear usuario: " . $stmt_insert->error);
        }
        
        $stmt_insert->close();
    }
    
    $stmt->close();
    $conexion->close();
    error_log("Conexión cerrada");
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log($error_message);
    
    // Asegurarse de que siempre se devuelva una respuesta JSON válida
    http_response_code(500);
    echo json_encode(['error' => $error_message]);
}

error_log("====== FIN DE LOGIN SOCIAL ======");
?>