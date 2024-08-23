<?php
/**
 * Altf4 Yazılım
 * Uğurcan Yaş
 * Software Architect
 */

require_once __DIR__ . '/config.php'; 

interface DatabaseInterface
{
    public function query($sql, $params = []);
    public function fetchAll($sql, $params = []);
}

class PDODatabase implements DatabaseInterface
{
    private $pdo;

    public function __construct($dsn, $user, $pass, $options)
    {
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            $this->handleError("Veritabanı bağlantısı kurulamadı: " . $e->getMessage());
        }
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function handleError($message)
    {
        error_log($message);
        echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        exit();
    }
}

$database = new PDODatabase($DB_DSN, $DB_USER, $DB_PASS, $DB_OPTIONS);


function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371; // Kilometre cinsinden dünya yarıçapı

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}
function getCouriers(DatabaseInterface $db)
{
    $query = "SELECT id, latitude, longitude FROM couriers WHERE active_package = 1";
    try {
        return $db->fetchAll($query);
    } catch (\PDOException $e) {
        handleError("Kuryeler alınamadı: " . $e->getMessage());
    }
}

function getOrders(DatabaseInterface $db)
{
    $query = "SELECT id, latitude, longitude, package_type FROM orders";
    try {
        return $db->fetchAll($query);
    } catch (\PDOException $e) {
        handleError("Siparişler alınamadı: " . $e->getMessage());
    }
}


function findBestCourierForOrder($order, $couriers)
{
    if (empty($couriers)) {
        handleError("Kuryeler listesi boş.");
    }

    $validCouriers = [];
    $shortestDistance = PHP_INT_MAX;

    foreach ($couriers as $courier) {
        $distance = calculateDistance(
            $order['latitude'],
            $order['longitude'],
            $courier['latitude'],
            $courier['longitude']
        );

        if ($distance < $shortestDistance) {
            $shortestDistance = $distance;
            $validCouriers = [$courier];
        } elseif ($distance == $shortestDistance) {
            $validCouriers[] = $courier;
        }
    }

    if (empty($validCouriers)) {
        handleError("Uygun kurye bulunamadı.");
    }

    return $validCouriers[0];
}


function cacheData($key, $data)
{
    $cacheFile = __DIR__ . "/cache/{$key}.json";
    $cacheLifetime = 3600; // Önbelleğin geçerlilik süresi (saniye)
    $cacheTime = file_exists($cacheFile) ? filemtime($cacheFile) : 0;

    if (time() - $cacheTime > $cacheLifetime) {
        file_put_contents($cacheFile, json_encode($data));
    }
}


function getCache($key)
{
    $cacheFile = __DIR__ . "/cache/{$key}.json";
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

function handleError($message)
{
    error_log($message);
    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    exit();
}


function validateCoordinate($coordinate)
{
    return is_numeric($coordinate) && $coordinate >= -90 && $coordinate <= 90;
}

function sanitizeInput($input)
{
    return htmlspecialchars(strip_tags($input));
}

try {
    $couriers = getCache('couriers');
    if ($couriers === null) {
        $couriers = getCouriers($database);
        cacheData('couriers', $couriers);
    }

    $orders = getOrders($database);

    foreach ($orders as $order) {
        if (!validateCoordinate($order['latitude']) || !validateCoordinate($order['longitude'])) {
            handleError("Geçersiz koordinat verisi.");
        }

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
