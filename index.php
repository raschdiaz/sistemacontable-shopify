<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify Bulk Operation</title>
</head>
<body>
    <h1>Sistemacontable Shopify Admin GraphQL API</h1>
    <label id="messages"></label>
    <div id="table-container"></div>
</body>
</html>

<?php
    $shop = "";
    $accessToken = "";
    $apiVersion = "2026-01"; // Latest stable version for early 2026

    $url = "https://$shop.myshopify.com/admin/api/$apiVersion/graphql.json";

    $postData = array(
        "query" => 'mutation bulkOperationRunQuery($query: String!, $groupObjects: Boolean!) {
            bulkOperationRunQuery(query: $query, groupObjects: $groupObjects) {
                bulkOperation {
                completedAt
                createdAt
                errorCode
                fileSize
                id
                objectCount
                partialDataUrl
                query
                rootObjectCount
                status
                type
                url 
                }
                userErrors {
                field
                message
                }
            }
        }',
        "variables" => array(
            "query" => '{ orders(reverse: true, query: \"financial_status:paid updated_at:>'.getYesterdayLastSecondDate().'\") { edges { node { id name createdAt displayFinancialStatus displayFulfillmentStatus updatedAt totalPriceSet { shopMoney { amount currencyCode } } customer { id email firstName lastName } shippingAddress { address1 city zip country } lineItems(first: 250) { edges { node { title quantity sku originalUnitPriceSet { shopMoney { amount } } } } } transactions(first: 250) { gateway kind status paymentDetails { __typename ... on CardPaymentDetails { company number } } } shippingLines(first: 250) { edges { node { title carrierIdentifier code source originalPriceSet { shopMoney { amount currencyCode } } discountedPriceSet { shopMoney { amount } } } } } } } pageInfo { hasNextPage hasPreviousPage endCursor startCursor } } }',
            "groupObjects" => true
        )
    );

    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception('cURL Error: ' . curl_error($curl));
        }

        // Check HTTP status code
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error: $httpCode - " . json_encode($response));
        }        
    
        // Check for empty response
        if (empty($response)) {
            echo "Empty response received from API.<br>";
        } else {
            //echo $response;
            currentBulkOperation();
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage()."<br>";
    } finally {
        if (isset($curl) && is_resource($curl)) {
            curl_close($curl);
        }
    }

    function getYesterdayLastSecondDate() {
        $yesterday = new DateTime('yesterday 23:59:59');
        $date_string = $yesterday->format('Y-m-d\TH:i:s\Z');
        echo "Fetching orders updated after: " . $date_string . "<br>";
        return $date_string;
    }

    function currentBulkOperation() {
        global $url, $accessToken;
        try {
            $postData = array(
                "query" => 'query {
                currentBulkOperation {
                    id
                    status
                    errorCode
                    createdAt
                    completedAt
                    objectCount
                    fileSize
                    url
                }
                }'
            );

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "X-Shopify-Access-Token: $accessToken"
            ));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            if (curl_errno($curl)) {
                throw new Exception('cURL Error: ' . curl_error($curl));
            }

            // Check HTTP status code
            if ($httpCode >= 400) {
                throw new Exception("HTTP Error: $httpCode - " . json_encode($response));
            }        
        
            // Check for empty response
            if (empty($response)) {
                echo "Empty response received from API.<br>";
            } else {
                //echo $response;
                $responseDecoded = json_decode($response, true);
                if(isset($responseDecoded['data']['currentBulkOperation']['status'])) {
                    if($responseDecoded['data']['currentBulkOperation']['status'] === 'RUNNING') {
                        echo "Bulk operation is still running. Checking again in 1 seconds..<br>";
                        sleep(1); // Wait for 1 seconds before checking again
                        currentBulkOperation();
                    } else if($responseDecoded['data']['currentBulkOperation']['status'] === 'COMPLETED') {
                        echo "Bulk operation completed successfully. File URL: " . $responseDecoded['data']['currentBulkOperation']['url']."<br>";
                        downloadJSONL($responseDecoded['data']['currentBulkOperation']['url']);
                    } else {
                        echo "Bulk operation ended with status: " . $responseDecoded['data']['currentBulkOperation']['status'] . "<br>";
                    }
                } else {
                    echo "Status not found in response.<br>";
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        } finally {
            if (isset($curl) && is_resource($curl)) {
                curl_close($curl);
            }
        }
    }

    function downloadJSONL($url) {
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl)) {
                throw new Exception('cURL Error: ' . curl_error($curl));
            }

            // Check HTTP status code
            if ($httpCode >= 400) {
                throw new Exception("HTTP Error: $httpCode - " . json_encode($response));
            }        
        
            // Check for empty response
            if (empty($response)) {
                echo "Empty response received from API.<br>";
            } else {
                echo "File downloaded successfully.<br>";
                //file_put_contents('bulk_operation_result.jsonl', $response);
                

                //$jsonArray = array_map(function($line) {
                //    return json_decode($line, true);
                //    }, $jsonArray);
                $responseMapped = formatJsonlToPhpObject($response);
                generateShopifyTable($responseMapped, 'table-container');
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage()."<br>";
        } finally {
            if (isset($curl) && is_resource($curl)) {
                curl_close($curl);
            }
        }
    }
    
    function formatJsonlToPhpObject($jsonlString) {
        $orders = [];
        $lineItems = [];
        $lines = explode("\n", trim($jsonlString));

        foreach ($lines as $line) {
            if (!empty($line)) {
                $jsonObject = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (isset($jsonObject['id']) && strpos($jsonObject['id'], 'Order') !== false) {
                        // It's an order
                        $orders[$jsonObject['id']] = $jsonObject;
                        $orders[$jsonObject['id']]['lineItems'] = []; // Initialize line_items array
                    } elseif (isset($jsonObject['__parentId'])) {
                        // It's a line item (child)
                        $lineItems[] = $jsonObject;
                    }
                } else {
                    error_log("Error decoding JSON: " . json_last_error_msg());
                }
            }
        }

        // Attach line items to their respective orders
        foreach ($lineItems as $lineItem) {
            $parentId = $lineItem['__parentId'];
            if (isset($orders[$parentId])) {
                $orders[$parentId]['lineItems'][] = $lineItem;
            }
        }

        // Convert the associative array of orders to a simple indexed array
        $phpObject = array_values($orders);

        return $phpObject;
    }
    
    function generateShopifyTable($data, $containerId) {
        echo "<div id='" . $containerId . "'>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-family: sans-serif;'>";
        echo "<thead>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th></th>"; // Column for the expand/collapse icon
        echo "<th>Order Name</th>";
        echo "<th>Customer</th>";
        echo "<th>Creation Date</th>";
        echo "<th>Status</th>";
        echo "<th>Action</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($data as $index => $order) {
            $orderId = htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8');
            $rowId = "details_" . $index; // Unique ID for the child row
            
            $customerName = isset($order['customer']) ? $order['customer']['firstName'] . ' ' . $order['customer']['lastName'] : 'N/A';
            $total = isset($order['totalPriceSet']['shopMoney']) ? $order['totalPriceSet']['shopMoney']['amount'] . ' ' . $order['totalPriceSet']['shopMoney']['currencyCode'] : 'N/A';

            // --- Parent Row ---
            echo "<tr>";
            // Toggle Button
            echo "<td style='text-align:center;'><button onclick=\"toggleRow('$rowId')\" style='cursor:pointer;'>[+]</button></td>";
            echo "<td><strong>" . htmlspecialchars($order['name'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
            echo "<td>" . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($order['createdAt'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($order['displayFinancialStatus'], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td><button onclick=\"createInvoice('$orderId')\">Create Invoice</button></td>";
            echo "</tr>";

            // --- Child Row (Hidden by default) ---
            echo "<tr id='$rowId' style='display:none; background-color: #fafafa;'>";
            echo "<td></td>"; // Empty cell under the toggle column
            echo "<td colspan='5'>"; // Span across the rest of the columns
            
            echo "<div style='padding: 15px;'>";
            echo "<strong>Order Details:</strong><br>";
            echo "Updated At: " . htmlspecialchars($order['updatedAt'], ENT_QUOTES, 'UTF-8') . "<br>";
            echo "Total Amount: " . htmlspecialchars($total, ENT_QUOTES, 'UTF-8') . "<hr>";
            
            echo "<strong>Line Items:</strong>";
            if (isset($order['lineItems']) && is_array($order['lineItems'])) {
                foreach ($order['lineItems'] as $li) {
                    echo "<div style='margin-left: 15px;'>• " . htmlspecialchars($li['title'], ENT_QUOTES, 'UTF-8') . 
                        " (<strong>" . htmlspecialchars($li['sku'], ENT_QUOTES, 'UTF-8') . "</strong>) x" . 
                        ($li['quantity'] ?? 1) . "</div>";
                }
            }
            echo "</div>";
            
            echo "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
        echo "</div>";

        // Simple JS helper to toggle visibility
        echo "
        <script>
        function toggleRow(id) {
            var row = document.getElementById(id);
            var btn = event.target;
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                btn.innerText = '[-]';
            } else {
                row.style.display = 'none';
                btn.innerText = '[+]';
            }
        }
        </script>
        ";
    }
?>
