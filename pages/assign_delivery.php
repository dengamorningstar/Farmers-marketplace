<?php
session_start();
include("../includes/db.php");

/* =========================
   ADMIN AUTH CHECK
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

/* =========================
   FETCH DELIVERY ZONES
========================= */
$zonesRes = $conn->query("
    SELECT zone_id, zone_key, zone_label
    FROM delivery_zones
    WHERE is_active = 1
    ORDER BY zone_label ASC
");

/* =========================
   FETCH DELIVERY PERSONS
========================= */
$riders = $conn->query("
    SELECT
        u.user_id,
        u.name,
        u.specific_location,
        u.zone_id,
        dz.zone_label
    FROM users u
    LEFT JOIN delivery_zones dz
        ON u.zone_id = dz.zone_id
    WHERE u.role = 'delivery_person'
      AND u.account_status = 'active'
    ORDER BY u.name ASC
");

/* =========================
   FETCH READY ORDERS (FIXED TO ORDER-LEVEL SAFETY)
========================= */
$query = $conn->query("
    SELECT 
        oi.order_item_id,
        oi.order_id,

        o.zone_id AS buyer_zone_id,
        dz.zone_label AS buyer_zone_label,

        p.name AS product_name,

        buyer.name AS buyer_name,
        buyer.specific_location AS buyer_specific_location,

        farmer.name AS farmer_name,
        farmer.specific_location AS farmer_specific_location,

        COUNT(da.assignment_id) AS assignment_count

    FROM order_items oi

    INNER JOIN orders o
        ON oi.order_id = o.order_id

    INNER JOIN users buyer
        ON o.user_id = buyer.user_id

    LEFT JOIN delivery_zones dz
        ON o.zone_id = dz.zone_id

    INNER JOIN products p
        ON oi.product_id = p.product_id

    INNER JOIN users farmer
        ON p.farmer_id = farmer.user_id

    /* IMPORTANT FIX: detect ANY assignment for the ORDER */
    LEFT JOIN delivery_assignments da
        ON da.order_id = oi.order_id

    WHERE oi.status = 'ready_for_pickup'

    GROUP BY oi.order_id, oi.order_item_id

    HAVING assignment_count = 0

    ORDER BY oi.order_item_id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Delivery Assignment Center</title>

<style>
body{font-family: Arial;margin:0;background:#f4f6f9;}
.container{padding:20px;}
h2{margin-bottom:20px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:15px;}
.card{background:white;padding:18px;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,0.08);}
.badge{padding:5px 10px;border-radius:5px;color:white;font-size:12px;text-transform:uppercase;display:inline-block;}
.ready_for_pickup{background:purple;}
.pending{background:#ff9800;}
.assigned{background:#17a2b8;}
.label{font-weight:bold;}
.small{font-size:12px;color:#777;}
.zone{background:#eef2ff;padding:6px 10px;border-radius:5px;display:inline-block;font-size:12px;margin-top:5px;margin-bottom:5px;}
.location-box{background:#f9fafb;border-left:4px solid #16a34a;padding:10px;margin-top:10px;border-radius:6px;font-size:14px;}
select, button{width:100%;padding:10px;margin-top:10px;border-radius:6px;border:1px solid #ccc;}
button{background:#007bff;color:white;border:none;cursor:pointer;font-weight:bold;}
button:hover{background:#0056b3;}
.success{background:#d4edda;padding:12px;margin-bottom:15px;border-radius:6px;color:#155724;}
.empty{background:white;padding:20px;border-radius:10px;}
</style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="container">

<h2>🚚 Delivery Assignment Center</h2>

<?php if (isset($_GET['success'])): ?>
    <div class="success">
        Delivery person assigned successfully.
    </div>
<?php endif; ?>

<div class="grid">

<?php if ($query && $query->num_rows > 0): ?>

    <?php while($row = $query->fetch_assoc()): ?>

        <div class="card">

            <h3>Order #<?php echo (int)$row['order_id']; ?></h3>

            <p><span class="label">Product:</span>
                <?php echo htmlspecialchars($row['product_name']); ?>
            </p>

            <p><span class="label">Buyer:</span>
                <?php echo htmlspecialchars($row['buyer_name']); ?>
            </p>

            <div class="zone">
                🗺️ Buyer Zone:
                <?php echo htmlspecialchars($row['buyer_zone_label'] ?? 'Unknown Zone'); ?>
            </div>

            <div class="location-box">
                <strong>Buyer Specific Location:</strong><br>
                <?php echo htmlspecialchars($row['buyer_specific_location'] ?: 'Not provided'); ?>
            </div>

            <hr>

            <p><span class="label">Farmer:</span>
                <?php echo htmlspecialchars($row['farmer_name']); ?>
            </p>

            <div class="location-box">
                <strong>Farmer Pickup Location:</strong><br>
                <?php echo htmlspecialchars($row['farmer_specific_location'] ?: 'Not provided'); ?>
            </div>

            <p>
                <span class="label">Status:</span>
                <span class="badge ready_for_pickup">READY FOR PICKUP</span>
            </p>

            <p class="small">
                Order Item ID: <?php echo (int)$row['order_item_id']; ?>
            </p>

            <!-- ASSIGN FORM -->
            <form action="../actions/assign_delivery_action.php" method="POST">

                <input type="hidden" name="order_id"
                       value="<?php echo (int)$row['order_id']; ?>">

                <input type="hidden" name="order_item_id"
                       value="<?php echo (int)$row['order_item_id']; ?>">

                <select name="delivery_person_id" required>
                    <option value="">-- Assign Delivery Person --</option>

                    <?php
                    $buyer_zone_id = (int)$row['buyer_zone_id'];
                    $riders->data_seek(0);

                    while($r = $riders->fetch_assoc()):
                        $is_same_zone = ((int)$r['zone_id'] === $buyer_zone_id);
                    ?>
                        <option value="<?php echo $r['user_id']; ?>"
                            <?php echo !$is_same_zone ? 'disabled' : ''; ?>>

                            <?php
                            echo $is_same_zone ? '✅ ' : '❌ ';
                            echo htmlspecialchars($r['name']);

                            if (!empty($r['zone_label'])) {
                                echo " | " . htmlspecialchars($r['zone_label']);
                            }

                            if (!empty($r['specific_location'])) {
                                echo " | " . htmlspecialchars($r['specific_location']);
                            }

                            if (!$is_same_zone) {
                                echo " (Different Zone)";
                            }
                            ?>
                        </option>

                    <?php endwhile; ?>

                </select>

                <button type="submit">🚚 Assign Delivery</button>

            </form>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty">No ready-for-pickup orders available.</div>

<?php endif; ?>

</div>
</div>

</body>
</html>