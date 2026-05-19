# System Delivery Module Improvements
## 1. Select Branch if Admin
If user adds it automatically selects a branch
## 1. Delivery Form Structure

When adding a delivery, the form should include the following:

### 1.1 Select Supplier
- The user must select a supplier before adding items.

### 1.2 Add Items
Replace the “Select Item” dropdown with a manual input field to allow flexibility for different brands and suppliers.

Each item entry must include:
- **Item Description** (manual input, no dropdown)
- **Quantity**
- **Unit** (must be drop down all units)
- **Price**
- **Destination**

---

## 2. Action Buttons

Each added item should have the following actions:

### 2.1 Select Destination
- Opens a **modal**
- Allows selection based on **item name**
- When searching Displays the following under the item name:
  - Item ID
  - Location
  - Subcategory
- Must include an option to:
  - Send item to **production** (for production reporting)

### 2.2 Remove
- Removes the item from the list before submission

---

## 3. Submission Flow

- After submission:
  - The system should create a **Pending Delivery**
- On the **Admin side**:
  - The delivery should be **automatically created/approved immediately**

---

## 4. Delivery Listing (index.blade.php)

- After submission, all added items must be displayed in the table inside `index.blade.php`
- Each item should be listed individually with complete details:
  - Item Description
  - Quantity
  - Unit
  - Price
  - Destination
  - Status



## Issues to fix:
- The Destination column should not contain any buttons. Only the Action column should have a button to select the destination.

- Destination modal must include:
  1. Selection option:
     - Production
     - Inventory Storage

  2. If Inventory Storage is selected:
     - User must be able to search by:
       - Item Name
       - Item ID
     - Search results must display:
       - Item Name
       - Item ID
       - Quantity
       - Location
       - Category
     - Results should be filtered within the selected branch only

- Remove Delivery Source; it is not useful. Also include and review the controllers attached to that module.
- Do not use icons other than Feather icons. Thank you.
- Remove the Destination icon button from the action column. Instead, place a [Select] button in the Destination column.
- The table must show a Select button first, then the user chooses directly between Inventory or Production, then saves.
- Remove the Destination Branch dropdown and its functionality.
- The button label should not change its value whether the user is an admin or not. Branch Managers should also be able to automatically approve deliveries.
- As an Admin, I cannot select a branch from the form.
- When storing a delivery, it throws this error: Call to undefined method App\Http\Controllers\DeliveryManagementController::authorize()
- The badge in the Destination column must have white text when it is set to Production.
- In index.blade.php, make it a table, and move the details to view.blade.php along with the contents of the delivery. Also remove the Location column from the table and fix the price format to ₱2,599.00
## Updates:
- Why is the modal steps are broken please do the steps first select either production or inventory storage if they select inventory storage they must be select items. If production it will head to production report
---