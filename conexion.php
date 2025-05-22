<?php
// Mostrar errores para desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Hacer que MySQLi lance excepciones en vez de warnings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $host = 'sql106.byethost4.com';
    $dbname = 'b4_39052330_triviamind';
    $username = 'b4_39052330';
    $password = 'triviamindpass';
    
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Establecer codificación UTF-8
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    // Responder con JSON en caso de error de conexión
    http_response_code(500);
    echo json_encode(['error' => 'Error al conectar a la base de datos', 'detalle' => $e->getMessage()]);
    exit;
}
?>
