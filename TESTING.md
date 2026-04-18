# Local Testing Guide for Buy-Sell Application

## Quick Start with Docker

### Build and Run
```bash
# Build and start the application
docker-compose up --build -d

# Access the application
# Open http://localhost:8080 in your browser

# Stop the application
docker-compose down

# View logs
docker-compose logs -f
```

### What's Included in the Docker Setup
- **PHP 8.2 Apache** web server
- **MySQL 8.0** database
- **Persistent database storage** via Docker volume
- **Automatic network configuration** for container communication

## Testing Environment Features

### 1. Development Mode (via Docker)
The application runs in a fully functional PHP environment with:
- All PHP extensions required (pdo, pdo_mysql)
- Apache with mod_rewrite enabled
- Proper file permissions
- MySQL database connectivity

### 2. Test UI Pages
Two test interfaces are available for local testing:

#### `test-ui-accurate.html`
- **Design-accurate** version matching your production CSS
- All visual elements match the actual application
- Perfect for UI/UX testing and iteration
- Includes:
  - Complete dashboard with stats
  - Full inventory management (CRUD operations)
  - Add/edit item forms
  - Settings with toggle switches
  - Transaction workflow visualization
  - Modal dialogs and notifications
  - Responsive design testing

#### `test-ui.html`
- **Basic template** with all functionality
- Good for rapid prototyping
- Same features as accurate version but with generic styling

### 3. Core Functionality to Test

#### Dashboard
- Real-time statistics updates
- Recent activity display
- Refresh functionality with notifications

#### Inventory Management
- Add new items (POST requests)
- Edit existing items (PUT/PATCH requests)
- Delete items (DELETE requests)
- Sell items (transaction simulation)
- Sort by: Name, Price, Date
- Filter by: Price range

#### Settings
- Toggle switches for preferences
- Save/Reset functionality
- Two-factor authentication toggle
- Session timeout settings

#### Workflow
- 5-step transaction process
- Step-by-step completion tracking
- Simulation mode for testing
- Reset functionality

## Testing Checklist

### ✅ UI/UX Testing
- [ ] Verify color scheme matches production
- [ ] Test responsive design on different screen sizes
- [ ] Check modal dialog functionality
- [ ] Test notification system
- [ ] Verify form validation
- [ ] Test drag-and-drop (if applicable)

### ✅ Functionality Testing
- [ ] Add item with all fields
- [ ] Edit existing item
- [ ] Delete item with confirmation
- [ ] Sell item workflow
- [ ] Sort and filter inventory
- [ ] Settings persistence
- [ ] Workflow step completion

### ✅ Performance Testing
- [ ] Load time under 2 seconds
- [ ] Smooth animations and transitions
- [ ] No JavaScript errors in console
- [ ] Database queries performant

### ✅ Cross-Browser Testing
- [ ] Chrome - latest version
- [ ] Firefox - latest version
- [ ] Safari - latest version
- [ ] Edge - latest version
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

## Database Setup

### MySQL Database Schema
The application uses MySQL with the following structure:

```sql
-- Example schema (check database_schema.sql for full structure)
CREATE DATABASE buy_sell;
USE buy_sell;

-- Items table
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    condition VARCHAR(20),
    date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales table
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    sale_date DATE,
    sale_platform VARCHAR(50),
    sale_price DECIMAL(10,2),
    shipping_cost DECIMAL(10,2),
    FOREIGN KEY (item_id) REFERENCES items(id)
);
```

## API Testing

### Endpoints (when connected to backend)
- `GET /api/items` - List all items
- `POST /api/items` - Create new item
- `PUT /api/items/{id}` - Update item
- `DELETE /api/items/{id}` - Delete item
- `GET /api/sales` - List sales
- `POST /api/sales` - Record sale
- `GET /api/reports` - Generate reports

### Testing with Postman/curl
```bash
# Get all items
curl http://localhost:8080/api/items

# Add new item
curl -X POST http://localhost:8080/api/items \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Item","price":29.99,"category":"electronics"}'
```

## Debugging Tips

### JavaScript Console
- Open browser developer tools (F12)
- Check Console tab for errors
- Monitor Network tab for API calls

### PHP Errors
- Check Docker logs: `docker-compose logs web`
- Enable error reporting in development:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```

### Database Connection
- Verify MySQL is running: `docker-compose ps`
- Check database credentials in database.php
- Test connection: `docker-compose exec db mysql -u appuser -papppassword buy_sell`

## Next Steps

1. **Run the application**: `docker-compose up --build -d`
2. **Open test UI**: Navigate to `http://localhost:8080/test-ui-accurate.html`
3. **Test core features**: Add, edit, delete items
4. **Verify design accuracy**: Compare with production UI
5. **Iterate and refine**: Make improvements based on testing

## Troubleshooting

### Port Already in Use
```bash
# Find and kill process using port 8080
lsof -ti:8080 | xargs kill -9
```

### Database Connection Issues
```bash
# Restart database
docker-compose restart db

# Check database logs
docker-compose logs db
```

### File Permission Issues
```bash
# Rebuild with correct permissions
docker-compose down
docker-compose up --build -d
```

## Additional Testing Tools

- **BrowserStack** - Cross-browser testing
- **Lighthouse** - Performance auditing
- **React Developer Tools** - Component inspection
- **Vue Devtools** - If using Vue.js
- **Postman** - API testing
- **Charles Proxy** - Network debugging