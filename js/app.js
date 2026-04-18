/**
 * Purchase Tracker Application
 * Main JavaScript application logic
 */

class PurchaseTracker {
    constructor() {
        this.apiBase = '/api';
        this.currentView = 'dashboard';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadDashboard();
    }

    setupEventListeners() {
        // Mobile navigation
        document.querySelectorAll('.mobile-nav__item').forEach(item => {
            item.addEventListener('click', (e) => {
                const view = e.currentTarget.dataset.view;
                if (view) this.loadView(view);
            });
        });

        // Desktop navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const view = link.dataset.view;
                if (view) this.loadView(view);
            });
        });

        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#item-form')) {
                this.handleItemForm(e);
            } else if (e.target.matches('#sale-form')) {
                this.handleSaleForm(e);
            }
        });

        // CSV import
        document.getElementById('csv-import')?.addEventListener('change', (e) => {
            this.handleCSVImport(e);
        });

        // Photo upload
        document.getElementById('photo-upload')?.addEventListener('change', (e) => {
            this.handlePhotoUpload(e);
        });
    }

    async loadView(view) {
        this.currentView = view;
        this.updateNavigation(view);
        
        switch (view) {
            case 'dashboard':
                await this.loadDashboard();
                break;
            case 'items':
                await this.loadItems();
                break;
            case 'sales':
                await this.loadSales();
                break;
            case 'reports':
                await this.loadReports();
                break;
            case 'import':
                await this.loadImport();
                break;
            case 'photos':
                await this.loadPhotos();
                break;
        }
    }

    async loadDashboard() {
        try {
            const response = await fetch(`${this.apiBase}/reports`);
            const data = await response.json();
            this.renderDashboard(data);
        } catch (error) {
            console.error('Failed to load dashboard:', error);
        }
    }

    async loadItems() {
        try {
            const response = await fetch(`${this.apiBase}/items`);
            const data = await response.json();
            this.renderItems(data.data);
        } catch (error) {
            console.error('Failed to load items:', error);
        }
    }

    async loadSales() {
        try {
            const response = await fetch(`${this.apiBase}/sales`);
            const data = await response.json();
            this.renderSales(data.data);
        } catch (error) {
            console.error('Failed to load sales:', error);
        }
    }

    async loadReports() {
        try {
            const response = await fetch(`${this.apiBase}/reports`);
            const data = await response.json();
            this.renderReports(data);
        } catch (error) {
            console.error('Failed to load reports:', error);
        }
    }

    async loadImport() {
        // Show import form
        document.getElementById('import-section')?.classList.remove('hidden');
    }

    async loadPhotos() {
        try {
            const response = await fetch(`${this.apiBase}/items`);
            const data = await response.json();
            this.renderPhotos(data.data);
        } catch (error) {
            console.error('Failed to load photos:', error);
        }
    }

    async handleItemForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch(`${this.apiBase}/items`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                this.showNotification('Item added successfully!');
                e.target.reset();
                await this.loadItems();
            } else {
                throw new Error('Failed to add item');
            }
        } catch (error) {
            console.error('Error adding item:', error);
            this.showNotification('Failed to add item', 'error');
        }
    }

    async handleSaleForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch(`${this.apiBase}/sales`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                this.showNotification('Sale recorded successfully!');
                e.target.reset();
                await this.loadSales();
            } else {
                throw new Error('Failed to record sale');
            }
        } catch (error) {
            console.error('Error recording sale:', error);
            this.showNotification('Failed to record sale', 'error');
        }
    }

    async handleCSVImport(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        
        try {
            const response = await fetch(`${this.apiBase}/import`, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                this.showNotification('CSV imported successfully!');
                e.target.value = '';
            } else {
                throw new Error('Failed to import CSV');
            }
        } catch (error) {
            console.error('Error importing CSV:', error);
            this.showNotification('Failed to import CSV', 'error');
        }
    }

    async handlePhotoUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('photo', file);
        
        try {
            const response = await fetch(`${this.apiBase}/photos`, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                this.showNotification('Photo uploaded successfully!');
                e.target.value = '';
            } else {
                throw new Error('Failed to upload photo');
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
            this.showNotification('Failed to upload photo', 'error');
        }
    }

    updateNavigation(activeView) {
        document.querySelectorAll('.nav-link, .mobile-nav__item').forEach(el => {
            el.classList.toggle('active', el.dataset.view === activeView);
        });
    }

    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification--${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('notification--show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('notification--show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Dashboard rendering
    renderDashboard(data) {
        document.getElementById('total-profit').textContent = 
            new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(data.profit);
        document.getElementById('total-items').textContent = data.total_items;
        document.getElementById('inventory-value').textContent = 
            new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(data.inventory_value);
        
        const recentSalesList = document.getElementById('recent-sales');
        if (recentSalesList) {
            recentSalesList.innerHTML = data.recent_sales.map(sale => `
                <div class="activity-list__item">
                    <div class="activity-list__icon" style="background: var(--primary-fixed); color: var(--primary);">
                        <span class="material-symbols-outlined">sell</span>
                    </div>
                    <div class="activity-list__content">
                        <div class="activity-list__title">${this.escapeHtml(sale.item_name)}</div>
                        <div class="activity-list__meta">
                            Sold for ${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(sale.sale_price)} 
                            via ${this.escapeHtml(sale.sale_platform)}
                        </div>
                    </div>
                </div>
            `).join('');
        }
    }

    // Items rendering
    renderItems(items) {
        const container = document.getElementById('items-list');
        if (!container) return;
        
        container.innerHTML = items.map(item => `
            <div class="card">
                <div class="card__header">
                    <h3 class="card__title">${this.escapeHtml(item.name)}</h3>
                    <button class="btn btn--small btn--danger" onclick="app.deleteItem(${item.id})">Delete</button>
                </div>
                <div class="grid grid--2">
                    <div class="metric-card">
                        <div class="metric-card__icon" style="background: var(--primary-fixed); color: var(--primary);">
                            <span class="material-symbols-outlined">attach_money</span>
                        </div>
                        <div class="metric-card__value">$${item.purchase_price}</div>
                        <div class="metric-card__label">Purchase Price</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__icon" style="background: var(--tertiary-fixed); color: var(--tertiary);">
                            <span class="material-symbols-outlined">attach_money</span>
                        </div>
                        <div class="metric-card__value">$${item.current_retail_price || 0}</div>
                        <div class="metric-card__label">Retail Price</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Sales rendering
    renderSales(sales) {
        const container = document.getElementById('sales-list');
        if (!container) return;
        
        container.innerHTML = sales.map(sale => `
            <div class="card">
                <div class="card__header">
                    <h3 class="card__title">${this.escapeHtml(sale.item_name)}</h3>
                    <span class="badge badge--success">Sold</span>
                </div>
                <div class="grid grid--2">
                    <div class="metric-card">
                        <div class="metric-card__value">$${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(sale.sale_price)}</div>
                        <div class="metric-card__label">Sale Price</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__value">$${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(sale.shipping_cost)}</div>
                        <div class="metric-card__label">Shipping</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Reports rendering
    renderReports(data) {
        // Implement reports rendering
        console.log('Reports data:', data);
    }

    // Photo rendering
    renderPhotos(items) {
        const container = document.getElementById('photos-grid');
        if (!container) return;
        
        container.innerHTML = items.map(item => `
            <div class="card">
                <img src="${item.photo_path || 'placeholder.jpg'}" alt="${item.name}" style="width: 100%; height: 200px; object-fit: cover; border-radius: var(--border-radius);" />
                <h4>${this.escapeHtml(item.name)}</h4>
            </div>
        `).join('');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    window.app = new PurchaseTracker();
});