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
    public function fetch($sql, $params = []);
}

class PDODatabase implements DatabaseInterface
{
    private $pdo;

    public function __construct($dsn, $user, $pass, $options = [])
    {
        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            $this->handleError("Veritabanı bağlantısı başarısız: " . $e->getMessage());
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

    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function handleError($message)
    {
        error_log($message);
        echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
        exit();
    }
}

class CacheManager
{
    private $cacheDir;
    private $cacheLifetime;

    public function __construct($cacheDir, $cacheLifetime = 3600)
    {
        $this->cacheDir = $cacheDir;
        $this->cacheLifetime = $cacheLifetime;
    }

    public function cacheData($key, $data)
    {
        $cacheFile = $this->getCacheFilePath($key);
        if (time() - @filemtime($cacheFile) > $this->cacheLifetime) {
            file_put_contents($cacheFile, json_encode($data));
        }
    }

    public function getCache($key)
    {
        $cacheFile = $this->getCacheFilePath($key);
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        return null;
    }

    private function getCacheFilePath($key)
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
    }
}

class CourierService
{
    private $db;
    private $cacheManager;

    public function __construct(DatabaseInterface $db, CacheManager $cacheManager)
    {
        $this->db = $db;
        $this->cacheManager = $cacheManager;
    }

    public function getCouriers()
    {
        $couriers = $this->cacheManager->getCache('couriers');
        if ($couriers === null) {
            $query = "SELECT id, latitude, longitude FROM couriers WHERE active_package = 1";
            $couriers = $this->db->fetchAll($query);
            $this->cacheManager->cacheData('couriers', $couriers);
        }
        return $couriers;
    }

    public function findBestCourierForOrder($order, $couriers)
    {
        if (empty($couriers)) {
            throw new Exception("Kuryeler listesi boş.");
        }

        $shortestDistance = PHP_INT_MAX;
        $bestCourier = null;

        foreach ($couriers as $courier) {
            $distance = calculateDistance(
                $order['latitude'],
                $order['longitude'],
                $courier['latitude'],
                $courier['longitude']
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
                $bestCourier = $courier;
            }
        }

        if ($bestCourier === null) {
            throw new Exception("Uygun kurye bulunamadı.");
        }

        return $bestCourier;
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function validateCoordinate($coordinate)
{
    return is_numeric($coordinate) && $coordinate >= -90 && $coordinate <= 90;
}

function handleError($message)
{
    error_log($message);
    echo "Bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    exit();
}

function main(DatabaseInterface $database, CacheManager $cacheManager)
{
    try {
        $courierService = new CourierService($database, $cacheManager);
        $couriers = $courierService->getCouriers();

        $orders = $database->fetchAll("SELECT id, latitude, longitude, package_type FROM orders");

        foreach ($orders as $order) {
            if (!validateCoordinate($order['latitude']) || !validateCoordinate($order['longitude'])) {
                handleError("Geçersiz koordinat verisi.");
            }

            $bestCourier = $courierService->findBestCourierForOrder($order, $couriers);

            if ($bestCourier) {
                echo "Sipariş ID: {$order['id']} için en yakın kurye ID: {$bestCourier['id']} seçilmiştir.<br>";
            } else {
                echo "Sipariş ID: {$order['id']} için uygun kurye bulunamadı.<br>";
            }
        }
    } catch (Exception $e) {
        handleError("Bir hata oluştu: " . $e->getMessage());
    }
}

$dsn = "mysql:host=localhost;dbname=veritabani;charset=utf8";
$user = "kullanici";
$pass = "sifre";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$database = new PDODatabase($dsn, $user, $pass, $options);

$cacheManager = new CacheManager(__DIR__ . '/cache');

main($database, $cacheManager);
