## Issues

- When a delivery is approved and the destination is set to **Inventory**, it does not record in the **transaction logs**.
- Transaction logs are missing the following fields:
  - Beginning quantity
  - Item ID
  - Transaction price
  - Approval date
- When creating a transaction, there is no **transaction date**, which is required since some items are delayed (especially from the main branch with a separate system).

---

## Required Fixes

### Transaction Logs
- Add **transaction date**
- Add **transaction item price**
- Add **beginning quantity column**

---

### Inventory (index.blade.php)
- Add **date field** when adding or deducting stock
- When deducting stock, calculate and store:
  - **Total price = quantity × item price**

---

### Delivery Report
- When approving a delivery:
  - If destination is **Inventory**, create a corresponding **transaction log**
  - If destination is **Production**, do NOT create a transaction log yet

---

### Production Flow
- If items are sent to **Production**:
  - Proceed to processing
  - Do NOT log to transaction logs during this stage
  - Only create a **transaction log once production is completed**

---

### Example Transaction Log
- Item ID: 12312311
- Item Name: Apple
- Beginning: 1
- Quantity: 10
- Type: Add
- Remaining: 11
- Reason: FROM DELIVERY ID: 23131231
- Transaction Date: (date here)
- Transaction Price: (price here)