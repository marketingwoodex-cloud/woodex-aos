<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

const WDX_MAX_GENERATED_VARIATIONS = 150;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['attributes']) || !is_array($_POST['attributes'])) {
        echo "<div class='alert alert-warning'>Please select at least one attribute value.</div>";
        exit;
    }

    $attributes = [];
    foreach ($_POST['attributes'] as $attr) {
        if (!isset($attr['name'], $attr['value'])) {
            continue;
        }
        $attrId = str_replace('attributes[', '', str_replace('][]', '', $attr['name']));
        $attributes[$attrId][] = $attr['value'];
    }

    $variationCount = 1;
    foreach ($attributes as $attrId => $values) {
        $uniqueValues = array_values(array_unique($values));
        $attributes[$attrId] = $uniqueValues;
        $variationCount *= max(1, count($uniqueValues));
        if ($variationCount > WDX_MAX_GENERATED_VARIATIONS) {
            echo "<div class='alert alert-danger'>Too many variations selected. Please reduce selections to " . WDX_MAX_GENERATED_VARIATIONS . " combinations or fewer.</div>";
            exit;
        }
    }
    
    $productId = (int)($_POST['productId'] ?? 0);

    // Fetch attribute names and value labels
    $attrInfo = [];
    foreach ($attributes as $attrId => $values) {
        $attr = $conn->query("SELECT name, value_type FROM attributes WHERE id = " . (int)$attrId)->fetch_assoc();
        $attrInfo[$attrId] = [
            'name' => $attr['name'],
            'type' => $attr['value_type'],
            'values' => []
        ];
        
        foreach ($values as $value) {
            $val = $conn->query("SELECT label FROM attribute_values WHERE attribute_id = " . (int)$attrId . " AND value = '" . $conn->real_escape_string($value) . "'")->fetch_assoc();
            $attrInfo[$attrId]['values'][$value] = $val['label'] ?? $value;
        }
    }

    function combinations($arrays, $attrInfo) {
        $result = [[]];
        foreach ($arrays as $attrId => $values) {
            $tmp = [];
            foreach ($result as $prod) {
                foreach ($values as $value) {
                    $new = $prod + [
                        $attrId => [
                            'name' => $attrInfo[$attrId]['name'],
                            'value' => $value,
                            'label' => $attrInfo[$attrId]['values'][$value],
                            'type' => $attrInfo[$attrId]['type']
                        ]
                    ];
                    $tmp[] = $new;
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    $variations = combinations($attributes, $attrInfo);

    foreach ($variations as $i => $combo) {
        $desc = [];
        $variationData = [];
        
        foreach ($combo as $attrId => $data) {
            $desc[] = "<strong>{$data['name']}</strong>: " . 
                     ($data['type'] === 'color' ? 
                     "<span style='display:inline-block;width:15px;height:15px;background:{$data['value']};border:1px solid #ccc;'></span> {$data['label']}" : 
                     $data['label']);
            
            $variationData[$data['name']] = [
                'value' => $data['value'],
                'label' => $data['label'],
                'type' => $data['type']
            ];
        }
        
        // Generate unique key for this variation
        $variationKey = md5(json_encode($variationData));
        
        // Check if variation exists
        $existing = $productId ? $conn->query("SELECT * FROM product_variations WHERE product_id = $productId AND variation_key = '$variationKey'")->fetch_assoc() : null;
        
        echo "<div class='variation-item border p-2 mb-2' data-index='$i'>";
        echo "<div class='row'>";
        echo "<div class='col-md-4'>" . implode("<br>", $desc) . "</div>";
        echo "<div class='col-md-2'><input type='number' step='0.01' name='variations[$i][price]' class='form-control' placeholder='Price' value='" . ($existing['price'] ?? '') . "' required></div>";
        echo "<div class='col-md-2'><input type='number' name='variations[$i][stock]' class='form-control' placeholder='Stock' value='" . ($existing['stock'] ?? '') . "' required></div>";
        echo "<div class='col-md-2'><input type='text' name='variations[$i][sku]' class='form-control' placeholder='SKU' value='" . ($existing['sku'] ?? '') . "'></div>";
        echo "<div class='col-md-2'><input type='text' name='variations[$i][image]' class='form-control' placeholder='Image URL' value='" . ($existing['image'] ?? '') . "'></div>";
        echo "</div>";
        echo "<input type='hidden' name='variations[$i][variation_data]' value='" . htmlspecialchars(json_encode($variationData)) . "'>";
        echo "<input type='hidden' name='variations[$i][variation_key]' value='$variationKey'>";
        echo "</div>";
    }
}
?>
