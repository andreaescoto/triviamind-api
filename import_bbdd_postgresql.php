<?php
// Importador completo de base de datos TriviaMind para PostgreSQL
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Importar Base de Datos PostgreSQL</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
echo "</head><body>";

echo "<h1>🗄️ Importador COMPLETO PostgreSQL - TriviaMind</h1>";

try {
    // Obtener variables de entorno de Render
    $database_url = getenv('DATABASE_URL');
    
    if (!$database_url) {
        throw new Exception("DATABASE_URL no encontrada");
    }

    echo "<p class='info'>🔗 Conectando a PostgreSQL...</p>";
    
    // Conectar a PostgreSQL usando DATABASE_URL
    $pdo = new PDO($database_url, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "<p class='success'>✅ Conexión exitosa a PostgreSQL</p>";

    // 1. CREAR TABLA CATEGORIAS
    echo "<p class='info'>📁 Creando tabla categorias...</p>";
    $pdo->exec("DROP TABLE IF EXISTS categorias CASCADE");
    $pdo->exec("
        CREATE TABLE categorias (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL UNIQUE
        )
    ");
    
    // Insertar categorías
    $categorias_sql = "INSERT INTO categorias (nombre) VALUES
        ('Historia'),
        ('Geografía'),
        ('Ciencia'),
        ('Literatura'),
        ('Arte'),
        ('Tecnología'),
        ('Matemáticas'),
        ('Deportes'),
        ('Cine'),
        ('Música'),
        ('Filosofía'),
        ('Cultura general')";
    $pdo->exec($categorias_sql);
    echo "<p class='success'>✅ 12 categorías insertadas</p>";

    // 2. CREAR TABLA USUARIOS
    echo "<p class='info'>👥 Creando tabla usuarios...</p>";
    $pdo->exec("DROP TABLE IF EXISTS usuarios CASCADE");
    $pdo->exec("
        CREATE TABLE usuarios (
            id SERIAL PRIMARY KEY,
            nombre VARCHAR(50),
            apellido VARCHAR(50),
            email VARCHAR(100) UNIQUE,
            contrasena VARCHAR(255),
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            token_recuperacion VARCHAR(255),
            token_expiracion TIMESTAMP,
            proveedor_id VARCHAR(100),
            proveedor VARCHAR(50),
            foto_url VARCHAR(255)
        )
    ");
    echo "<p class='success'>✅ Tabla usuarios creada</p>";

    // 3. CREAR TABLA PREGUNTAS
    echo "<p class='info'>❓ Creando tabla preguntas...</p>";
    $pdo->exec("DROP TABLE IF EXISTS preguntas CASCADE");
    $pdo->exec("
        CREATE TABLE preguntas (
            id SERIAL PRIMARY KEY,
            texto TEXT NOT NULL,
            categoria VARCHAR(50),
            nivel INTEGER,
            fecha_creacion TIMESTAMP,
            categoria_id INTEGER REFERENCES categorias(id)
        )
    ");

    // 4. CREAR TABLA RESPUESTAS
    echo "<p class='info'>✏️ Creando tabla respuestas...</p>";
    $pdo->exec("DROP TABLE IF EXISTS respuestas CASCADE");
    $pdo->exec("
        CREATE TABLE respuestas (
            id SERIAL PRIMARY KEY,
            id_pregunta INTEGER REFERENCES preguntas(id),
            texto VARCHAR(255),
            es_correcta BOOLEAN NOT NULL,
            fecha_creacion TIMESTAMP
        )
    ");

    // 5. CREAR TABLA RANKING
    echo "<p class='info'>🏆 Creando tabla ranking...</p>";
    $pdo->exec("DROP TABLE IF EXISTS ranking CASCADE");
    $pdo->exec("
        CREATE TABLE ranking (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER REFERENCES usuarios(id),
            total_puntos INTEGER NOT NULL DEFAULT 0,
            partidas_jugadas INTEGER DEFAULT 0
        )
    ");

    // 6. CREAR TABLA HISTORIAL_JUEGOS
    echo "<p class='info'>📊 Creando tabla historial_juegos...</p>";
    $pdo->exec("DROP TABLE IF EXISTS historial_juegos CASCADE");
    $pdo->exec("
        CREATE TABLE historial_juegos (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER REFERENCES usuarios(id),
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            partidas INTEGER DEFAULT 0
        )
    ");

    // 7. INSERTAR PREGUNTAS DE EJEMPLO
    echo "<p class='info'>❓ Insertando preguntas de ejemplo...</p>";
    
    $preguntas_ejemplos = [
        ['¿Cuál es la capital de Francia?', 'Geografía', 1, NOW(), 2],
        ['¿Quién escribió Don Quijote?', 'Literatura', 1, NOW(), 4],
        ['¿Qué país tiene forma de bota?', 'Geografía', 1, NOW(), 2],
        ['¿Cuál es el planeta más grande del sistema solar?', 'Ciencia', 2, NOW(), 3],
        ['¿En qué año llegó el hombre a la luna?', 'Historia', 2, NOW(), 1],
    ];

    $stmt = $pdo->prepare("INSERT INTO preguntas (texto, categoria, nivel, fecha_creacion, categoria_id) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)");
    foreach ($preguntas_ejemplos as $pregunta) {
        $stmt->execute($pregunta);
    }
    
    echo "<p class='success'>✅ 5 preguntas de ejemplo insertadas</p>";

    // 8. INSERTAR RESPUESTAS DE EJEMPLO
    echo "<p class='info'>✏️ Insertando respuestas de ejemplo...</p>";
    
    $respuestas_ejemplos = [
        // Pregunta 1: Capital de Francia
        [1, 'París', true],
        [1, 'Madrid', false],
        [1, 'Londres', false],
        [1, 'Roma', false],
        
        // Pregunta 2: Don Quijote
        [2, 'Miguel de Cervantes', true],
        [2, 'Gabriel García Márquez', false],
        [2, 'Mario Vargas Llosa', false],
        [2, 'Federico García Lorca', false],
        
        // Pregunta 3: País con forma de bota
        [3, 'Italia', true],
        [3, 'España', false],
        [3, 'Grecia', false],
        [3, 'Portugal', false],
        
        // Pregunta 4: Planeta más grande
        [4, 'Júpiter', true],
        [4, 'Saturno', false],
        [4, 'Neptuno', false],
        [4, 'Urano', false],
        
        // Pregunta 5: Llegada a la luna
        [5, '1969', true],
        [5, '1968', false],
        [5, '1970', false],
        [5, '1967', false],
    ];

    $stmt = $pdo->prepare("INSERT INTO respuestas (id_pregunta, texto, es_correcta, fecha_creacion) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    foreach ($respuestas_ejemplos as $respuesta) {
        $stmt->execute($respuesta);
    }

    echo "<p class='success'>✅ 20 respuestas insertadas</p>";

    // Verificar datos insertados
    echo "<h3>📊 Resumen de datos insertados:</h3>";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM categorias");
    echo "<p class='success'>📁 Categorías: " . $stmt->fetchColumn() . "</p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM preguntas");
    echo "<p class='info'>❓ Preguntas: " . $stmt->fetchColumn() . "</p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM respuestas");
    echo "<p class='info'>✏️ Respuestas: " . $stmt->fetchColumn() . "</p>";

    echo "<h3>🎯 Estado de la importación:</h3>";
    echo "<p class='success'>✅ Estructura PostgreSQL creada</p>";
    echo "<p class='success'>✅ Categorías completas (12/12)</p>";
    echo "<p class='success'>✅ Preguntas de ejemplo (5)</p>";
    echo "<p class='success'>✅ Respuestas de ejemplo (20)</p>";
    
    echo "<hr>";
    echo "<h3>🔗 Probar la API:</h3>";
    echo "<ul>";
    echo "<li><a href='preguntas.php?numero_preguntas=1&dificultad=1&categorias=Historia' target='_blank'>Probar preguntas.php</a></li>";
    echo "<li><a href='preguntas.php?numero_preguntas=3&dificultad=1&categorias=Geografía' target='_blank'>Probar geografía</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Importador PostgreSQL - TriviaMind Render.com</em></p>";
echo "</body></html>";
?>