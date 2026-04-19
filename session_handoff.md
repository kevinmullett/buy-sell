# "Bought It" Session Handoff - April 19, 2026

## 🚀 Project Status
The bulk-import pipeline is now fully stabilized and multi-format compatible. Historical data (2022-2024) and recent sales (2026) are successfully ingested.

## 💻 Technical Stack
- **Backend**: PHP 8.x (Native, performance-focused)
- **Database**: SQLite 3 (Database: `data/purchase_tracker.db`)
- **Frontend**: Vanilla JavaScript (ES6+ SPA architecture), HTML5, CSS3 (Modern Flex/Grid)
- **Design**: Google Material Symbols, Google Fonts (Inter), Modern Dark/Light mode support
- **Infrastructure**: Docker (Apache/PHP), Git/GitHub
- **Key PHP Extensions**: `ZipArchive` (XLSX), `SimpleXML` (XML/PDF parsing), `PDO_SQLite` (Data)


## ✅ Completed Tasks
- **Multi-Format XML Parsing**: Added support for Adobe Acrobat "Save As XML" (TR/TD format) used in older eBay statements.
- **Smarter XLSX Import**: Upgraded the inventory parser to be permissive with prices (extracts from `$10`, `10.00 ea`, etc.) and quantities (`(x5)`, `5ea`).
- **Data Integrity Cleanup**:
    - Fixed erroneous 2026 dates from historical imports.
    - Restored legitimate April 2026 sales.
    - Standardized `Unlisted` items to `Available` status for dashboard visibility.
    - Cleared unsold items to allow for a clean, high-precision re-import of the Master Inventory.
- **UI Enhancements**: Added "All Time" filter to Sales & Reports.
- **Database Schema**: Added `fees` column to the `sales` table for accurate P&L tracking.

## 🛠 Technical Details
- **Active Database**: `data/purchase_tracker.db` (SQLite).
- **Core Logic**:
    - `api/statement_import.php`: Handles both Excel XML and Adobe Tagged PDF XML.
    - `api/import_inventory.php`: Refined regex-based extraction for unit prices and quantities.
    - `api/reports.php`: Supports `year=all` and calculates profit as `sale_price - purchase_price - shipping_cost - fees`.

## 📌 Pending / Next Steps
1.  **Reconciler Tool (High Priority)**: Build the interface to link `$0 cost` sales (from eBay statements) to actual inventory records (from XLSX).
    - Match logic: Search by Item ID (if available) or Fuzzy Name + Date.
2.  **Missing XLSX Items**: Investigate why only ~2,700 items imported from a 6,000-row sheet.
    - Check for hidden sheets or titles < 4 characters.
3.  **Cross-Platform P&L**: Ensure other platforms (Poshmark, etc.) follow the same fee/net structure.

## 💾 Repository
Pushed to: `https://github.com/kevinmullett/buy-sell` (master)
Includes `.gitignore` (excludes assets, data, and scratch scripts).
