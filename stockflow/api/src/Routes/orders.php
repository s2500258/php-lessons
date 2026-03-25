<?php

/**
 * Orders Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time handling (timestamps, relative dates)
 * - Exercise 6: CRUD operations for orders and order items
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

if (!function_exists('formatOrderRow')) {
    function formatOrderRow(array $orderRow): array
    {
        $timestamp = strtotime($orderRow['created_at'] ?? '');
        $createdDate = $timestamp ? date('j M Y, H:i', $timestamp) : null;
        $daysAgo = $timestamp ? max(0, (int) floor((time() - $timestamp) / 86400)) : null;

        if ($daysAgo === 0) {
            $createdAgo = 'Today';
        } elseif ($daysAgo === 1) {
            $createdAgo = 'Yesterday';
        } elseif ($daysAgo !== null) {
            $createdAgo = $daysAgo . ' days ago';
        } else {
            $createdAgo = null;
        }

        $orderRow['created_date'] = $createdDate;
        $orderRow['created_ago'] = $createdAgo;
        $orderRow['age_days'] = $daysAgo;
        $orderRow['total_amount'] = number_format((float) ($orderRow['total_amount'] ?? 0), 2, '.', '');

        return $orderRow;
    }
}

// ============================================================
// GET /api/orders — List orders (authenticated)
// ============================================================
// Currently returns raw order data.
//
// EXERCISE 3: Add date/time post-processing:
//   - Format 'created_at' as a human-readable date (e.g., "9 Mar 2026, 14:30")
//   - Add a 'created_ago' field with relative time (e.g., "2 days ago")
//   - Add an 'age_days' field (number of days since creation)
//   - Format 'total_amount' as currency with 2 decimal places
//
// EXERCISE 5 (Step 1): Add filtering:
//   - Filter by status: ?status=confirmed
//   - Sort by date: ?sort=created_at&order=desc
//
// PHP date/time hints:
//   $timestamp = strtotime($row['created_at']);         // Parse ISO date to Unix timestamp
//   $formatted = date('j M Y, H:i', $timestamp);       // "9 Mar 2026, 14:30"
//   $daysAgo = floor((time() - $timestamp) / 86400);   // 86400 = seconds in a day
//
//   For relative time, you can build a simple helper:
//   if ($daysAgo === 0) return 'Today';
//   if ($daysAgo === 1) return 'Yesterday';
//   return $daysAgo . ' days ago';
// ============================================================

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params = $request->getQueryParams();
    $status = $params['status'] ?? null;
    $sort = $params['sort'] ?? 'created_at';
    $order = $params['order'] ?? 'desc';

    $queryParams = [
        'order' => $sort . '.' . $order,
    ];

    if ($status) {
        $queryParams['status'] = 'eq.' . $status;
    }

    $orders = $auth->query('orders', $queryParams);

    // --- POST-PROCESSING (Exercise 3) ---
    $processed = array_map('formatOrderRow', $orders);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());


// ============================================================
// GET /api/orders/{id} — Get single order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 2): Students build this route
//
// Hints:
//   - Fetch the order: query('orders', ['id' => 'eq.' . $id])
//   - Fetch its items: query('order_items', ['order_id' => 'eq.' . $id])
//   - Combine them: $order['items'] = $items
//   - Return 404 if order not found
//   - Apply the same date formatting from Exercise 3
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 2).
$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id,
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $items = $auth->query('order_items', [
        'order_id' => 'eq.' . $id,
        'order' => 'created_at.asc',
    ]);

    $order = formatOrderRow($orders[0]);
    $order['items'] = array_map(function ($item) {
        $item['unit_price'] = number_format((float) ($item['unit_price'] ?? 0), 2, '.', '');
        $item['line_total'] = number_format((float) ($item['line_total'] ?? 0), 2, '.', '');
        return $item;
    }, $items);

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/orders — Create an order with items (authenticated)
// ============================================================
// EXERCISE 5 (Step 3): Students build this route
//
// This is the most complex exercise — creating an order involves:
//   1. Validate the order data (customer_name required)
//   2. Insert the order (without items first)
//   3. Loop through items and insert each one
//   4. Calculate the total_amount from the items
//   5. Update the order with the calculated total
//
// The frontend sends:
//   {
//     customer_name: "Company Oy",
//     notes: "Rush order",
//     items: [
//       { product_id: "uuid", product_name: "Widget", quantity: 3, unit_price: 29.99 },
//       { product_id: "uuid", product_name: "Gadget", quantity: 1, unit_price: 49.99 }
//     ]
//   }
//
// EXERCISE 3 (bonus): Record timestamps correctly:
//   - The database auto-sets created_at, but you should understand that
//     Supabase stores timestamps in UTC (TIMESTAMPTZ)
//   - When displaying, the frontend handles timezone conversion
//   - If you need to set a date manually in PHP: date('c') gives ISO 8601 format
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 3).
$app->post('/api/orders', function (Request $request, Response $response) {
    $body = $request->getParsedBody() ?? [];
    $customerName = trim($body['customer_name'] ?? '');
    $items = $body['items'] ?? [];

    if ($customerName === '') {
        $response->getBody()->write(json_encode([
            'error' => 'customer_name is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!is_array($items) || count($items) === 0) {
        $response->getBody()->write(json_encode([
            'error' => 'At least one order item is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $sanitizedItems = [];
    $totalAmount = 0.0;

    foreach ($items as $index => $item) {
        $productId = trim($item['product_id'] ?? '');
        $productName = trim($item['product_name'] ?? '');
        $quantity = (int) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);

        if ($productId === '' || $productName === '' || $quantity <= 0 || $unitPrice < 0) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid order item at position ' . ($index + 1)
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $lineTotal = $quantity * $unitPrice;
        $totalAmount += $lineTotal;

        $sanitizedItems[] = [
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $createdOrder = $auth->insert('orders', [
        'customer_name' => $customerName,
        'notes' => trim($body['notes'] ?? ''),
        'status' => 'draft',
        'total_amount' => 0,
    ]);

    $orderId = $createdOrder[0]['id'] ?? null;

    if (!$orderId) {
        $response->getBody()->write(json_encode([
            'error' => 'Failed to create order'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $createdItems = [];
    foreach ($sanitizedItems as $item) {
        $insertedItem = $auth->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total'],
        ]);

        if (!empty($insertedItem[0])) {
            $insertedItem[0]['unit_price'] = number_format((float) $insertedItem[0]['unit_price'], 2, '.', '');
            $insertedItem[0]['line_total'] = number_format((float) $insertedItem[0]['line_total'], 2, '.', '');
            $createdItems[] = $insertedItem[0];
        }
    }

    $updatedOrder = $auth->update('orders', 'id=eq.' . $orderId, [
        'total_amount' => $totalAmount,
    ]);

    if (empty($updatedOrder)) {
        $response->getBody()->write(json_encode([
            'error' => 'Failed to update order total'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $order = formatOrderRow($updatedOrder[0]);
    $order['items'] = $createdItems;

    $response->getBody()->write(json_encode($order));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/orders/{id}/status — Update order status (authenticated)
// ============================================================
// EXERCISE 5 (Step 4): Students build this route
//
// This teaches state machine logic — not every status transition is valid:
//   draft → confirmed → fulfilled
//   draft → cancelled
//   confirmed → cancelled
//
// Hints:
//   - Fetch the current order to check its current status
//   - Define valid transitions as an array:
//     $validTransitions = [
//         'draft' => ['confirmed', 'cancelled'],
//         'confirmed' => ['fulfilled', 'cancelled'],
//     ];
//   - Return 400 if the transition is not valid
//   - Use $auth->update() to change the status
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 6 (Step 4).
$app->put('/api/orders/{id}/status', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $body = $request->getParsedBody() ?? [];
    $newStatus = $body['status'] ?? null;
    $allowedStatuses = ['draft', 'confirmed', 'fulfilled', 'cancelled'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid status'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id,
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $currentOrder = $orders[0];
    $currentStatus = $currentOrder['status'] ?? null;
    $validTransitions = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['fulfilled', 'cancelled'],
    ];

    if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus], true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Cannot change from ' . $currentStatus . ' to ' . $newStatus
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $updated = $auth->update('orders', 'id=eq.' . $id, [
        'status' => $newStatus,
    ]);

    if (empty($updated)) {
        $response->getBody()->write(json_encode([
            'error' => 'Failed to update order status'
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode(formatOrderRow($updated[0])));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
