<?php
session_start();
include("../includes/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (!isset($_SESSION['user'])) {
    header("Location: /myformapp/pages/login.php");
    exit();
}

$role    = $_SESSION['role'] ?? '';
$user_id = intval($_SESSION['user']);
$name    = $_SESSION['name'] ?? 'User';
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{margin:0;font-family:Arial;background:#f4f6f9;}
.content{padding:20px;}

.card{
    background:#fff;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
    box-shadow:0 0 12px rgba(0,0,0,.08);
}

.header{
    display:flex;
    justify-content:space-between;
    flex-wrap:wrap;
}

.role-badge{
    background:#007BFF;
    color:#fff;
    padding:6px 14px;
    border-radius:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
}

.box{
    background:#f8f9fa;
    padding:18px;
    border-radius:10px;
}

.box h4{margin:0;color:#666;}
.box p{font-size:26px;font-weight:bold;margin:10px 0 0;}

.green{color:#16a34a;}
.orange{color:#f59e0b;}
.purple{color:#7c3aed;}
.blue{color:#2563eb;}

.box canvas{
    width:100% !important;
    height:220px !important;
}

.filter-bar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:15px;
}

.filter-bar select,
.filter-bar button{
    padding:10px;
    border-radius:6px;
    border:1px solid #ccc;
}

.filter-bar button{
    background:#2563eb;
    color:white;
    border:none;
    cursor:pointer;
}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="content">

<div class="card">
    <div class="header">
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($name); ?></h2>
            <p>User ID: <?php echo $user_id; ?></p>
        </div>
        <div class="role-badge"><?php echo ucfirst($role); ?></div>
    </div>
</div>

<?php if ($role === 'admin'): ?>

<!-- =========================
   CONTROLS (MONTH FILTER)
========================= -->
<div class="card">
    <h3>Analytics Controls</h3>

    <div class="filter-bar">

        <select id="monthFilter">

            <option value="current" selected>
                Current Month
            </option>

            <option value="previous">
                Previous Month
            </option>

            <option value="2026-01">
                January 2026
            </option>

            <option value="2026-02">
                February 2026
            </option>

            <option value="2026-03">
                March 2026
            </option>

            <option value="2026-04">
                April 2026
            </option>

            <option value="2026-05">
                May 2026
            </option>

        </select>

        <button onclick="toggleLive()" id="liveBtn">
            ⏸ Pause Live
        </button>

        <!-- EXPORT BUTTONS -->
        <button onclick="exportPDF()">
            📄 Export PDF
        </button>

        <button onclick="exportExcel()">
            📊 Export Excel
        </button>

    </div>
</div>

<!-- KPI -->
<div class="card">
    <h3>Business Intelligence</h3>

    <div class="grid">

        <div class="box">
            <h4>Revenue</h4>
            <p class="green" id="revenue">KES 0</p>
        </div>

        <div class="box">
            <h4>Conversion</h4>
            <p id="conversion">0%</p>
        </div>

        <div class="box">
            <h4>Delivery</h4>
            <p id="delivery_rate">0%</p>
        </div>

        <div class="box">
            <h4>Riders</h4>
            <p id="riders">0</p>
        </div>

        <div class="box">
            <h4>Users</h4>
            <p id="users">0</p>
        </div>

        <div class="box">
            <h4>Products</h4>
            <p id="products">0</p>
        </div>

        <div class="box">
            <h4>Orders</h4>
            <p id="orders">0</p>
        </div>

        <div class="box">
            <h4>Top Product</h4>
            <p id="top_product">Loading...</p>
        </div>

    </div>
</div>

<!-- CHARTS -->
<div class="card">
    <h3>Charts</h3>

    <div class="grid">

        <div class="box">
            <canvas id="revenueChart"></canvas>
        </div>

        <div class="box">
            <canvas id="ordersChart"></canvas>
        </div>

        <div class="box">
            <canvas id="deliveryChart"></canvas>
        </div>

        <div class="box">
            <canvas id="farmersChart"></canvas>
        </div>

    </div>
</div>

<?php endif; ?>

</div>

<?php if ($role === 'admin'): ?>

<script>

let revenueChart, ordersChart, deliveryChart, farmersChart;

let live = true;

let interval = setInterval(loadDashboard, 5000);

/* =========================
   LIVE TOGGLE
========================= */
function toggleLive(){

    live = !live;

    const btn = document.getElementById("liveBtn");

    if (live) {

        btn.innerText = "⏸ Pause Live";

        interval = setInterval(loadDashboard, 5000);

    } else {

        btn.innerText = "▶ Resume Live";

        clearInterval(interval);
    }
}

/* =========================
   MONTH FILTER
========================= */
function getMonth(){

    return document.getElementById("monthFilter").value;
}

/* =========================
   EXPORTS
========================= */
function exportPDF(){

    const month = getMonth();

    window.open(
        `../actions/export_pdf.php?month=${month}`,
        "_blank"
    );
}

function exportExcel(){

    const month = getMonth();

    window.open(
        `../actions/export_excel.php?month=${month}`,
        "_blank"
    );
}

/* =========================
   LOAD DASHBOARD
========================= */
async function loadDashboard(){

    const month = getMonth();

    const res = await fetch(
        `../actions/fetch_dashboard_data.php?month=${month}`
    );

    const data = await res.json();

    if (data.status !== "success") return;

    /* =========================
       KPI UPDATES
    ========================= */

    document.getElementById("revenue").innerText =
        "KES " +
        Number(data.revenue_kpi.total_revenue).toLocaleString();

    document.getElementById("conversion").innerText =
        data.conversion_kpi.conversion_rate + "%";

    document.getElementById("delivery_rate").innerText =
        data.delivery_kpi.success_rate + "%";

    document.getElementById("riders").innerText =
        data.rider_kpi.active_riders;

    document.getElementById("users").innerText =
        data.system.users;

    document.getElementById("products").innerText =
        data.system.products;

    document.getElementById("orders").innerText =
        data.system.orders;

    const top = data.top_products ?? [];

    document.getElementById("top_product").innerText =
        top.length
        ? `${top[0].name} (${top[0].value})`
        : "No data";

    /* =========================
       RESET CHARTS
    ========================= */

    revenueChart?.destroy();
    ordersChart?.destroy();
    deliveryChart?.destroy();
    farmersChart?.destroy();

    /* =========================
       REVENUE CHART
    ========================= */

    revenueChart = new Chart(
        document.getElementById("revenueChart"),
        {
            type: "line",

            data: {

                labels: data.revenue_trend.map(x => x.day),

                datasets: [{
                    label: "Revenue",
                    data: data.revenue_trend.map(x => x.revenue)
                }]
            }
        }
    );

    /* =========================
       ORDERS CHART
    ========================= */

    ordersChart = new Chart(
        document.getElementById("ordersChart"),
        {
            type: "line",

            data: {

                labels: data.orders_trend.map(x => x.day),

                datasets: [{
                    label: "Orders",
                    data: data.orders_trend.map(x => x.total)
                }]
            }
        }
    );

    /* =========================
       DELIVERY CHART
    ========================= */

    deliveryChart = new Chart(
        document.getElementById("deliveryChart"),
        {
            type: "doughnut",

            data: {

                labels: data.delivery_breakdown.map(x => x.status),

                datasets: [{
                    data: data.delivery_breakdown.map(x => x.total)
                }]
            }
        }
    );

    /* =========================
       FARMERS CHART
    ========================= */

    farmersChart = new Chart(
        document.getElementById("farmersChart"),
        {
            type: "bar",

            data: {

                labels: data.top_farmers.map(x => x.name),

                datasets: [{
                    label: "Revenue",
                    data: data.top_farmers.map(x => x.revenue)
                }]
            }
        }
    );
}

/* =========================
   INITIAL LOAD
========================= */
loadDashboard();

</script>

<?php endif; ?>

</body>
</html>