# Carthage Tech Backend (PHP + mysqli)

A simple, framework-free backend for Carthage Tech using vanilla PHP and `mysqli`. It includes user auth, product catalog, cart, and order creation with JSON APIs.

## Why This Design
- Simplicity: No frameworks; easy to read and reason about.
- Separation of concerns: `controllers` handle logic, `utils` holds DB, `public/index.php` is the router.
- Single entry point: All requests go through `public/index.php`.
- Safety basics: Prepared statements to avoid SQL injection; transactions for cart → order flow.
- Self-initializing DB: Creates the database, tables, and seed data on first run.

## Stack
- PHP 7.4+ (works on 8.x) on WAMP
- MySQL (via `mysqli`)
- No external libraries required (Composer file provided but optional)

## Folder Structure
```
carthage-tech-backend/
  public/
    index.php              # Router / front controller
  src/
    controllers/
      AuthController.php
      ProductController.php
      CartController.php
      OrderController.php
    models/
      Product.php          # Minimal placeholders
      User.php
    routes/
      middleware.php       # JSON helpers, path parsing, auth guard
    utils/
      Database.php         # mysqli singleton, schema + seed
  composer.json
  README.md
```

## Database
- Connection: host `localhost`, user `root`, password `""` (empty), db `carthage_tech`.
- On first connection, `Database.php` creates database and tables if missing and seeds 6 products.
- Tables:
  - `users(id, name, email UNIQUE, password_hash, created_at)`
  - `products(id, name, slug UNIQUE, description, category, brand, price, stock, specs, created_at)`
  - `carts(id, user_id FK→users, status ENUM('open','ordered'), created_at)`
  - `cart_items(id, cart_id FK→carts, product_id FK→products, quantity, unit_price, UNIQUE(cart_id, product_id))`
  - `orders(id, user_id FK→users, total, status, created_at)`
  - `order_items(id, order_id FK→orders, product_id FK→products, quantity, unit_price)`

### Seeded Products
- 4 PCs: Atlas Pro, Aegis Gaming, Nova Mini, Titan Creator
- 2 Accessories: Pro Mechanical Keyboard, Precision Mouse

## Auth & Tokens
- Register/Login with `password_hash` / `password_verify`.
- Token: Base64 of `user_id:timestamp` (simple demo token, not secure). Sent as `Authorization: Bearer <token>`.
- Protected routes: `/api/cart*` and `/api/orders*` require a valid token.

## Routing
- Implemented in `public/index.php` using `$method` + `REQUEST_URI` switch/regex.
- Helper `get_path()` in `middleware.php` normalizes the URL (works when accessed via `public/index.php`).

## JSON Response Shape
```json
{
  "status": "success|error",
  "message": "Human-readable message",
  "data": { /* endpoint-specific */ }
}
```

## Endpoints
- POST `/api/auth/register`
  - Body: `{ name, email, password }`
  - Returns: `{ token, user }`
- POST `/api/auth/login`
  - Body: `{ email, password }`
  - Returns: `{ token, user }`
- GET `/api/products`
  - Query: `page`, `limit`, optional `category`, `minPrice`, `maxPrice`
  - Returns: `{ items, pagination }`
- GET `/api/products/{slug}`
  - Returns: full product
- POST `/api/cart/add` (protected)
  - Body: `{ product_id, quantity }`
  - Returns: `{ cart_id, items }`
- GET `/api/cart` (protected)
  - Returns: `{ cart_id, items }`
- POST `/api/orders/create` (protected)
  - Creates order from current open cart; moves items to `order_items`, decrements stock, closes cart.
  - Returns: `{ order_id, total }`
- GET `/api/orders` (protected)
  - Returns: list of orders with items for the user

## How to Run (WAMP)
1. Ensure WAMP is running (Apache + MySQL). Ensure `mysqli` extension is enabled.
2. Place this folder at `c:\wamp64\www\carthage-tech-backend`.
3. Hit an endpoint to trigger DB creation/seed, e.g.:
   - `http://localhost/carthage-tech-backend/public/index.php/api/products`

Optional (cleaner URLs): Map Apache DocumentRoot/alias to `public/` so you can call `/api/...` directly.

## Example cURL (Windows PowerShell)
- Register
```powershell
curl -X POST "http://localhost/carthage-tech-backend/public/index.php/api/auth/register" ^
  -H "Content-Type: application/json" ^
  -d '{ "name":"Alice", "email":"alice@example.com", "password":"secret123" }'
```
- Login
```powershell
curl -X POST "http://localhost/carthage-tech-backend/public/index.php/api/auth/login" ^
  -H "Content-Type: application/json" ^
  -d '{ "email":"alice@example.com", "password":"secret123" }'
```
- List products
```powershell
curl "http://localhost/carthage-tech-backend/public/index.php/api/products?limit=5&page=1&category=PC&minPrice=500&maxPrice=2000"
```
- Get product by slug
```powershell
curl "http://localhost/carthage-tech-backend/public/index.php/api/products/carthage-tech-atlas-pro-pc"
```
- Add to cart (replace TOKEN and product_id)
```powershell
curl -X POST "http://localhost/carthage-tech-backend/public/index.php/api/cart/add" ^
  -H "Authorization: Bearer TOKEN" ^
  -H "Content-Type: application/json" ^
  -d '{ "product_id": 1, "quantity": 2 }'
```
- Get cart
```powershell
curl "http://localhost/carthage-tech-backend/public/index.php/api/cart" ^
  -H "Authorization: Bearer TOKEN"
```
- Create order
```powershell
curl -X POST "http://localhost/carthage-tech-backend/public/index.php/api/orders/create" ^
  -H "Authorization: Bearer TOKEN"
```
- List orders
```powershell
curl "http://localhost/carthage-tech-backend/public/index.php/api/orders" ^
  -H "Authorization: Bearer TOKEN"
```

## Design Rationale (What & Why)
- `Database.php`: Singleton `mysqli` connection avoids reconnect overhead and centralizes config; auto-creates schema and seeds products for quick demos.
- Prepared statements everywhere: Mitigates SQL injection.
- Transactions in cart/order: Ensures consistency when creating orders and updating stock.
- Simple token: Easy to inspect for a demo. For real apps, replace with signed JWTs and expiry/refresh.
- Router in one file: Keeps routing logic visible and easy to follow without adding a framework.
- JSON helpers: Consistent response envelope for frontend consumption and debugging.

## Troubleshooting
- If MySQL has a root password, update credentials in `src/utils/Database.php`.
- If URLs 404, include `public/index.php` in the path or set Apache to serve from `public/`.
- To reset data: drop the `carthage_tech` database; it will be recreated on the next request.

---
This README is meant for study and quick review if your teacher asks about the design and tradeoffs.
"# Carthage_Tech_Backend" 
