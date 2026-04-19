/**
 * Bought It — Purchase Tracker
 * Main SPA Application Logic
 */

class BoughtItApp {
    constructor() {
        this.apiBase = '/api';
        this.currentView = 'dashboard';
        this.currentFilter = 'all';
        this.items = [];
        this.categories = [];
        this.locations = [];
        this.packaging = [];
        this.currentItemId = null;   // tracks item being added/edited for photos
        this.scanStream = null;      // camera stream for barcode scanning
        this.init();
    }

    async init() {
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(el => { el.value = today; });

        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.replace('light', 'dark');
            const cb = document.getElementById('setting-dark-mode');
            if (cb) cb.checked = true;
        }

        // Populate report year dropdown (current year back to 2020)
        const yearSel = document.getElementById('rpt-year');
        if (yearSel) {
            const curYear = new Date().getFullYear();
            yearSel.innerHTML = '<option value="all">All Time</option>';
            for (let y = curYear; y >= 2020; y--) {
                yearSel.innerHTML += `<option value="${y}"${y === curYear ? ' selected' : ''}>${y}</option>`;
            }
        }
        // Set current month in report month dropdown
        const monthSel = document.getElementById('rpt-month');
        if (monthSel) monthSel.value = ''; // default = full year

        await Promise.all([
            this.loadCategories(),
            this.loadLocations(),
            this.loadPackaging(),
        ]);
        await this.loadDashboard();
    }

    // ==================== NAVIGATION ====================
    navigate(view, event) {
        if (event) event.preventDefault();
        this.currentView = view;

        // Update views
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        const target = document.getElementById('view-' + view);
        if (target) target.classList.add('active');

        // Update nav
        document.querySelectorAll('.bottom-nav__item').forEach(n => {
            n.classList.remove('bottom-nav__item--active');
            if (!n.classList.contains('bottom-nav__item--add')) {
                n.querySelector('.material-symbols-outlined').style.fontVariationSettings = "'FILL' 0";
            }
        });
        const activeNav = document.querySelector(`.bottom-nav__item[data-view="${view}"]`);
        if (activeNav && !activeNav.classList.contains('bottom-nav__item--add')) {
            activeNav.classList.add('bottom-nav__item--active');
            activeNav.querySelector('.material-symbols-outlined').style.fontVariationSettings = "'FILL' 1";
        }

        // Load data for the view
        switch (view) {
            case 'dashboard': this.loadDashboard(); break;
            case 'inventory': this.loadInventory(); break;
            case 'add': this.prepareAddForm(); break;
            case 'sell': this.prepareSellForm(); break;
            case 'reports': this.loadReports(); break;
        }

        window.scrollTo(0, 0);
    }

    // ==================== DASHBOARD ====================
    async loadDashboard() {
        try {
            const res = await fetch(`${this.apiBase}/reports?type=dashboard`);
            const data = await res.json();
            if (!data.success) return;
            const d = data.dashboard;
            this.setText('dash-profit',          this.money(d.net_profit));
            this.setText('dash-inventory-value',  '$' + this.money(d.inventory_value));
            this.setText('dash-month-revenue',    '$' + this.money(d.month_revenue));
            this.setText('dash-month-sales-count', d.month_sales + ' sales');
            this.setText('dash-available-count',  d.available_items);
            this.setText('dash-days-since-sale',  d.days_since_last_sale !== null ? String(d.days_since_last_sale) : '—');
            this.setText('dash-days-since-item',  d.days_since_last_item !== null ? String(d.days_since_last_item) : '—');
            this.setText('dash-avg-days',         d.avg_days_to_sell > 0 ? String(Math.round(d.avg_days_to_sell)) : '—');
            this.setText('dash-stale',            d.stale_items);
            this.setText('dash-avail-n',          d.available_items);
            this.setText('dash-listed-n',         d.listed_items);
            this.setText('dash-sold-n',           d.sold_items);

            const actEl = document.getElementById('dash-recent-activity');
            if (data.recent_activity && data.recent_activity.length > 0) {
                actEl.innerHTML = data.recent_activity.map(a => {
                    const isSale = a.type === 'sale';
                    const icon  = isSale ? 'sell' : 'add_circle';
                    const cls   = isSale ? 'activity-item__icon--sale' : 'activity-item__icon--item';
                    const title = isSale ? `Sold: ${this.esc(a.item_name)}` : `Added: ${this.esc(a.item_name)}`;
                    const meta  = isSale
                        ? `$${this.money(a.amount)} via ${this.esc(a.detail || '')}` 
                        : `$${this.money(a.amount)} from ${this.esc(a.detail || '')}`;
                    return `<div class="activity-item">
                        <div class="activity-item__icon ${cls}"><span class="material-symbols-outlined" style="font-size:18px">${icon}</span></div>
                        <div class="activity-item__content">
                            <div class="activity-item__title">${title}</div>
                            <div class="activity-item__meta">${meta}</div>
                            <div class="activity-item__time">${this.timeAgo(a.created_at)}</div>
                        </div>
                    </div>`;
                }).join('');
            } else {
                actEl.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">history</span><p>No activity yet. Add your first item!</p></div>';
            }
        } catch(e) { console.error('Dashboard:', e); }
    }

    // ==================== INVENTORY ====================
    async loadInventory() {
        try {
            let url = `${this.apiBase}/items`;
            const params = new URLSearchParams();
            if (this.currentFilter && this.currentFilter !== 'all') params.set('status', this.currentFilter);
            const search = document.getElementById('inventory-search')?.value;
            if (search) params.set('search', search);
            const sortRaw = document.getElementById('inventory-sort')?.value || 'created_at|DESC';
            const [sortBy, sortDir] = sortRaw.split('|');
            params.set('sort', sortBy);
            params.set('dir', sortDir);
            if (params.toString()) url += '?' + params.toString();

            const res  = await fetch(url);
            const data = await res.json();
            if (!data.success) return;
            this.items = data.data;
            this.setText('inventory-count', data.count + ' items');
            this.renderInventoryList();
        } catch(e) { console.error('Inventory:', e); }
    }

    renderInventoryList() {
        const el = document.getElementById('inventory-list');
        if (!this.items.length) {
            el.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">inventory_2</span><p>No items found. Add your first purchase!</p></div>';
            return;
        }
        el.innerHTML = this.items.map(item => {
            const statusClass = 'status--' + (item.status || 'available').toLowerCase().replace(/[^a-z]/g,'');
            const price = parseFloat(item.current_retail_price) > 0 ? item.current_retail_price : item.purchase_price;
            const age   = parseInt(item.age_days) || 0;
            let ageCls  = 'age-badge--fresh', ageLabel = `${age}d`;
            if (age >= 60) ageCls = 'age-badge--stale';
            else if (age >= 30) ageCls = 'age-badge--warn';
            const ebayLink = item.ebay_listing_url
                ? `<a class="item-card__ebay-link" href="${this.esc(item.ebay_listing_url)}" target="_blank" rel="noopener"><span class="material-symbols-outlined" style="font-size:12px">open_in_new</span>eBay</a>`
                : '';
            const pkgBadge = item.packaging
                ? `<span class="item-card__packaging">${this.esc(item.packaging)}</span>`
                : '';
            return `<div class="item-card" id="item-${item.id}">
                <div class="item-card__img"><span class="material-symbols-outlined" style="font-size:28px">image</span></div>
                <div class="item-card__info">
                    <div class="item-card__name">${this.esc(item.name)}</div>
                    <div class="item-card__location"><span class="material-symbols-outlined">location_on</span>${this.esc(item.purchase_location || 'Unknown')}</div>
                    ${pkgBadge}${ebayLink}
                    <div class="item-card__actions">
                        ${item.status !== 'Sold' ? `<button class="btn-sell" onclick="event.stopPropagation();app.startSale(${item.id})"><span class="material-symbols-outlined">sell</span>Sell</button>` : ''}
                        <button class="btn-edit" onclick="event.stopPropagation();app.editItem(${item.id})"><span class="material-symbols-outlined">edit</span>Edit</button>
                        <button class="btn-delete" onclick="event.stopPropagation();app.deleteItem(${item.id})"><span class="material-symbols-outlined">delete</span></button>
                    </div>
                </div>
                <div class="item-card__right">
                    <div class="item-card__price">$${this.money(price)}</div>
                    <span class="item-card__status ${statusClass}">${item.status || 'Available'}</span><br>
                    <span class="age-badge ${ageCls}">${ageLabel} old</span>
                </div>
            </div>`;
        }).join('');
    }

    setFilter(filter) {
        this.currentFilter = filter;
        document.querySelectorAll('#inventory-filters .chip').forEach(c => {
            c.classList.toggle('chip--active', c.dataset.filter === filter);
        });
        this.loadInventory();
    }

    filterInventory() {
        clearTimeout(this._searchTimeout);
        this._searchTimeout = setTimeout(() => this.loadInventory(), 300);
    }

    // ==================== ADD/EDIT ITEM ====================
    async prepareAddForm() {
        document.getElementById('edit-item-id').value = '';
        document.getElementById('item-form').reset();
        document.getElementById('add-form-title').textContent = 'Add New Item';
        document.getElementById('item-submit-btn').innerHTML = '<span class="material-symbols-outlined">save</span> Save Item';
        document.getElementById('item-purchase-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('item-purchase-type').value = 'Standard';
        document.querySelectorAll('#purchase-mode-chips .chip').forEach(c => {
            c.classList.toggle('chip--active', c.dataset.value === 'Standard');
        });
    }

    async editItem(id) {
        try {
            const res = await fetch(`${this.apiBase}/items?id=${id}`);
            const data = await res.json();
            if (!data.success) return;

            const item = data.data;
            document.getElementById('edit-item-id').value = item.id;
            document.getElementById('item-name').value = item.name || '';
            document.getElementById('item-category').value = item.category || '';
            document.getElementById('item-purchase-date').value = item.purchase_date || '';
            document.getElementById('item-location').value = item.purchase_location || '';
            document.getElementById('item-quantity').value = item.quantity || 1;
            document.getElementById('item-condition').value = item.condition || 'Good';
            document.getElementById('item-price').value = item.purchase_price || 0;
            document.getElementById('item-retail-price').value = item.current_retail_price || '';
            document.getElementById('item-notes').value = item.purchase_notes || '';
            document.getElementById('item-purchase-type').value = item.purchase_type || 'Standard';

            document.querySelectorAll('#purchase-mode-chips .chip').forEach(c => {
                c.classList.toggle('chip--active', c.dataset.value === (item.purchase_type || 'Standard'));
            });

            document.getElementById('add-form-title').textContent = 'Edit Item';
            document.getElementById('item-submit-btn').innerHTML = '<span class="material-symbols-outlined">save</span> Update Item';
            this.navigate('add');
        } catch (e) {
            console.error('Edit load failed:', e);
        }
    }

    async handleItemForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        const editId = document.getElementById('edit-item-id').value;

        try {
            const url = editId ? `${this.apiBase}/items?id=${editId}` : `${this.apiBase}/items`;
            const method = editId ? 'PUT' : 'POST';

            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (result.success) {
                this.toast(editId ? 'Item updated!' : 'Item added!');
                form.reset();
                document.getElementById('edit-item-id').value = '';
                this.navigate('inventory');
            } else {
                this.toast(result.error || 'Failed to save item', true);
            }
        } catch (e) {
            this.toast('Network error', true);
        }
        return false;
    }

    cancelEdit() {
        document.getElementById('item-form').reset();
        document.getElementById('edit-item-id').value = '';
        this.navigate('inventory');
    }

    async deleteItem(id) {
        if (!confirm('Delete this item? This cannot be undone.')) return;
        try {
            const res = await fetch(`${this.apiBase}/items?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) {
                this.toast('Item deleted');
                this.loadInventory();
            }
        } catch (e) {
            this.toast('Failed to delete', true);
        }
    }

    // ==================== SALES ====================
    async startSale(itemId) {
        this.navigate('sell');
        await this.prepareSellForm();
        document.getElementById('sale-item').value = itemId;
    }

    async prepareSellForm() {
        document.getElementById('sale-form').reset();
        document.getElementById('sale-date').value = new Date().toISOString().split('T')[0];

        // Load available items for dropdown
        try {
            const res = await fetch(`${this.apiBase}/items?status=Available`);
            const data = await res.json();
            if (data.success) {
                const select = document.getElementById('sale-item');
                const current = select.value;
                select.innerHTML = '<option value="">Select Item to Sell</option>';
                data.data.forEach(item => {
                    select.innerHTML += `<option value="${item.id}">${this.esc(item.name)} ($${this.money(item.purchase_price)})</option>`;
                });
                if (current) select.value = current;
            }
        } catch (e) { console.error(e); }
    }

    async handleSaleForm(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        try {
            const res = await fetch(`${this.apiBase}/sales`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (result.success) {
                this.toast('Sale recorded!');
                form.reset();
                this.navigate('dashboard');
            } else {
                this.toast(result.error || 'Failed to record sale', true);
            }
        } catch (e) {
            this.toast('Network error', true);
        }
        return false;
    }

    // ==================== REPORTS ====================
    async loadReports() {
        await Promise.all([
            this.loadReportOverview(),
            this.loadSalesHistory(),
            this.loadTaxReport(),
        ]);
    }

    async loadReportOverview() {
        try {
            const [dashRes, catRes, platRes, bestRes] = await Promise.all([
                fetch(`${this.apiBase}/reports?type=dashboard`),
                fetch(`${this.apiBase}/reports?type=by_category`),
                fetch(`${this.apiBase}/reports?type=by_platform`),
                fetch(`${this.apiBase}/reports?type=best_items`),
            ]);

            const dash = await dashRes.json();
            const cats = await catRes.json();
            const plats = await platRes.json();
            const best = await bestRes.json();

            if (dash.success) {
                const d = dash.dashboard;
                this.setText('rpt-net-profit', '$' + this.money(d.net_profit));
                this.setText('rpt-revenue', '$' + this.money(d.total_revenue));
                this.setText('rpt-avg-margin', d.avg_margin + '%');
                this.setText('rpt-sold-count', d.sold_items);
            }

            if (cats.success) {
                document.getElementById('rpt-by-category').innerHTML = (cats.data || []).map(c =>
                    `<div class="report-row"><span class="report-row__label">${this.esc(c.category || 'Uncategorized')}</span><span class="report-row__value">$${this.money(c.total_profit)} <small style="color:var(--text-secondary);font-weight:400">(${c.item_count} items)</small></span></div>`
                ).join('') || '<p style="color:var(--text-secondary);font-size:0.85rem">No data yet</p>';
            }

            if (plats.success) {
                document.getElementById('rpt-by-platform').innerHTML = (plats.data || []).map(p =>
                    `<div class="report-row"><span class="report-row__label">${this.esc(p.sale_platform || 'Unknown')}</span><span class="report-row__value">$${this.money(p.total_profit)} <small style="color:var(--text-secondary);font-weight:400">(${p.sale_count} sales)</small></span></div>`
                ).join('') || '<p style="color:var(--text-secondary);font-size:0.85rem">No data yet</p>';
            }

            if (best.success) {
                document.getElementById('rpt-best-items').innerHTML = (best.data || []).slice(0, 5).map(b =>
                    `<div class="report-row"><span class="report-row__label">${this.esc(b.name)}</span><span class="report-row__value" style="color:var(--success)">+$${this.money(b.profit)} <small style="color:var(--text-secondary);font-weight:400">${b.margin}%</small></span></div>`
                ).join('') || '<p style="color:var(--text-secondary);font-size:0.85rem">No sales yet</p>';
            }
        } catch (e) { console.error('Report load error:', e); }
    }

    async loadSalesHistory() {
        try {
            const res = await fetch(`${this.apiBase}/sales`);
            const data = await res.json();
            const el = document.getElementById('rpt-sales-history');

            if (data.success && data.data.length) {
                el.innerHTML = data.data.map(s => {
                    const profitColor = s.profit >= 0 ? 'var(--success)' : 'var(--error)';
                    return `<div class="item-card">
                        <div class="item-card__img" style="background:var(--primary-fixed);color:var(--primary)"><span class="material-symbols-outlined">sell</span></div>
                        <div class="item-card__info">
                            <div class="item-card__name">${this.esc(s.item_name)}</div>
                            <div class="item-card__location">${this.esc(s.sale_platform)} · ${s.sale_date}</div>
                        </div>
                        <div class="item-card__right">
                            <div class="item-card__price">$${this.money(s.sale_price)}</div>
                            <span style="font-size:0.7rem;font-weight:700;color:${profitColor}">${s.profit >= 0 ? '+' : ''}$${this.money(s.profit)}</span>
                        </div>
                    </div>`;
                }).join('');
            } else {
                el.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">sell</span><p>No sales recorded yet</p></div>';
            }
        } catch (e) { console.error(e); }
    }

    async loadTaxReport() {
        const year = document.getElementById('tax-year')?.value || new Date().getFullYear();
        try {
            const res = await fetch(`${this.apiBase}/reports?type=tax&year=${year}`);
            const data = await res.json();
            const el = document.getElementById('rpt-tax-data');

            if (data.success) {
                const sc = data.schedule_c;
                let html = `
                    <div class="tax-row"><span>Gross Revenue</span><span>$${this.money(sc.gross_revenue)}</span></div>
                    <div class="tax-row"><span>Cost of Goods Sold</span><span>($${this.money(sc.cost_of_goods_sold)})</span></div>
                    <div class="tax-row"><span>Shipping Expenses</span><span>($${this.money(sc.shipping_expenses)})</span></div>
                    <div class="tax-row tax-row--total"><span>Net Business Income</span><span>$${this.money(sc.net_business_income)}</span></div>
                    <div class="tax-row"><span>Total Transactions</span><span>${sc.total_transactions}</span></div>
                `;

                if (data.quarterly) {
                    html += '<h4 style="margin-top:20px;font-family:var(--font-headline);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.1em;color:var(--outline)">Quarterly Breakdown</h4>';
                    for (const [q, qd] of Object.entries(data.quarterly)) {
                        html += `<div class="tax-row"><span>${q}</span><span>$${this.money(qd.revenue)} revenue / $${this.money(qd.profit)} profit</span></div>`;
                    }
                }
                el.innerHTML = html;
            }
        } catch (e) { console.error(e); }
    }

    showReportTab(tab) {
        document.querySelectorAll('.report-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('report-' + tab)?.classList.add('active');
        document.querySelectorAll('#report-tabs .chip').forEach(c => {
            c.classList.toggle('chip--active', c.dataset.tab === tab);
        });
    }

    // ==================== CATEGORIES & LOCATIONS ====================
    async loadCategories() {
        try {
            const res = await fetch(`${this.apiBase}/categories`);
            const data = await res.json();
            if (data.success) {
                this.categories = data.data;
                const select = document.getElementById('item-category');
                if (select) {
                    select.innerHTML = '<option value="">Select Category</option>';
                    data.data.forEach(c => {
                        select.innerHTML += `<option value="${this.esc(c.name)}">${this.esc(c.name)}</option>`;
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    async loadLocations() {
        try {
            const res = await fetch(`${this.apiBase}/locations`);
            const data = await res.json();
            if (data.success) {
                this.locations = data.data;
                const datalist = document.getElementById('location-suggestions');
                if (datalist) {
                    datalist.innerHTML = data.data.map(l => `<option value="${this.esc(l.name)}">`).join('');
                }
            }
        } catch (e) { console.error(e); }
    }

    // ==================== CSV IMPORT ====================
    async importCSV() {
        const file = document.getElementById('csv-import-file').files[0];
        const type = document.getElementById('csv-import-type').value;
        if (!file) { this.toast('Please select a file', true); return; }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);

        try {
            const res = await fetch(`${this.apiBase}/import?type=${type}`, { method: 'POST', body: formData });
            const data = await res.json();
            const el = document.getElementById('import-result');
            el.style.display = 'block';

            if (data.success) {
                el.className = 'import-result import-result--success';
                el.textContent = data.message;
                document.getElementById('csv-import-file').value = '';
            } else {
                el.className = 'import-result import-result--error';
                el.textContent = data.error || 'Import failed';
            }
        } catch (e) {
            this.toast('Import failed', true);
        }
    }

    async exportData() {
        try {
            const res = await fetch(`${this.apiBase}/items`);
            const data = await res.json();
            if (!data.success || !data.data.length) { this.toast('No data to export'); return; }

            const headers = ['name', 'purchase_date', 'purchase_price', 'purchase_location', 'category', 'current_retail_price', 'quantity', 'condition', 'status', 'purchase_notes'];
            const csv = [headers.join(',')];
            data.data.forEach(item => {
                csv.push(headers.map(h => `"${(item[h] || '').toString().replace(/"/g, '""')}"`).join(','));
            });

            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `bought-it-export-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            this.toast('Export downloaded!');
        } catch (e) {
            this.toast('Export failed', true);
        }
    }

    // ==================== UI HELPERS ====================
    selectChip(el, targetId) {
        el.parentElement.querySelectorAll('.chip').forEach(c => c.classList.remove('chip--active'));
        el.classList.add('chip--active');
        document.getElementById('item-' + targetId).value = el.dataset.value;
    }

    toggleDarkMode() {
        const isDark = document.documentElement.classList.contains('dark');
        document.documentElement.classList.replace(isDark ? 'dark' : 'light', isDark ? 'light' : 'dark');
        localStorage.setItem('darkMode', !isDark);
        const cb = document.getElementById('setting-dark-mode');
        if (cb) cb.checked = !isDark;
        const btn = document.getElementById('darkModeToggle');
        if (btn) btn.querySelector('.material-symbols-outlined').textContent = isDark ? 'dark_mode' : 'light_mode';
    }

    toast(msg, isError = false) {
        const el = document.getElementById('toast');
        el.textContent = msg;
        el.className = 'toast show' + (isError ? ' toast--error' : '');
        clearTimeout(this._toastTimeout);
        this._toastTimeout = setTimeout(() => { el.className = 'toast'; }, 3000);
    }

    setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
    money(n) { return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    timeAgo(dateStr) {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return new Date(dateStr).toLocaleDateString();
    }

    // ==================== PACKAGING ====================
    async loadPackaging() {
        try {
            const res = await fetch(`${this.apiBase}/packaging`);
            const data = await res.json();
            if (data.success) {
                this.packaging = data.data;
                const dl = document.getElementById('packaging-suggestions');
                if (dl) dl.innerHTML = data.data.map(p => `<option value="${this.esc(p)}">`).join('');
            }
        } catch(e) { console.error('Packaging:', e); }
    }

    // ==================== REPORT DATE FILTER ====================
    getReportParams() {
        const year  = document.getElementById('rpt-year')?.value  || new Date().getFullYear();
        const month = document.getElementById('rpt-month')?.value || '';
        return month ? `year=${year}&month=${month}` : `year=${year}`;
    }

    onReportDateChange() {
        if (this.currentView === 'reports') this.loadReports();
    }

    // ==================== REPORTS (updated) ====================
    async loadReports() {
        await Promise.all([
            this.loadReportOverview(),
            this.loadSalesHistory(),
            this.loadTaxReport(),
        ]);
    }

    async loadReportOverview() {
        try {
            const dp = this.getReportParams();
            const [dashRes, catRes, platRes, bestRes, srcRes] = await Promise.all([
                fetch(`${this.apiBase}/reports?type=dashboard`),
                fetch(`${this.apiBase}/reports?type=by_category&${dp}`),
                fetch(`${this.apiBase}/reports?type=by_platform&${dp}`),
                fetch(`${this.apiBase}/reports?type=best_items&${dp}`),
                fetch(`${this.apiBase}/reports?type=by_source&${dp}`),
            ]);
            const [dash, cats, plats, best, src] = await Promise.all([
                dashRes.json(), catRes.json(), platRes.json(), bestRes.json(), srcRes.json()
            ]);

            if (dash.success) {
                const d = dash.dashboard;
                this.setText('rpt-net-profit', '$' + this.money(d.net_profit));
                this.setText('rpt-revenue',    '$' + this.money(d.total_revenue));
                this.setText('rpt-avg-margin', d.avg_margin + '%');
                this.setText('rpt-sold-count', d.sold_items);
            }

            const noData = '<p style="color:var(--text-secondary);font-size:0.85rem">No data yet</p>';
            const reportRow = (label, value, sub) =>
                `<div class="report-row"><span class="report-row__label">${label}</span><span class="report-row__value">${value} <small style="color:var(--text-secondary);font-weight:400">${sub}</small></span></div>`;

            document.getElementById('rpt-by-category').innerHTML = cats.success && cats.data.length
                ? cats.data.map(c => reportRow(this.esc(c.category||'Uncategorized'), '$'+this.money(c.total_profit), `${c.item_count} items`)).join('') : noData;

            document.getElementById('rpt-by-platform').innerHTML = plats.success && plats.data.length
                ? plats.data.map(p => reportRow(this.esc(p.sale_platform||'Unknown'), '$'+this.money(p.total_profit), `${p.sale_count} sales`)).join('') : noData;

            document.getElementById('rpt-by-source').innerHTML = src.success && src.data.length
                ? src.data.map(s => reportRow(this.esc(s.source||'Unknown'), '$'+this.money(s.total_profit), `${s.item_count} items`)).join('') : noData;

            document.getElementById('rpt-best-items').innerHTML = best.success && best.data.length
                ? best.data.slice(0,5).map(b => reportRow(this.esc(b.name), `<span style="color:var(--success)">+$${this.money(b.profit)}</span>`, `${b.margin}%`)).join('') : noData;

        } catch(e) { console.error('Report overview:', e); }
    }

    async loadTaxReport() {
        const year = document.getElementById('rpt-year')?.value || new Date().getFullYear();
        try {
            const res  = await fetch(`${this.apiBase}/reports?type=tax&year=${year}`);
            const data = await res.json();
            const el   = document.getElementById('rpt-tax-data');
            if (data.success) {
                const sc = data.schedule_c;
                let html = `
                    <div class="tax-row"><span>Gross Revenue</span><span>$${this.money(sc.gross_revenue)}</span></div>
                    <div class="tax-row"><span>Cost of Goods Sold</span><span>($${this.money(sc.cost_of_goods_sold)})</span></div>
                    <div class="tax-row"><span>Shipping Expenses</span><span>($${this.money(sc.shipping_expenses)})</span></div>
                    <div class="tax-row tax-row--total"><span>Net Business Income</span><span>$${this.money(sc.net_business_income)}</span></div>
                    <div class="tax-row"><span>Total Transactions</span><span>${sc.total_transactions}</span></div>`;
                if (data.quarterly) {
                    html += '<h4 style="margin-top:20px;font-family:var(--font-headline);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.1em;color:var(--outline)">Quarterly</h4>';
                    for (const [q, qd] of Object.entries(data.quarterly)) {
                        html += `<div class="tax-row"><span>${q}</span><span>$${this.money(qd.revenue)} rev / $${this.money(qd.profit)} profit</span></div>`;
                    }
                }
                el.innerHTML = html;
            }
        } catch(e) { console.error('Tax report:', e); }
    }

    exportTaxCSV() {
        const year = document.getElementById('rpt-year')?.value || new Date().getFullYear();
        window.open(`${this.apiBase}/reports?type=tax&year=${year}&export=csv`, '_blank');
    }

    // ==================== PHOTOS ====================
    setPhotoType(type, btn) {
        document.getElementById('photo-upload-type').value = type;
        btn.parentElement.querySelectorAll('.chip').forEach(c => c.classList.remove('chip--active'));
        btn.classList.add('chip--active');
    }

    async uploadPhotos(event) {
        const itemId = this.currentItemId;
        if (!itemId) { this.toast('Save item first before adding photos', true); return; }

        const files = event.target.files;
        if (!files.length) return;

        const photoType = document.getElementById('photo-upload-type').value;
        const formData  = new FormData();
        formData.append('item_id', itemId);
        formData.append('photo_type', photoType);
        for (const f of files) formData.append('photos[]', f);

        try {
            const res  = await fetch(`${this.apiBase}/photos`, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                this.toast(`${data.count} photo(s) uploaded`);
                this.loadPhotoGrid(itemId);
            } else {
                this.toast(data.errors?.[0] || 'Upload failed', true);
            }
        } catch(e) { this.toast('Upload error', true); }
        event.target.value = ''; // reset file input
    }

    async loadPhotoGrid(itemId) {
        try {
            const res  = await fetch(`${this.apiBase}/photos?item_id=${itemId}`);
            const data = await res.json();
            const grid = document.getElementById('photo-preview-grid');
            if (!grid) return;
            if (data.success && data.data.length) {
                grid.innerHTML = data.data.map(p => `
                    <div class="photo-thumb">
                        <img src="${this.esc(p.file_path)}" alt="Photo">
                        ${p.photo_type === 'receipt' ? '<span class="photo-thumb__receipt-badge">Receipt</span>' : ''}
                        <button class="photo-thumb__del" onclick="app.deletePhoto(${p.id})">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>`).join('');
            } else {
                grid.innerHTML = '';
            }
        } catch(e) { console.error('Photo grid:', e); }
    }

    async deletePhoto(photoId) {
        if (!confirm('Delete this photo?')) return;
        await fetch(`${this.apiBase}/photos?id=${photoId}`, { method: 'DELETE' });
        this.loadPhotoGrid(this.currentItemId);
    }

    showPhotoSection(itemId) {
        this.currentItemId = itemId;
        const sec = document.getElementById('photo-upload-section');
        if (sec) sec.style.display = 'block';
        this.loadPhotoGrid(itemId);
    }

    // Override handleItemForm to show photo section after save
    async handleItemFormWithPhotos(event) {
        event.preventDefault();
        const form    = event.target;
        const fd      = new FormData(form);
        const data    = Object.fromEntries(fd);
        const editId  = document.getElementById('edit-item-id').value;

        try {
            const url    = editId ? `${this.apiBase}/items?id=${editId}` : `${this.apiBase}/items`;
            const method = editId ? 'PUT' : 'POST';
            const res    = await fetch(url, { method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(data) });
            const result = await res.json();
            if (result.success) {
                const savedId = editId || result.id;
                this.toast(editId ? 'Item updated!' : 'Item added!');
                this.showPhotoSection(savedId);
                document.getElementById('edit-item-id').value = savedId;
            } else {
                this.toast(result.error || 'Failed to save', true);
            }
        } catch(e) { this.toast('Network error', true); }
        return false;
    }

    // ==================== BARCODE SCAN ====================
    async startScan() {
        const overlay = document.getElementById('scanner-overlay');
        overlay.style.display = 'flex';
        const video = document.getElementById('scanner-video');
        try {
            this.scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = this.scanStream;
            this.setText('scanner-status', 'Point camera at barcode');

            // Load ZXing dynamically
            if (!window.ZXing) {
                await new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = 'https://unpkg.com/@zxing/library@latest/umd/index.min.js';
                    s.onload = resolve; s.onerror = reject;
                    document.head.appendChild(s);
                });
            }
            const codeReader = new ZXing.BrowserMultiFormatReader();
            this._codeReader = codeReader;
            codeReader.decodeFromVideoDevice(null, 'scanner-video', (result, err) => {
                if (result) {
                    this.onBarcodeScanned(result.getText());
                }
            });
        } catch(e) {
            this.toast('Camera not available', true);
            overlay.style.display = 'none';
        }
    }

    stopScan() {
        if (this._codeReader) { this._codeReader.reset(); this._codeReader = null; }
        if (this.scanStream)  { this.scanStream.getTracks().forEach(t => t.stop()); this.scanStream = null; }
        document.getElementById('scanner-overlay').style.display = 'none';
    }

    async onBarcodeScanned(upc) {
        this.stopScan();
        this.setText('scanner-status', `Found: ${upc}`);
        this.setText('item-name', 'Looking up...');
        try {
            // Free UPC lookup
            const res  = await fetch(`https://api.upcitemdb.com/prod/trial/lookup?upc=${upc}`);
            const data = await res.json();
            const item = data.items?.[0];
            if (item) {
                document.getElementById('item-name').value     = item.title || upc;
                if (item.category) document.getElementById('item-category').value = item.category;
                if (item.lowest_recorded_price) document.getElementById('item-retail-price').value = item.lowest_recorded_price;
                this.toast(`Found: ${item.title}`);
            } else {
                document.getElementById('item-name').value = upc;
                this.toast('UPC not found — enter name manually');
            }
        } catch(e) {
            document.getElementById('item-name').value = upc;
            this.toast('Lookup failed — UPC filled in');
        }
    }

    // ==================== SHARED BULK IMPORT ENGINE ====================

    /**
     * Run a list of files through an API endpoint sequentially, showing per-file progress.
     * @param {FileList} files       - Selected files
     * @param {string}   endpoint    - e.g. '/api/ebay_import'
     * @param {string}   progressId  - DOM id of .bulk-progress container
     * @param {string}   resultId    - DOM id of .import-result container
     * @param {string}   btnId       - DOM id of submit button (to disable during import)
     * @param {string}   inputId     - DOM id of file input (to clear on success)
     */
    async runBulkImport(files, endpoint, progressId, resultId, btnId, inputId) {
        if (!files || files.length === 0) { this.toast('No files selected', true); return; }

        const total     = files.length;
        const progEl    = document.getElementById(progressId);
        const resultEl  = document.getElementById(resultId);
        const btn       = document.getElementById(btnId);

        // Disable button
        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
        resultEl.style.display = 'none';

        // Build progress UI
        progEl.style.display = 'block';
        const fileArray = Array.from(files);
        progEl.innerHTML = `
            <div class="bulk-progress__header">
                <span id="${progressId}-label">Processing 1 of ${total}…</span>
                <span id="${progressId}-counts" style="font-size:0.7rem"></span>
            </div>
            <div class="bulk-progress__bar-wrap">
                <div class="bulk-progress__bar" id="${progressId}-bar"></div>
            </div>
            <div class="bulk-file-list">
                ${fileArray.map((f, i) => `
                    <div class="bulk-file-row" id="${progressId}-row-${i}">
                        <span class="material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--wait" id="${progressId}-icon-${i}">radio_button_unchecked</span>
                        <span class="bulk-file-row__name" title="${this.esc(f.name)}">${this.esc(f.name)}</span>
                        <span class="bulk-file-row__result" id="${progressId}-res-${i}">Waiting…</span>
                    </div>`).join('')}
            </div>`;

        let totalImported = 0, totalDupes = 0, totalErrors = 0;

        for (let i = 0; i < fileArray.length; i++) {
            const file       = fileArray[i];
            const iconEl     = document.getElementById(`${progressId}-icon-${i}`);
            const resEl      = document.getElementById(`${progressId}-res-${i}`);
            const labelEl    = document.getElementById(`${progressId}-label`);
            const barEl      = document.getElementById(`${progressId}-bar`);

            // Update progress bar and label
            barEl.style.width = `${Math.round((i / total) * 100)}%`;
            labelEl.textContent = `Processing ${i + 1} of ${total}…`;

            // Spinner state
            iconEl.className = 'material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--spin';
            iconEl.textContent = 'progress_activity';
            resEl.textContent = 'Uploading…';

            // Scroll this row into view
            document.getElementById(`${progressId}-row-${i}`)?.scrollIntoView({ block: 'nearest' });

            try {
                const fd = new FormData();
                fd.append('file', file);
                const res  = await fetch(endpoint, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    const imp  = data.imported || 0;
                    const dupe = data.dupes    || 0;
                    totalImported += imp;
                    totalDupes    += dupe;
                    iconEl.className = 'material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--ok';
                    iconEl.textContent = 'check_circle';
                    resEl.textContent  = imp > 0 ? `+${imp} sale${imp !== 1 ? 's' : ''}${dupe ? ` (${dupe} dupes)` : ''}` : `Skipped (${dupe} dupes)`;
                    resEl.style.color  = imp > 0 ? 'var(--success)' : 'var(--outline)';
                    if (imp === 0 && dupe === 0) {
                        iconEl.className = 'material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--skip';
                        iconEl.textContent = 'remove_circle';
                        resEl.textContent = 'No data found';
                    }
                } else {
                    const rowEl = document.getElementById(`${progressId}-row-${i}`);
                    if (rowEl) rowEl.classList.add('bulk-file-row--error');
                    
                    totalErrors++;
                    iconEl.className = 'material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--error';
                    iconEl.textContent = 'error';
                    resEl.textContent = file.name + ': ' + (data.error || 'Failed');
                    resEl.style.color = 'var(--error)';
                    resEl.style.fontWeight = '600';
                }
            } catch(e) {
                const rowEl = document.getElementById(`${progressId}-row-${i}`);
                if (rowEl) rowEl.classList.add('bulk-file-row--error');
                
                totalErrors++;
                iconEl.className = 'material-symbols-outlined bulk-file-row__icon bulk-file-row__icon--error';
                iconEl.textContent = 'error';
                resEl.textContent = file.name + ': Network error';
                resEl.style.color = 'var(--error)';
                resEl.style.fontWeight = '600';
            }
        }

        // Final state
        barEl.style.width = '100%';
        labelEl.textContent = 'Complete';
        document.getElementById(`${progressId}-counts`).textContent =
            `${totalImported} imported · ${totalDupes} dupes · ${totalErrors} errors`;

        // Summary card appended to progress block
        progEl.insertAdjacentHTML('beforeend', `
            <div class="bulk-summary">
                <div class="bulk-summary__stat">
                    <span class="bulk-summary__num" style="color:var(--success)">${totalImported}</span>
                    <span class="bulk-summary__label">Sales Imported</span>
                </div>
                <div class="bulk-summary__stat">
                    <span class="bulk-summary__num" style="color:var(--outline)">${totalDupes}</span>
                    <span class="bulk-summary__label">Duplicates Skipped</span>
                </div>
                <div class="bulk-summary__stat">
                    <span class="bulk-summary__num">${total}</span>
                    <span class="bulk-summary__label">Files Processed</span>
                </div>
                ${totalErrors > 0 ? `<div class="bulk-summary__stat">
                    <span class="bulk-summary__num" style="color:var(--error)">${totalErrors}</span>
                    <span class="bulk-summary__label">Errors</span>
                </div>` : ''}
            </div>`);

        // Re-enable button, clear input
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        if (totalImported > 0) {
            document.getElementById(inputId).value = '';
            this.loadDashboard(); // refresh dashboard numbers
        }
    }

    // ==================== MASTER INVENTORY IMPORT (XLSX) ====================
    async importMasterInventory() {
        const fileInput = document.getElementById('inventory-import-file');
        const file      = fileInput.files[0];
        if (!file) return this.toast('Please select an XLSX file', true);

        const btn   = document.getElementById('inventory-import-btn');
        const resEl = document.getElementById('inventory-import-result');
        btn.disabled = true;
        btn.style.opacity = '0.6';
        resEl.style.display = 'block';
        resEl.className = 'import-result';
        resEl.innerHTML = '<span class="material-symbols-outlined spin" style="font-size:16px">sync</span> Parsing master inventory... (this may take a minute)';

        const fd = new FormData();
        fd.append('file', file);

        try {
            const res = await fetch(`${this.apiBase}/import_inventory`, { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                resEl.innerHTML = `<span style="color:var(--success)">✔ ${data.message}</span>`;
                fileInput.value = '';
                this.loadDashboard();
            } else {
                resEl.innerHTML = `<span style="color:var(--error)">✘ ${data.error || 'Import failed'}</span>`;
            }
        } catch(e) {
            resEl.innerHTML = `<span style="color:var(--error)">✘ Network error during import</span>`;
        }
        btn.disabled = false;
        btn.style.opacity = '1';
    }

    // ==================== EBAY SELLER HUB CSV (BULK) ====================
    importEbayCSVBulk() {
        const files = document.getElementById('ebay-import-file')?.files;
        this.runBulkImport(
            files,
            `${this.apiBase}/ebay_import`,
            'ebay-import-progress',
            'ebay-import-result',
            'ebay-import-btn',
            'ebay-import-file'
        );
    }

    // ==================== EBAY STATEMENT XML (BULK) ====================
    importStatementBulk() {
        const files = document.getElementById('statement-import-file')?.files;
        this.runBulkImport(
            files,
            `${this.apiBase}/statement_import`,
            'statement-import-progress',
            'statement-import-result',
            'statement-import-btn',
            'statement-import-file'
        );
    }

    // Keep legacy single-file methods as aliases
    importEbayCSV()    { this.importEbayCSVBulk(); }
    importStatement()  { this.importStatementBulk(); }

    // ==================== CSV TEMPLATE ====================
    downloadTemplate() {
        const headers = ['name','purchase_date','purchase_price','purchase_location','category','current_retail_price','quantity','condition','packaging','ebay_listing_url','purchase_notes'];
        const example = ['Nike Air Zoom Pegasus','2026-01-15','12.50','Goodwill','Clothing & Apparel','89.99','1','Good','Green Bubble 6x9','',''];
        const csv = [headers.join(','), example.map(v => `"${v}"`).join(',')].join('\n');
        const a = document.createElement('a');
        a.href = 'data:text/csv,' + encodeURIComponent(csv);
        a.download = 'bought-it-import-template.csv';
        a.click();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.app = new BoughtItApp();
    // Patch handleItemForm to show photo section
    app.handleItemForm = app.handleItemFormWithPhotos.bind(app);
});