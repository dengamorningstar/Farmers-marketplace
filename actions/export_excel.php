<?php
session_start();
include("../includes/db.php");
require_once("../engines/analytics_engine.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

/* =========================
   RANGE (SAFE → CONVERT TO DATE RANGE)
========================= */
$range = isset($_GET['range']) ? (int)$_GET['range'] : 30;
$range = max(7, min(90, $range));

$startDate = date('Y-m-d', strtotime("-$range days"));
$endDate   = date('Y-m-d');

/* =========================
   LOAD DATA (NEW SYSTEM COMPATIBLE)
========================= */
$data = [

    "revenue_kpi" => getRevenueKPI($conn),
    "conversion_kpi" => getConversionKPI($conn),

    "delivery_kpi" => getDeliveryKPI($conn, $startDate, $endDate),

    "revenue_trend" => getRevenueTrend($conn, $startDate, $endDate),
    "orders_trend" => getOrdersTrend($conn, $startDate, $endDate),

    "delivery_breakdown" => getDeliveryBreakdown($conn, $startDate, $endDate),

    "top_farmers" => getTopFarmers($conn)
];

/* =========================
   HEADERS
========================= */
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=Farm_Market_BI_Report_" . date("Y-m-d") . ".csv");

$output = fopen("php://output", "w");

/* =========================
   REPORT HEADER
========================= */
fputcsv($output, ["FARM MARKETPLACE - BUSINESS INTELLIGENCE REPORT"]);
fputcsv($output, ["Generated At", date("Y-m-d H:i:s")]);
fputcsv($output, ["Report Range", "$startDate to $endDate"]);
fputcsv($output, []);

/* =========================
   EXECUTIVE SUMMARY
========================= */
fputcsv($output, ["================ EXECUTIVE SUMMARY ================"]);
fputcsv($output, ["Metric", "Value"]);

fputcsv($output, ["Total Revenue", $data['revenue_kpi']['total_revenue']]);
fputcsv($output, ["Total Orders", $data['revenue_kpi']['total_orders']]);
fputcsv($output, ["Average Order Value", $data['revenue_kpi']['avg_order_value']]);
fputcsv($output, ["Conversion Rate (%)", $data['conversion_kpi']['conversion_rate']]);
fputcsv($output, ["Delivery Success (%)", $data['delivery_kpi']['success_rate']]);

fputcsv($output, []);

/* =========================
   DELIVERY BREAKDOWN
========================= */
fputcsv($output, ["================ DELIVERY STATUS BREAKDOWN ================"]);
fputcsv($output, ["Status", "Count", "Share (%)"]);

$totalDeliveries = max(1, $data['delivery_kpi']['total_deliveries']);

foreach ($data['delivery_breakdown'] as $d) {

    $percent = round(($d['total'] / $totalDeliveries) * 100, 2);

    fputcsv($output, [
        strtoupper($d['status']),
        $d['total'],
        $percent . "%"
    ]);
}

fputcsv($output, []);

/* =========================
   REVENUE TREND
========================= */
fputcsv($output, ["================ REVENUE TREND ================"]);
fputcsv($output, ["Date", "Revenue"]);

foreach ($data['revenue_trend'] as $r) {
    fputcsv($output, [$r['day'], $r['revenue']]);
}

fputcsv($output, []);

/* =========================
   ORDERS TREND
========================= */
fputcsv($output, ["================ ORDERS TREND ================"]);
fputcsv($output, ["Date", "Orders"]);

foreach ($data['orders_trend'] as $o) {
    fputcsv($output, [$o['day'], $o['total']]);
}

fputcsv($output, []);

/* =========================
   TOP FARMERS
========================= */
fputcsv($output, ["================ TOP FARMERS ================"]);
fputcsv($output, ["Farmer Name", "Revenue"]);

if (!empty($data['top_farmers'])) {
    foreach ($data['top_farmers'] as $f) {
        fputcsv($output, [$f['name'], $f['revenue']]);
    }
} else {
    fputcsv($output, ["No Data", "0"]);
}

fputcsv($output, []);

/* =========================
   FOOTER METADATA
========================= */
fputcsv($output, ["================ REPORT METADATA ================"]);
fputcsv($output, ["Generated At", date("Y-m-d H:i:s")]);
fputcsv($output, ["Report Range", "$startDate to $endDate"]);
fputcsv($output, ["System", "Farm Marketplace BI Engine"]);

fclose($output);
exit();
?>