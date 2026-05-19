# 🍽️ Table Management Module – Restaurant POS (PHP + MySQL)

## 📌 Objective

Create a **Table Management Module** for an existing Restaurant POS system.
The module should manage restaurant tables, their status, and their relationship with orders.

---

## 🗄️ 1. Database Design

Create a `tables` table:

* `id` (INT, Primary Key, Auto Increment)
* `table_number` (VARCHAR, UNIQUE, NOT NULL)
* `capacity` (INT, NOT NULL)
* `status` (ENUM: `available`, `occupied`, `reserved`, `cleaning`) → default: `available`
* `current_order_id` (INT, NULL, Foreign Key → `orders.id`)
* `created_at` (TIMESTAMP)
* `updated_at` (TIMESTAMP)

### Rules:

* `current_order_id` must be `NULL` when table is available
* Use `ON DELETE SET NULL` for foreign key

---

## ⚙️ 2. Backend Requirements (PHP)

Use a **modular structure** (no monolithic file).

### Required Features:

### CRUD Operations

* Add new table
* Edit table details
* Delete table
* Fetch/list all tables (for DataTables AJAX)

### Table Actions

* Assign table to an order:

  * Set `current_order_id`
  * Update `status = occupied`
* Release table:

  * Set `current_order_id = NULL`
  * Update `status = available`
* Update table status manually (optional admin control)

---

## 🌐 3. Frontend (Bootstrap + jQuery + DataTables)

### Table List UI:

* Use **DataTables.js (AJAX-based, non-inline editing)**

### Columns:

* Table Number
* Capacity
* Status (Badge UI):

  * 🟢 Available
  * 🔴 Occupied
  * 🟡 Reserved
  * 🔵 Cleaning
* Actions:

  * Edit
  * Delete
  * Assign
  * Release

---

## 🔘 Buttons:

* `Add Table`
* `Assign Order`
* `Release Table`

---

## 🔄 4. AJAX Requirements

Use AJAX for:

* Assigning table
* Releasing table
* Fetching table list
* Updating status

⚠️ No full page reloads

---

## ✅ 5. Validation Rules

* ❌ Cannot assign table if status ≠ `available`
* ❌ Cannot delete table if `current_order_id` is NOT NULL
* ❌ Table number must be unique
* ❌ Capacity must be greater than 0

---

## 🔗 6. Integration with Orders Module

### When creating an order:

* Only show **available tables**
* Selecting a table:

  * Automatically set table → `occupied`

### When order is:

* **Completed or Cancelled**

  * Automatically release table:

    * `status = available`
    * `current_order_id = NULL`

---

## ✨ 7. Optional Enhancements

* Color-coded table grid view (like real restaurant layout)
* Filters:

  * Available
  * Occupied
  * Reserved
* Real-time updates (polling or WebSocket)
* Search by table number
* Table capacity grouping

---

## 🧱 8. Coding Guidelines

* Use clean, reusable PHP functions or controller pattern
* Separate:

  * Logic
  * UI
  * AJAX endpoints
* Sanitize all inputs (prevent SQL injection)
* Use prepared statements (PDO or MySQLi)
* Keep UI responsive and minimal

---

## 🎯 Expected Output

A fully functional **Table Management Module** that:

* Integrates with existing POS orders
* Uses AJAX for smooth UX
* Displays data via DataTables
* Maintains accurate real-time table status
