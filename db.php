<?php
$host = "localhost";
$user = "epiz_XXXXXX_admin";   // usuario por defecto en XAMPP
$pass = "Lubricentro123";       // normalmente vacío
$db   = "epiz_XXXXXX_fichas";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8");
?>
