<?php
require_once 'config.php';

// Require login to access this page
requireLogin();

// Fetch sample power readings data (in a real system, this would come from IoT devices)
$stmt = $pdo->prepare("
    SELECT pr.*, d.name as device_name, d.location 
    FROM power_readings pr 
    JOIN devices d ON pr.device_id = d.device_id
    WHERE pr.user_id = ?
    ORDER BY pr.timestamp DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$readings = $stmt->fetchAll();

// If there's no data, insert sample data for demonstration
if (count($readings) === 0) {
    // First, create a sample device if none exist
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        $deviceId = 'DEVICE_' . $_SESSION['user_id'] . '_001';
        $stmt = $pdo->prepare("INSERT INTO devices (device_id, name, location, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$deviceId, 'Main Power Monitor', 'Main Panel', $_SESSION['user_id']]);
        
        // Insert 24 sample readings (for a day, one per hour)
        $basetime = time() - (24 * 3600); // Start 24 hours ago
        
        for ($i = 0; $i < 24; $i++) {
            $timestamp = date('Y-m-d H:i:s', $basetime + ($i * 3600));
            
            // Generate random but plausible values for power metrics
            $voltage = rand(218, 242); // 220-240V with some fluctuation
            $current = rand(5 * 10, 30 * 10) / 10; // 5-30A
            $activePower = $voltage * $current * (rand(85, 98) / 100); // P = V * I * PF
            $powerFactor = rand(85, 98) / 100; // 0.85-0.98
            $frequency = rand(498, 502) / 10; // 49.8-50.2Hz
            
            $stmt = $pdo->prepare("
                INSERT INTO power_readings 
                (device_id, voltage, current, active_power, power_factor, frequency, timestamp, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $deviceId, 
                $voltage, 
                $current, 
                $activePower, 
                $powerFactor, 
                $frequency, 
                $timestamp, 
                $_SESSION['user_id']
            ]);
        }
        
        // Fetch the inserted readings
        $stmt = $pdo->prepare("
            SELECT pr.*, d.name as device_name, d.location 
            FROM power_readings pr 
            JOIN devices d ON pr.device_id = d.device_id
            WHERE pr.user_id = ?
            ORDER BY pr.timestamp DESC
            LIMIT 24
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $readings = $stmt->fetchAll();
    }
}

// Get latest readings for dashboard cards
$latestReading = !empty($readings) ? $readings[0] : null;

// Calculate daily average consumption if we have data
$avgPower = 0;
$avgVoltage = 0;
$avgCurrent = 0;
$avgPowerFactor = 0;
$avgFrequency = 0;

if (!empty($readings)) {
    $totalPower = 0;
    $totalVoltage = 0;
    $totalCurrent = 0;
    $totalPowerFactor = 0;
    $totalFrequency = 0;
    $count = count($readings);
    
    foreach ($readings as $reading) {
        $totalPower += $reading['active_power'];
        $totalVoltage += $reading['voltage'];
        $totalCurrent += $reading['current'];
        $totalPowerFactor += $reading['power_factor'];
        $totalFrequency += $reading['frequency'];
    }
    
    $avgPower = $totalPower / $count;
    $avgVoltage = $totalVoltage / $count;
    $avgCurrent = $totalCurrent / $count;
    $avgPowerFactor = $totalPowerFactor / $count;
    $avgFrequency = $totalFrequency / $count;
}

// Extract data for charts
$labels = [];
$voltageData = [];
$currentData = [];
$powerData = [];
$powerFactorData = [];
$frequencyData = [];

foreach (array_reverse($readings) as $reading) {
    $labels[] = date('H:i', strtotime($reading['timestamp']));
    $voltageData[] = $reading['voltage'];
    $currentData[] = $reading['current'];
    $powerData[] = $reading['active_power'];
    $powerFactorData[] = $reading['power_factor'];
    $frequencyData[] = $reading['frequency'];
}

$chartLabels = json_encode($labels);
$voltageChartData = json_encode($voltageData);
$currentChartData = json_encode($currentData);
$powerChartData = json_encode($powerData);
$powerFactorChartData = json_encode($powerFactorData);
$frequencyChartData = json_encode($frequencyData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Power Monitoring System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/custom.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar is-primary" role="navigation" aria-label="main navigation">
        <div class="container">
            <div class="navbar-brand">
                <a class="navbar-item" href="index.php">
                    <strong>Power Monitoring System</strong>
                </a>

                <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasicExample">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </a>
            </div>

            <div id="navbarBasicExample" class="navbar-menu">
                <div class="navbar-end">
                    <div class="navbar-item">
                        <p class="has-text-white">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></p>
                    </div>
                    <div class="navbar-item">
                        <div class="buttons">
                            <a class="button is-light" href="logout.php">
                                <span class="icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </span>
                                <span>Log out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <section class="section">
        <div class="container">
            <h1 class="title">Power Monitoring Dashboard</h1>
            <h2 class="subtitle">Real-time power metrics</h2>
            
            <!-- Status Cards -->
            <div class="columns is-multiline">
                <!-- Voltage Card -->
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <p class="heading">Voltage</p>
                            <p class="title is-size-4">
                                <?= isset($latestReading) ? number_format($latestReading['voltage'], 1) : '0' ?> V
                            </p>
                        </div>
                        <footer class="card-footer">
                            <p class="card-footer-item">
                                <span>Avg: <?= number_format($avgVoltage, 1) ?> V</span>
                            </p>
                        </footer>
                    </div>
                </div>
                
                <!-- Current Card -->
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <p class="heading">Current</p>
                            <p class="title is-size-4">
                                <?= isset($latestReading) ? number_format($latestReading['current'], 1) : '0' ?> A
                            </p>
                        </div>
                        <footer class="card-footer">
                            <p class="card-footer-item">
                                <span>Avg: <?= number_format($avgCurrent, 1) ?> A</span>
                            </p>
                        </footer>
                    </div>
                </div>
                
                <!-- Active Power Card -->
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <p class="heading">Active Power</p>
                            <p class="title is-size-4">
                                <?= isset($latestReading) ? number_format($latestReading['active_power'], 0) : '0' ?> W
                            </p>
                        </div>
                        <footer class="card-footer">
                            <p class="card-footer-item">
                                <span>Avg: <?= number_format($avgPower, 0) ?> W</span>
                            </p>
                        </footer>
                    </div>
                </div>
                
                <!-- Power Factor Card -->
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <p class="heading">Power Factor</p>
                            <p class="title is-size-4">
                                <?= isset($latestReading) ? number_format($latestReading['power_factor'], 2) : '0' ?>
                            </p>
                        </div>
                        <footer class="card-footer">
                            <p class="card-footer-item">
                                <span>Avg: <?= number_format($avgPowerFactor, 2) ?></span>
                            </p>
                        </footer>
                    </div>
                </div>
                
                <!-- Frequency Card -->
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-content has-text-centered">
                            <p class="heading">Frequency</p>
                            <p class="title is-size-4">
                                <?= isset($latestReading) ? number_format($latestReading['frequency'], 1) : '0' ?> Hz
                            </p>
                        </div>
                        <footer class="card-footer">
                            <p class="card-footer-item">
                                <span>Avg: <?= number_format($avgFrequency, 1) ?> Hz</span>
                            </p>
                        </footer>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="columns is-multiline mt-5">
                <!-- Voltage Chart -->
                <div class="column is-half">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                Voltage Over Time
                            </p>
                        </div>
                        <div class="card-content">
                            <canvas id="voltageChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Current Chart -->
                <div class="column is-half">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                Current Over Time
                            </p>
                        </div>
                        <div class="card-content">
                            <canvas id="currentChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Power Chart -->
                <div class="column is-half">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                Active Power Over Time
                            </p>
                        </div>
                        <div class="card-content">
                            <canvas id="powerChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Power Factor & Frequency Chart -->
                <div class="column is-half">
                    <div class="card">
                        <div class="card-header">
                            <p class="card-header-title">
                                Power Factor & Frequency
                            </p>
                        </div>
                        <div class="card-content">
                            <div class="tabs is-boxed">
                                <ul>
                                    <li class="is-active" id="tab-pf">
                                        <a>
                                            <span class="icon is-small"><i class="fas fa-bolt"></i></span>
                                            <span>Power Factor</span>
                                        </a>
                                    </li>
                                    <li id="tab-freq">
                                        <a>
                                            <span class="icon is-small"><i class="fas fa-wave-square"></i></span>
                                            <span>Frequency</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content" id="tab-content-pf">
                                <canvas id="powerFactorChart"></canvas>
                            </div>
                            <div class="tab-content" id="tab-content-freq" style="display: none;">
                                <canvas id="frequencyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Readings Table -->
            <div class="card mt-5">
                <div class="card-header">
                    <p class="card-header-title">
                        Recent Readings
                    </p>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table class="table is-fullwidth is-striped is-hoverable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Device</th>
                                    <th>Location</th>
                                    <th>Voltage (V)</th>
                                    <th>Current (A)</th>
                                    <th>Active Power (W)</th>
                                    <th>Power Factor</th>
                                    <th>Frequency (Hz)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($readings as $reading): ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i:s', strtotime($reading['timestamp'])) ?></td>
                                    <td><?= htmlspecialchars($reading['device_name']) ?></td>
                                    <td><?= htmlspecialchars($reading['location']) ?></td>
                                    <td><?= number_format($reading['voltage'], 1) ?></td>
                                    <td><?= number_format($reading['current'], 1) ?></td>
                                    <td><?= number_format($reading['active_power'], 0) ?></td>
                                    <td><?= number_format($reading['power_factor'], 2) ?></td>
                                    <td><?= number_format($reading['frequency'], 1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($readings)): ?>
                                <tr>
                                    <td colspan="8" class="has-text-centered">No readings available yet</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="content has-text-centered">
            <p>
                <strong>Power Monitoring System</strong> - Real-time power monitoring dashboard
            </p>
        </div>
    </footer>

    <script>
    // Toggle tabs for PF and Frequency charts
    document.getElementById('tab-pf').addEventListener('click', function() {
        document.getElementById('tab-pf').classList.add('is-active');
        document.getElementById('tab-freq').classList.remove('is-active');
        document.getElementById('tab-content-pf').style.display = 'block';
        document.getElementById('tab-content-freq').style.display = 'none';
    });
    
    document.getElementById('tab-freq').addEventListener('click', function() {
        document.getElementById('tab-freq').classList.add('is-active');
        document.getElementById('tab-pf').classList.remove('is-active');
        document.getElementById('tab-content-freq').style.display = 'block';
        document.getElementById('tab-content-pf').style.display = 'none';
    });
    
    // Mobile navigation menu toggle
    document.addEventListener('DOMContentLoaded', () => {
        const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
        if ($navbarBurgers.length > 0) {
            $navbarBurgers.forEach( el => {
                el.addEventListener('click', () => {
                    const target = el.dataset.target;
                    const $target = document.getElementById(target);
                    el.classList.toggle('is-active');
                    $target.classList.toggle('is-active');
                });
            });
        }
    });

    // Initialize charts
    const labels = <?= $chartLabels ?>;
    
    // Voltage Chart
    const voltageCtx = document.getElementById('voltageChart').getContext('2d');
    new Chart(voltageCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Voltage (V)',
                data: <?= $voltageChartData ?>,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });
    
    // Current Chart
    const currentCtx = document.getElementById('currentChart').getContext('2d');
    new Chart(currentCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Current (A)',
                data: <?= $currentChartData ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });
    
    // Power Chart
    const powerCtx = document.getElementById('powerChart').getContext('2d');
    new Chart(powerCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Active Power (W)',
                data: <?= $powerChartData ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false
                }
            }
        }
    });
    
    // Power Factor Chart
    const pfCtx = document.getElementById('powerFactorChart').getContext('2d');
    new Chart(pfCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Power Factor',
                data: <?= $powerFactorChartData ?>,
                borderColor: 'rgba(153, 102, 255, 1)',
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0.8,
                    max: 1.0
                }
            }
        }
    });
    
    // Frequency Chart
    const freqCtx = document.getElementById('frequencyChart').getContext('2d');
    new Chart(freqCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Frequency (Hz)',
                data: <?= $frequencyChartData ?>,
                borderColor: 'rgba(255, 159, 64, 1)',
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 49.5,
                    max: 50.5
                }
            }
        }
    });
    </script>
</body>
</html>