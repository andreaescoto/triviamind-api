<?php
// Configuración de base de datos para Render.com PostgreSQL

function getDBConnection() {
    try {
        // Render proporciona DATABASE_URL como variable de entorno
        $database_url = getenv('DATABASE_URL');
        
        if (!$database_url) {
            // Fallback para desarrollo local
            $database_url = 'postgresql://localhost/triviamind';
        }
        
        $pdo = new PDO($database_url, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para responder con JSON en caso de error
function jsonError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
?>