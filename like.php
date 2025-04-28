<?php
include 'db.php';

if (isset($_POST['photo_id'])) {
    $photo_id = intval($_POST['photo_id']);
    $conn->query("UPDATE photos SET likes = likes + 1 WHERE id = $photo_id");

    $result = $conn->query("SELECT likes FROM photos WHERE id = $photo_id");
    $row = $result->fetch_assoc();
    echo $row['likes'];
}
?>
