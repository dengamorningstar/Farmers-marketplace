<?php
include("../includes/db.php");

/* =========================
   LOAD ZONES FROM DATABASE
========================= */
include_once("../includes/zones.php");
$zones = getZones($conn);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Twiga AgroMarket | Register</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{ box-sizing:border-box; }

        body{
            margin:0;
            font-family:Arial,sans-serif;
            background:#f4f7f2;
            display:flex;
            min-height:100vh;
        }

        .left{
            flex:1;
            background:
            linear-gradient(rgba(0,0,0,.6), rgba(0,0,0,.6)),
            url('https://images.unsplash.com/photo-1500937386664-56d1dfef3854?q=80&w=1400');
            background-size:cover;
            background-position:center;
            color:white;
            display:flex;
            flex-direction:column;
            justify-content:center;
            padding:60px;
        }

        .left h1{ font-size:52px; margin-bottom:15px; }
        .left p{ font-size:18px; max-width:500px; line-height:1.6; }

        .right{
            width:430px;
            background:white;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px;
        }

        .form-box{ width:100%; }

        .logo{
            text-align:center;
            font-size:34px;
            font-weight:bold;
            color:#16a34a;
            margin-bottom:10px;
        }

        .welcome{
            text-align:center;
            margin-bottom:30px;
            color:#6b7280;
        }

        input, select{
            width:100%;
            padding:14px;
            margin-bottom:16px;
            border-radius:10px;
            border:1px solid #d1d5db;
            font-size:15px;
        }

        input:focus, select:focus{
            outline:none;
            border-color:#16a34a;
        }

        button{
            width:100%;
            padding:15px;
            background:#16a34a;
            color:white;
            border:none;
            border-radius:10px;
            font-size:16px;
            font-weight:bold;
            cursor:pointer;
        }

        button:hover{ background:#15803d; }

        .login-link{
            text-align:center;
            margin-top:20px;
        }

        .login-link a{
            color:#16a34a;
            text-decoration:none;
            font-weight:bold;
        }

        @media(max-width:900px){
            .left{ display:none; }
            .right{ width:100%; }
        }
    </style>
</head>

<body>

<div class="left">
    <h1>🦒 Twiga AgroMarket</h1>
    <p>
        Connecting farmers, buyers and delivery partners
        across Kiambu County through a smart digital
        agricultural marketplace.
    </p>
</div>

<div class="right">

    <div class="form-box">

        <div class="logo">🦒 Twiga AgroMarket</div>
        <div class="welcome">Create your marketplace account</div>

        <form action="/myformapp/actions/register_action.php" method="POST">

            <input type="text" name="name" placeholder="Full Name" required maxlength="50">

            <input type="email" name="email" placeholder="Email Address" required>

            <input type="password" name="password" placeholder="Password" required minlength="6">

            <input type="text" name="phone" placeholder="Phone (Optional)" pattern="[0-9]{9,15}">

            <input type="hidden" name="county" value="Kiambu">

            <select name="zone_id" required>
                <option value="">Select Zone</option>

                <?php if (!empty($zones)): ?>
                    <?php foreach ($zones as $z): ?>
                        <option value="<?= $z['zone_id']; ?>">
                            <?= htmlspecialchars($z['zone_label']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option disabled>No zones loaded</option>
                <?php endif; ?>
            </select>

            <input type="text" name="specific_location" placeholder="Optional specific location / estate">

            <select name="role" required>
                <option value="">Choose Role</option>
                <option value="buyer">Buyer</option>
                <option value="farmer">Farmer</option>
                <option value="delivery_person">Delivery Person</option>
            </select>

            <button type="submit">Create Account</button>

        </form>

        <div class="login-link">
            Already have an account?
            <a href="/myformapp/pages/login.php">Login</a>
        </div>

    </div>

</div>

</body>
</html>