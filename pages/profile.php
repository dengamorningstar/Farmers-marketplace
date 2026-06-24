<?php
session_start();
include("../includes/db.php");
include("../includes/zones.php");

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user']);

/* =========================
   LOAD ZONES
========================= */
$zones_config = getZones($conn);

/* =========================
   FETCH USER (FIXED JOIN VERSION)
========================= */
$stmt = $conn->prepare("
    SELECT 
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.zone_id,
        u.specific_location,
        u.county,
        d.zone_label
    FROM users u
    LEFT JOIN delivery_zones d 
    ON u.zone_id = d.zone_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

/* =========================
   AUTO FIX COUNTY
========================= */
if (($user['county'] ?? '') !== 'Kiambu') {

    $fixCounty = $conn->prepare("
        UPDATE users
        SET county = 'Kiambu'
        WHERE user_id = ?
    ");

    $fixCounty->bind_param("i", $user_id);
    $fixCounty->execute();

    $user['county'] = 'Kiambu';
}

/* =========================
   UPDATE PROFILE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $zone_id = intval($_POST['zone_id'] ?? 0);
    $specific_location = trim($_POST['specific_location'] ?? '');

    if (empty($name) || empty($phone) || empty($zone_id)) {
        $error = "Name, phone and zone are required.";
    } else {

        /* =========================
           GET ZONE LABEL
        ========================== */
        $zoneStmt = $conn->prepare("
            SELECT zone_label
            FROM delivery_zones
            WHERE zone_id = ?
            LIMIT 1
        ");

        $zoneStmt->bind_param("i", $zone_id);
        $zoneStmt->execute();
        $zoneData = $zoneStmt->get_result()->fetch_assoc();

        if (!$zoneData) {
            $error = "Invalid zone selected.";
        } else {

            $zone_label = $zoneData['zone_label'];

            /* =========================
               UPDATE USER
            ========================== */
            $update = $conn->prepare("
                UPDATE users
                SET
                    name = ?,
                    phone = ?,
                    zone_id = ?,
                    county = 'Kiambu',
                    specific_location = ?
                WHERE user_id = ?
            ");

            $update->bind_param(
                "ssisi",
                $name,
                $phone,
                $zone_id,
                $specific_location,
                $user_id
            );

            if ($update->execute()) {
                header("Location: profile.php?updated=1");
                exit();
            } else {
                $error = "Profile update failed.";
            }
        }
    }
}

/* =========================
   REFRESH USER (JOIN AGAIN)
========================= */
$stmt = $conn->prepare("
    SELECT 
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.zone_id,
        u.specific_location,
        u.county,
        d.zone_label
    FROM users u
    LEFT JOIN delivery_zones d 
    ON u.zone_id = d.zone_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Twiga AgroMarket | My Profile</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            font-family:Arial,sans-serif;
            background:#f4f7f2;
        }

        .hero{
            background:
            linear-gradient(rgba(0,0,0,.55), rgba(0,0,0,.55)),
            url('https://images.unsplash.com/photo-1500937386664-56d1dfef3854?q=80&w=1400');
            background-size:cover;
            background-position:center;
            padding:70px 20px;
            text-align:center;
            color:white;
        }

        .container{
            max-width:650px;
            margin:40px auto;
            padding:20px;
        }

        .card{
            background:white;
            padding:30px;
            border-radius:16px;
            box-shadow:0 5px 20px rgba(0,0,0,0.08);
        }

        label{
            font-size:13px;
            color:#555;
            display:block;
            margin-top:10px;
        }

        input, select{
            width:100%;
            padding:13px;
            border-radius:10px;
            border:1px solid #d1d5db;
            margin-top:6px;
        }

        button{
            width:100%;
            padding:15px;
            margin-top:25px;
            background:#16a34a;
            color:white;
            border:none;
            border-radius:10px;
            font-weight:bold;
            cursor:pointer;
        }

        button:hover{
            background:#15803d;
        }

        .success{
            background:#dcfce7;
            padding:12px;
            border-radius:8px;
            margin-bottom:15px;
        }

        .error{
            background:#fee2e2;
            padding:12px;
            border-radius:8px;
            margin-bottom:15px;
        }

        .info-box{
            background:#eff6ff;
            padding:10px;
            border-radius:8px;
            font-size:13px;
            margin-bottom:15px;
            color:#1e3a8a;
        }
    </style>
</head>

<body>

<?php include("../includes/navbar.php"); ?>

<div class="hero">
    <h1>🦒 Twiga AgroMarket</h1>
    <p>Profile Management (Zone System Fixed)</p>
</div>

<div class="container">

    <div class="card">

        <div class="info-box">
            Role: <b><?php echo ucfirst($user['role'] ?? 'user'); ?></b> |
            County: <b><?php echo htmlspecialchars($user['county']); ?></b> |
            Zone: <b><?php echo htmlspecialchars($user['zone_label'] ?? 'N/A'); ?></b>
        </div>

        <?php if(isset($_GET['updated'])): ?>
            <div class="success">Profile updated successfully.</div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">

            <label>Full Name</label>
            <input type="text" name="name"
                   value="<?php echo htmlspecialchars($user['name']); ?>" required>

            <label>Email (locked)</label>
            <input type="text"
                   value="<?php echo htmlspecialchars($user['email']); ?>"
                   disabled>

            <label>Phone</label>
            <input type="text" name="phone"
                   value="<?php echo htmlspecialchars($user['phone']); ?>" required>

            <label>Zone (Delivery Area)</label>

            <select name="zone_id" required>

                <option value="">Select Zone</option>

                <?php foreach ($zones_config as $z): ?>
                    <option value="<?php echo $z['zone_id']; ?>"
                        <?php echo ($user['zone_id'] == $z['zone_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($z['zone_label']); ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <label>Specific Location (Optional)</label>

            <input type="text" name="specific_location"
                   value="<?php echo htmlspecialchars($user['specific_location'] ?? ''); ?>"
                   placeholder="Estate, Street, Landmark">

            <button type="submit">Update Profile</button>

        </form>

    </div>

</div>

</body>
</html>