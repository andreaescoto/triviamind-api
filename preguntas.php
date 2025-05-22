<?php
// Establecer encabezados antes de cualquier salida
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Para permitir peticiones CORS

// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexi�n a la base de datos
include('conexion.php');

// Establecer la codificaci�n de la conexi�n en UTF-8
$conn->set_charset("utf8mb4");

// Funci�n para depuraci�n
function debug_to_file($data) {
    $log_file = 'debug_log.txt';
    if (is_array($data) || is_object($data)) {
        error_log(print_r($data, true) . "\n", 3, $log_file);
    } else {
        error_log($data . "\n", 3, $log_file);
    }
}

// Registrar la solicitud recibida
debug_to_file("Solicitud recibida: " . print_r($_GET, true));

// Comprobar si se recibieron los par�metros necesarios
if (isset($_GET['numero_preguntas'], $_GET['dificultad'], $_GET['categorias'])) {
    // Recibir par�metros
    $numero_preguntas = (int) $_GET['numero_preguntas'];  // N�mero de preguntas solicitadas
    $dificultad = $_GET['dificultad'];  // Dificultad (F�cil, Media, Dif�cil)
    $categorias = $_GET['categorias'];  // Categor�as seleccionadas (ejemplo: "Historia, Ciencia")

    // Filtrar las categor�as seleccionadas (separadas por comas)
    $categoriasArray = explode(",", $categorias);
    
    // Escapar cada categor�a para evitar inyecci�n SQL
    foreach($categoriasArray as &$cat) {
        $cat = $conn->real_escape_string(trim($cat));
    }
    
    $categoriasString = "'" . implode("','", $categoriasArray) . "'";
    
    debug_to_file("Categor�as procesadas: " . $categoriasString);
    debug_to_file("Dificultad: " . $dificultad);

    try {
        // Consulta SQL generada
        $sql = "SELECT p.id AS pregunta_id, p.texto AS pregunta, 
                       c.nombre AS categoria, p.nivel, 
                       r.id AS respuesta_id, r.texto AS respuesta, 
                       r.es_correcta 
                FROM preguntas p
                JOIN respuestas r ON p.id = r.id_pregunta
                JOIN categorias c ON p.categoria_id = c.id
                WHERE c.nombre IN ($categoriasString)";
        
        // A�adir filtro de dificultad si est� especificada
        if (!empty($dificultad)) {
            $dificultad = $conn->real_escape_string($dificultad);
            $sql .= " AND p.nivel = '$dificultad'";
        }
        
        $sql .= " ORDER BY RAND() LIMIT " . ($numero_preguntas * 10);  // Multiplicamos para asegurar suficientes respuestas
        
        debug_to_file("SQL: " . $sql);

        // Ejecutar la consulta
        $result = $conn->query($sql);

        if (!$result) {
            debug_to_file("Error en la consulta: " . $conn->error);
            echo json_encode(['error' => 'Error en la consulta: ' . $conn->error], JSON_UNESCAPED_UNICODE);
            exit;
        }

        debug_to_file("N�mero de filas: " . $result->num_rows);

        if ($result->num_rows > 0) {
            // Crear un array para las preguntas y respuestas
            $preguntasRespuestas = [];
            $preguntasUnicas = [];
            
            while ($row = $result->fetch_assoc()) {
                $pregunta_id = $row['pregunta_id'];
                
                $respuestaData = [
                    'respuesta_id' => (int)$row['respuesta_id'],
                    'texto' => $row['respuesta'],
                    'es_correcta' => $row['es_correcta'] == '1' || $row['es_correcta'] == 1
                ];

                // Si la pregunta no est� en el array, la a�adimos
                if (!isset($preguntasRespuestas[$pregunta_id])) {
                    // Solo a�adir si no hemos alcanzado el l�mite de preguntas
                    if (count($preguntasUnicas) < $numero_preguntas) {
                        $preguntasRespuestas[$pregunta_id] = [
                            'pregunta' => $row['pregunta'],
                            'categoria' => $row['categoria'],
                            'nivel' => $row['nivel'],
                            'categoria_id' => $pregunta_id,  // Usando id de pregunta como id de categor�a por simplicidad
                            'respuestas' => []
                        ];
                        $preguntasUnicas[$pregunta_id] = true;
                    } else {
                        // Si ya tenemos suficientes preguntas, seguimos solo si la pregunta ya est� en el array
                        if (!isset($preguntasRespuestas[$pregunta_id])) {
                            continue;
                        }
                    }
                }
                
                // A�adir la respuesta a la pregunta
                if (isset($preguntasRespuestas[$pregunta_id])) {
                    $preguntasRespuestas[$pregunta_id]['respuestas'][] = $respuestaData;
                }
            }

            debug_to_file("Preguntas procesadas: " . count($preguntasRespuestas));
            
            // Asegurar que cada pregunta tiene al menos 2 respuestas
            foreach ($preguntasRespuestas as $id => $pregunta) {
                if (count($pregunta['respuestas']) < 2) {
                    unset($preguntasRespuestas[$id]);
                }
            }

            // Limitamos al n�mero exacto de preguntas solicitadas
            $preguntasRespuestas = array_slice($preguntasRespuestas, 0, $numero_preguntas, true);

            if (empty($preguntasRespuestas)) {
                echo json_encode(['error' => 'No se encontraron preguntas completas para los par�metros seleccionados.'], JSON_UNESCAPED_UNICODE);
            } else {
                // Devolver las preguntas y respuestas como JSON con la codificaci�n adecuada
                $jsonResult = json_encode($preguntasRespuestas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                
                if ($jsonResult === false) {
                    debug_to_file("Error en json_encode: " . json_last_error_msg());
                    echo json_encode(['error' => 'Error al codificar JSON: ' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
                } else {
                    echo $jsonResult;
                }
            }
        } else {
            // Si no se encuentran preguntas
            echo json_encode(['error' => 'No se encontraron preguntas para los par�metros seleccionados.'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        // Manejo de excepciones
        debug_to_file("Excepci�n: " . $e->getMessage());
        echo json_encode(['error' => 'Error al obtener las preguntas: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
} else {
    // Par�metros faltantes
    $params = [];
    if (!isset($_GET['numero_preguntas'])) $params[] = 'numero_preguntas';
    if (!isset($_GET['dificultad'])) $params[] = 'dificultad';
    if (!isset($_GET['categorias'])) $params[] = 'categorias';
    
    echo json_encode(['error' => 'Faltan par�metros: ' . implode(', ', $params)], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>