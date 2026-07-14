# Shopify-Like CSV Upload Fixtures

These files are upload-ready for the current assessment wizard CSV importer.

They use realistic Shopify-style merchant data values, but the headers are the app's normalized MVP import format, not raw Shopify Admin exports. Upload them through the wizard cards:

- `products.csv` -> Products
- `orders_and_returns.csv` -> Orders & returns
- `inventory_and_locations.csv` -> Inventory & locations

Expected inferred answers:

- `products.csv` should infer SKU count as `Under 500`.
- `orders_and_returns.csv` should infer monthly order volume as `10,001-50,000`.
