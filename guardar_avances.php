<?php
include('conexion.php');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar si es una solicitud OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener los datos JSON de la solicitud
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Verificar si se recibió la información necesaria para guardar el avance
if (isset($data['usuario_id'], $data['preguntas'], $data['puntaje'], $data['fecha'])) {
    $usuario_id = (int) $data['usuario_id'];
    $preguntas = $data['preguntas']; // Las preguntas y respuestas seleccionadas
    $puntaje = (int) $data['puntaje'];
    $fecha = $data['fecha'];  // Fecha de la partida

    try {
        // Iniciar transacción para asegurar que todas las operaciones sean exitosas
        $conn->begin_transaction();

        // 1. Guardar el avance en historial_juegos
        $stmt = $conn->prepare("INSERT INTO historial_juegos (usuario_id, fecha, puntuaje, preguntas_contestadas) VALUES (?, ?, ?, ?)");
        $preguntas_contestadas = count($preguntas);
        $stmt->bind_param("isii", $usuario_id, $fecha, $puntaje, $preguntas_contestadas);
        
        if ($stmt->execute()) {
            // Obtener el ID del historial de la partida
            $historial_id = $conn->insert_id;

            // 2. Guardar cada pregunta y respuesta en el historial
            $stmt_respuesta = $conn->prepare("INSERT INTO respuestas_jugadas (historial_id, pregunta_id, respuesta_id, es_correcta) VALUES (?, ?, ?, ?)");
            
            foreach ($preguntas as $pregunta) {
                $pregunta_id = (int) $pregunta['pregunta_id'];
                $respuesta_id = (int) $pregunta['respuesta_id'];
                $es_correcta = (int) $pregunta['es_correcta']; // Convertir a 0 o 1 para MySQL
                
                $stmt_respuesta->bind_param("iiis", $historial_id, $pregunta_id, $respuesta_id, $es_correcta);
                
                if (!$stmt_respuesta->execute()) {
                    throw new Exception('Error al guardar las respuestas jugadas: ' . $stmt_respuesta->error);
                }
            }
            
            // 3. Actualizar los puntos totales del usuario en la tabla ranking
            
            // Primero verificamos si el usuario ya tiene un registro en la tabla ranking
            $check_ranking = $conn->prepare("SELECT id FROM ranking WHERE usuario_id = ?");
            $check_ranking->bind_param("i", $usuario_id);
            $check_ranking->execute();
            $result = $check_ranking->get_result();
            
            if ($result->num_rows > 0) {
                // El usuario ya existe en la tabla ranking, actualizar puntos y partidas jugadas
                $update_ranking = $conn->prepare("UPDATE ranking SET total_puntos = total_puntos + ?, partidas_jugadas = partidas_jugadas + 1 WHERE usuario_id = ?");
                $update_ranking->bind_param("ii", $puntaje, $usuario_id);
                
                if (!$update_ranking->execute()) {
                    throw new Exception('Error al actualizar puntos en el ranking: ' . $update_ranking->error);
                }
            } else {
                // El usuario no existe en la tabla ranking, insertar nuevo registro
                $insert_ranking = $conn->prepare("INSERT INTO ranking (usuario_id, total_puntos, partidas_jugadas) VALUES (?, ?, 1)");
                $insert_ranking->bind_param("ii", $usuario_id, $puntaje);
                
                if (!$insert_ranking->execute()) {
                    throw new Exception('Error al insertar en el ranking: ' . $insert_ranking->error);
                }
            }
            
            // Si todo fue exitoso, confirmar la transacción
            $conn->commit();
            
            // Devolver un mensaje de éxito
            echo json_encode([
                'success' => true,
                'mensaje' => 'Avance guardado correctamente.',
                'puntos_sumados' => $puntaje
            ]);
            
        } else {
            throw new Exception('Error al guardar el avance del juego: ' . $stmt->error);
        }
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $conn->rollback();
        
        // Manejo de excepciones
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar los datos: ' . $e->getMessage()
        ]);
    }
} else {
    // Información faltante
    echo json_encode([
        'success' => false,
        'error' => 'Faltan parámetros para guardar el avance del juego.'
    ]);
}

$conn->close();
?>