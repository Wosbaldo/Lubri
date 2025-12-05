<?php
// Asegúrate de que 'db.php' contiene la conexión a tu base de datos ($conn)
require 'db.php';

// Si viene un POST desde EDITAR (fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id                  = (int)$_POST['id'];
    $registration_number = $_POST['registration_number'];
    $license_plate       = $_POST['license_plate'];
    $brand               = $_POST['brand'];
    $model               = $_POST['model'];
    $faults_found        = $_POST['faults_found'] ?? '';
    $parts_changed       = $_POST['parts_changed'] ?? '';
    $parts_added         = $_POST['parts_added'] ?? '';
    $recommendation      = $_POST['recommendation'] ?? '';
    $delivery_date       = $_POST['delivery_date'];
    $mechanic_name       = $_POST['mechanic_name'];
    $ultima_edicion      = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE vehiculos SET
        registration_number=?, license_plate=?, brand=?, model=?, faults_found=?,
        parts_changed=?, parts_added=?, recommendation=?, delivery_date=?,
        mechanic_name=?, ultima_edicion=?
        WHERE id=?");
    $stmt->bind_param(
        "sssssssssssi",
        $registration_number,
        $license_plate,
        $brand,
        $model,
        $faults_found,
        $parts_changed,
        $parts_added,
        $recommendation,
        $delivery_date,
        $mechanic_name,
        $ultima_edicion,
        $id
    );
    if ($stmt->execute()) {
        echo "ok";
    } else {
        error_log("Error al editar: " . $stmt->error);
        echo "error: " . $stmt->error;
    }
    $stmt->close();
    exit;
}

// Si viene un POST desde el formulario "Nueva ficha"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'nuevo') {
    $client_name         = $_POST['client_name'];
    $registration_number = $_POST['registration_number'];
    $license_plate       = $_POST['license_plate'];
    $brand               = $_POST['brand'];
    $model               = $_POST['model'];
    $faults_found        = $_POST['faults_found'] ?? '';
    $parts_changed       = $_POST['parts_changed'] ?? '';
    $parts_added         = $_POST['parts_added'] ?? '';
    $recommendation      = $_POST['recommendation'] ?? '';
    $delivery_date       = $_POST['delivery_date'];
    $mechanic_name       = $_POST['mechanic_name'];
    $ultima_edicion      = date('Y-m-d H:i:s');

    // --- Manejo de las 4 fotos subidas ---
    $fotos = [];
    $campos_fotos = ['foto', 'foto2', 'foto3', 'foto4']; // Nombres de los campos FILE
    $rutaCarpeta = __DIR__ . '/uploads/';
    
    // Crear carpeta si no existe
    if (!is_dir($rutaCarpeta)) {
        mkdir($rutaCarpeta, 0777, true);
    }
    $permitidas = ['jpg','jpeg','png','gif','webp'];

    foreach ($campos_fotos as $i => $campo) {
        $foto_url = null;
        
        if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
            $nombreTmp = $_FILES[$campo]['tmp_name'];
            $extension = pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION);
            $extension = strtolower($extension);
            
            if (in_array($extension, $permitidas)) {
                $nombreArchivo = 'vehiculo_' . time() . '_' . rand(1000,9999) . '_f' . ($i + 1) . '.' . $extension;
                $rutaDestino   = $rutaCarpeta . $nombreArchivo;
        
                if (move_uploaded_file($nombreTmp, $rutaDestino)) {
                    $foto_url = 'uploads/' . $nombreArchivo;
                }
            }
        }

        // Si no se sube foto o hubo error, usar placeholder
        if (!$foto_url) {
            $texto_placa = urlencode($license_plate) . ' F' . ($i + 1);
            $foto_url = "https://via.placeholder.com/400x250/0056A6/ffffff?text=" . $texto_placa;
        }
        $fotos[] = $foto_url;
    }
    // Asignar las URLs a variables
    $foto1 = $fotos[0]; 
    $foto2 = $fotos[1];
    $foto3 = $fotos[2];
    $foto4 = $fotos[3];
    // --- Fin del Manejo de 4 fotos ---


    $stmt = $conn->prepare("INSERT INTO vehiculos
        (client_name, registration_number, license_plate, brand, model, faults_found,
         parts_changed, parts_added, recommendation, delivery_date, mechanic_name, ultima_edicion,
         foto, foto2, foto3, foto4) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        
    $stmt->bind_param(
        "ssssssssssssssss",
        $client_name,
        $registration_number,
        $license_plate,
        $brand,
        $model,
        $faults_found,
        $parts_changed,
        $parts_added,
        $recommendation,
        $delivery_date,
        $mechanic_name,
        $ultima_edicion,
        $foto1, 
        $foto2,
        $foto3,
        $foto4
    );

    if (!$stmt->execute()) {
        die("Error al insertar nueva ficha: (" . $stmt->errno . ") " . $stmt->error);
    }
    
    $stmt->close();

    header("Location: index.php");
    exit;
}


// --- LÓGICA PARA ENLACE DE SÓLO LECTURA (DEBE IR ANTES DE CARGAR TODOS LOS VEHÍCULOS) ---
$ver_solo_lectura = false;
$ficha_a_mostrar = null;

if (isset($_GET['ver']) && is_numeric($_GET['ver'])) {
    $id_ver = (int)$_GET['ver'];
    
    $stmt = $conn->prepare("SELECT * FROM vehiculos WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_ver);
        $stmt->execute();
        $result_ver = $stmt->get_result();
        
        if ($result_ver->num_rows > 0) {
            $ficha_a_mostrar = $result_ver->fetch_assoc();
            $ver_solo_lectura = true; // La bandera que usamos para ocultar la interfaz
        }
        $stmt->close();
    }
}
// --- FIN LÓGICA PARA ENLACE DE SÓLO LECTURA ---

// Obtener todos los vehículos SOLO si NO estamos en vista de solo lectura
$vehiculos = [];
if (!$ver_solo_lectura) {
    $result = $conn->query("SELECT * FROM vehiculos ORDER BY id DESC");
    while ($row = $result->fetch_assoc()) {
        $vehiculos[] = $row;
    }
    $result->free();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $ver_solo_lectura ? 'Ficha de Servicio - ' . htmlspecialchars($ficha_a_mostrar['license_plate'] ?? 'Vehículo') : 'Gestión de Fichas - Lubri Auto-Parts Limeño'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --azul:#0056A6;
            --amarillo:#F2B500;
        }
        body{ background:#f9f9fa; }
        .navbar{ background:var(--azul); }
        .navbar-brand{ font-size:1.1rem; }
        .logo-navbar{ height:38px; margin-right:.4rem; }
        .app-container{ max-width:480px; margin:0 auto; padding:0 .75rem 5rem; }
        .card-vehiculo{ border:0; border-radius:14px; overflow:hidden; box-shadow:0 4px 10px rgba(0,0,0,.06); }
        .card-vehiculo img{ height:170px; object-fit:cover; }
        .badge-mecanico{ background:var(--amarillo); color:#000; font-weight:600; }
        .btn-primary-custom{ background:var(--azul); border-color:var(--azul); }
        .btn-primary-custom:hover{ background:#003f78; border-color:#003f78; }
        .btn-accent{ background:var(--amarillo); border-color:var(--amarillo); color:#000; font-weight:600; }
        .btn-accent:hover{ background:#d99f00; border-color:#d99f00; color:#000; }
        .search-card{ border-radius:14px; border:0; box-shadow:0 4px 10px rgba(0,0,0,.06); }
        .search-icon{ color:var(--azul); }
        .modal-header.logo-header{ background:var(--azul); color:#fff; }
        .modal-header.logo-header .modal-title i{ color:var(--amarillo); }
        label{ font-size:.85rem; font-weight:600; }
        textarea{ resize:vertical; }
    </style>
</head>
<body>

<?php 
// 🔑 OCULTAR TODA LA INTERFAZ DE ADMINISTRACIÓN si es vista de solo lectura
if (!$ver_solo_lectura): 
?>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container-fluid" style="max-width:480px;">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="Logo01Taller-Photoroom.png" alt="Logo Lubri Auto-Parts LIMEÑO" class="logo-navbar">
            <span class="fw-bold text-uppercase">Lubri Auto-Parts Limeño</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse mt-2 mt-lg-0" id="navbarNav">
            <ul class="navbar-nav ms-auto small">
                <li class="nav-item">
                    <a class="nav-link active" href="#dashboard">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#nuevo" data-bs-toggle="modal" data-bs-target="#modalNuevo">
                        <i class="fas fa-plus-circle me-1"></i>Nuevo
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="app-container mt-3">

    <div class="card search-card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-bold">
                    <i class="fas fa-search search-icon me-2"></i>Buscar por placa
                </h6>
                <button class="btn btn-sm btn-link text-secondary p-0" type="button" onclick="limpiarBusqueda()">
                    Limpiar
                </button>
            </div>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-car text-muted"></i>
                </span>
                <input type="text" id="buscadorPlaca" class="form-control border-start-0"
                        placeholder="Ej: ABC-123" onkeyup="filtrarPorPlaca()">
            </div>
        </div>
    </div>

    <div class="d-grid mb-3">
        <button class="btn btn-accent rounded-pill" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            <i class="fas fa-plus me-2"></i>Nueva ficha de vehículo
        </button>
    </div>

    <div id="dashboard">
        <div id="listaVehiculos" class="row g-3">
            <?php if (count($vehiculos) === 0): ?>
                <p class="text-center text-muted small mt-3">No hay vehículos registrados.</p>
            <?php else: ?>
                <?php foreach ($vehiculos as $v): ?>
                    <div class="col-12">
                        <div class="card card-vehiculo">
                            <img src="<?php echo htmlspecialchars($v['foto']); ?>" class="card-img-top"
                                    alt="<?php echo htmlspecialchars($v['brand'].' '.$v['model']); ?>">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <?php echo htmlspecialchars($v['brand'].' '.$v['model']); ?>
                                    </h6>
                                    <span class="badge badge-mecanico small">
                                        <?php echo htmlspecialchars($v['mechanic_name']); ?>
                                    </span>
                                </div>
                                <p class="mb-0 small text-primary fw-bold">
                                    Placa: <?php echo htmlspecialchars($v['license_plate']); ?>
                                </p>
                                <p class="mb-1 small text-muted">
                                    Reg: <?php echo htmlspecialchars($v['registration_number']); ?>
                                </p>
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-wrench me-1 text-warning"></i>
                                    <?php echo $v['faults_found'] ? htmlspecialchars($v['faults_found']) : 'Sin fallas registradas'; ?>
                                </small>
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="text-success fw-semibold">
                                        <i class="fas fa-calendar-check me-1"></i>
                                        <?php echo htmlspecialchars($v['delivery_date']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-footer bg-white py-2">
                                <div class="d-flex justify-content-between align-items-center gap-1">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo htmlspecialchars($v['ultima_edicion']); ?>
                                    </small>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-secondary"
                                                    onclick='verFicha(<?php echo json_encode($v); ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-info text-white" 
                                                title="Copiar enlace de solo lectura"
                                                onclick="copiarEnlace(<?php echo $v['id']; ?>)">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary-custom"
                                                    onclick='abrirEditar(<?php echo json_encode($v); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// 🔒 Mensaje de bienvenida simple para el cliente
else: 
?>
<div class="app-container mt-5 text-center">
    <img src="Logo01Taller-Photoroom.png" alt="Logo Lubri Auto-Parts LIMEÑO" style="height: 60px; margin-bottom: 20px;">
    <h3 class="fw-bold text-muted">Detalle de Servicio</h3>
    <p class="text-secondary">Abriendo ficha de: **<?php echo htmlspecialchars($ficha_a_mostrar['client_name'] ?? 'Cliente'); ?>**</p>
    <?php if (!$ficha_a_mostrar): ?>
        <p class="text-danger mt-4">⚠️ Ficha no encontrada o el enlace es inválido.</p>
        <p class="small text-muted">Por favor, contacte al taller para validar el acceso.</p>
    <?php endif; ?>
</div>
<?php 
endif; 
?>

<?php if (!$ver_solo_lectura): ?>
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <div class="modal-header logo-header">
                <h5 class="modal-title">
                    <i class="fas fa-car-side me-2"></i>Nueva ficha de vehículo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVehiculo" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="nuevo">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12 mb-2">
                            <label>Nombre del Cliente:</label>
                            <input type="text" name="client_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Número de Registro:</label>
                            <input type="text" name="registration_number" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Número de Placa:</label>
                            <input type="text" name="license_plate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Marca:</label>
                            <input type="text" name="brand" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Modelo:</label>
                            <input type="text" name="model" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Fallas encontradas:</label>
                            <textarea name="faults_found" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Piezas cambiadas:</label>
                            <textarea name="parts_changed" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Piezas agregadas:</label>
                            <textarea name="parts_added" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Recomendación:</label>
                            <textarea name="recommendation" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Fecha de entrega:</label>
                            <input type="date" name="delivery_date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Mecánico:</label>
                            <input type="text" name="mechanic_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Foto 1 (Principal):</label>
                            <input type="file" name="foto" accept="image/*" capture="environment" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Foto 2:</label>
                            <input type="file" name="foto2" accept="image/*" capture="environment" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Foto 3:</label>
                            <input type="file" name="foto3" accept="image/*" capture="environment" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Foto 4:</label>
                            <input type="file" name="foto4" accept="image/*" capture="environment" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary-custom">Guardar ficha</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalVerEditar" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <div class="modal-header logo-header">
                <h5 class="modal-title">
                    <i class="fas fa-car me-2"></i><span id="modalTitulo"></span>
                </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditar">
                <div class="modal-body">
                    <input type="hidden" id="editId" name="id">
                    
                    <div class="mb-3" id="contenedorFotosModal">
                        <h6 class="fw-bold mb-2">Imágenes del vehículo</h6>
                        <div class="row g-2">
                            <div class="col-6 col-sm-3">
                                <label class="small text-muted mb-1 d-block text-center">Foto 1</label>
                                <img id="img_foto1" src="" class="img-fluid rounded border" alt="Foto 1" style="height:100px; object-fit:cover; width:100%;">
                            </div>
                            <div class="col-6 col-sm-3">
                                <label class="small text-muted mb-1 d-block text-center">Foto 2</label>
                                <img id="img_foto2" src="" class="img-fluid rounded border" alt="Foto 2" style="height:100px; object-fit:cover; width:100%;">
                            </div>
                            <div class="col-6 col-sm-3">
                                <label class="small text-muted mb-1 d-block text-center">Foto 3</label>
                                <img id="img_foto3" src="" class="img-fluid rounded border" alt="Foto 3" style="height:100px; object-fit:cover; width:100%;">
                            </div>
                            <div class="col-6 col-sm-3">
                                <label class="small text-muted mb-1 d-block text-center">Foto 4</label>
                                <img id="img_foto4" src="" class="img-fluid rounded border" alt="Foto 4" style="height:100px; object-fit:cover; width:100%;">
                            </div>
                        </div>
                        <hr>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-12 mb-2">
                            <label>Cliente:</label>
                            <input type="text" id="edit_client_name" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Número de Registro:</label>
                            <input type="text" name="registration_number" id="edit_reg_num" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Número de Placa:</label>
                            <input type="text" name="license_plate" id="edit_license_plate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Marca:</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Modelo:</label>
                            <input type="text" name="model" id="edit_model" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Fallas encontradas:</label>
                            <textarea name="faults_found" id="edit_faults" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Piezas cambiadas:</label>
                            <textarea name="parts_changed" id="edit_parts_changed" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Piezas agregadas:</label>
                            <textarea name="parts_added" id="edit_parts_added" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label>Recomendación:</label>
                            <textarea name="recommendation" id="edit_recom" class="form-control form-control-sm" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Fecha de entrega:</label>
                            <input type="date" name="delivery_date" id="edit_delivery" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label>Mecánico:</label>
                            <input type="text" name="mechanic_name" id="edit_mechanic" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">
                                <strong>Última edición:</strong> <span id="ultimaEdicion"></span>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-primary-custom">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filtrarPorPlaca(){
    const texto = document.getElementById('buscadorPlaca').value.toLowerCase();
    const cards = document.querySelectorAll('#listaVehiculos .card-vehiculo');
    cards.forEach(card => {
        const placa = card.querySelector('.text-primary').textContent.toLowerCase();
        card.parentElement.style.display = placa.includes(texto) ? '' : 'none';
    });
}
function limpiarBusqueda(){
    document.getElementById('buscadorPlaca').value = '';
    filtrarPorPlaca();
}

function llenarModal(v){
    document.getElementById('editId').value = v.id;
    // Nuevo campo para el nombre del cliente
    const clientNameInput = document.getElementById('edit_client_name');
    if (clientNameInput) {
        clientNameInput.value = v.client_name || 'N/A';
    }
    
    document.getElementById('edit_reg_num').value = v.registration_number;
    document.getElementById('edit_license_plate').value = v.license_plate;
    document.getElementById('edit_brand').value = v.brand;
    document.getElementById('edit_model').value = v.model;
    document.getElementById('edit_faults').value = v.faults_found || '';
    document.getElementById('edit_parts_changed').value = v.parts_changed || '';
    document.getElementById('edit_parts_added').value = v.parts_added || '';
    document.getElementById('edit_recom').value = v.recommendation || '';
    document.getElementById('edit_delivery').value = v.delivery_date;
    document.getElementById('edit_mechanic').value = v.mechanic_name || '';
    document.getElementById('ultimaEdicion').textContent = v.ultima_edicion || '';
    document.getElementById('modalTitulo').textContent = (v.client_name ? v.client_name + ' - ' : '') + v.brand + ' ' + v.model;


    // Cargar las 4 fotos
    document.getElementById('img_foto1').src = v.foto || '';
    document.getElementById('img_foto2').src = v.foto2 || '';
    document.getElementById('img_foto3').src = v.foto3 || '';
    document.getElementById('img_foto4').src = v.foto4 || '';
}

function verFicha(v){
    llenarModal(v);
    document.getElementById('modalTitulo').textContent += ' (Detalle)';

    // Deshabilitar todos los campos
    document.querySelectorAll('#formEditar input, #formEditar textarea')
        .forEach(el => el.setAttribute('disabled','disabled'));
    // Ocultar el botón de Guardar
    document.querySelector('#formEditar .btn-primary-custom').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('modalVerEditar')).show();
}

function abrirEditar(v){
    llenarModal(v);
    
    // Habilitar todos los campos (excepto el campo de cliente que está arriba)
    document.querySelectorAll('#formEditar input:not(#edit_client_name), #formEditar textarea')
        .forEach(el => el.removeAttribute('disabled'));
    // Mostrar el botón de Guardar
    document.querySelector('#formEditar .btn-primary-custom').style.display = 'inline-block';
    
    new bootstrap.Modal(document.getElementById('modalVerEditar')).show();
}

// Función para copiar el enlace de Solo Lectura
function copiarEnlace(id) {
    const enlace = window.location.origin + window.location.pathname + '?ver=' + id;
    
    navigator.clipboard.writeText(enlace)
        .then(() => {
            alert('✅ Enlace de solo lectura copiado:\n' + enlace);
        })
        .catch(err => {
            prompt('Enlace de solo lectura (copia manual - presiona Ctrl+C o Cmd+C):', enlace);
        });
}

// Enviar edición por POST (fetch) a index.php (misma página)
document.getElementById('formEditar').addEventListener('submit', function(e){
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion','editar');

    fetch('index.php', {
        method: 'POST',
        body: formData
    }).then(r => r.text())
      .then(res => {
          if(res.trim().startsWith('ok')){
              location.reload();
          }else{
              alert('Error al guardar: ' + res.trim());
          }
      });
});

// --- LÓGICA DE AUTOCARGADO DEL MODAL (Activación si hay ?ver=ID) ---
<?php if ($ver_solo_lectura && $ficha_a_mostrar): ?>
    // Convertir el array de PHP a un objeto JSON de JavaScript
    const fichaData = <?php echo json_encode($ficha_a_mostrar); ?>;
    
    // Abrir automáticamente el modal de vista de solo lectura
    setTimeout(() => {
        verFicha(fichaData);
    }, 100); 
<?php endif; ?>
</script>
</body>
</html>