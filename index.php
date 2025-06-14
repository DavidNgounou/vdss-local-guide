<?php
$host = 'vehicle-decison-support.postgres.database.azure.com';
$db = 'postgres';
$user = 'superUser';
$pass = 'Vdss2468.';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $data = json_decode(file_get_contents("php://input"), true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($data['lat'], $data['lng'], $data['speed'], $data['plate'])) {
            $lat = floatval($data['lat']);
            $lng = floatval($data['lng']);
            $speed = intval($data['speed']);
            $plate = $data['plate'];
            $warn_count = isset($data['warn_count']) ? intval($data['warn_count']) : 1;

            $pointWKT = sprintf("POINT(%s %s)", $lng, $lat);

            // Trouver le segment de route le plus proche
            $stmt = $pdo->prepare("
                SELECT id, speed_limit, road_coordinates
                FROM road_segment
                WHERE ST_DWithin(road_coordinates, ST_GeomFromText(?, 4326), 0.0005)
                ORDER BY ST_Distance(road_coordinates, ST_GeomFromText(?, 4326))
                LIMIT 1
            ");
            $stmt->execute([$pointWKT, $pointWKT]);
            $segment = $stmt->fetch(PDO::FETCH_ASSOC);

            $violationRecorded = false; // Variable pour indiquer si une violation a été enregistrée

            if ($segment && $segment['speed_limit'] !== null) {
                $segment_id = $segment['id'];
                $speed_limit = intval($segment['speed_limit']);

                if ($speed > $speed_limit) {
                    // Enregistrer la violation
                    $stmt = $pdo->prepare("
                        INSERT INTO exceed_speed_limit (
                            time_of_violation,
                            running_speed,
                            number_of_times_warned,
                            positions_of_sp_violation,
                            road_segment_id,
                            car_plate_number
                        )
                        VALUES (
                            CURRENT_TIMESTAMP,
                            ?, ?, ST_GeomFromText(?, 4326), ?, ?
                        )
                    ");
                    $stmt->execute([
                        $speed,
                        $warn_count,
                        $pointWKT,
                        $segment_id,
                        $plate
                    ]);

                    $violationRecorded = true; // Indiquer qu'une violation a été enregistrée
                }
            }
            
            // Vérification de la variable pour activer le buzzer
            if ($violationRecorded) {
                // Code pour activer le buzzer, par exemple :
                // $esp_url = 'http://adresse_ip_esp/activate_buzzer';
                // file_get_contents($esp_url);
                
                echo json_encode([
                    'status' => 'violation_recorded',
                    'speed_limit' => $speed_limit,
                    'your_speed' => $speed,
                    'segment_id' => $segment_id
                ]);
            } else {
                echo json_encode([
                    'status' => 'ok',
                    'speed_limit' => $speed_limit,
                    'your_speed' => $speed
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid HTTP method']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'db_error', 'message' => $e->getMessage()]);
}
?>
