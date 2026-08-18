const MOTION_TYPES = new Set(['tab', 'push', 'pop', 'fade', 'morph']);

function kfNormalizePath(pathname) {
    const path = String(pathname || '/').replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

export function kfShellRoot(pathname) {
    const path = kfNormalizePath(pathname);
    if (path === '/borrower' || path.startsWith('/borrower/')) {
        return 'borrower';
    }
    if (path === '/affiliate-portal' || path.startsWith('/affiliate-portal/')) {
        return 'affiliate';
    }
    if (path === '/partner/affiliate' || path.startsWith('/partner/affiliate/')) {
        return 'affiliate';
    }
    if (path === '/partner/supplier' || path.startsWith('/partner/supplier/')) {
        return 'supplier';
    }
    if (path === '/supplier' || path.startsWith('/supplier/')) {
        return 'supplier';
    }
    if (path === '/investor' || path.startsWith('/investor/')) {
        return 'investor';
    }
    if (path === '/partner' || path.startsWith('/partner/')) {
        return 'partner';
    }
    if (path === '/vendor' || path.startsWith('/vendor/')) {
        return 'partner';
    }

    return null;
}

function kfIsTabPath(path) {
    const p = kfNormalizePath(path);
    const patterns = [
        /^\/borrower$/,
        /^\/borrower\/(engagement|loans|marketplace|notifications|profile|settings|support|loan-products|guarantors|documents|payments|verify|refunds)$/,
        /^\/affiliate-portal(?:\/(referrals|wallet|notifications|profile|settings|documents))?$/,
        /^\/partner\/affiliate(?:\/(referrals|wallet|notifications|profile|settings|documents))?$/,
        /^\/supplier(?:\/(assets|applications|reservations|requests|settlements|notifications|profile|settings|documents|delivered))?$/,
        /^\/partner\/supplier(?:\/(assets|applications|reservations|requests|settlements|notifications|profile|settings|documents|delivered))?$/,
        /^\/investor(?:\/(pools|investments|funded-loans|returns|analytics|transactions|wallet|documents|notifications|profile|settings|support))?$/,
        /^\/(?:partner|vendor)$/,
        /^\/(?:partner|vendor)\/(tasks|tasks\/active|tasks\/completed|recovery-cases|recovery-wallet|payments|calendar|notifications|profile|settings|support|documents)$/,
    ];

    return patterns.some((re) => re.test(p));
}

function kfIsProfileSection(path) {
    const p = kfNormalizePath(path);

    return /^\/borrower\/profile\/(personal|activity|residence|payment|assets|membership|kin|kyc|security)$/.test(p)
        || /^\/(?:affiliate-portal|partner\/affiliate|supplier|partner\/supplier|investor|partner|vendor)\/profile\/(personal|company|face|residence|activity|payment)$/.test(p);
}

function kfFamily(path) {
    const p = kfNormalizePath(path);
    const shell = kfShellRoot(p) || 'other';

    if (p.startsWith('/borrower/marketplace')) {
        return 'borrower-marketplace';
    }
    if (
        p.startsWith('/borrower/apply')
        || p.startsWith('/borrower/applications')
        || p.startsWith('/borrower/loan-profile')
        || p.startsWith('/borrower/loans')
        || p.startsWith('/borrower/schedule')
        || p.startsWith('/borrower/guarantor')
        || p.startsWith('/borrower/group-member')
        || p.startsWith('/borrower/agreements')
    ) {
        return 'borrower-loans';
    }
    if (
        p.startsWith('/borrower/profile')
        || p.startsWith('/borrower/kyc')
        || p.startsWith('/borrower/face')
        || p.startsWith('/borrower/membership')
    ) {
        return 'borrower-profile';
    }
    if (p.startsWith('/borrower/payments') || p.startsWith('/borrower/refunds')) {
        return 'borrower-payments';
    }
    if (p.startsWith('/borrower/notifications')) {
        return 'borrower-notifications';
    }
    if (p.startsWith('/borrower/engagement')) {
        return 'borrower-engagement';
    }
    if (/\/tasks(?:\/|$)/.test(p)) {
        return `${shell}-tasks`;
    }
    if (/\/recovery-cases|\/recovery-wallet/.test(p)) {
        return `${shell}-recovery`;
    }
    if (/\/payments(?:\/|$)|\/invoice/.test(p)) {
        return `${shell}-payments`;
    }
    if (/\/assets(?:\/|$)/.test(p)) {
        return `${shell}-assets`;
    }
    if (/\/pools(?:\/|$)|\/investments(?:\/|$)/.test(p)) {
        return `${shell}-invest`;
    }
    if (/\/profile(?:\/|$)/.test(p)) {
        return `${shell}-profile`;
    }

    return shell;
}

export function kfTransitionType(fromPath, toPath, override) {
    if (override && MOTION_TYPES.has(override)) {
        return override;
    }

    const from = kfNormalizePath(fromPath);
    const to = kfNormalizePath(toPath);
    if (from === to) {
        return 'tab';
    }

    const fromShell = kfShellRoot(from);
    const toShell = kfShellRoot(to);
    if (!fromShell || !toShell || fromShell !== toShell) {
        return 'fade';
    }

    if (kfIsTabPath(from) && kfIsTabPath(to)) {
        return 'tab';
    }
    if (kfIsProfileSection(from) && kfIsProfileSection(to)) {
        return 'tab';
    }
    if (to.startsWith(`${from}/`)) {
        return 'push';
    }
    if (from.startsWith(`${to}/`)) {
        return 'pop';
    }

    const family = kfFamily(from);
    if (family === kfFamily(to)) {
        if (kfIsTabPath(from) && !kfIsTabPath(to)) {
            return 'push';
        }
        if (!kfIsTabPath(from) && kfIsTabPath(to)) {
            return 'pop';
        }
        const fromDepth = from.split('/').filter(Boolean).length;
        const toDepth = to.split('/').filter(Boolean).length;
        if (toDepth > fromDepth) {
            return 'push';
        }
        if (toDepth < fromDepth) {
            return 'pop';
        }
    }

    return 'fade';
}

function kfSetTab(component, key, value) {
    component[key] = value;
}

export function bindPageTransitions() {
    if (typeof window === 'undefined' || window.__kfPageTransitionsBound) {
        return;
    }
    window.__kfPageTransitionsBound = true;
    window.kfTransitionType = kfTransitionType;
    window.kfSetTab = kfSetTab;
}
