<?php
$servidor = "localhost";
$usuario = "root";
$password = "";
$basedatos = "Luvi_Manager";

// Crear conexión
$conexion = new mysqli($servidor, $usuario, $password, $basedatos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
echo "Conexión exitosa";
?>