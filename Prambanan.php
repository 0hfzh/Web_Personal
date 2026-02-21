<?php 
 
// Koneksi ke database 
$host = 'localhost'; 
$dbname = 'layarputih'; 
$username = 'root'; 
$password = ''; 
 
try { 
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
 
    // Query untuk mengambil data kecamatan dengan kolom SHAPE sebagai geometri 
    $sql = "SELECT ST_AsGeoJSON(SHAPE) as geometry, NAMOBJ as nama_desa FROM prambanan"; 
    $stmt = $conn->prepare($sql); 
    $stmt->execute(); 
     
    // Format data sebagai GeoJSON 
    $features = []; 
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
        $geometry = json_decode($row['geometry']); 
        $feature = [ 
            'type' => 'Feature', 
            'geometry' => $geometry, 
            'properties' => [ 
                'nama_desa' => $row['nama_desa'] 
            ] 
        ]; 
        array_push($features, $feature); 
    } 
     
    $featureCollection = [ 
        'type' => 'FeatureCollection', 
        'features' => $features 
    ]; 
     
    echo json_encode($featureCollection); 
 
} catch(PDOException $e) { 
    echo json_encode(['error' => $e->getMessage()]); 
}
$conn = null; 
?>  
