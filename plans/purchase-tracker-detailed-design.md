# Purchase Tracker App - Detailed Design Plan

## Updated Requirements from User Feedback

### Key Insights from User Response
1. **Current workflow**: Uses Amazon Lists, converts to spreadsheet
2. **Sample spreadsheet columns**: 
   - Column C: Retail price (if known based on Amazon)
   - Column D: Quantity
   - Red/green highlighting for keeping/selling decisions
   - "$2 - 12/31/25 (keeping)" format in some cells
3. **Tax reporting**: Schedule C (business income tracking)
4. **Photo importance**: "Photos would be very helpful" (priority increase)
5. **Hosting**: Has Hostgator Baby Croc plan (PHP/MySQL compatible)
6. **Platform preference**: Mobile website or PWA (not native app)
7. **Additional need**: "Packing I put it in" tracking for shipping
8. **Design assets**: Color palette and design elements provided in assets folder

## Revised Database Schema

### Tables

#### `items` (Main inventory)
- `id` (INTEGER PRIMARY KEY)
- `name` (TEXT NOT NULL)
- `purchase_date` (DATE NOT NULL)
- `purchase_price` (DECIMAL(10,2) NOT NULL)
- `purchase_location` (TEXT)
- `purchase_notes` (TEXT)
- `category` (TEXT)
- `current_retail_price` (DECIMAL(10,2)) -- Optional, for known retail values
- `quantity` (INTEGER DEFAULT 1)
- `condition` (TEXT) -- New, Like New, Good, Fair, Poor
- `photo_path` (TEXT) -- URL/path to photo
- `created_at` (DATETIME DEFAULT CURRENT_TIMESTAMP)
- `updated_at` (DATETIME DEFAULT CURRENT_TIMESTAMP)

#### `sales`
- `id` (INTEGER PRIMARY KEY)
- `item_id` (INTEGER REFERENCES items(id))
- `sale_date` (DATE NOT NULL)
- `sale_price` (DECIMAL(10,2) NOT NULL)
- `sale_platform` (TEXT) -- Facebook Marketplace, eBay, Garage Sale, etc.
- `sale_location` (TEXT)
- `sale_notes` (TEXT)
- `packing_method` (TEXT) -- "Green Bubble Mailer", etc.
- `shipping_cost` (DECIMAL(10,2)) -- Optional shipping cost
- `created_at` (DATETIME DEFAULT CURRENT_TIMESTAMP)

#### `categories`
- `id` (INTEGER PRIMARY KEY)
- `name` (TEXT UNIQUE NOT NULL)
- `description` (TEXT)

#### `locations`
- `id` (INTEGER PRIMARY KEY)
- `name` (TEXT UNIQUE NOT NULL)
- `type` (TEXT) -- Goodwill, Auction House, Thrift Store, Garage Sale, etc.
- `notes` (TEXT)

## Enhanced Features Based on User Needs

### 1. Advanced Data Import
- **Purchase CSV Import**: Enhanced format supporting all new fields
- **Sales CSV Import**: eBay and platform-specific formats
- **Photo Import**: Support for uploading photos during import
- **Bulk Operations**: Import multiple items at once

### 2. Packing & Shipping Tracking
- **Packing Method Field**: Track what packaging was used
- **Shipping Cost Tracking**: Include in sales for true profit calculation
- **Shipping Label Integration**: Future enhancement

### 3. Enhanced Reporting
- **Profit Calculations**: 
  - Simple profit: sale_price - purchase_price
  - Net profit: sale_price - purchase_price - shipping_cost
  - Profit margin: (net_profit / sale_price) * 100
- **Time Analysis**:
  - Days to sell: sale_date - purchase_date
  - Sell velocity by category/location
- **Tax Reports**:
  - Schedule C formatted output
  - Annual profit/loss summaries
  - Category-based income reports
- **Best Items Analysis**:
  - By profit amount
  - By profit margin
  - By sell velocity
  - By location/platform

### 4. Photo Management
- **Photo Upload**: During item creation/editing
- **Photo Gallery**: View all item photos
- **Photo Import**: Batch photo import from directory
- **Photo Tags**: Associate multiple photos with items

### 5. Location Management
- **Location Types**: Goodwill, Auction House, Thrift Store, Garage Sale, Retail, etc.
- **Location Notes**: Additional info about selling venues
- **Location Performance**: Track which locations perform best

## CSV Import Specifications (Enhanced)

### Purchases CSV Format
```csv
name,purchase_date,purchase_price,purchase_location,purchase_notes,category,current_retail_price,quantity,condition,photo_path
"Sprayway Glass Cleaner","2025-12-31",2.00,"Goodwill Store","Beautiful condition",Home Decor,15.99,1,Like New,photos/sprayway_glass.jpg
"T-Shirt","2025-12-31",2.00,"Bargain Lane","Great find",Apparel,25.00,3,New,photos/tshirt.jpg
```

### Sales CSV Format (eBay)
```csv
item_id,sale_date,sale_price,sale_platform,sale_location,sale_notes,packing_method,shipping_cost
123,"2026-01-15",45.00,eBay,"Local Pickup","Fast sale","Bubble Mailer",0.00
```

## User Interface Design

### Mobile-First Approach
- **Responsive Design**: Works on all mobile devices
- **Touch-Friendly**: Large buttons, easy form inputs
- **Progressive Enhancement**: Basic functionality first, advanced features optional
- **PWA Capabilities**: Installable, offline support

### Key Screens
1. **Dashboard**: Overview, recent sales, profit summary
2. **Items List**: Browse all items with filters
3. **Item Detail**: Full item information, photos, history
4. **Add/Edit Item**: Form for data entry
5. **Sales Entry**: Record sales with platform selection
6. **Reports**: All reporting and analytics
7. **Import**: CSV import interface
8. **Photos**: Photo management

### Color Palette (from assets)
- Primary: [Color from design assets]
- Secondary: [Color from design assets]
- Success: Green (#28a745)
- Warning: Yellow (#ffc107)
- Danger: Red (#dc3545)
- Info: Blue (#17a2b8)

## Technology Stack (Updated)

### Backend Options
1. **PHP/MySQL** (Hostgator compatible)
   - Simple deployment on existing hosting
   - No additional dependencies
   - Good for budget constraints

2. **Python/Flask/SQLite** (Alternative)
   - Easier development
   - Better for future scaling
   - Can export to PHP later

### Frontend
- **HTML5/CSS3/JavaScript** (Vanilla)
- **Bootstrap 5** (Responsive framework)
- **Chart.js** (Data visualization)
- **PWA Manifest** (Installability)

## Implementation Roadmap

### Phase 1: Core Functionality
- Basic item tracking (CRUD operations)
- Simple data entry forms
- Basic dashboard
- SQLite database setup

### Phase 2: Import/Export
- CSV import for purchases
- CSV export for sales
- Photo upload functionality

### Phase 3: Enhanced Features
- Advanced reporting
- Location management
- Packing/shipping tracking
- Tax report generation

### Phase 4: Polish & Optimization
- PWA features
- Performance optimization
- Mobile UX improvements
- Export formatting

## Security Considerations
- Input validation on all forms
- SQL parameterization
- File upload security (if photos enabled)
- Data backup procedures

## Future Enhancements
- eBay API integration
- Photo OCR for data extraction
- Barcode/UPC scanning
- Multi-user support
- Advanced analytics
- Automated tax form generation