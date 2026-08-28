# Phase 13: Fixed Assets & Depreciation Design

## Accounting policy

- The initial method is straight-line depreciation, posted once per calendar month.
- Depreciation starts in the month an asset is available for use. The final period is adjusted to the salvage value, so rounding cannot depreciate below it.
- Every capitalization, monthly depreciation, disposal, and write-off uses `JournalPostingService`. Existing accounting-period locks and source-event idempotency therefore apply.

## Capitalization sources

- Approved or paid expense: `Dr Fixed Asset / Cr Operating Expense` reclassification, excluding input VAT.
- Goods receipt: `Dr Fixed Asset / Cr Inventory` reclassification, excluding input VAT.
- Purchase orders are commitments, not recognition events. A PO is represented through its GRN before capitalization to avoid a duplicate posting.

## Lifecycle

`active -> disposed | written_off`

Before disposal, the service posts any missing depreciation through the prior month. It then removes asset cost and accumulated depreciation and records proceeds plus gain or loss. A non-active asset cannot be depreciated or disposed again.

## Operational command

`php artisan assets:depreciate --month=YYYY-MM`

Without `--month`, the command posts the previous month. The scheduler runs it on the first day of each month at 01:00.
