# Purchase Tracker App

A mobile-friendly web application for tracking purchases from various sources (bin stores, auctions, pallet sales, thrifting, garage sales, retail arbitrage) with comprehensive reporting for sales and taxes.

## Features

- **Track Purchases**: Record items with date, price, location, notes, and photos
- **Track Sales**: Record sales with platform (eBay, Facebook Marketplace, etc.), price, and packing method
- **Profit Analysis**: Calculate profit, net profit, profit margin, and time to sell
- **Advanced Reporting**: 
  - Best items by profit, margin, or velocity
  - Reports by category, location, or platform
  - Schedule C tax report formatting
- **CSV Import**: Import purchases and sales from CSV files
- **Photo Management**: Upload and manage item photos
- **Location Tracking**: Track performance of different selling locations
- **Packing & Shipping**: Track packing methods and shipping costs
- **Mobile-First**: Responsive design that works on all devices
- **PWA Ready**: Installable progressive web app

## Quick Start

### Development Setup

```bash
# Clone the repository
git clone <repository-url>
cd purchase-tracker

# Install dependencies
npm install

# Start development server
npm run dev
```

### Deployment

The app can be deployed to any PHP-compatible hosting (like Hostgator) or as a static site with Netlify/Vercel.

## CSV Import Formats

### Purchases CSV
```csv
name,purchase_date,purchase_price,purchase_location,purchase_notes,category,current_retail_price,quantity,condition,photo_path
"Sprayway Glass Cleaner","2025-12-31",2.00,"Goodwill Store","Beautiful condition",Home Decor,15.99,1,Like New,photos/sprayway_glass.jpg
```

### Sales CSV (eBay)
```csv
item_id,sale_date,sale_price,sale_platform,sale_location,sale_notes,packing_method,shipping_cost
123,"2026-01-15",45.00,eBay,"Local Pickup","Fast sale","Bubble Mailer",0.00
```

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5, Chart.js
- **Backend**: PHP/MySQL (Hostgator compatible) or Python/Flask/SQLite
- **Database**: MySQL (production) or SQLite (development)
- **Mobile**: Responsive PWA with offline capabilities

## Project Structure

```
purchase-tracker/
├── index.php              # Main dashboard
├── api/                   # API endpoints
│   ├── items.php
│   ├── sales.php
│   └── reports.php
├── import.php             # CSV import interface
├── photos/                # Photo storage
├── css/                   # Custom styles
├── js/                    # JavaScript functionality
├── database.php           # Database connection
└── README.md
```

## Reporting Examples

### Profit Report
- Total profit by period
- Net profit (including shipping costs)
- Profit margin percentages

### Tax Report (Schedule C)
- Annual income summary
- Category-based income breakdown
- Export-ready format

### Best Items Analysis
- Top profit generators
- Fastest selling items
- Best margins by category

## Future Enhancements

- eBay API integration for automatic sales import
- Barcode/UPC scanning support
- Advanced photo OCR for data extraction
- Multi-user support with permissions
- Automated tax form generation