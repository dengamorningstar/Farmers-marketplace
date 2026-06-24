<?php
session_start();
include("../includes/db.php");

/* =========================
   PROTECT PAGE (FARMER ONLY)
========================= */
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

/* 
   SECURITY NOTE:
   farmer_id MUST ALWAYS come from SESSION in backend processing.
   The hidden input is only for UI compatibility.
*/
$farmer_id = intval($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Product</title>

<style>
/* (UNCHANGED STYLES) */
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family: Arial, Helvetica, sans-serif;
    background:#f5f7fa;
    min-height:100vh;
    padding:40px 15px;
}

.wrapper{
    max-width:900px;
    margin:auto;
}

.page-header{
    margin-bottom:25px;
}

.page-header h1{
    font-size:28px;
    color:#222;
    margin-bottom:8px;
}

.page-header p{
    color:#666;
    font-size:15px;
}

.card{
    background:#fff;
    border-radius:14px;
    box-shadow:0 8px 24px rgba(0,0,0,0.08);
    overflow:hidden;
}

.card-top{
    background:linear-gradient(135deg,#1c8f3c,#28a745);
    color:#fff;
    padding:20px;
}

.card-top h2{
    font-size:22px;
}

.form-body{
    padding:25px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.full{
    grid-column:1 / -1;
}

label{
    display:block;
    font-size:14px;
    font-weight:bold;
    margin-bottom:7px;
    color:#333;
}

input, textarea, select{
    width:100%;
    padding:12px 14px;
    border:1px solid #d7dbe0;
    border-radius:10px;
    font-size:15px;
    outline:none;
    transition:0.2s;
}

input:focus,
textarea:focus,
select:focus{
    border-color:#28a745;
    box-shadow:0 0 0 3px rgba(40,167,69,0.12);
}

textarea{
    min-height:120px;
    resize:vertical;
}

.helper{
    font-size:12px;
    color:#777;
    margin-top:6px;
}

.upload-box{
    border:2px dashed #cfd6dd;
    padding:18px;
    border-radius:12px;
    background:#fafafa;
}

.actions{
    margin-top:25px;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.btn{
    border:none;
    padding:13px 18px;
    border-radius:10px;
    font-size:15px;
    cursor:pointer;
    transition:0.2s;
}

.btn-primary{
    background:#28a745;
    color:#fff;
    flex:1;
}

.btn-primary:hover{
    background:#218838;
}

.btn-secondary{
    background:#eef1f4;
    color:#333;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
    min-width:150px;
}

.btn-secondary:hover{
    background:#dde3e8;
}

.note{
    margin-top:18px;
    background:#f8fff9;
    border-left:4px solid #28a745;
    padding:12px;
    border-radius:8px;
    color:#444;
    font-size:14px;
}

@media(max-width:768px){
    .grid{
        grid-template-columns:1fr;
    }

    .actions{
        flex-direction:column;
    }
}
</style>
</head>

<body>

<div class="wrapper">

    <div class="page-header">
        <h1>Farmer Product Management</h1>
        <p>Add fresh produce to your marketplace store professionally.</p>
    </div>

    <div class="card">

        <div class="card-top">
            <h2>Add New Product</h2>
        </div>

        <div class="form-body">

            <form action="../actions/add_product_action.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="farmer_id" value="<?php echo $farmer_id; ?>">

                <div class="grid">

                    <div>
                        <label>Product Name</label>
                        <input type="text" name="name" placeholder="e.g Bananas" required>
                    </div>

                    <div>
                        <label>Price (KES)</label>
                        <input type="number"
                               name="price"
                               min="1"
                               step="0.01"
                               inputmode="decimal"
                               oninput="this.value=this.value.replace(/[^0-9.]/g,'')"
                               placeholder="e.g 150"
                               required>
                    </div>

                    <div>
                        <label>Available Quantity</label>
                        <input type="number" name="quantity" min="1" placeholder="e.g 50" required>
                        <div class="helper">Current stock buyers can order from.</div>
                    </div>

                    <div>
                        <label>Unit</label>
                        <select name="unit" required>
                            <option value="kg">Kilogram (kg)</option>
                            <option value="bunch">Bunch</option>
                            <option value="piece">Piece</option>
                            <option value="crate">Crate</option>
                            <option value="bag">Bag</option>
                            <option value="dozen">Dozen</option>
                        </select>
                        <div class="helper">Standardized units for consistent inventory.</div>
                    </div>

                    <div class="full">
                        <label>Description</label>
                        <textarea name="description"></textarea>
                    </div>

                    <div class="full">
                        <label>Product Image</label>
                        <div class="upload-box">
                            <input type="file" name="image" accept="image/*" required>
                            <div class="helper">Use clear bright photos to attract buyers.</div>
                        </div>
                    </div>

                </div>

                <div class="note">
                    ⚠ Ensure stock quantity is accurate — system uses it for order validation.
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Publish Product</button>
                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>