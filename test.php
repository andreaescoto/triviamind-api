<?php
// Archivo de prueba simple
echo "¡Hola! El servidor PHP funciona correctamente.<br>";
echo "Hora actual: " . date('Y-m-d H:i:s') . "<br>";

// Mostrar variables de entorno
echo "<h3>Variables MySQL:</h3>";
echo "MYSQLHOST: " . ($_ENV['MYSQLHOST'] ?? 'NO DEFINIDA') . "<br>";
echo "MYSQLPORT: " . ($_ENV['MYSQLPORT'] ?? 'NO DEFINIDA') . "<br>";
echo "MYSQL_DATABASE: " . ($_ENV['MYSQL_DATABASE'] ?? 'NO DEFINIDA') . "<br>";
echo "MYSQLUSER: " . ($_ENV['MYSQLUSER'] ?? 'NO DEFINIDA') . "<br>";
echo "MYSQLPASSWORD: " . (isset($_ENV['MYSQLPASSWORD']) ? '[DEFINIDA]' : 'NO DEFINIDA') . "<br>";

// Probar conexión MySQL
echo "<h3>Prueba de conexión MySQL:</h3>";
try {
    $host = $_ENV['MYSQLHOST'] ?? 'localhost';
    $port = $_ENV['MYSQLPORT'] ?? '3306';
    $database = $_ENV['MYSQL_DATABASE'] ?? 'railway';
    $username = $_ENV['MYSQLUSER'] ?? 'root';
    $password = $_ENV['MYSQLPASSWORD'] ?? '';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexión MySQL exitosa!<br>";
    
    // Probar una consulta simple
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch();
    echo "📊 Base de datos actual: " . $result['db_name'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
}
?>