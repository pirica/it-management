<?php
$host = "127.0.0.1";
$user = "root";
$pass = "itmanagement";
$db   = "itmanagement";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("❌ Connection failed: " . mysqli_connect_error());
}


$result = mysqli_query($conn, "SHOW TABLES");
echo mysqli_num_rows($result);
?>
