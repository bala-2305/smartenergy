<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM energy_consumption WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$id, $user_id])) {
        $success = "Record deleted successfully!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Add/Edit Consumption
    if (isset($_POST['add_consumption']) || isset($_POST['edit_consumption'])) {
        $appliance_id = !empty($_POST['appliance_id']) ? intval($_POST['appliance_id']) : null;
        $custom_appliance = trim($_POST['custom_appliance']);
        $hours_used = floatval($_POST['hours_used']);
        $custom_wattage = isset($_POST['custom_wattage']) ? intval($_POST['custom_wattage']) : 0;
        $date = $_POST['date'];
        $appliance_name = '';
        $wattage = 0;

        if ($appliance_id) {
            $stmt = $pdo->prepare("SELECT name, estimated_wattage FROM appliances WHERE id = ?");
            $stmt->execute([$appliance_id]);
            $res = $stmt->fetch();
            $appliance_name = $res['name'];
            $wattage = $res['estimated_wattage'] ?? $custom_wattage;
        } else {
            $appliance_name = $custom_appliance;
            $wattage = $custom_wattage;
        }

        // Check if manual unit entry is provided
        if (isset($_POST['manual_units']) && !empty($_POST['manual_units'])) {
            $consumption_kwh = floatval($_POST['manual_units']);
            // If manual units, we can set wattage/hours to represent that consumption (Units = (W*H)/1000)
            if ($wattage > 0) {
                $hours_used = ($consumption_kwh * 1000) / $wattage;
            } else {
                $wattage = 1000;
                $hours_used = $consumption_kwh;
            }
        } else {
            $consumption_kwh = ($wattage * $hours_used) / 1000;
        }

        if (isset($_POST['edit_consumption'])) {
            $record_id = intval($_POST['record_id']);
            $stmt = $pdo->prepare("UPDATE energy_consumption SET appliance_id=?, appliance_name=?, hours_used=?, wattage=?, consumption_kwh=?, date_recorded=? WHERE id=? AND user_id=?");
            $stmt->execute([$appliance_id, $appliance_name, $hours_used, $wattage, $consumption_kwh, $date, $record_id, $user_id]);
            $success = "Record updated!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO energy_consumption (user_id, appliance_id, appliance_name, hours_used, wattage, consumption_kwh, date_recorded) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $appliance_id, $appliance_name, $hours_used, $wattage, $consumption_kwh, $date]);
            $success = "Usage added! (" . number_format($consumption_kwh, 3) . " Units)";
        }
    }
}

/**
 * TANGEDCO Domestic Billing Calculation (2024-25 approx)
 * Note: TN billing is bi-monthly, but here we calculate per entry for estimation.
 * Slab 1: 0 - 100 Units: Free
 * Slab 2: 101 - 200 Units: Rs 4.50
 * Slab 3: 201 - 500 Units: Rs 6.00
 * Slab 4: Above 500 Units: Rs 8.00 - 11.00
 */
function calculateTNCost($units) {
    if ($units <= 100) return 0;
    $cost = 0;
    if ($units <= 200) {
        $cost = ($units - 100) * 4.50;
    } elseif ($units <= 400) {
        $cost = (100 * 4.50) + ($units - 200) * 6.00;
    } elseif ($units <= 500) {
        $cost = (100 * 4.50) + (200 * 6.00) + ($units - 400) * 8.00;
    } else {
        $cost = (100 * 4.50) + (200 * 6.00) + (100 * 8.00) + ($units - 500) * 9.00;
    }
    return $cost;
}

// Fetch data for charts
// 1. Daily consumption for the last 7 days
$daily_stmt = $pdo->prepare("SELECT date_recorded, SUM(consumption_kwh) as daily_total FROM energy_consumption WHERE user_id = ? AND date_recorded >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY) GROUP BY date_recorded ORDER BY date_recorded");
$daily_stmt->execute([$user_id]);
$daily_data = $daily_stmt->fetchAll();

// 2. Consumption by appliance (Top 5)
$appliance_dist_stmt = $pdo->prepare("SELECT appliance_name, SUM(consumption_kwh) as total FROM energy_consumption WHERE user_id = ? GROUP BY appliance_name ORDER BY total DESC LIMIT 5");
$appliance_dist_stmt->execute([$user_id]);
$appliance_dist_data = $appliance_dist_stmt->fetchAll();

// Fetch all appliances grouped by category
$categories_stmt = $pdo->query("SELECT DISTINCT category FROM appliances ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

$appliances_by_category = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare("SELECT id, name, estimated_wattage FROM appliances WHERE category = ? ORDER BY name");
    $stmt->execute([$cat]);
    $appliances_by_category[$cat] = $stmt->fetchAll();
}

// Stats queries
$total_consumption_stmt = $pdo->prepare("SELECT SUM(consumption_kwh) as total FROM energy_consumption WHERE user_id = ?");
$total_consumption_stmt->execute([$user_id]);
$total_consumption = $total_consumption_stmt->fetch()['total'] ?? 0;

$monthly_consumption_stmt = $pdo->prepare("SELECT SUM(consumption_kwh) as total FROM energy_consumption WHERE user_id = ? AND MONTH(date_recorded) = MONTH(CURRENT_DATE()) AND YEAR(date_recorded) = YEAR(CURRENT_DATE())");
$monthly_consumption_stmt->execute([$user_id]);
$monthly_consumption = $monthly_consumption_stmt->fetch()['total'] ?? 0;

$count_appliances_stmt = $pdo->prepare("SELECT COUNT(DISTINCT appliance_name) as count FROM energy_consumption WHERE user_id = ?");
$count_appliances_stmt->execute([$user_id]);
$appliance_count = $count_appliances_stmt->fetch()['count'] ?? 0;

$history_stmt = $pdo->prepare("SELECT * FROM energy_consumption WHERE user_id = ? ORDER BY date_recorded DESC LIMIT 10");
$history_stmt->execute([$user_id]);
$history = $history_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SmartEnergy</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: #fff; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); min-height: 350px; display: flex; flex-direction: column; }
        .chart-card h3 { margin-top: 0; color: #374151; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; width: 100%; }
        .energy-form { margin-top: 20px; }
    </style>
</head>
<body>
    <nav class="dashboard-nav">
        <a href="dashboard.php" class="logo">SmartEnergy</a>
        <div class="nav-links">
            <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Energy Dashboard</h1>
            <a href="export_pdf.php" target="_blank" class="btn btn-pdf">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export PDF Report
            </a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Units Used</h3>
                <div class="value"><?php echo number_format($total_consumption, 2); ?> <small>Units</small></div>
            </div>
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="value"><?php echo number_format($monthly_consumption, 2); ?> <small>Units</small></div>
            </div>
            <div class="stat-card">
                <h3>Devices Tracked</h3>
                <div class="value"><?php echo $appliance_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Estimated Cost (TN)</h3>
                <div class="value">₹<?php echo number_format(calculateTNCost($total_consumption), 2); ?></div>
            </div>
        </div>

        <div class="chart-container-row">
            <div class="chart-card">
                <h3>Weekly Trend</h3>
                <div class="canvas-wrapper">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3>Top Energy Users</h3>
                <div class="canvas-wrapper">
                    <canvas id="applianceChart"></canvas>
                </div>
            </div>
        </div>

        <div class="energy-form">
            <h2 id="form-title">Track Usage</h2>
            <?php if ($success): ?>
                <div class="alert" style="background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; border: 1px solid #c3e6cb; margin-bottom:1rem;"><?php echo $success; ?></div>
            <?php endif; ?>
            <form action="dashboard.php" method="POST" id="energyForm">
                <input type="hidden" name="record_id" id="form_record_id">
                <div class="form-row">
                    <div class="form-group" style="flex: 1.5; min-width: 250px;">
                        <label>Select Appliance</label>
                        <select name="appliance_id" id="appliance_select" class="form-control" onchange="toggleFormFields(this.value)">
                            <option value="">-- Choose an Appliance --</option>
                            <?php foreach ($appliances_by_category as $cat => $apps): ?>
                                <optgroup label="<?php echo htmlspecialchars($cat); ?>">
                                    <?php foreach ($apps as $app): ?>
                                        <option value="<?php echo $app['id']; ?>" data-wattage="<?php echo $app['estimated_wattage']; ?>">
                                            <?php echo htmlspecialchars($app['name']); ?> (<?php echo $app['estimated_wattage']; ?>W)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <option value="other">Other / Custom</option>
                        </select>
                    </div>

                    <div id="custom_details" style="display: none; display: contents;">
                        <div id="custom_name_group" class="form-group" style="display: none; flex: 1; min-width: 200px;">
                            <label>Appliance Name</label>
                            <input type="text" name="custom_appliance" id="form_custom_appliance" class="form-control" placeholder="e.g. Vintage Radio">
                        </div>
                        <div id="custom_wattage_group" class="form-group" style="display: none; flex: 0.5; min-width: 100px;">
                            <label>Wattage (W)</label>
                            <input type="number" name="custom_wattage" id="form_custom_wattage" class="form-control" placeholder="Watts">
                        </div>
                    </div>

                    <div class="form-group" style="flex: 0.5; min-width: 120px;">
                        <label>Hours Run</label>
                        <input type="number" step="0.1" name="hours_used" id="form_hours_used" class="form-control" placeholder="hrs">
                    </div>

                    <div class="form-group" style="flex: 0.5; min-width: 120px;">
                        <label>OR Manual Units</label>
                        <input type="number" step="0.01" name="manual_units" id="form_manual_units" class="form-control" placeholder="kWh">
                    </div>

                    <div class="form-group" style="flex: 1; min-width: 150px;">
                        <label>Date</label>
                        <input type="date" name="date" id="form_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" name="add_consumption" id="form_submit_btn" class="btn btn-primary" style="width: auto; padding: 0.75rem 2rem;">Add Usage</button>
                    <button type="button" id="form_cancel_btn" class="btn btn-secondary" style="display: none; width: auto; padding: 0.75rem 2rem;" onclick="resetForm()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="history-table-wrapper">
            <table class="history-table">
                <thead>
                        <tr>
                            <th>Date</th>
                            <th>Appliance</th>
                            <th>Usage Time</th>
                            <th>Consumption (Units)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="5" style="text-align: center;">No usage data yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['date_recorded'])); ?></td>
                                <td><?php echo htmlspecialchars($row['appliance_name']); ?></td>
                                <td><?php echo $row['hours_used']; ?> hrs <small>(<?php echo $row['wattage']; ?>W)</small></td>
                                <td><?php echo number_format($row['consumption_kwh'], 3); ?> Units</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action edit" onclick='editRecord(<?php echo json_encode($row); ?>)' title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <a href="dashboard.php?delete=<?php echo $row['id']; ?>" class="btn-action delete" onclick="return confirm('Delete this record?')" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
            </table>
        </div>
    </main>

    <footer style="text-align: center; padding: 2rem; color: #64748b; font-size: 0.9rem; border-top: 1px solid #e2e8f0; margin-top: 2rem; background: white;">
        <p>&copy; <?php echo date('Y'); ?> SmartEnergy. All rights reserved.</p>
        <p style="margin-top: 0.5rem; font-weight: 500;">Made by <strong>team-sme</strong></p>
    </footer>

    <script>
    function toggleFormFields(value) {
        const nameGroup = document.getElementById('custom_name_group');
        const wattageGroup = document.getElementById('custom_wattage_group');
        const customDetails = document.getElementById('custom_details');
        
        if (value === 'other') {
            nameGroup.style.display = 'block';
            wattageGroup.style.display = 'block';
            nameGroup.querySelector('input').setAttribute('required', 'required');
            wattageGroup.querySelector('input').setAttribute('required', 'required');
        } else {
            nameGroup.style.display = 'none';
            wattageGroup.style.display = 'none';
            nameGroup.querySelector('input').removeAttribute('required');
            wattageGroup.querySelector('input').removeAttribute('required');
        }
    }

    function editRecord(record) {
        document.getElementById('form-title').innerText = 'Edit Usage Record';
        document.getElementById('form_record_id').value = record.id;
        document.getElementById('form_hours_used').value = record.hours_used;
        document.getElementById('form_manual_units').value = record.consumption_kwh;
        document.getElementById('form_date').value = record.date_recorded;
        
        const select = document.getElementById('appliance_select');
        if (record.appliance_id) {
            select.value = record.appliance_id;
            toggleFormFields(record.appliance_id);
        } else {
            select.value = 'other';
            toggleFormFields('other');
            document.getElementById('form_custom_appliance').value = record.appliance_name;
            document.getElementById('form_custom_wattage').value = record.wattage;
        }
        
        document.getElementById('form_submit_btn').name = 'edit_consumption';
        document.getElementById('form_submit_btn').innerText = 'Update Record';
        document.getElementById('form_cancel_btn').style.display = 'block';
        
        window.scrollTo({ top: document.querySelector('.energy-form').offsetTop - 20, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('form-title').innerText = 'Track Usage';
        document.getElementById('energyForm').reset();
        document.getElementById('form_record_id').value = '';
        document.getElementById('form_submit_btn').name = 'add_consumption';
        document.getElementById('form_submit_btn').innerText = 'Add Usage';
        document.getElementById('form_cancel_btn').style.display = 'none';
        toggleFormFields('');
    }

    // Charting
    const dailyLabels = <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date_recorded'])); }, $daily_data)); ?>;
    const dailyValues = <?php echo json_encode(array_column($daily_data, 'daily_total')); ?>;

    const applianceLabels = <?php echo json_encode(array_column($appliance_dist_data, 'appliance_name')); ?>;
    const applianceValues = <?php echo json_encode(array_column($appliance_dist_data, 'total')); ?>;

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Units (kWh)',
                data: dailyValues,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true
        }
    });

    new Chart(document.getElementById('applianceChart'), {
        type: 'doughnut',
        data: {
            labels: applianceLabels,
            datasets: [{
                data: applianceValues,
                backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    </script>
</body>
</html>
