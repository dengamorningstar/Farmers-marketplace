<?php
session_start();
include("../includes/db.php");
require_once("../engines/analytics_engine.php");

header("Content-Type: application/json");

/* =========================
   AUTH
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit();
}

/* =========================
   MONTH FILTER (NEW SYSTEM)
========================= */
$month = $_GET['month'] ?? 'current';

/* =========================
   DATE RANGE RESOLUTION
========================= */
if ($month === 'current') {

    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-t');

} elseif ($month === 'previous') {

    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate   = date('Y-m-t', strtotime('last day of last month'));

} else {

    $startDate = date('Y-m-01', strtotime($month . '-01'));
    $endDate   = date('Y-m-t', strtotime($month . '-01'));
}

/* =========================
   SAFE COUNT HELPER
========================= */
function safeCount($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res) return 0;
    $row = $res->fetch_assoc();
    return intval($row['c'] ?? 0);
}

/* =========================
   SYSTEM METRICS (GLOBAL)
========================= */
$system = [
    "users" => safeCount($conn, "SELECT COUNT(*) c FROM users"),
    "products" => safeCount($conn, "SELECT COUNT(*) c FROM products"),
    "orders" => safeCount($conn, "SELECT COUNT(*) c FROM orders")
];

/* =========================
   LEVEL 2 ANALYTICS RESPONSE
========================= */
$response = [

    "status" => "success",

    /* =========================
       CORE KPIs (GLOBAL)
    ========================= */
    "revenue_kpi" => getRevenueKPI($conn),
    "conversion_kpi" => getConversionKPI($conn),

    /* =========================
       DELIVERY KPI (MONTH FILTERED)
    ========================= */
    "delivery_kpi" => getDeliveryKPI($conn, $startDate, $endDate),

    "rider_kpi" => getRiderKPI($conn),
    "farmer_kpi" => getFarmerKPI($conn),

    /* =========================
       INSIGHTS
    ========================= */
    "top_products" => getTopProducts($conn),

    "zones" => getZonePerformance($conn, $startDate, $endDate),

    /* =========================
       CHART DATA (MONTH FILTERED)
    ========================= */
    "revenue_trend" => getRevenueTrend($conn, $startDate, $endDate),

    "orders_trend" => getOrdersTrend($conn, $startDate, $endDate),

    "delivery_breakdown" => getDeliveryBreakdown($conn, $startDate, $endDate),

    "top_farmers" => getTopFarmers($conn),

    /* =========================
       SYSTEM (GLOBAL)
    ========================= */
    "system" => $system
];

/* =========================
   OUTPUT
========================= */
echo json_encode($response);
?>