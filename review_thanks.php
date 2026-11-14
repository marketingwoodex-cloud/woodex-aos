<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Thank You for Your Feedback!</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">
  
  <style>

    /* Container for the main thank-you content */
    .thank-you-container {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 2rem;
    }

    /* Main thank-you card styling */
    .thank-you-card {
      position: relative;
      background-color: #fff;
      border-radius: 1rem;
      padding: 2rem;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      animation: fadeIn 1.5s ease-in-out both;
      z-index: 2; /* Above the background images */
    }

    .thank-you-card h1 {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      color: #000;
    }

    .thank-you-card p {
      font-size: 1.2rem;
      color: #555;
      margin-bottom: 2rem;
      line-height: 1.5;
    }
  </style>
</head>
<body>

<?php include 'header/header.php'; ?>

<div class="thank-you-container">

  <div class="thank-you-card">
    <h1>Thank You!</h1>
    <p>
      We really appreciate you taking the time to review your purchase. 
      Your feedback helps us improve and provide the best experience possible.
    </p>
    <p>
      We look forward to serving you again soon!
    </p>
    <a href="<?php echo $domain; ?>" class="btn btn-primary">Return to Home</a>
  </div>
</div>

<!-- Optionally include your footer -->
<?php include 'footer/footer.php'; ?>

<!-- Bootstrap JS -->
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>
<script src="<?php echo $domain; ?>assets/script/cart.js"></script>
</body>
</html>
