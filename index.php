<?php
// Paramètres de connexion à la base de données
$host = 'vehicle-decison-support.postgres.database.azure.com';
$db = 'postgres';
$user = 'superUser@vehicle-decison-support';
$pass = 'Vdss2468.';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Récupération des données JSON envoyées par le module GPS
    $data = json_decode(file_get_contents("php://input"), true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification des paramètres requis
        if (isset($data['lat'], $data['lng'], $data['speed'], $data['plate'])) {
            $lat = $data['lat'];
            $lng = $data['lng'];
            $speed = $data['speed'];
            $plate = $data['plate'];

            // Création du point géographique
            $pointWKT = sprintf("POINT(%s %s)", $lng, $lat);

            // Insertion des données GPS dans la table gps_data
            $stmt = $pdo->prepare("INSERT INTO gps_data (car_plate_number, position, speed_kmh) VALUES (?, ST_GeomFromText(?, 4326), ?)");
            $stmt->execute([$plate, $pointWKT, $speed]);

            // Récupération du segment de route correspondant
            $stmt = $pdo->prepare("
                SELECT id, speed_limit
                FROM road_segment
                WHERE ST_DWithin(road_coordinates, ST_GeomFromText(?, 4326), 0.0005)
                ORDER BY ST_Distance(road_coordinates, ST_GeomFromText(?, 4326))
                LIMIT 1
            ");
            $stmt->execute([$pointWKT, $pointWKT]);
            $segment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($segment) {
                echo json_encode([
                    'status' => 'success',
                    'speed_limit' => $segment['speed_limit'],
                    'segment_id' => $segment['id']
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'speed_limit' => null,
                    'segment_id' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres manquants']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Récupération des données GET
        if (isset($_GET['lat'], $_GET['lng'])) {
            $lat = $_GET['lat'];
            $lng = $_GET['lng'];
            $pointWKT = sprintf("POINT(%s %s)", $lng, $lat);

            // Récupération du segment de route correspondant
            $stmt = $pdo->prepare("
                SELECT id, speed_limit
                FROM road_segment
                WHERE ST_DWithin(road_coordinates, ST_GeomFromText(?, 4326), 0.0005)
                ORDER BY ST_Distance(road_coordinates, ST_GeomFromText(?, 4326))
                LIMIT 1
            ");
            $stmt->execute([$pointWKT, $pointWKT]);
            $segment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($segment) {
                echo json_encode([
                    'speed_limit' => $segment['speed_limit'],
                    'segment_id' => $segment['id']
                ]);
            } else {
                echo json_encode([
                    'speed_limit' => null,
                    'segment_id' => null
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres lat et lng requis']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
