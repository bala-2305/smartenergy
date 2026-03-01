<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_consumption'])) {
    $appliance = trim($_POST['appliance']);
    $consumption = floatval($_POST['consumption']);
    $date = $_POST['date'];

    if (!empty($appliance) && $consumption > 0) {
        $stmt = $pdo->prepare("INSERT INTO energy_consumption (user_id, appliance_name, consumption_kwh, date_recorded) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $appliance, $consumption, $date]);
        $success = "Consumption record added!";
    }
}

// Stats queries
$total_consumption_stmt = $pdo->prepare("SELECT SUM(consumption_kwh) as total FROM energy_consumption WHERE user_id = ?");
$total_consumption_stmt->execute([$user_id]);
$total_consumption = $total_consumption_stmt->fetch()['total'] ?? 0;

$monthly_consumption_stmt = $pdo->prepare("SELECT SUM(consumption_kwh) as total FROM energy_consumption WHERE user_id = ? AND MONTH(date_recorded) = MONTH(CURRENT_DATE())");
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
        <h1>Energy Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Consumption</h3>
                <div class="value"><?php echo number_format($total_consumption, 2); ?> <small>kWh</small></div>
            </div>
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="value"><?php echo number_format($monthly_consumption, 2); ?> <small>kWh</small></div>
            </div>
            <div class="stat-card">
                <h3>Devices Tracked</h3>
                <div class="value"><?php echo $appliance_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Estimated Cost</h3>
                <div class="value">$<?php echo number_format($total_consumption * 0.12, 2); ?></div>
            </div>
        </div>

        <div class="energy-form">
            <h2>Track New Consumption</h2>
            <?php if ($success): ?>
                <div class="alert" style="background: #dcfce7; color: #166534;"><?php echo $success; ?></div>
            <?php endif; ?>
            <form action="dashboard.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Appliance Name</label>
                        <input type="text" name="appliance" placeholder="e.g. Fridge, AC" required>
                    </div>
                    <div class="form-group">
                        <label>Consumption (kWh)</label>
                        <input type="number" step="0.01" name="consumption" required>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <button type="submit" name="add_consumption" class="btn btn-primary">Add Record</button>
            </form>
        </div>

        <table class="history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Appliance</th>
                    <th>Consumption (kWh)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="3" style="text-align: center;">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['date_recorded'])); ?></td>
                        <td><?php echo htmlspecialchars($row['appliance_name']); ?></td>
                        <td><?php echo $row['consumption_kwh']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
