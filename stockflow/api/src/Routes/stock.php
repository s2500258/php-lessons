<?php

/**
 * Stock Movement Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time recording for stock movements
 * (Dashboard analytics are in dashboard.php — Exercise 7)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/stock/movements — List stock movements (authenticated)
// ============================================================
// EXERCISE 3 (Step 2): Students build this route
//
// Stock movements track inventory changes (in, out, adjustment).
// Each movement has a timestamp — this is where date/time matters most.
//
// Hints:
//   - Query the stock_movements table
//   - Join with products: 'select' => '*,products(name,sku)'
//   - Sort by newest first: 'order' => 'created_at.desc'
//   - Post-process: format dates, add relative time
//   - Optional filter: ?product_id=uuid to see movements for one product
// ============================================================

// STUB: Returns empty array until students implement Exercise 3 (Step 2).
// Replace the body of this route with your own logic.
$app->get('/api/stock/movements', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $params = $request->getQueryParams();
    $productId = $params['product_id'] ?? null;

    $queryParams = [
        'select' => '*,products(name,sku)',
        'order' => 'created_at.desc',
    ];

    if ($productId) {
        $queryParams['product_id'] = 'eq.' . $productId;
    }

    $movements = $auth->query('stock_movements', $queryParams);

    $processed = array_map(function ($movement) {
        $timestamp = strtotime($movement['created_at']);
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

        $movement['product_name'] = $movement['products']['name'] ?? null;
        $movement['product_sku'] = $movement['products']['sku'] ?? null;
        $movement['created_date'] = $createdDate;
        $movement['created_ago'] = $createdAgo;

        return $movement;
    }, $movements);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/stock/movements — Record a stock movement (authenticated)
// ============================================================
// EXERCISE 3 (Step 3): Students build this route
//
// When stock moves in or out, we record it AND update the product's stock_quantity.
// This is a two-step operation:
//   1. Insert the movement record
//   2. Update the product's stock_quantity
//
// The frontend sends:
//   {
//     product_id: "uuid",
//     quantity: 10,
//     movement_type: "in",       // "in", "out", or "adjustment"
//     reason: "Supplier delivery",
//     notes: "Invoice #12345"
//   }
//
// EXERCISE 3 focus: The created_at timestamp is auto-set by the database.
// But if you needed to record a movement for a past date, you could send:
//   'created_at' => date('c', strtotime('2026-03-01'))  // ISO 8601 format
//
// Hints:
//   - Validate: product_id, quantity (> 0), movement_type (in/out/adjustment)
//   - For "out" movements, check that enough stock exists
//   - Calculate new stock: for "in" add, for "out" subtract, for "adjustment" set directly
//   - Update the product's stock_quantity after inserting the movement
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 3 (Step 3).
// Replace the body of this route with your own logic.
$app->post('/api/stock/movements', function (Request $request, Response $response) {
    $body = $request->getParsedBody();

    $productId = trim($body['product_id'] ?? '');
    $quantity = (int) ($body['quantity'] ?? 0);
    $movementType = $body['movement_type'] ?? '';
    $reason = trim($body['reason'] ?? '');
    $notes = trim($body['notes'] ?? '');

    if ($productId === '') {
        $response->getBody()->write(json_encode([
            'error' => 'product_id is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($quantity <= 0) {
        $response->getBody()->write(json_encode([
            'error' => 'quantity must be greater than 0'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if (!in_array($movementType, ['in', 'out', 'adjustment'], true)) {
        $response->getBody()->write(json_encode([
            'error' => 'movement_type must be in, out, or adjustment'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $products = $auth->query('products', [
        'id' => 'eq.' . $productId,
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];
    $currentStock = (int) ($product['stock_quantity'] ?? 0);

    if ($movementType === 'out' && $quantity > $currentStock) {
        $response->getBody()->write(json_encode([
            'error' => 'Not enough stock for this movement'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($movementType === 'in') {
        $newStockQuantity = $currentStock + $quantity;
    } elseif ($movementType === 'out') {
        $newStockQuantity = $currentStock - $quantity;
    } else {
        $newStockQuantity = $quantity;
    }

    $createdMovement = $auth->insert('stock_movements', [
        'product_id' => $productId,
        'quantity' => $quantity,
        'movement_type' => $movementType,
        'reason' => $reason !== '' ? $reason : null,
        'notes' => $notes !== '' ? $notes : null,
    ]);

    $updatedProduct = $auth->update('products', 'id=eq.' . $productId, [
        'stock_quantity' => $newStockQuantity,
    ]);

    $response->getBody()->write(json_encode([
        'movement' => $createdMovement[0] ?? null,
        'product' => $updatedProduct[0] ?? null,
        'stock_quantity' => $newStockQuantity,
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
