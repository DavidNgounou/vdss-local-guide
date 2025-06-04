<?php
// Paramètres de connexion Azure PostgreSQL
$host = 'vehicle-decison-support.postgres.database.azure.com';
$db = 'postgres';
$user = 'superUser@vehicle-decison-support'; // Pour Azure
$pass = 'Vdss2468.';
$port = '5432';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if (isset($_GET['lat'], $_GET['lng'], $_GET['speed'])) {
        // INSERTION
        $stmt = $pdo->prepare("INSERT INTO gps_data (latitude, longitude, speed_kmh) VALUES (?, ?, ?)");
        $stmt->execute([$_GET['lat'], $_GET['lng'], $_GET['speed']]);
        echo "Données insérées avec succès.";

    } elseif (isset($_GET['latest'])) {
        // LECTURE
        $stmt = $pdo->query("SELECT speed_kmh FROM gps_data ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $result ? $result['speed_kmh'] : "0";
    } else {
        echo "Paramètres invalides.";
    }

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>

