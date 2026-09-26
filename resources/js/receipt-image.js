export function bindReceiptSave() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-kf-save-receipt]');
        if (! (button instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        const selector = button.getAttribute('data-kf-save-receipt') || '[data-kf-receipt]';
        const root = document.querySelector(selector);
        if (! (root instanceof HTMLElement)) {
            return;
        }

        saveReceiptPng(root).catch(() => {});
    });
}

function saveReceiptPng(root) {
    const data = root.dataset;
    const width = 720;
    const height = data.phone ? 980 : 900;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (! ctx) {
        return Promise.reject(new Error('canvas'));
    }

    const paint = (logo) => {
        ctx.fillStyle = '#fffbf5';
        ctx.fillRect(0, 0, width, height);
        ctx.strokeStyle = '#e7e0d4';
        ctx.lineWidth = 2;
        ctx.strokeRect(24, 24, width - 48, height - 48);

        if (logo) {
            ctx.drawImage(logo, 56, 56, 52, 52);
        }

        ctx.fillStyle = '#111827';
        ctx.font = '700 30px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(data.brand || 'kopafasta', logo ? 124 : 56, 92);
        ctx.fillStyle = '#6b7280';
        ctx.font = '600 13px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(String(data.kicker || 'RECEIPT').toUpperCase(), 56, 150);

        ctx.fillStyle = '#111827';
        ctx.font = '800 44px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(data.amount || '', 56, 230);

        const rows = [
            [data.typeLabel || 'Type', data.type || ''],
            [data.referenceLabel || 'Reference', data.reference || ''],
            [data.dateLabel || 'Date', data.date || ''],
            [data.statusLabel || 'Status', data.status || ''],
        ];
        if (data.phone) {
            rows.push([data.phoneLabel || 'Mobile', data.phone]);
        }

        let y = 300;
        rows.forEach(([label, value]) => {
            ctx.fillStyle = '#6b7280';
            ctx.font = '600 13px ui-sans-serif, system-ui, sans-serif';
            ctx.fillText(String(label).toUpperCase(), 56, y);
            ctx.fillStyle = '#111827';
            ctx.font = '700 22px ui-sans-serif, system-ui, sans-serif';
            ctx.fillText(String(value), 56, y + 32);
            y += 88;
        });

        canvas.toBlob((blob) => {
            if (! blob) {
                return;
            }
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = data.filename || 'kopafasta-receipt.png';
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(link.href), 1500);
        }, 'image/png');
    };

    return new Promise((resolve) => {
        const mark = new Image();
        mark.crossOrigin = 'anonymous';
        mark.onload = () => {
            paint(mark);
            resolve();
        };
        mark.onerror = () => {
            paint(null);
            resolve();
        };
        mark.src = data.mark || '/images/brand/kopafasta-mark.png';
    });
}
