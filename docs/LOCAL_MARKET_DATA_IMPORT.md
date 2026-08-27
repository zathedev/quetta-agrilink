# Local Market-Data Import

Use the administrator-only **Workspace → Market-data import** register to add approved local reference prices. The application begins with no market-price records and never supplies fictional local data. Every successful batch records its named source, optional source reference, filename, timestamp, importer, and the number of new or updated rows; it does not retain the uploaded file.

## Prepare the source file

Confirm the source owner, collection date, market area, units, and data-update process before import. Save a local CSV using exactly this header row and sequence, with no extra columns:

```text
product,district,minimum_price,maximum_price,average_price,unit,price_date,notes
```

| Column | Required format | Validation rule |
|---|---|---|
| `product` | Active Quetta AgriLink produce-category name | Must match an active local category. |
| `district` | Existing local district name | Must match a configured reference location. |
| `minimum_price`, `maximum_price`, `average_price` | Non-negative decimal with up to two places | Minimum ≤ average ≤ maximum. |
| `unit` | Plain text, such as `kg`, `crate`, `bag`, or `tonne` | 1–30 letters, spaces, underscores, or hyphens. |
| `price_date` | `YYYY-MM-DD` | Must be a real calendar date. |
| `notes` | Optional factual context | Up to 500 characters; never enter credentials or formula-like values. |

## Protected import sequence

| Step | Action | Outcome |
|---|---|---|
| 1 | Import `database/migrations/20260827_add_market_price_imports.sql` once. | Source fields and protected import history are available. |
| 2 | Sign in as an administrator and open **Market-data import**. | The protected source and batch register opens. |
| 3 | Enter the accountable source and any stable reference, then select the approved CSV. | The source context accompanies every row in the batch. |
| 4 | Submit **Validate and import local prices**. | All rows are checked before any row is saved. |
| 5 | Correct any reported row locally and submit again. | A failed validation writes no price row and no batch record. |
| 6 | Review the public **Market prices** register and protected batch history. | The local reference range and named source are visible; the original CSV is not stored. |

The importer accepts at most **500 data rows** and a **2 MB** CSV per batch. When an imported row has the same product, district, date, and unit as an existing price record, it updates that reference record and records the change in the new batch. It does not create a marketplace listing, offer, order, payment, message, notification, or external email.

> **Operational boundary:** A named source explains the basis of a reference record. It does not make the range a guaranteed trade price, an offer, a payment request, financial advice, or a claim of live availability.
