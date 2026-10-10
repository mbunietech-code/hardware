import Alpine from 'alpinejs';

window.Alpine = Alpine;

const num = (v) => parseFloat(v) || 0;

/**
 * Point-of-sale cart used by the sale and purchase screens.
 * products: [{id, code, name, unit, category_id, stock, <priceKey>}]
 */
window.pos = (products, priceKey, opts = {}) => ({
    products,
    search: '',
    category: '',
    cart: [],
    discount: 0,
    paid: '',
    method: 'cash',
    discounts: opts.discounts ?? false,
    checkStock: opts.checkStock ?? false,
    get filtered() {
        const q = this.search.trim().toLowerCase();
        return this.products.filter((p) =>
            (!this.category || String(p.category_id) === String(this.category)) &&
            (!q || p.name.toLowerCase().includes(q) || (p.code || '').toLowerCase().includes(q)));
    },
    inCart(p) { return this.cart.find((l) => l.id === p.id); },
    add(p) {
        const line = this.inCart(p);
        if (line) line.quantity = num(line.quantity) + 1;
        else this.cart.push({ id: p.id, name: p.name, unit: p.unit, stock: p.stock, quantity: 1, price: num(p[priceKey]), discount: 0 });
    },
    addFirst() { if (this.filtered.length) { this.add(this.filtered[0]); this.search = ''; } },
    inc(l) { l.quantity = num(l.quantity) + 1; },
    dec(l) { l.quantity = num(l.quantity) - 1; if (l.quantity <= 0) this.remove(l); },
    remove(l) { this.cart = this.cart.filter((x) => x !== l); },
    clear() { this.cart = []; this.discount = 0; this.paid = ''; },
    line(l) { return Math.max(0, num(l.quantity) * num(l.price) - num(l.discount)); },
    over(l) { return this.checkStock && num(l.quantity) > num(l.stock); },
    get count() { return this.cart.reduce((s, l) => s + num(l.quantity), 0); },
    get subtotal() { return this.cart.reduce((s, l) => s + this.line(l), 0); },
    get total() { return Math.max(0, this.subtotal - num(this.discount)); },
    get paidAmount() { return this.paid === '' ? (this.method === 'credit' ? 0 : this.total) : num(this.paid); },
    get balance() { return Math.max(0, this.total - this.paidAmount); },
    get change() { return Math.max(0, num(this.paid) - this.total); },
    fmt(v) { return Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }); },
    initials(name) { return name.split(' ').filter((w) => /^[a-z]/i.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase(); },
});

Alpine.start();
