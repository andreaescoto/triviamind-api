<?php
include('conexion.php');

// Consultar el ranking de los usuarios
try {
    $sql = "SELECT usuarios.id, usuarios.nombre, SUM(historial_juegos.puntuaje) AS total_puntos
            FROM usuarios
            JOIN historial_juegos ON usuarios.id = historial_juegos.usuario_id
            GROUP BY usuarios.id
            ORDER BY total_puntos DESC
            LIMIT 10"; // Mostrar los primeros 10 usuarios

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $ranking = [];
        while ($row = $result->fetch_assoc()) {
            $ranking[] = [
                'usuario_id' => $row['id'],
                'nombre' => $row['nombre'],
                'total_puntos' => $row['total_puntos']
            ];
        }
        // Devolver el ranking en formato JSON
        echo json_encode($ranking);
    } else {
        echo json_encode(['error' => 'No hay datos de ranking disponibles.']);
    }
} catch (Exception $e) {
    // Manejo de excepciones
    echo json_encode(['error' => 'Error al obtener el ranking: ' . $e->getMessage()]);
}

$conn->close();
?>
