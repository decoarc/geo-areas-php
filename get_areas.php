<?php
header('Content-Type: application/json');
require 'connection.php';

$sql = "SELECT id, name, description, coords, created_at FROM areas ORDER BY created_at DESC";
$res = mysqli_query($conn, $sql);
$areas = [];
while ($row = mysqli_fetch_assoc($res)) {
    $areas[] = $row;
}
echo json_encode($areas);
?>