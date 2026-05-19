# 📦 Inventory Management System
### Project Specification — Sales & Expense Tracking

> **Status: Pre-Development**
> This document is a project specification and prompt for building a multi-branch inventory management system using **Laravel 11**. It describes the full expected behavior, business rules, data structure, and technical requirements.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Tech Stack](#tech-stack)
3. [User Roles & Permissions](#user-roles--permissions)
4. [Inventory Structure](#inventory-structure)
5. [Branch Management](#branch-management)
6. [Supplier Management](#supplier-management)
7. [Delivery Flow](#delivery-flow)
8. [Production Workflow](#production-workflow)
9. [Wastage Report](#wastage-report)
10. [Inter-Branch Transfers](#inter-branch-transfers)
11. [Sales & VAT](#sales--vat)
12. [Low Stock SMS Notifications](#low-stock-sms-notifications)
13. [Database Schema](#database-schema)
14. [Laravel Architecture](#laravel-architecture)
15. [API Endpoints](#api-endpoints)
16. [Environment Variables](#environment-variables)
17. [Business Flow Diagram](#business-flow-diagram)

---

## Project Overview

Build a **multi-branch Inventory Management System** with the following core capabilities:

- Multiple branches, each with its own inventory, deliveries, sales, and production logs
- Items are received via deliveries from external suppliers or other branches
- Received items can be routed to **inventory storage** or directly to **production**
- Production converts raw goods into finished food products; wastage must be reported
- Branches can transfer items to each other through a delivery-approval process
- All sales must include **12% VAT** and be linked to the user who made the sale
- When stock drops to or below a threshold, the system must **automatically send an SMS**
- Role-based access control with three user types: Admin, Branch Manager, Regular User

---

## Tech Stack

| Layer | Required Technology |
|---|---|
| Backend Framework | Laravel 11 |
| Language | PHP 8.2+ |
| Database | MySQL 8.0+ |
| Authentication | Laravel Sanctum |
| Authorization | Laravel Policies & Gates |
| Background Jobs | Laravel Queues (database driver) |
| Scheduling | Laravel Scheduler |
| SMS | ZapZaf Studios SMS API (see `.env`) |
| API Style | RESTful JSON API |

---

## User Roles & Permissions

The system must support three roles. Roles should be stored on the `users` table as an enum.

| Permission | Admin | Branch Manager | Regular User |
|---|:---:|:---:|:---:|
| Approve deliveries (all branches) | ✅ | ❌ | ❌ |
| Approve deliveries (own branch only) | ✅ | ✅ | ❌ |
| Edit items / records | ✅ | ✅ | ❌ |
| Edit locations & categories | ✅ | ✅ | ❌ |
| Create items | ✅ | ✅ | ✅ |
| Create delivered items (log deliveries) | ✅ | ✅ | ✅ |
| Create & view suppliers | ✅ | ✅ | ✅ |
| Edit & delete suppliers | ✅ | ✅ | ❌ |
| View inventory | ✅ | ✅ | ✅ |
| Create sales | ✅ | ✅ | ✅ |
| Manage users & branches | ✅ | ❌ | ❌ |

### Role Behavior Requirements

**Admin**
- Has full system access across all branches
- Can approve all incoming deliveries regardless of destination branch
- Manages users, branches, locations, and categories

**Branch Manager**
- Is scoped to their assigned branch
- Can approve deliveries only within their own branch
- Can edit all records within their branch
- Cannot access or approve deliveries in other branches

**Regular User**
- Can create new inventory items
- Can log sales and initiate production orders
- Can create delivery records (which start as `PENDING`)
- Can create supplier records and view supplier details
- Cannot edit or delete any existing records
- Cannot manage locations or categories

---

## Inventory Structure

The inventory must follow a strict **tree structure**. No item may exist outside this hierarchy.

```
Location → Category → Item
```

### Rules

- An item **must** belong to a category; it cannot be added directly to a location
- A location can have many categories
- A category can have many items
- Locations and categories are scoped per branch

### Example

```
📍 Location: Kitchen Freezer  (Branch: Main Branch)
  📂 Category: Chicken
    🍗 Item: Sweet and Sour Chicken   | qty: 20 pcs  | threshold: 5
    🍗 Item: Fried Chicken            | qty: 15 pcs  | threshold: 5
  📂 Category: Pork
    🥩 Item: Pork Liempo              | qty: 8 kg    | threshold: 2

📍 Location: Dry Storage  (Branch: Main Branch)
  📂 Category: Canned Goods
    🥫 Item: Evaporated Milk (370ml)  | qty: 50 cans | threshold: 10
    🥫 Item: Condensed Milk (300ml)   | qty: 30 cans | threshold: 10
```

### Required Fields per Item

| Field | Type | Notes |
|---|---|---|
| `name` | string | Item display name |
| `sku` | string | Optional, unique |
| `category_id` | foreign key | Required — must belong to a category |
| `branch_id` | foreign key | Required — scoped to a branch |
| `unit` | string | e.g., pcs, kg, cans, liters |
| `quantity` | decimal | Current stock level |
| `low_stock_threshold` | decimal | Triggers SMS when quantity ≤ this value |
| `created_by` | foreign key | The user who created the record |

---

## Branch Management

Each branch is a fully independent operational unit. The system must support an unlimited number of branches.

Each branch should have:
- A name and address
- An assigned Branch Manager (optional at creation; can be set later)
- Its own locations, categories, items, deliveries, sales, and production logs

### Required Fields per Branch

| Field | Type | Notes |
|---|---|---|
| `name` | string | Branch display name |
| `address` | string | Physical address |
| `manager_id` | foreign key | References a User with `branch_manager` role |

---

## Supplier Management

Suppliers represent external vendors who deliver goods to a branch.

### Access Rules

- **Any authenticated user** can create a supplier record and view the supplier list
- Only **Admin** and **Branch Manager** can edit or delete supplier records

### Required Fields per Supplier

| Field | Type | Notes |
|---|---|---|
| `name` | string | Supplier or company name |
| `contact_person` | string | Optional |
| `phone` | string | Optional |
| `email` | string | Optional |
| `address` | text | Optional |
| `notes` | text | Internal notes |
| `created_by` | foreign key | The user who created the record |

### Relationship

A supplier can be linked to many deliveries. When a delivery originates from an external source (not a branch), the `supplier_id` field on the delivery should be populated.

---

## Delivery Flow

Deliveries represent incoming items to a branch — either from an external supplier or from another branch (transfer).

### Who Can Do What

| Action | Who |
|---|---|
| Create a delivery record | Any authenticated user |
| Approve a delivery (mark as received) | Admin (all branches), Branch Manager (own branch only) |

### Delivery States

```
PENDING  ──▶  RECEIVED
```

A delivery must remain `PENDING` until explicitly approved. Inventory quantities must **not** be updated until the delivery is approved.

### After Approval

When a delivery is approved, each delivery item must be allocated to one of:

```
RECEIVED DELIVERY
       │
       ├──▶  INVENTORY    (item added to branch stock)
       │
       └──▶  PRODUCTION   (raw goods queued for production use)
```

### Required Fields per Delivery

| Field | Type | Notes |
|---|---|---|
| `reference_number` | string | Auto-generated, unique |
| `supplier_id` | foreign key | Nullable — set if source is an external supplier |
| `source_branch_id` | foreign key | Nullable — set if source is another branch |
| `destination_branch_id` | foreign key | Required — the receiving branch |
| `status` | enum | `pending` or `received` |
| `approved_by` | foreign key | The user who approved |
| `approved_at` | timestamp | When it was approved |
| `created_by` | foreign key | The user who created the record |

### Required Fields per Delivery Item

| Field | Type | Notes |
|---|---|---|
| `delivery_id` | foreign key | Parent delivery |
| `item_id` | foreign key | The item being delivered |
| `quantity` | decimal | Quantity delivered |
| `unit` | string | Unit of measure |
| `allocated_to` | enum | `inventory` or `production` (set on approval) |

---

## Production Workflow

Production converts raw goods (ingredients) into finished per-order food products.

### Steps the System Must Support

1. **Create a Production Order**
   - A user selects one or more raw items from inventory
   - For each item, they specify the quantity to consume
   - The production order is assigned to a branch
   - Status starts as `IN_PROGRESS`

2. **Finish the Production**
   - The user marks the order as `FINISHED`
   - The system records which items were produced and their quantities
   - Each output item must have an `allocated_to` field: `INVENTORY`, `SALE`, or `TRANSFER`
   - The system automatically deducts the consumed raw goods from inventory

3. **Wastage Report (if applicable)**
   - If there are any losses or unused leftovers, a wastage report must be generated
   - See [Wastage Report](#wastage-report) for details

### Required Fields per Production Order

| Field | Type | Notes |
|---|---|---|
| `branch_id` | foreign key | Branch where production occurs |
| `status` | enum | `in_progress` or `finished` |
| `finished_at` | timestamp | Nullable — set when marked finished |
| `created_by` | foreign key | The initiating user |

### Production Inputs (Raw Goods Consumed)

| Field | Type | Notes |
|---|---|---|
| `production_order_id` | foreign key | Parent order |
| `item_id` | foreign key | Raw good being used |
| `quantity_used` | decimal | How much was consumed |
| `unit` | string | Unit of measure |

### Production Outputs (Finished Products)

| Field | Type | Notes |
|---|---|---|
| `production_order_id` | foreign key | Parent order |
| `item_id` | foreign key | The finished product |
| `quantity_produced` | decimal | How much was made |
| `unit` | string | Unit of measure |
| `allocated_to` | enum | `inventory`, `sale`, or `transfer` |

---

## Wastage Report

A wastage report must be generated after production if there are any losses or leftover materials.

### Features the Wastage Report Must Support

- Log the quantity lost per item and a reason for the loss
- Optionally **convert leftover items into a different item** and store that back into inventory

### Leftover Conversion Example

> After producing Leche Flan, 2 liters of evaporated milk remained. Instead of discarding, the wastage report allows the user to convert this into **Sahog** (a different item) and store it back in inventory with a specified quantity.

### Required Fields per Wastage Report

| Field | Type | Notes |
|---|---|---|
| `production_order_id` | foreign key | The related production order |
| `branch_id` | foreign key | Branch where the wastage occurred |
| `created_by` | foreign key | User who filed the report |

### Required Fields per Wastage Item

| Field | Type | Notes |
|---|---|---|
| `wastage_report_id` | foreign key | Parent report |
| `item_id` | foreign key | Item with the loss |
| `quantity_lost` | decimal | How much was lost |
| `reason` | string | Optional explanation |
| `convert_to_item_id` | foreign key | Optional — item to convert leftovers into |
| `converted_quantity` | decimal | Optional — how much of the new item is created |

---

## Inter-Branch Transfers

A branch must be able to send items to another branch. All inter-branch transfers must go through the **delivery approval process**.

### Transfer Flow

```
Branch A user initiates a transfer request
              │
              ▼
System auto-creates a Delivery record
(status: PENDING, source = Branch A, destination = Branch B)
              │
              ▼
Branch B (or Admin) sees the incoming pending delivery
              │
              ▼
Admin or Branch B Manager approves the delivery
              │
              ▼
Items are deducted from Branch A's inventory
Items are added to Branch B's inventory
```

### Example

> Branch A wants to send 10 cans of Evaporated Milk to Branch B.
> A transfer is created. Branch B sees a pending delivery.
> Branch B's manager (or Admin) approves it.
> Inventory updates on both sides automatically.

### Required Fields per Transfer

| Field | Type | Notes |
|---|---|---|
| `from_branch_id` | foreign key | The sending branch |
| `to_branch_id` | foreign key | The receiving branch |
| `delivery_id` | foreign key | Auto-created delivery record |
| `status` | enum | `pending`, `approved`, or `rejected` |
| `approved_by` | foreign key | The approving user |
| `created_by` | foreign key | The initiating user |

---

## Sales & VAT

All sales must apply **12% VAT** and must be linked to the user who processed the transaction.

### VAT Calculation

| Field | Formula |
|---|---|
| Subtotal | `unit_price × quantity` |
| VAT Amount | `subtotal × 0.12` |
| Total | `subtotal + vat_amount` |

### Example

| Item | Qty | Unit Price | Subtotal | VAT (12%) | Total |
|---|---|---|---|---|---|
| Sweet & Sour Chicken | 2 | ₱150.00 | ₱300.00 | ₱36.00 | ₱336.00 |
| Fried Chicken | 1 | ₱120.00 | ₱120.00 | ₱14.40 | ₱134.40 |
| **TOTAL** | | | **₱420.00** | **₱50.40** | **₱470.40** |

### Required Fields per Sale

| Field | Type | Notes |
|---|---|---|
| `reference_number` | string | Auto-generated, unique |
| `branch_id` | foreign key | Branch where the sale occurred |
| `user_id` | foreign key | The user who processed the sale |
| `subtotal` | decimal | Sum of all item subtotals |
| `vat_total` | decimal | Sum of all VAT amounts |
| `grand_total` | decimal | `subtotal + vat_total` |

### Required Fields per Sale Item

| Field | Type | Notes |
|---|---|---|
| `sale_id` | foreign key | Parent sale |
| `item_id` | foreign key | Item sold |
| `quantity_sold` | decimal | Quantity |
| `unit_price` | decimal | Price at time of sale |
| `subtotal` | decimal | `unit_price × quantity_sold` |
| `vat_amount` | decimal | `subtotal × 0.12` |
| `total` | decimal | `subtotal + vat_amount` |

---

## Low Stock SMS Notifications

When an item's `quantity` drops to or below its `low_stock_threshold`, the system must **automatically send an SMS** to the branch manager.

### Trigger Conditions

The SMS must fire whenever an item's quantity is updated and the result is:

```
item.quantity <= item.low_stock_threshold
```

This can happen after: a sale, a transfer out, wastage deduction, or manual adjustment.

### SMS Format

```
[Branch Name] LOW STOCK ALERT
Item: [Item Name]
Location: [Location Name] > [Category Name]
Current Stock: [quantity] [unit]
Threshold: [low_stock_threshold] [unit]
Please restock immediately.
```

### SMS API Details

Use the ZapZaf Studios SMS API with the credentials from `.env`.

**Endpoint:** `POST {SMS_ENDPOINT}`

**Request Body:**

```json
{
  "to": "<branch_manager_phone>",
  "message": "<formatted message>",
  "device": "<SMS_DEVICE>",
  "sim": "<SMS_SIM>"
}
```

**Headers:**

```
Content-Type: application/json
Authorization: Bearer <SMS_API_KEY>
```

> The SMS sending must be handled via a **queued job** (`ShouldQueue`) so it does not block the main request.

---

## Database Schema

Below is the full expected schema. The developer must implement these as **Laravel migrations**.

### Tables Overview

| Table | Purpose |
|---|---|
| `users` | All system users with roles |
| `branches` | Branch records |
| `suppliers` | External vendor/supplier records |
| `locations` | Physical storage locations per branch |
| `categories` | Item categories within a location |
| `items` | Inventory items within a category |
| `deliveries` | Incoming delivery records |
| `delivery_items` | Individual items within a delivery |
| `production_orders` | Production batch records |
| `production_inputs` | Raw goods consumed per production |
| `production_outputs` | Finished products per production |
| `wastage_reports` | Loss reports per production order |
| `wastage_items` | Individual waste records |
| `transfers` | Inter-branch transfer records |
| `sales` | Sale transaction records |
| `sale_items` | Individual items per sale |

### Key Relationships

```
users             ──▶  branches         (manager_id)
branches          ──▶  locations
locations         ──▶  categories
categories        ──▶  items
suppliers         ──▶  deliveries        (supplier_id)
branches          ──▶  deliveries        (destination_branch_id)
deliveries        ──▶  delivery_items
items             ──▶  delivery_items
production_orders ──▶  production_inputs
production_orders ──▶  production_outputs
production_orders ──▶  wastage_reports
wastage_reports   ──▶  wastage_items
deliveries        ──▶  transfers         (delivery_id)
sales             ──▶  sale_items
users             ──▶  sales             (user_id)
```

---

## Laravel Architecture

The project must follow standard Laravel conventions. Below is the expected folder structure.

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── BranchController.php
│   │   ├── SupplierController.php
│   │   ├── DeliveryController.php
│   │   ├── InventoryController.php
│   │   ├── LocationController.php
│   │   ├── CategoryController.php
│   │   ├── ItemController.php
│   │   ├── ProductionController.php
│   │   ├── WastageReportController.php
│   │   ├── TransferController.php
│   │   └── SaleController.php
│   ├── Middleware/
│   │   └── RoleMiddleware.php
│   └── Requests/
│       ├── StoreItemRequest.php
│       ├── StoreDeliveryRequest.php
│       ├── StoreSupplierRequest.php
│       ├── StoreProductionRequest.php
│       ├── StoreSaleRequest.php
│       └── ...
├── Models/
│   ├── User.php
│   ├── Branch.php
│   ├── Supplier.php
│   ├── Location.php
│   ├── Category.php
│   ├── Item.php
│   ├── Delivery.php
│   ├── DeliveryItem.php
│   ├── ProductionOrder.php
│   ├── ProductionInput.php
│   ├── ProductionOutput.php
│   ├── WastageReport.php
│   ├── WastageItem.php
│   ├── Transfer.php
│   ├── Sale.php
│   └── SaleItem.php
├── Policies/
│   ├── DeliveryPolicy.php
│   ├── ItemPolicy.php
│   ├── SupplierPolicy.php
│   ├── LocationPolicy.php
│   └── CategoryPolicy.php
├── Services/
│   ├── SmsService.php
│   └── InventoryService.php
└── Jobs/
    └── SendLowStockSms.php

database/
├── migrations/
└── seeders/
    ├── RoleSeeder.php
    └── BranchSeeder.php

routes/
└── api.php
```

---

## API Endpoints

All routes must be protected by `auth:sanctum` middleware. Role restrictions are enforced via Laravel Policies.

### Auth

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/auth/login` | Login and receive API token |
| POST | `/api/auth/logout` | Revoke current token |

### Branches

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/branches` | All roles |
| POST | `/api/branches` | Admin only |
| PUT | `/api/branches/{id}` | Admin only |

### Suppliers

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/suppliers` | All roles |
| POST | `/api/suppliers` | All roles |
| GET | `/api/suppliers/{id}` | All roles |
| PUT | `/api/suppliers/{id}` | Admin, Branch Manager |
| DELETE | `/api/suppliers/{id}` | Admin only |

### Locations & Categories

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/locations` | All roles |
| POST | `/api/locations` | Admin, Branch Manager |
| POST | `/api/locations/{id}/categories` | Admin, Branch Manager |

### Items

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/items` | All roles |
| POST | `/api/items` | All roles |
| PUT | `/api/items/{id}` | Admin, Branch Manager |
| DELETE | `/api/items/{id}` | Admin, Branch Manager |

### Deliveries

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/deliveries` | All roles |
| POST | `/api/deliveries` | All roles (creates as PENDING) |
| GET | `/api/deliveries/{id}` | All roles |
| POST | `/api/deliveries/{id}/approve` | Admin, Branch Manager (own branch) |

### Production

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/productions` | All roles |
| POST | `/api/productions` | All roles |
| POST | `/api/productions/{id}/finish` | All roles |
| POST | `/api/productions/{id}/wastage` | All roles |

### Transfers

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/transfers` | All roles |
| POST | `/api/transfers` | All roles |

### Sales

| Method | Endpoint | Access |
|---|---|---|
| GET | `/api/sales` | All roles |
| POST | `/api/sales` | All roles |
| GET | `/api/sales/{id}` | All roles |

---

## Environment Variables

The `.env` file must include the following. The SMS credentials are pre-defined and must not be changed.

```env
APP_NAME="Inventory Management System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_db
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

# SMS Notification — ZapZaf Studios
SMS_ENDPOINT=https://sms.zapzafstudios.com/api/v1/sms/send
SMS_API_KEY=A98VAXLN9H95E93P8ZTVLM9BCOIKPBBN2B8X8SDU
SMS_DEVICE=0116d89f43ae9b9b
SMS_SIM=1,2
```

| Variable | Description |
|---|---|
| `SMS_ENDPOINT` | The SMS API URL |
| `SMS_API_KEY` | Bearer token for authentication |
| `SMS_DEVICE` | Device ID registered with the SMS provider |
| `SMS_SIM` | SIM slot(s) to use (comma-separated for fallback) |

---

## Business Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                          BRANCH                                 │
│                                                                 │
│   [External Supplier]          [Another Branch]                 │
│          │                            │                         │
│          └──────────┬─────────────────┘                        │
│                     ▼                                           │
│             ┌───────────────┐                                   │
│             │   DELIVERY    │  ← Any user can create (PENDING)  │
│             │   (PENDING)   │                                   │
│             └───────┬───────┘                                   │
│                     │  ← Admin / Branch Manager approves        │
│                     ▼                                           │
│             ┌───────────────┐                                   │
│             │   DELIVERY    │                                   │
│             │  (RECEIVED)   │                                   │
│             └───────┬───────┘                                   │
│                     │                                           │
│             ┌───────┴────────┐                                  │
│             ▼                ▼                                  │
│       ┌──────────┐    ┌────────────┐                            │
│       │INVENTORY │    │ PRODUCTION │                            │
│       └────┬─────┘    └─────┬──────┘                           │
│            │                │                                   │
│            │        ┌───────┴────────────────┐                  │
│            │        ▼                        ▼                  │
│            │  ┌───────────────┐  ┌─────────────────────┐        │
│            │  │   FINISHED    │  │   WASTAGE REPORT    │        │
│            │  │   PRODUCTS    │  │   (losses logged)   │        │
│            │  └──────┬────────┘  └──────────┬──────────┘        │
│            │         │                      │                   │
│            │         │           ┌──────────┴──────────┐        │
│            │         │           ▼                     ▼        │
│            │         │     [Discarded]    [Convert & return      │
│            │         │                    to Inventory]         │
│            ▼         ▼                                          │
│           ┌─────────────────┐                                   │
│           │      SALES      │  ← 12% VAT · linked to User       │
│           └─────────────────┘                                   │
│                                                                 │
│  ── ── ── ── ── INTER-BRANCH TRANSFER ── ── ── ── ── ── ──      │
│  Inventory ──▶ Transfer Created ──▶ Delivery (PENDING)          │
│  ──▶ Approved by Admin / Branch Manager ──▶ Inventory Updated  │
└─────────────────────────────────────────────────────────────────┘

  📉 quantity ≤ low_stock_threshold  →  📱 SMS sent via queue job
```

---

## Summary of Business Rules

1. Inventory must follow a strict **Location → Category → Item** tree. Items cannot exist without a category.
2. **Any authenticated user** can create delivery records and supplier details. Deliveries start as `PENDING`.
3. Only **Admin** (all branches) or **Branch Manager** (own branch) can approve deliveries and update inventory.
4. A delivery must specify either a **supplier** (external source) or a **source branch** (transfer), not both.
5. After a delivery is approved, each item must be allocated to either **inventory** or **production**.
6. Production orders may consume **multiple items** simultaneously and produce **multiple output items**.
7. Wastage reports must be filed for any production losses. Leftovers can be **converted into a different item** and returned to inventory.
8. Inter-branch transfers are handled as deliveries and require approval from the **receiving branch**.
9. All sales must compute and store **12% VAT** per line item and in the sale total.
10. When any item's quantity reaches or drops below its threshold, a **queued SMS job** must fire automatically.
11. **Regular Users** can create items, deliveries, and suppliers — but cannot edit or delete existing records.

---

*Project Specification v1.0 — Inventory Management System*
*Status: Awaiting Development*