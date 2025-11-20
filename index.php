<?php
// =============================================
// CONFIGURACIÓN Y CONEXIÓN A BASE DE DATOS
// =============================================
session_start();

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "luvistore_manager";

// Inicializar variables
$db_error = false;
$mensaje = "";
$productos = [];
$ventas = [];
$usuario_actual = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
$pagina_actual = isset($_GET['pagina']) ? $_GET['pagina'] : 'login';

// Intentar conexión a base de datos
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }
} catch (Exception $e) {
    $db_error = true;
    // Crear base de datos temporal en sesión
    if (!isset($_SESSION['productos_temp'])) {
        $_SESSION['productos_temp'] = [
            ['id' => 1, 'codigo' => 'ARROZ-001', 'nombre' => 'Arroz', 'precio' => 12.50, 'descripcion' => 'Arroz blanco 1kg', 'stock' => 50, 'categoria' => 'Granos'],
            ['id' => 2, 'codigo' => 'FRIJOL-002', 'nombre' => 'Frijol', 'precio' => 18.00, 'descripcion' => 'Frijol negro 1kg', 'stock' => 40, 'categoria' => 'Granos'],
            ['id' => 3, 'codigo' => 'ACEITE-003', 'nombre' => 'Aceite', 'precio' => 25.00, 'descripcion' => 'Aceite vegetal 1L', 'stock' => 30, 'categoria' => 'Aceites'],
            ['id' => 4, 'codigo' => 'AZUCAR-004', 'nombre' => 'Azúcar', 'precio' => 15.50, 'descripcion' => 'Azúcar refinada 1kg', 'stock' => 60, 'categoria' => 'Endulzantes']
        ];
    }
    if (!isset($_SESSION['ventas_temp'])) {
        $_SESSION['ventas_temp'] = [];
    }
}

// Procesar login
if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    // Validación simple
    if (($user === 'admin' && $pass === 'admin123') || 
        ($user === 'empleado' && $pass === 'empleado123')) {
        
        $_SESSION['usuario'] = [
            'username' => $user,
            'tipo' => ($user === 'admin') ? 'admin' : 'empleado',
            'nombre' => ($user === 'admin') ? 'Administrador' : 'Empleado'
        ];
        $usuario_actual = $_SESSION['usuario'];
        $pagina_actual = ($user === 'admin') ? 'inventario' : 'ventas';
        $mensaje = "Bienvenido " . $usuario_actual['nombre'];
    } else {
        $mensaje = "Error: Usuario o contraseña incorrectos";
    }
}

// Procesar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// FUNCIONALIDADES DE ADMINISTRADOR
if ($usuario_actual && $usuario_actual['tipo'] === 'admin') {
    
    // RF-001: Agregar producto
    if (isset($_POST['agregar_producto'])) {
        $codigo = $_POST['codigo'];
        $nombre = $_POST['nombre'];
        $descripcion = $_POST['descripcion'];
        $precio = floatval($_POST['precio']);
        $stock = intval($_POST['stock']);
        $categoria = $_POST['categoria'];
        
        if (!$db_error) {
            // Con base de datos real
            $sql = "INSERT INTO productos (codigo, nombre, descripcion, precio, stock, categoria) 
                    VALUES ('$codigo', '$nombre', '$descripcion', $precio, $stock, '$categoria')";
            
            if ($conn->query($sql)) {
                $mensaje = "✅ Producto agregado exitosamente!";
            } else {
                $mensaje = "❌ Error al agregar producto: " . $conn->error;
            }
        } else {
            // Con base de datos temporal
            $nuevo_id = count($_SESSION['productos_temp']) + 1;
            $_SESSION['productos_temp'][] = [
                'id' => $nuevo_id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'precio' => $precio,
                'descripcion' => $descripcion,
                'stock' => $stock,
                'categoria' => $categoria
            ];
            $mensaje = "✅ Producto agregado exitosamente! (Modo temporal)";
        }
    }
    
    // RF-003: Eliminar producto
    if (isset($_GET['eliminar_producto'])) {
        $id = intval($_GET['eliminar_producto']);
        
        if (!$db_error) {
            $sql = "DELETE FROM productos WHERE id = $id";
            
            if ($conn->query($sql)) {
                $mensaje = "✅ Producto eliminado exitosamente!";
            } else {
                $mensaje = "❌ Error al eliminar producto";
            }
        } else {
            // Eliminar de array temporal
            $_SESSION['productos_temp'] = array_filter($_SESSION['productos_temp'], function($p) use ($id) {
                return $p['id'] != $id;
            });
            $mensaje = "✅ Producto eliminado exitosamente! (Modo temporal)";
        }
    }
}

// FUNCIONALIDADES DE VENTAS (EMPLEADOS)
if ($usuario_actual && $usuario_actual['tipo'] === 'empleado') {
    
    // Procesar venta
    if (isset($_POST['procesar_venta'])) {
        $productos_venta = $_POST['productos'] ?? [];
        $metodo_pago = $_POST['metodo_pago'];
        $total = 0;
        
        foreach ($productos_venta as $producto_id => $cantidad) {
            if ($cantidad > 0) {
                // Buscar producto y calcular total
                if (!$db_error) {
                    $sql = "SELECT * FROM productos WHERE id = $producto_id";
                    $result = $conn->query($sql);
                    if ($producto = $result->fetch_assoc()) {
                        $total += $producto['precio'] * $cantidad;
                    }
                } else {
                    foreach ($_SESSION['productos_temp'] as $producto) {
                        if ($producto['id'] == $producto_id) {
                            $total += $producto['precio'] * $cantidad;
                            break;
                        }
                    }
                }
            }
        }
        
        if ($total > 0) {
            $nueva_venta = [
                'id' => count($_SESSION['ventas_temp']) + 1,
                'fecha' => date('Y-m-d H:i:s'),
                'total' => $total,
                'metodo_pago' => $metodo_pago,
                'productos' => $productos_venta
            ];
            $_SESSION['ventas_temp'][] = $nueva_venta;
            $mensaje = "✅ Venta procesada exitosamente! Total: $" . number_format($total, 2);
        }
    }
}

// CARGAR DATOS
if (!$db_error && isset($conn)) {
    // Cargar productos de base de datos real
    $sql = "SELECT * FROM productos WHERE activo = 1 ORDER BY nombre";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    }
} else {
    // Usar datos temporales
    $productos = $_SESSION['productos_temp'];
    $ventas = $_SESSION['ventas_temp'];
}

// Cerrar conexión si existe
if (!$db_error && isset($conn)) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuvíStore Manager - Sistema de Gestión</title>
    <style>
        /* ESTILOS GENERALES */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ESTILOS PARA PANTALLAS DE LOGIN */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        }
        
        .login-box {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #2575fc;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #2575fc;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #2575fc;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            padding: 8px 15px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* INVENTARIO */
        .inventory-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .inventory-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2575fc;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .edit-btn, .delete-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .edit-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        
        /* FORMULARIOS */
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .form-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2575fc;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        
        .save-btn {
            padding: 10px 20px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .cancel-btn {
            padding: 10px 20px;
            background-color: #9e9e9e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* MENSAJES */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* NAVEGACIÓN */
        .nav-menu {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .nav-btn {
            padding: 10px 20px;
            background-color: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-btn.active {
            background-color: #2575fc;
            color: white;
        }
        
        /* VENTAS */
        .sales-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .product-card {
            background-color: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
        }
        
        .product-card:hover {
            background-color: #e3f2fd;
            border-color: #2575fc;
        }
    </style>
</head>
<body>
    <?php if (!$usuario_actual): ?>
    <!-- PANTALLA DE LOGIN -->
    <div class="login-container">
        <div class="login-box">
            <div class="logo">LUVÍSTORE MANAGER</div>
            <h2>INICIO DE SESIÓN</h2>
            
            <?php if ($mensaje): ?>
                <div class="alert <?php echo strpos($mensaje, 'Error') !== false ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($db_error): ?>
                <div class="alert alert-warning">
                    ⚠ Modo temporal: Base de datos no disponible
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ingrese su usuario" required value="admin">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required value="admin123">
                </div>
                
                <div style="text-align: left; font-size: 12px; color: #666; background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>Credenciales de prueba:</strong><br>
                    Admin: admin / admin123<br>
                    Empleado: empleado / empleado123
                </div>
                
                <button type="submit" class="login-btn" name="login">INICIAR SESIÓN</button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- HEADER PRINCIPAL -->
    <div class="header">
        <div class="header-title">LUVÍSTORE MANAGER</div>
        <div class="user-info">
            <div class="user-name"><?php echo $usuario_actual['nombre']; ?> (<?php echo $usuario_actual['tipo']; ?>)</div>
            <a href="?logout=true" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- MENÚ DE NAVEGACIÓN -->
        <div class="nav-menu">
            <?php if ($usuario_actual['tipo'] === 'admin'): ?>
                <a href="?pagina=inventario" class="nav-btn <?php echo $pagina_actual === 'inventario' ? 'active' : ''; ?>">📦 INVENTARIO</a>
                <a href="?pagina=ventas" class="nav-btn <?php echo $pagina_actual === 'ventas' ? 'active' : ''; ?>">💰 VENTAS</a>
                <a href="?pagina=reportes" class="nav-btn <?php echo $pagina_actual === 'reportes' ? 'active' : ''; ?>">📊 REPORTES</a>
            <?php else: ?>
                <a href="?pagina=ventas" class="nav-btn <?php echo $pagina_actual === 'ventas' ? 'active' : ''; ?>">💰 PUNTO DE VENTA</a>
            <?php endif; ?>
        </div>

        <!-- MENSAJES DEL SISTEMA -->
        <?php if ($mensaje): ?>
            <div class="alert <?php echo strpos($mensaje, '❌') !== false ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
            <div class="alert alert-warning">
                ⚠ <strong>Modo temporal activado:</strong> Los datos se guardan en sesión temporalmente.
            </div>
        <?php endif; ?>

        <!-- PANTALLA DE INVENTARIO -->
        <?php if ($pagina_actual === 'inventario' && $usuario_actual['tipo'] === 'admin'): ?>
            
            <!-- Formulario para agregar producto -->
            <div class="form-container">
                <div class="form-title">AGREGAR NUEVO PRODUCTO</div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="codigo">Código del Producto *</label>
                            <input type="text" id="codigo" name="codigo" placeholder="Ej: ARROZ-001" required>
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre del Producto *</label>
                            <input type="text" id="nombre" name="nombre" placeholder="Ej: Arroz blanco" required>
                        </div>
                        <div class="form-group">
                            <label for="precio">Precio *</label>
                            <input type="number" id="precio" name="precio" step="0.01" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Inicial *</label>
                            <input type="number" id="stock" name="stock" placeholder="0" required>
                        </div>
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select id="categoria" name="categoria">
                                <option value="Sabritas">Antojos</option>
                                <option value="Aceites">Aceites</option>
                                <option value="Sabritas">Neveria</option>
                                <option value="Lácteos">Lácteos</option>
                                <option value="Limpieza">Limpieza</option>
                                <option value="Cuidado Personal">Cuidado Personal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <input type="text" id="descripcion" name="descripcion" placeholder="Descripción del producto">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="reset" class="cancel-btn">LIMPIAR</button>
                        <button type="submit" class="save-btn" name="agregar_producto">GUARDAR PRODUCTO</button>
                    </div>
                </form>
            </div>

            <!-- Lista de productos -->
            <div class="inventory-container">
                <div class="inventory-title">INVENTARIO ACTUAL (<?php echo count($productos); ?> productos)</div>

                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Precio</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Stock</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #666;">
                                    No hay productos en el inventario
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><strong><?php echo $producto['codigo']; ?></strong></td>
                                <td><?php echo $producto['nombre']; ?></td>
                                <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                <td><?php echo $producto['descripcion']; ?></td>
                                <td><?php echo $producto['categoria']; ?></td>
                                <td><?php echo $producto['stock']; ?></td>
                                <td class="action-buttons">
                                    <a href="?pagina=inventario&eliminar_producto=<?php echo $producto['id']; ?>" 
                                       class="delete-btn" 
                                          onclick="return confirm('¿Está seguro de eliminar este producto?')">
                                        ELIMINAR
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- PANTALLA DE VENTAS -->
        <?php elseif ($pagina_actual === 'ventas'): ?>
            
            <div class="sales-container">
                <div class="inventory-container">
                    <div class="inventory-title">PRODUCTOS DISPONIBLES</div>
                    <div class="products-grid">
                        <?php foreach ($productos as $producto): ?>
                        <div class="product-card">
                            <div class="product-name"><?php echo $producto['nombre']; ?></div>
                            <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                            <div style="font-size: 12px; color: #666;">Stock: <?php echo $producto['stock']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="inventory-container">
                    <div class="inventory
                    -title">CARRITO DE COMPRAS</div>
                    <p style="color: #666; margin-bottom: 20px;">Funcionalidad de carrito en desarrollo...</p>
                </div>
            </div>

        <!-- PANTALLA DE REPORTES -->
        <?php elseif ($pagina_actual === 'reportes' && $usuario_actual['tipo'] === 'admin'): ?>
            
            <div class="inventory-container">
                <div class="inventory-title">REPORTES DEL SISTEMA</div>
                <div style="padding: 20px; text-align: center; color: #666;">
                    <h3>📊 Módulo de Reportes</h3>
                    <p>Esta funcionalidad estará disponible en la próxima versión</p>
                </div>
            </div>

        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>


