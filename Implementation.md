## Implementation and Concerns

- Can you change the receipt format to print on a receipt printer roll instead of bond paper size?
- As regards to `production/edit.blade.php`, please remove **Current Outputs** and **Wastage History** since production is only processed once. Once the production is finished, it can no longer be edited, making the history and current output sections unnecessary because there is only one production record.
- As regards to delivery, please remove **Batch Total** and **Unit Price**, and replace them with a single **Price** field only.
- Also, please add the **Total Price** below the table.
- Please allow the admin to edit a delivery report, but only the delivery price should be editable.
- Please also refactor the codebase and fix existing coding errors to improve maintainability, readability, and system stability.

## Items and Inventory

- When a user processes a delivery, the item should be transferred to another branch. Make sure the user selects an item from the modal before proceeding.
- If the user is an admin, the delivery should be automatically approved at the destination branch.
- Make sure the supplier is based on the origin branch.
- Delivery Reports and Inventory Transactions must be branch-specific.

## Delivery and Production Flow

### Flow
Delivery → Either goes to Inventory or Production

- Every delivered item must be logged directly into the **Inventory Transactions** if the item is not intended for production.
- If the delivered item is intended for production, it should first go to the **Production Module**.
- Once the production process is finished, the produced items must also be automatically logged into the **Inventory Transactions**.

## Costing Report

- Implement a **Costing Report** module.
- The costing report should automatically update the price of the item based on the latest production or delivery cost.
- The updated inventory price should only reflect once the transaction or delivery has been approved by the admin.
- Ensure that the updated costing reflects correctly in inventory, delivery reports, and related transactions.