/**
 * Main JavaScript File for Farm Management System
 */

// Global variables
let currentFarmType = 'poultry';
let currentUserId = null;

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initSidebar();
    initTooltips();
    initDatePickers();
    initAutoCalculations();
    initFormValidations();
    
    // Get current user info
    currentUserId = document.body.getAttribute('data-user-id') || null;
    currentFarmType = document.body.getAttribute('data-farm-type') || 'poultry';
    
    // Check for messages
    checkForMessages();
    
    // Set up event listeners
    setupEventListeners();
});

/**
 * Initialize sidebar functionality
 */
function initSidebar() {
    const sidebarItems = document.querySelectorAll('.sidebar .list-group-item');
    sidebarItems.forEach(item => {
        item.addEventListener('click', function() {
            sidebarItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize date pickers
 */
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.valueAsDate = new Date();
        }
    });
}

/**
 * Initialize auto-calculation fields
 */
function initAutoCalculations() {
    // Auto-calculate total amount for sales
    const quantityInputs = document.querySelectorAll('input[name="quantity"]');
    const priceInputs = document.querySelectorAll('input[name="unit_price"]');
    
    quantityInputs.forEach(input => {
        input.addEventListener('input', calculateTotalAmount);
    });
    
    priceInputs.forEach(input => {
        input.addEventListener('input', calculateTotalAmount);
    });
    
    // Auto-calculate laying rate
    const layerStockInputs = document.querySelectorAll('input[name="opening_stock"], input[name="egg_production"]');
    layerStockInputs.forEach(input => {
        input.addEventListener('input', calculateLayingRate);
    });
}

/**
 * Calculate total amount for sales
 */
function calculateTotalAmount() {
    const form = this.closest('form');
    if (!form) return;
    
    const quantity = parseFloat(form.querySelector('input[name="quantity"]')?.value) || 0;
    const unitPrice = parseFloat(form.querySelector('input[name="unit_price"]')?.value) || 0;
    const totalAmountInput = form.querySelector('input[name="total_amount"], #totalAmount');
    
    if (totalAmountInput) {
        const totalAmount = quantity * unitPrice;
        totalAmountInput.value = '₦' + totalAmount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

/**
 * Calculate laying rate percentage
 */
function calculateLayingRate() {
    const form = this.closest('form');
    if (!form) return;
    
    const openingStock = parseInt(form.querySelector('input[name="opening_stock"]')?.value) || 0;
    const eggProduction = parseInt(form.querySelector('input[name="egg_production"]')?.value) || 0;
    const layingRateInput = form.querySelector('input[name="laying_rate"]');
    
    if (layingRateInput && openingStock > 0) {
        const layingRate = (eggProduction / openingStock) * 100;
        layingRateInput.value = layingRate.toFixed(1);
    }
}

/**
 * Initialize form validations
 */
function initFormValidations() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Validate form
 */
function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            showFieldError(field, 'This field is required');
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    });
    
    // Validate numeric fields
    const numericFields = form.querySelectorAll('input[type="number"]');
    numericFields.forEach(field => {
        const value = parseFloat(field.value);
        const min = parseFloat(field.min) || 0;
        
        if (value < min) {
            isValid = false;
            field.classList.add('is-invalid');
            showFieldError(field, `Value must be at least ${min}`);
        }
    });
    
    return isValid;
}

/**
 * Show field error message
 */
function showFieldError(field, message) {
    let errorDiv = field.nextElementSibling;
    
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        field.parentNode.appendChild(errorDiv);
    }
    
    errorDiv.textContent = message;
}

/**
 * Check for and display messages
 */
function checkForMessages() {
    // Check for success messages in session
    const successMessage = document.body.getAttribute('data-success-message');
    if (successMessage) {
        showAlert('success', successMessage);
        document.body.removeAttribute('data-success-message');
    }
    
    // Check for error messages
    const errorMessage = document.body.getAttribute('data-error-message');
    if (errorMessage) {
        showAlert('danger', errorMessage);
        document.body.removeAttribute('data-error-message');
    }
}

/**
 * Show alert message
 */
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto remove after duration
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, duration);
}

/**
 * Create alert container if it doesn't exist
 */
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
    document.body.appendChild(container);
    return container;
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // Stock update form
    const stockForms = document.querySelectorAll('form[data-stock-update="true"]');
    stockForms.forEach(form => {
        form.addEventListener('submit', handleStockUpdate);
    });
    
    // Record forms
    const recordForms = document.querySelectorAll('form[data-record-form="true"]');
    recordForms.forEach(form => {
        form.addEventListener('submit', handleRecordSubmit);
    });
    
    // Expense forms
    const expenseForms = document.querySelectorAll('form[data-expense-form="true"]');
    expenseForms.forEach(form => {
        form.addEventListener('submit', handleExpenseSubmit);
    });
    
    // Sales forms
    const salesForms = document.querySelectorAll('form[data-sales-form="true"]');
    salesForms.forEach(form => {
        form.addEventListener('submit', handleSalesSubmit);
    });
}

/**
 * Handle stock update form submission
 */
async function handleStockUpdate(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) return;
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('api/update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Stock updated successfully!');
            form.reset();
            
            // Refresh stock display if exists
            const stockDisplay = document.querySelector('.current-stock-display');
            if (stockDisplay && result.new_stock !== undefined) {
                stockDisplay.textContent = result.new_stock;
            }
            
            // Reload page after 1 second to show updated data
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('danger', 'Error: ' + result.message);
        }
    } catch (error) {
        showAlert('danger', 'Network error: ' + error.message);
    }
}

/**
 * Handle record form submission
 */
async function handleRecordSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) return;
    
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Show loading state
    submitButton.innerHTML = '<span class="loading-spinner"></span> Saving...';
    submitButton.disabled = true;
    
    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            showAlert('success', 'Record saved successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error('Failed to save record');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    }
}

/**
 * Handle expense form submission
 */
async function handleExpenseSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) return;
    
    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            showAlert('success', 'Expense recorded successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error('Failed to record expense');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
}

/**
 * Handle sales form submission
 */
async function handleSalesSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) return;
    
    try {
        const formData = new FormData(form);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            showAlert('success', 'Sale recorded successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error('Failed to record sale');
        }
    } catch (error) {
        showAlert('danger', 'Error: ' + error.message);
    }
}

/**
 * Confirm action before proceeding
 */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

/**
 * Delete record
 */
async function deleteRecord(recordId, recordType) {
    if (!confirmAction('Delete this record?')) return;
    
    try {
        const response = await fetch(`api/delete_record.php?type=${recordType}&id=${recordId}`);
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Record deleted successfully!');
            location.reload();
        } else {
            showAlert('danger', 'Error: ' + result.message);
        }
    } catch (error) {
        showAlert('danger', 'Network error: ' + error.message);
    }
}

/**
 * Delete sale
 */
async function deleteSale(saleId) {
    if (!confirmAction('Delete this sale record?')) return;
    
    try {
        const response = await fetch(`api/delete_sale.php?id=${saleId}`);
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Sale record deleted successfully!');
            location.reload();
        } else {
            showAlert('danger', 'Error: ' + result.message);
        }
    } catch (error) {
        showAlert('danger', 'Network error: ' + error.message);
    }
}

/**
 * Delete expense
 */
async function deleteExpense(expenseId) {
    if (!confirmAction('Delete this expense record?')) return;
    
    try {
        const response = await fetch(`api/delete_expense.php?id=${expenseId}`);
        const result = await response.json();
        
        if (result.success) {
            showAlert('success', 'Expense record deleted successfully!');
            location.reload();
        } else {
            showAlert('danger', 'Error: ' + result.message);
        }
    } catch (error) {
        showAlert('danger', 'Network error: ' + error.message);
    }
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '₦' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Format date
 */
function formatDate(dateString, format = 'dd/mm/yyyy') {
    const date = new Date(dateString);
    if (format === 'dd/mm/yyyy') {
        return date.toLocaleDateString('en-GB');
    }
    return date.toLocaleDateString();
}

/**
 * Export data to CSV
 */
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Convert data to CSV
 */
function convertToCSV(data) {
    if (!Array.isArray(data) || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const rows = data.map(row => 
        headers.map(header => 
            JSON.stringify(row[header] || '')
        ).join(',')
    );
    
    return [headers.join(','), ...rows].join('\n');
}

/**
 * Print report
 */
function printReport(elementId = 'report-content') {
    const printContent = document.getElementById(elementId) || document.body;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent.innerHTML;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

/**
 * Get stock history for an item
 */
async function getStockHistory(itemId, days = 30) {
    try {
        const response = await fetch(`api/get_stock_history.php?item_id=${itemId}&days=${days}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching stock history:', error);
        return [];
    }
}

/**
 * Update stock display in real-time
 */
function updateStockDisplay(itemId, newStock) {
    const stockElements = document.querySelectorAll(`[data-item-id="${itemId}"] .stock-value`);
    stockElements.forEach(element => {
        element.textContent = newStock;
        
        // Update status if exists
        const minStock = element.closest('.stock-item')?.dataset.minStock;
        if (minStock) {
            if (newStock <= minStock) {
                element.classList.add('stock-low');
                element.classList.remove('stock-adequate', 'stock-moderate');
            } else if (newStock <= minStock * 2) {
                element.classList.add('stock-moderate');
                element.classList.remove('stock-low', 'stock-adequate');
            } else {
                element.classList.add('stock-adequate');
                element.classList.remove('stock-low', 'stock-moderate');
            }
        }
    });
}

/**
 * Load data for charts
 */
async function loadChartData(chartType, period = 'month') {
    try {
        const response = await fetch(`api/get_chart_data.php?type=${chartType}&period=${period}`);
        return await response.json();
    } catch (error) {
        console.error('Error loading chart data:', error);
        return null;
    }
}