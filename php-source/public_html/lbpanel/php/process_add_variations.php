<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $productId = (int)$_POST['productId'];
        $maxVariations = 150;
        
        // Verify product exists and is variable type
        $product = $conn->query("SELECT product_type FROM products WHERE id = $productId")->fetch_assoc();
        if (!$product || $product['product_type'] !== 'variable') {
            throw new Exception("Invalid product or product type");
        }
        
        // Process variations
        if (empty($_POST['variations'])) {
            throw new Exception("No variations provided");
        }

        if (count($_POST['variations']) > $maxVariations) {
            throw new Exception("Too many variations. Please save {$maxVariations} variations or fewer at once.");
        }
        
        foreach ($_POST['variations'] as $variation) {
            if (empty($variation['price']) || empty($variation['stock'])) {
                continue;
            }
            
            $variationData = $variation['variation_data'];
            $variationKey = $variation['variation_key'];
            $sku = $variation['sku'] ?? '';
            $price = (float)$variation['price'];
            $stock = (int)$variation['stock'];
            $image = $variation['image'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO product_variations 
                (product_id, sku, price, stock, image, variation_data, variation_key) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdssss", 
                $productId,
                $sku,
                $price,
                $stock,
                $image,
                $variationData,
                $variationKey
            );
            $stmt->execute();
        }
        
        $_SESSION['success'] = "Product variations added successfully!";
        unset($_SESSION['current_product_id']);
        header("Location: ../view-products.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error saving variations: " . $e->getMessage();
        header("Location: ../add-products.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../add-products.php");
    exit;
}
