<?php

/**
 * Products Routes
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 1: Pre-process product data (stock status, formatted prices)
 * - Exercise 2: Add search and filtering via query parameters
 * - Exercise 4: Full CRUD operations (create, update, delete)
 * - Exercise 5: Image upload to Supabase Storage
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/categories — List categories (public)
// ============================================================
$app->get('/api/categories', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

    $categories = $auth->query('categories', [
        'select' => 'id,name',
        'order' => 'name.asc',
    ]);

    $response->getBody()->write(json_encode($categories));
    return $response->withHeader('Content-Type', 'application/json');
});

// ============================================================
// GET /api/products — List products (public)
// ============================================================
// Currently returns raw data from Supabase.
//
// EXERCISE 1: Add post-processing to transform the data:
//   - Format price as a string with 2 decimal places
//   - Add a 'stock_status' field: 'out_of_stock', 'low_stock', or 'in_stock'
//     (hint: compare stock_quantity to reorder_threshold)
//   - Add 'category_name' as a flat string instead of nested object
//   - Keep 'image_url' — the frontend uses it for thumbnails
//   - Remove fields the frontend doesn't need (supplier, reorder_threshold)
//
// EXERCISE 2: Add pre-processing for search and filtering:
//   - Read query params: ?search=wireless&category=Audio&status=active
//   - Build Supabase filters from those params
//   - Add sorting: ?sort=price&order=desc
//   - Add pagination: ?page=1&limit=10
//
// See _route_examples.php for how to read query params and build filters.
// ============================================================

$app->get('/api/products', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();

    // --- PRE-PROCESSING (Exercise 2) ---
    // TODO: Read query parameters from the request
    // $params = $request->getQueryParams();
    // $search = $params['search'] ?? null;
    // $category = $params['category'] ?? null;
    // ... then build $queryParams based on what was sent

    $params = $request->getQueryParams();
    $search = $params['search'] ?? null;
    $category = $params['category'] ?? null;
    $status = $params['status'] ?? null;
    $sort = $params['sort'] ?? 'name';
    $order = $params['order'] ?? 'asc';
    $page = max(1, (int) ($params['page'] ?? 1));
    $limit = 10;

    // Build the query params
    $queryParams = [
        'select' => '*,categories(name)',
        'order' => $sort . '.' . $order,
    ];

    // Add search filter
    if ($search) {
        $queryParams['name'] = 'ilike.*' . rawurlencode($search) . '*';
    }

    // Status filter:
    if ($status) {
        $queryParams['status'] = 'eq.' . $status;
    }

    // Current query — fetches products matching the base filters
    $products = $auth->query('products', $queryParams);

    // --- POST-PROCESSING (Exercise 1) ---
    $processed = array_map(function ($product) {
        $stockQuantity = (int) ($product['stock_quantity'] ?? 0);
        $reorderThreshold = (int) ($product['reorder_threshold'] ?? 0);

        if ($stockQuantity === 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($stockQuantity <= $reorderThreshold) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'price' => number_format((float)$product['price'], 2, ','),   
            'description' => $product['description'] ?? '',
            'stock_quantity' => $stockQuantity,
            'stock_status' => $stockStatus,
            'category_name' => $product['categories']['name'] ?? 'Uncategorized',
            'category_id' => $product['category_id'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'status' => $product['status'],

        ];
    }, $products);

    if ($category) {
        $processed = array_values(array_filter($processed, function ($product) use ($category) {
            return ($product['category_name'] ?? null) === $category;
        }));
    }

    $processed = array_slice($processed, ($page - 1) * $limit, $limit);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/products/{id} — Get single product (public)
// ============================================================
// EXERCISE 4 (Step 1): Students build this route
// This is needed before update/delete — you need to fetch one product.
//
// Hints:
//   - $args['id'] contains the UUID from the URL
//   - Use $auth->query('products', ['id' => 'eq.' . $id, 'select' => '...'])
//   - Supabase returns an array even for single items — use [0] to get the first
//   - Return 404 if the product doesn't exist
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 1).
$app->get('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $auth = new SupabaseAuth();

    $products = $auth->query('products', [
        'id' => 'eq.' . $id,
        'select' => '*,categories(name)',
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($products[0]));
    return $response->withHeader('Content-Type', 'application/json');

});


// ============================================================
// POST /api/products — Create a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 2): Students build this route
//
// Hints:
//   - Use $request->getParsedBody() to get the JSON body
//   - Validate required fields: name, sku, price
//   - Sanitize: trim strings, cast price to float
//   - Include image_url if it was sent (from Exercise 5)
//   - Use $auth->insert('products', $data)
//   - Return 201 status on success
//   - Don't forget ->add(new AuthMiddleware()) at the end!
//
// The frontend sends:
//   { name: "...", sku: "...", price: 29.99, description: "...", category_id: "uuid", image_url: "..." }
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 2).
$app->post('/api/products', function (Request $request, Response $response) {
    $body = $request->getParsedBody() ?? [];

    $name = trim($body['name'] ?? '');
    $sku = trim($body['sku'] ?? '');
    $price = $body['price'] ?? null;

    if ($name === '' || $sku === '' || $price === null || $price === '') {
        $response->getBody()->write(json_encode([
            'error' => 'name, sku, and price are required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $data = [
        'name' => $name,
        'sku' => $sku,
        'price' => (float) $price,
        'description' => trim($body['description'] ?? ''),
    ];

    if (!empty($body['category_id'])) {
        $data['category_id'] = $body['category_id'];
    }

    if (!empty($body['image_url'])) {
        $data['image_url'] = $body['image_url'];
    }

    if (isset($body['stock_quantity'])) {
        $data['stock_quantity'] = (int) $body['stock_quantity'];
    }

    if (isset($body['reorder_threshold'])) {
        $data['reorder_threshold'] = (int) $body['reorder_threshold'];
    }

    if (!empty($body['supplier'])) {
        $data['supplier'] = trim($body['supplier']);
    }

    if (!empty($body['status'])) {
        $data['status'] = trim($body['status']);
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $created = $auth->insert('products', $data);

    $response->getBody()->write(json_encode($created[0] ?? null));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/products/{id} — Update a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 3): Students build this route
//
// Hints:
//   - Only update fields that were actually sent in the body
//   - Include image_url if a new image was uploaded (Exercise 5)
//   - Use $auth->update('products', 'id=eq.' . $id, $data)
//   - Return 400 if no fields to update
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 3).
$app->put('/api/products/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $body = $request->getParsedBody() ?? [];
    $data = [];

    if (array_key_exists('name', $body)) {
        $data['name'] = trim((string) $body['name']);
    }

    if (array_key_exists('sku', $body)) {
        $data['sku'] = trim((string) $body['sku']);
    }

    if (array_key_exists('price', $body)) {
        $data['price'] = (float) $body['price'];
    }

    if (array_key_exists('description', $body)) {
        $data['description'] = trim((string) ($body['description'] ?? ''));
    }

    if (array_key_exists('category_id', $body)) {
        $data['category_id'] = $body['category_id'] ?: null;
    }

    if (array_key_exists('image_url', $body)) {
        $data['image_url'] = $body['image_url'] ?: null;
    }

    if (array_key_exists('stock_quantity', $body)) {
        $data['stock_quantity'] = (int) $body['stock_quantity'];
    }

    if (array_key_exists('reorder_threshold', $body)) {
        $data['reorder_threshold'] = (int) $body['reorder_threshold'];
    }

    if (array_key_exists('supplier', $body)) {
        $data['supplier'] = trim((string) ($body['supplier'] ?? ''));
    }

    if (array_key_exists('status', $body)) {
        $data['status'] = trim((string) $body['status']);
    }

    if (empty($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'No fields provided for update'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $updated = $auth->update('products', 'id=eq.' . $id, $data);

    if (empty($updated)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($updated[0] ?? null));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// DELETE /api/products/{id} — Delete a product (admin only)
// ============================================================
// EXERCISE 4 (Step 4): Students build this route
//
// Hints:
//   - Use $auth->delete('products', 'id=eq.' . $id)
//   - Consider: should you hard-delete or soft-delete (set status='archived')?
//   - If soft-delete, use update() instead of delete()
//   - Return a confirmation message
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 4 (Step 4).
$app->delete('/api/products/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $updated = $auth->update('products', 'id=eq.' . $id, [
        'status' => 'archived'
    ]);

    if (empty($updated)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode([
        'message' => 'Product archived successfully',
        'product' => $updated[0],
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/products/upload-image — Upload a product image (authenticated)
// ============================================================
// EXERCISE 5: Students build this route
//
// This route receives a file upload, sends it to Supabase Storage,
// and returns the public URL. The frontend then uses this URL when
// creating or updating a product.
//
// How file uploads work in Slim:
//   - The frontend sends a FormData object (not JSON)
//   - Slim parses it automatically via addBodyParsingMiddleware()
//   - Use $request->getUploadedFiles() to get the file
//   - Each file is a PSR-7 UploadedFile object with methods:
//     ->getError()          — check for upload errors (UPLOAD_ERR_OK = success)
//     ->getClientFilename() — original filename (e.g., "photo.jpg")
//     ->getSize()           — file size in bytes
//     ->getClientMediaType()— MIME type (e.g., "image/jpeg")
//     ->getStream()         — the file data as a stream
//
// Steps:
//   1. Get the uploaded file from the request
//   2. Validate: file exists, no upload errors, correct type (image/*), size limit
//   3. Generate a unique filename (to avoid collisions)
//   4. Upload to Supabase Storage using $auth->uploadFile()
//   5. Get the public URL using $auth->getPublicUrl()
//   6. Return the URL as JSON
//
// Hints:
//   - Generate unique filename: $filename = uniqid() . '-' . $file->getClientFilename();
//   - Read file data: $fileData = (string) $file->getStream();
//   - Allowed types: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
//   - Max size: 5 * 1024 * 1024 (5MB)
//   - Bucket name: 'product-images' (must be created in Supabase first — see TASKS.md)
// ============================================================

// STUB: Returns "not implemented" until students implement Exercise 5.
$app->post('/api/products/upload-image', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();
    $file = $files['image'] ?? null;

    if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'Image upload failed'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mediaType = $file->getClientMediaType();

    if (!in_array($mediaType, $allowedTypes, true)) {
        $response->getBody()->write(json_encode([
            'error' => 'Only JPEG, PNG, WEBP, and GIF images are allowed'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    if ($file->getSize() > 5 * 1024 * 1024) {
        $response->getBody()->write(json_encode([
            'error' => 'Image size must be 5MB or less'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $originalName = basename((string) $file->getClientFilename());
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $originalName) ?: 'image';
    $filename = uniqid() . '-' . $safeName;

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $fileData = (string) $file->getStream();
    $auth->uploadFile('product-images', $filename, $fileData, $mediaType);

    $publicUrl = $auth->getPublicUrl('product-images', $filename);

    $response->getBody()->write(json_encode([
        'image_url' => $publicUrl
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());
