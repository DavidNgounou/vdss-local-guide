<?php
// Connexion à la base de données PostgreSQL + PostGIS
$host = 'vehicle-decison-support.postgres.database.azure.com';
$db = 'postgres';
$user = 'superUser';
$pass = 'Vdss2468.';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Récupération des données JSON envoyées
    $data = json_decode(file_get_contents("php://input"), true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($data['lat'], $data['lng'], $data['speed'], $data['plate'])) {
            $lat = $data['lat'];
            $lng = $data['lng'];
            $speed = $data['speed'];
            $plate = $data['plate'];

            $pointWKT = sprintf("POINT(%s %s)", $lng, $lat);

            // Trouver le segment de route le plus proche
            $stmt = $pdo->prepare("
                SELECT id, speed_limit_id, road_coordinates
                FROM road_segment
                WHERE ST_DWithin(road_coordinates, ST_GeomFromText(?, 4326), 0.0005)
                ORDER BY ST_Distance(road_coordinates, ST_GeomFromText(?, 4326))
                LIMIT 1
            ");
            $stmt->execute([$pointWKT, $pointWKT]);
            $segment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($segment && $segment['speed_limit_id'] !== null) {
                $segment_id = $segment['id'];
                $speed_limit = $segment['speed_limit_id'];

                if ($speed > $speed_limit) {
                    // Insertion dans exceed_speed_limit
                    $stmt = $pdo->prepare("
                        INSERT INTO exceed_speed_limit (
                            time_of_violation,
                            running_speed,
                            number_of_times_warned,
                            positions_of_sp_violation,
                            road_segment_id,
                            car_plate_number
                        )
                        VALUES (CURRENT_TIMESTAMP, ?, 1, ST_GeomFromText(?, 4326), ?, ?)
                    ");
                    $stmt->execute([
                        $speed,
                        $pointWKT,
                        $segment_id,
                        $plate
                    ]);

                    echo json_encode([
                        'status' => 'violation_recorded',
                        'speed_limit' => $speed_limit,
                        'your_speed' => $speed
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'status' => 'ok',
                        'speed_limit' => $speed_limit,
                        'your_speed' => $speed
                    ]);
                    exit;
                }
            } else {
                echo json_encode([
                    'status' => 'no_segment_found'
                ]);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'db_error', 'message' => $e->getMessage()]);
}
?>
