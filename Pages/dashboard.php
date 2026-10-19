<?php 
require_once '../PHP/connection.php';

$qDocentes = $pdo->query("SELECT COUNT(DISTINCT p.id) FROM personal p JOIN personal_cargos pc ON p.id = pc.personal_id JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id WHERE c.tipo_personal_id = 1 AND pc.estado_cargo = 'Vigente'")->fetchColumn();
$qAdmins = $pdo->query("SELECT COUNT(DISTINCT p.id) FROM personal p JOIN personal_cargos pc ON p.id = pc.personal_id JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id WHERE c.tipo_personal_id = 2 AND pc.estado_cargo = 'Vigente'")->fetchColumn();
$qObreros = $pdo->query("SELECT COUNT(DISTINCT p.id) FROM personal p JOIN personal_cargos pc ON p.id = pc.personal_id JOIN cat_cargos_areas c ON pc.cargo_area_id = c.id WHERE c.tipo_personal_id = 3 AND pc.estado_cargo = 'Vigente'")->fetchColumn();

include '../Includes/header.php'; include '../Includes/sidebar.php'; 
?>
<h2 class="mb-4">Panel Principal de Personal</h2>
<div class="row">
    <div class="col-md-4 mb-3"><div class="card bg-primary text-white shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Docentes</h5><h2 class="mb-0"><?= $qDocentes ?></h2></div><i class="bi bi-book fs-1 opacity-50"></i></div></div></div>
    <div class="col-md-4 mb-3"><div class="card bg-success text-white shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Administrativos</h5><h2 class="mb-0"><?= $qAdmins ?></h2></div><i class="bi bi-pc-display fs-1 opacity-50"></i></div></div></div>
    <div class="col-md-4 mb-3"><div class="card bg-warning text-dark shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h5 class="card-title">Obreros</h5><h2 class="mb-0"><?= $qObreros ?></h2></div><i class="bi bi-tools fs-1 opacity-50"></i></div></div></div>
</div>
<?php include '../Includes/footer.php'; ?>