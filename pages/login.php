<?php 
session_start();

/* =========================
   ALREADY LOGGED IN CHECK
========================= */
if (isset($_SESSION['user']) && isset($_SESSION['role'])) {

    // SINGLE ROLE-BASED DASHBOARD
    header("Location: /myformapp/pages/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login | Twiga AgroMarket</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
    *{
        box-sizing:border-box;
    }

    body{
        font-family:Arial, sans-serif;
        background:#f4f6f9;
        display:flex;
        justify-content:center;
        align-items:center;
        min-height:100vh;
        margin:0;
        padding:20px;
    }

    .container{
        background:white;
        padding:30px;
        width:100%;
        max-width:380px;
        border-radius:12px;
        box-shadow:0 0 12px rgba(0,0,0,0.1);
    }

    h2{
        text-align:center;
        margin-bottom:20px;
        color:#111827;
    }

    input{
        width:100%;
        padding:12px;
        margin:10px 0;
        border:1px solid #d1d5db;
        border-radius:6px;
        font-size:15px;
    }

    input:focus{
        outline:none;
        border-color:#16a34a;
    }

    button{
        width:100%;
        padding:12px;
        background:#16a34a;
        color:white;
        border:none;
        cursor:pointer;
        border-radius:6px;
        font-size:15px;
        font-weight:bold;
    }

    button:hover{
        background:#15803d;
    }

    .msg{
        text-align:center;
        margin-bottom:12px;
        font-size:14px;
    }

    .error{
        color:red;
    }

    .success{
        color:green;
    }

    .links{
        text-align:center;
        margin-top:15px;
    }

    .links a{
        text-decoration:none;
        color:#16a34a;
        font-size:14px;
    }

    .links a:hover{
        text-decoration:underline;
    }

    .back-home{
        display:block;
        text-align:center;
        margin-top:20px;
        color:#555;
        font-size:13px;
        text-decoration:none;
    }
</style>
</head>

<body>

<div class="container">

    <h2>Login</h2>

    <?php
    if (isset($_SESSION['success'])) {
        echo "<p class='msg success'>" . htmlspecialchars($_SESSION['success']) . "</p>";
        unset($_SESSION['success']);
    }

    if (isset($_SESSION['error'])) {
        echo "<p class='msg error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form action="/myformapp/actions/login_action.php" method="POST">

        <input 
            type="email" 
            name="email" 
            placeholder="Enter Email" 
            required
        >

        <input 
            type="password" 
            name="password" 
            placeholder="Enter Password" 
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

    <div class="links">
        <a href="/myformapp/pages/register.php">
            Don't have an account? Register
        </a>
    </div>

    <a href="/myformapp/marketplace.php" class="back-home">
        ← Back to Marketplace
    </a>

</div>

</body>
</html>