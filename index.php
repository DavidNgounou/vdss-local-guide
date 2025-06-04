<?php
// Connexion PostgreSQL + PostGIS
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
        // POST simple : GPS envoie lat,lng,speed,plate et on retourne vitesse limite
        if (isset($data['lat'], $data['lng'], $data['speed'], $data['plate']) && !isset($data['violation_trace'])) {
            $lat = $data['lat'];
            $lng = $data['lng'];
            $speed = $data['speed'];
            $plate = $data['plate'];
            $pointWKT = sprintf("POINT(%s %s)", $lng, $lat);

            // Recherche segment proche
            $stmt = $pdo->prepare("
                SELECT rs.id, sl.speed_limit
                FROM road_segment rs
                JOIN speed_limit sl ON rs.speed_limit_id = sl.id
                WHERE ST_DWithin(rs.road_coordinates, ST_GeomFromText(?, 4326), 0.0005)
                ORDER BY ST_Distance(rs.road_coordinates, ST_GeomFromText(?, 4326))
                LIMIT 1
            ");
            $stmt->execute([$pointWKT, $pointWKT]);
            $segment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($segment) {
                $segment_id = $segment['id'];
                $speed_limit = (int)$segment['speed_limit'];
                
                // Réponse vitesse limite au module Arduino
                echo json_encode([
                    'status' => 'ok',
                    'speed_limit' => $speed_limit,
                    'segment_id' => $segment_id
                ]);
                exit;
            } else {
                echo json_encode(['status' => 'no_segment_found']);
                exit;
            }
        }
        // POST violation : Arduino envoie la trace complète du dépassement
        elseif (isset($data['violation_trace'], $data['speed'], $data['plate'], $data['segment_id'])) {
            $traceWKT = $data['violation_trace']; // Ex: "LINESTRING(lon1 lat1, lon2 lat2, ...)"
            $speed = (int)$data['speed'];
            $plate = $data['plate'];
            $segment_id = (int)$data['segment_id'];

            $stmt = $pdo->prepare("
                INSERT INTO exceed_speed_limit (
                    time_of_violation,
                    running_speed,
                    number_of_times_warned,
                    positions_of_sp_violation,
                    road_segment_id,
                    car_plate_number
                ) VALUES (
                    CURRENT_TIMESTAMP, ?, 1, ST_GeomFromText(?, 4326), ?, ?
                )
            ");
            $stmt->execute([$speed, $traceWKT, $segment_id, $plate]);

            echo json_encode(['status' => 'violation_recorded']);
            exit;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants ou incorrects']);
            exit;
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'db_error', 'message' => $e->getMessage()]);
}
?>
