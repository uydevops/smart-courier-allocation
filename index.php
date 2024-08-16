<?php
/**
 * Altf4 Yazılım
 * Uğurcan Yaş
 * Software Architect
 */

$host = 'localhost';
$db = 'your_database';
$user = 'your_username';
$pass = 'your_password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    handleError("Veritabanı bağlantısı kurulamadı: " . $e->getMessage());
}

function handleError($message) {
    error_log($message); 
    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    exit();
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; 

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function getCouriers($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, latitude, longitude, active_package FROM couriers");
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        handleError("Kuryeler alınamadı: " . $e->getMessage());
    }
}

function getOrders($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, latitude, longitude, package_type FROM orders");
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        handleError("Siparişler alınamadı: " . $e->getMessage());
    }
}

function findBestCourierForOrder($order, $couriers) {
    if (empty($couriers)) {
        handleError("Kurierler listesi boş.");
    }

    $validCouriers = [];
    $shortestDistance = PHP_INT_MAX;

    foreach ($couriers as $courier) {
        if (!$courier['active_package']) {
            continue;
        }

        $distance = calculateDistance(
            $order['latitude'], $order['longitude'], $courier['latitude'], $courier['longitude']
        );

        if ($distance < $shortestDistance) {
            $shortestDistance = $distance;
            $validCouriers = [ $courier ]; 
        } elseif ($distance == $shortestDistance) {
            $validCouriers[] = $courier;
        }
    }

    if (empty($validCouriers)) {
        handleError("Uygun kurye bulunamadı.");
    }

    return $validCouriers[0];
}

function cacheData($key, $data) {
    $cacheFile = __DIR__ . "/cache/{$key}.json";
    file_put_contents($cacheFile, json_encode($data));
}

function getCache($key) {
    $cacheFile = __DIR__ . "/cache/{$key}.json";
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

// İşlem
try {
    $couriers = getCache('couriers');
    if ($couriers === null) {
        $couriers = getCouriers($pdo);
        cacheData('couriers', $couriers);
    }

    $orders = getOrders($pdo);

    foreach ($orders as $order) {
        $bestCourier = findBestCourierForOrder($order, $couriers);

        if ($bestCourier) {
            echo "Sipariş ID: {$order['id']} için en yakın kurye ID: {$bestCourier['id']} seçilmiştir.<br>";
        } else {
            echo "Sipariş ID: {$order['id']} için uygun kurye bulunamadı.<br>";
        }
    }
} catch (Exception $e) {
    handleError("Bir hata oluştu: " . $e->getMessage());
}
?>
